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

    public function test_creating_task_schedules_only_the_new_task_without_moving_existing_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 09:00:00'));

        $this->actingAs(User::factory()->create());

        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $project = Project::create([
            'name' => 'Incrementale',
            'color' => '#006a6a',
            'priority' => 3,
        ]);
        $existingTask = $this->openTask($project, 'Task gia pianificata');
        ScheduledBlock::create([
            'task_id' => $existingTask->id,
            'start_at' => Carbon::parse('2026-06-22 09:00:00'),
            'end_at' => Carbon::parse('2026-06-22 10:00:00'),
            'minutes' => 60,
        ]);

        $this->postJson('/planner-api/tasks', [
            'project_id' => $project->id,
            'title' => 'Nuova task urgente',
            'description' => null,
            'duration_minutes' => 60,
            'priority' => 5,
            'deadline' => null,
            'is_max_priority' => true,
            'is_pinned' => false,
            'pinned_start_at' => null,
            'status' => 'open',
        ])->assertOk();

        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $existingTask->id,
            'start_at' => '2026-06-22 09:00:00',
            'end_at' => '2026-06-22 10:00:00',
        ]);
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => Task::query()->where('title', 'Nuova task urgente')->value('id'),
            'start_at' => '2026-06-22 10:00:00',
            'end_at' => '2026-06-22 11:00:00',
        ]);
    }

    public function test_completing_past_event_does_not_run_global_recalculation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 10:00:00'));

        $this->actingAs(User::factory()->create());

        WorkSchedule::create([
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);
        $project = Project::create([
            'name' => 'Revisione',
            'color' => '#006a6a',
            'priority' => 3,
        ]);
        $pastTask = $this->openTask($project, 'Da confermare');
        $pastBlock = ScheduledBlock::create([
            'task_id' => $pastTask->id,
            'start_at' => Carbon::parse('2026-06-22 09:00:00'),
            'end_at' => Carbon::parse('2026-06-22 09:30:00'),
            'minutes' => 30,
        ]);
        $unscheduledTask = $this->openTask($project, 'Da non toccare');

        $this->postJson("/planner-api/past-events/{$pastBlock->id}/complete")
            ->assertOk()
            ->assertJsonCount(0, 'pastEvents');

        $this->assertSame('done', $pastTask->refresh()->status);
        $this->assertFalse($unscheduledTask->scheduledBlocks()->exists());
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
