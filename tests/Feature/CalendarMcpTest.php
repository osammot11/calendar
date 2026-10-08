<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CalendarMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_oauth_discovery_and_client_registration_are_limited_to_chatgpt(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/calendar')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/calendar'))
            ->assertJsonPath('authorization_servers.0', url('/'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', url('/'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none')
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');

        $this->postJson('/oauth/register', [
            'client_name' => 'Impostore',
            'redirect_uris' => ['https://example.com/callback'],
        ])->assertBadRequest();
        $this->postJson('/oauth/register', [
            'client_name' => 'Impostore',
            'redirect_uris' => ['https://chatgpt.com.evil.example/callback'],
        ])->assertBadRequest();

        $response = $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        ])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none');

        $this->assertDatabaseHas('oauth_clients', ['id' => $response->json('client_id')]);
    }

    public function test_mcp_requires_a_passport_token_and_lists_tools_when_authenticated(): void
    {
        $this->postJson('/mcp/calendar', $this->rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');

        Passport::actingAs(User::factory()->create(), []);
        $this->postJson('/mcp/calendar', $this->rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');

        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $response = $this->postJson('/mcp/calendar', $this->rpc('tools/list'))->assertOk();
        $names = collect($response->json('result.tools'))->pluck('name');

        $this->assertTrue($names->contains('list_projects'));
        $this->assertTrue($names->contains('create_task'));
        $this->assertTrue($names->contains('complete_task'));
    }

    public function test_authenticated_mcp_can_create_and_complete_a_scheduled_task(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00'));
        Passport::actingAs(User::factory()->create(), ['mcp:use']);
        $project = Project::create([
            'name' => 'Cliente',
            'color' => '#006a6a',
            'priority' => 4,
        ]);
        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->callTool('create_task', [
            'title' => 'Preparare preventivo',
            'project_id' => $project->id,
            'duration_minutes' => 45,
        ])->assertOk()->assertJsonPath('result.structuredContent.task.title', 'Preparare preventivo');

        $task = Task::query()->firstOrFail();
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $task->id,
            'start_at' => '2026-06-22 09:00:00',
            'end_at' => '2026-06-22 09:45:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00'));
        $this->callTool('complete_task', ['task_id' => $task->id])
            ->assertOk()->assertJsonPath('result.structuredContent.task.status', 'done');

        $this->assertSame('2026-06-22 09:00:00', $task->refresh()->worked_at->format('Y-m-d H:i:s'));
    }

    public function test_laravel_login_can_authorize_chatgpt_with_pkce_and_use_the_issued_token(): void
    {
        $client = $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        ])->assertCreated()->json();

        $this->actingAs(User::factory()->create());
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirectUri = 'https://chatgpt.com/connector_platform_oauth_redirect';
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $client['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => 'mcp:use',
            'state' => 'test-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'resource' => url('/mcp/calendar'),
        ]);

        $consent = $this->get('/oauth/authorize?'.$query)->assertOk();
        $this->assertStringContainsString('Collega ChatGPT', $consent->getContent());
        preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $approval = $this->post('/oauth/authorize', [
            'state' => '',
            'client_id' => $client['client_id'],
            'auth_token' => $matches[1],
        ])->assertRedirect();
        parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callback);
        $this->assertSame('test-state', $callback['state'] ?? null);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'redirect_uri' => $redirectUri,
            'code' => $callback['code'],
            'code_verifier' => $verifier,
            'resource' => url('/mcp/calendar'),
        ])->assertOk()->json('access_token');

        $this->withToken($token)
            ->postJson('/mcp/calendar', $this->rpc('tools/list'))
            ->assertOk()
            ->assertJsonPath('result.tools.0.name', 'list_projects');
    }

    public function test_mcp_can_create_and_update_a_fixed_appointment_and_read_the_agenda(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00'));
        Passport::actingAs(User::factory()->create(), ['mcp:use']);
        $project = Project::create([
            'name' => 'Riunioni',
            'color' => '#006a6a',
            'priority' => 4,
        ]);

        $this->callTool('list_projects', [])
            ->assertOk()->assertJsonPath('result.structuredContent.projects.0.id', $project->id);

        $this->callTool('create_task', [
            'title' => 'Incontro cliente',
            'project_id' => $project->id,
            'duration_minutes' => 60,
            'is_pinned' => true,
            'pinned_start_at' => '2026-06-22 14:00',
        ])->assertOk()->assertJsonPath('result.structuredContent.task.is_pinned', true);

        $task = Task::query()->firstOrFail();
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $task->id,
            'start_at' => '2026-06-22 14:00:00',
        ]);

        $this->callTool('update_task', [
            'task_id' => $task->id,
            'pinned_start_at' => '2026-06-22 16:00',
        ])->assertOk();
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $task->id,
            'start_at' => '2026-06-22 16:00:00',
        ]);

        $this->callTool('get_agenda', ['from' => '2026-06-22', 'to' => '2026-06-22'])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.events.0.title', 'Incontro cliente');

        $this->callTool('create_task', [
            'title' => 'Durata non valida',
            'project_id' => $project->id,
            'duration_minutes' => 12,
        ])->assertOk()->assertJsonPath('result.isError', true);
        $this->assertSame(1, Task::query()->count());
    }

    private function callTool(string $name, array $arguments): TestResponse
    {
        return $this->postJson('/mcp/calendar', $this->rpc('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]));
    }

    private function rpc(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ];
    }
}
