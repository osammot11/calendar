<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mcp.bridge_token', str_repeat('a', 64));
        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bridge_rejects_missing_or_wrong_token_even_with_web_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/internal/mcp/projects')->assertForbidden();
        $this->withToken('wrong')->getJson('/internal/mcp/projects')->assertForbidden();
    }

    public function test_bridge_creates_updates_reads_and_completes_task(): void
    {
        $this->withToken(str_repeat('a', 64));
        $project = Project::create(['name' => 'Lavoro', 'color' => '#006a6a', 'priority' => 3]);
        WorkSchedule::create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);

        $this->getJson('/internal/mcp/projects')->assertOk()->assertJsonPath('projects.0.name', 'Lavoro');

        $this->postJson('/internal/mcp/tasks', [
            'project_id' => $project->id,
            'title' => 'Preparare offerta',
            'duration_minutes' => 60,
            'earliest_start_time' => '14:00',
            'latest_end_time' => '15:00',
        ])->assertCreated()->assertJsonPath('task.scheduled.0.start_at', '2026-06-22T14:00:00+02:00');

        $task = Task::query()->firstOrFail();
        $this->getJson('/internal/mcp/tasks')->assertOk()->assertJsonPath('tasks.0.id', $task->id);
        $this->getJson('/internal/mcp/agenda?from=2026-06-22&to=2026-06-22')
            ->assertOk()->assertJsonPath('events.0.task_id', $task->id);

        $this->patchJson("/internal/mcp/tasks/{$task->id}", ['title' => 'Offerta aggiornata'])
            ->assertOk()->assertJsonPath('task.title', 'Offerta aggiornata');
        $this->assertSame('14:00', substr($task->refresh()->earliest_start_time, 0, 5));

        $this->postJson("/internal/mcp/tasks/{$task->id}/complete")
            ->assertOk()->assertJsonPath('task.status', 'done');
        $this->assertSame(0, ScheduledBlock::query()->where('task_id', $task->id)->count());
    }

    public function test_bridge_reuses_task_validation_and_limits_agenda_range(): void
    {
        $this->withToken(str_repeat('a', 64));
        $project = Project::create(['name' => 'Lavoro', 'color' => '#006a6a', 'priority' => 3]);

        $this->postJson('/internal/mcp/tasks', [
            'project_id' => $project->id,
            'title' => 'Impossibile',
            'duration_minutes' => 60,
            'earliest_start_time' => '15:00',
            'latest_end_time' => '14:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('latest_end_time');
        $this->assertSame(0, Task::query()->count());

        $this->getJson('/internal/mcp/agenda?from=2026-06-01&to=2026-08-01')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_bridge_keeps_pinned_time_and_preserves_past_history_on_completion(): void
    {
        $this->withToken(str_repeat('a', 64));
        $project = Project::create(['name' => 'Lavoro', 'color' => '#006a6a', 'priority' => 3]);
        $this->postJson('/internal/mcp/tasks', [
            'project_id' => $project->id,
            'title' => 'Appuntamento',
            'duration_minutes' => 60,
            'is_pinned' => true,
            'pinned_start_at' => '2026-06-22T09:00',
        ])->assertCreated()->assertJsonPath('task.scheduled.0.start_at', '2026-06-22T09:00:00+02:00');

        $task = Task::query()->firstOrFail();
        Carbon::setTestNow(Carbon::parse('2026-06-22 11:00:00'));
        $this->postJson("/internal/mcp/tasks/{$task->id}/complete")
            ->assertOk()->assertJsonPath('task.status', 'done');

        $this->assertSame(1, ScheduledBlock::query()->where('task_id', $task->id)->count());
        $this->assertSame('2026-06-22 09:00:00', $task->refresh()->pinned_start_at->format('Y-m-d H:i:s'));
    }
}
