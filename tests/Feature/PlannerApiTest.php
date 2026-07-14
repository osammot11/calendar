<?php

namespace Tests\Feature;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_create_project_and_task(): void
    {
        $this->actingAs(User::factory()->create());

        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->postJson('/planner-api/projects', [
            'name' => 'Clienti',
            'color' => '#006a6a',
            'priority' => 4,
            'deadline' => null,
        ])->assertOk()->assertJsonPath('projects.0.name', 'Clienti');

        $project = Project::first();

        $this->postJson('/planner-api/tasks', [
            'project_id' => $project->id,
            'title' => 'Preparare offerta',
            'description' => null,
            'duration_minutes' => 90,
            'priority' => 3,
            'deadline' => null,
            'is_max_priority' => false,
            'is_pinned' => false,
            'pinned_start_at' => null,
            'status' => 'open',
        ])->assertOk()->assertJson(fn ($json) => $json->has('events')->has('tasks')->etc());

        $this->assertDatabaseHas(Task::class, ['title' => 'Preparare offerta']);
    }

    public function test_past_events_can_be_completed_or_rescheduled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 10:00:00'));

        $this->actingAs(User::factory()->create());

        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $project = Project::create([
            'name' => 'Clienti',
            'color' => '#006a6a',
            'priority' => 4,
        ]);
        $completedTask = $this->openTask($project, 'Da chiudere');
        $completedBlock = ScheduledBlock::create([
            'task_id' => $completedTask->id,
            'start_at' => Carbon::parse('2026-06-22 09:00:00'),
            'end_at' => Carbon::parse('2026-06-22 09:30:00'),
            'minutes' => 30,
        ]);

        $this->getJson('/planner-api/bootstrap')
            ->assertOk()
            ->assertJsonCount(1, 'pastEvents')
            ->assertJsonPath('pastEvents.0.title', 'Da chiudere');

        $this->postJson("/planner-api/past-events/{$completedBlock->id}/complete")
            ->assertOk()
            ->assertJsonCount(0, 'pastEvents');

        $this->assertSame('done', $completedTask->refresh()->status);

        $rescheduledTask = $this->openTask($project, 'Da ripianificare');
        $rescheduledBlock = ScheduledBlock::create([
            'task_id' => $rescheduledTask->id,
            'start_at' => Carbon::parse('2026-06-22 09:30:00'),
            'end_at' => Carbon::parse('2026-06-22 09:45:00'),
            'minutes' => 15,
        ]);

        $this->postJson("/planner-api/past-events/{$rescheduledBlock->id}/reschedule")
            ->assertOk()
            ->assertJsonCount(0, 'pastEvents');

        $this->assertDatabaseMissing(ScheduledBlock::class, ['id' => $rescheduledBlock->id]);
        $this->assertTrue(
            $rescheduledTask
                ->scheduledBlocks()
                ->where('start_at', '>=', Carbon::parse('2026-06-22 10:00:00'))
                ->exists()
        );
    }

    public function test_bootstrap_includes_calendar_events_older_than_one_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));

        $this->actingAs(User::factory()->create());

        $project = Project::create([
            'name' => 'Archivio',
            'color' => '#006a6a',
            'priority' => 3,
        ]);
        $task = $this->openTask($project, 'Evento storico');
        ScheduledBlock::create([
            'task_id' => $task->id,
            'start_at' => Carbon::parse('2026-06-01 09:00:00'),
            'end_at' => Carbon::parse('2026-06-01 10:00:00'),
            'minutes' => 60,
        ]);
        BusyBlock::create([
            'title' => 'Riunione storica',
            'start_at' => Carbon::parse('2026-06-01 11:00:00'),
            'end_at' => Carbon::parse('2026-06-01 12:00:00'),
        ]);

        $response = $this->getJson('/planner-api/bootstrap')->assertOk();

        $titles = collect($response->json('events'))->pluck('title');

        $this->assertTrue($titles->contains('Evento storico'));
        $this->assertTrue($titles->contains('Riunione storica'));
        $this->assertCount(1, $response->json('busyBlocks'));
    }

    private function openTask(Project $project, string $title): Task
    {
        return Task::create([
            'project_id' => $project->id,
            'title' => $title,
            'duration_minutes' => 30,
            'priority' => 3,
            'is_max_priority' => false,
            'is_pinned' => false,
            'status' => 'open',
        ]);
    }
}
