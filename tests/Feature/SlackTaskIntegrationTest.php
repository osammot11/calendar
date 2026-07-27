<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\SlackTaskDraft;
use App\Models\Task;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackTaskIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-signing-secret';
    private string $userId = 'U123TASK';
    private string $channelId = 'D123TASK';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-28 08:00:00'));
        config([
            'services.slack.bot_token' => 'xoxb-test-token',
            'services.slack.signing_secret' => $this->secret,
            'services.slack.allowed_user_ids' => [$this->userId],
            'services.slack.task_draft_ttl_minutes' => 60,
        ]);

        Http::fake([
            'https://slack.com/api/conversations.open' => Http::response([
                'ok' => true,
                'channel' => ['id' => $this->channelId],
            ]),
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1722160800.000000',
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_slack_command_requires_valid_signature(): void
    {
        $this->post('/slack/commands/task', ['user_id' => $this->userId])
            ->assertStatus(401);
    }

    public function test_authorized_slash_command_starts_a_dm_draft(): void
    {
        $this->signedFormPost('/slack/commands/task', [
            'user_id' => $this->userId,
            'text' => '',
        ])->assertOk()
            ->assertJsonPath('response_type', 'ephemeral');

        $this->assertDatabaseHas(SlackTaskDraft::class, [
            'slack_user_id' => $this->userId,
            'slack_channel_id' => $this->channelId,
            'step' => 'title',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/conversations.open');
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage');
    }

    public function test_unauthorized_slack_user_is_rejected(): void
    {
        $this->signedFormPost('/slack/commands/task', [
            'user_id' => 'U-NOT-ME',
            'text' => '',
        ])->assertOk()
            ->assertJsonPath('text', 'Non sei autorizzato a creare task in questo calendario.');

        $this->assertDatabaseCount('slack_task_drafts', 0);
    }

    public function test_events_url_verification_returns_challenge(): void
    {
        $this->signedJsonPost('/slack/events', [
            'type' => 'url_verification',
            'challenge' => 'challenge-token',
        ])->assertOk()
            ->assertSee('challenge-token');
    }

    public function test_duplicate_message_events_do_not_advance_the_wizard_twice(): void
    {
        SlackTaskDraft::create([
            'slack_user_id' => $this->userId,
            'slack_channel_id' => $this->channelId,
            'step' => 'title',
            'payload' => [],
            'expires_at' => now()->addHour(),
        ]);

        $payload = [
            'type' => 'event_callback',
            'event_id' => 'Ev-DUPLICATE',
            'event' => [
                'type' => 'message',
                'channel_type' => 'im',
                'user' => $this->userId,
                'channel' => $this->channelId,
                'text' => 'Preparare offerta',
            ],
        ];

        $this->signedJsonPost('/slack/events', $payload)->assertOk();
        $this->signedJsonPost('/slack/events', $payload)->assertOk();

        $draft = SlackTaskDraft::first();

        $this->assertSame('description', $draft->step);
        $this->assertSame('Preparare offerta', $draft->payload['title']);
        $this->assertArrayNotHasKey('description', $draft->payload);
    }

    public function test_slash_command_can_cancel_an_active_draft(): void
    {
        SlackTaskDraft::create([
            'slack_user_id' => $this->userId,
            'slack_channel_id' => $this->channelId,
            'step' => 'title',
            'payload' => [],
            'expires_at' => now()->addHour(),
        ]);

        $this->signedFormPost('/slack/commands/task', [
            'user_id' => $this->userId,
            'text' => 'annulla',
        ])->assertOk();

        $this->assertNull(SlackTaskDraft::query()
            ->where('slack_user_id', $this->userId)
            ->where('expires_at', '>', now())
            ->first());
    }

    public function test_wizard_creates_an_automatic_task_after_confirmation(): void
    {
        WorkSchedule::create([
            'weekday' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $project = Project::create([
            'name' => 'Clienti',
            'color' => '#006a6a',
            'priority' => 4,
        ]);
        SlackTaskDraft::create([
            'slack_user_id' => $this->userId,
            'slack_channel_id' => $this->channelId,
            'step' => 'title',
            'payload' => [],
            'expires_at' => now()->addHour(),
        ]);

        $this->messageEvent('Preparare proposta');
        $this->interaction('skip_description', 'skip');
        $this->interaction('project_select', selectedOption: (string) $project->id);
        $this->messageEvent('1h30', 'Ev-DURATION');
        $this->interaction('priority_select', '4');
        $this->interaction('skip_deadline', 'skip');
        $this->interaction('task_type', 'auto');
        $this->interaction('max_priority', 'no');
        $this->interaction('confirm_task', 'confirm');
        $this->interaction('confirm_task', 'confirm');

        $task = Task::query()->where('title', 'Preparare proposta')->firstOrFail();

        $this->assertSame($project->id, $task->project_id);
        $this->assertSame(90, $task->duration_minutes);
        $this->assertFalse($task->is_pinned);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $task->id,
            'start_at' => '2026-07-28 09:00:00',
            'end_at' => '2026-07-28 10:30:00',
        ]);
    }

    public function test_wizard_creates_a_pinned_task_with_fixed_start(): void
    {
        $project = Project::create([
            'name' => 'Direzione',
            'color' => '#6750a4',
            'priority' => 5,
        ]);
        SlackTaskDraft::create([
            'slack_user_id' => $this->userId,
            'slack_channel_id' => $this->channelId,
            'step' => 'task_type',
            'payload' => [
                'title' => 'Riunione importante',
                'description' => null,
                'project_id' => $project->id,
                'duration_minutes' => 60,
                'priority' => 5,
                'deadline' => null,
            ],
            'expires_at' => now()->addHour(),
        ]);

        $fixedStart = Carbon::parse('2026-07-30 15:00:00', config('app.timezone'));

        $this->interaction('task_type', 'pinned');
        $this->interaction('pinned_datetime', selectedDateTime: $fixedStart->timestamp);
        $this->interaction('max_priority', 'yes');
        $this->interaction('confirm_task', 'confirm');

        $task = Task::query()->where('title', 'Riunione importante')->firstOrFail();

        $this->assertTrue($task->is_pinned);
        $this->assertTrue($task->is_max_priority);
        $this->assertSame('2026-07-30 15:00:00', $task->pinned_start_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $task->id,
            'start_at' => '2026-07-30 15:00:00',
            'end_at' => '2026-07-30 16:00:00',
        ]);
    }

    private function messageEvent(string $text, string $eventId = 'Ev-'.__METHOD__): void
    {
        $this->signedJsonPost('/slack/events', [
            'type' => 'event_callback',
            'event_id' => $eventId,
            'event' => [
                'type' => 'message',
                'channel_type' => 'im',
                'user' => $this->userId,
                'channel' => $this->channelId,
                'text' => $text,
            ],
        ])->assertOk();
    }

    private function interaction(
        string $actionId,
        ?string $value = null,
        ?string $selectedOption = null,
        ?int $selectedDateTime = null,
    ): void {
        $action = [
            'action_id' => $actionId,
        ];

        if ($value !== null) {
            $action['value'] = $value;
        }

        if ($selectedOption !== null) {
            $action['selected_option'] = ['value' => $selectedOption];
        }

        if ($selectedDateTime !== null) {
            $action['selected_date_time'] = $selectedDateTime;
        }

        $this->signedFormPost('/slack/interactions', [
            'payload' => json_encode([
                'type' => 'block_actions',
                'user' => ['id' => $this->userId],
                'channel' => ['id' => $this->channelId],
                'actions' => [$action],
            ], JSON_THROW_ON_ERROR),
        ])->assertOk();
    }

    private function signedFormPost(string $uri, array $parameters)
    {
        $body = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return $this->call('POST', $uri, $parameters, [], [], $this->signedServer($body, 'application/x-www-form-urlencoded'), $body);
    }

    private function signedJsonPost(string $uri, array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, [], [], [], $this->signedServer($body, 'application/json'), $body);
    }

    private function signedServer(string $body, string $contentType): array
    {
        $timestamp = (string) now()->timestamp;
        $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $this->secret);

        return [
            'CONTENT_TYPE' => $contentType,
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ];
    }
}
