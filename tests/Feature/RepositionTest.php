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

class RepositionTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Lunedi 22 giugno 2026, prima dell'orario di lavoro.
        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00'));
        $this->actingAs(User::factory()->create());
        $this->project = Project::create(['name' => 'Clienti', 'color' => '#2f5d50', 'priority' => 3]);
        foreach ([1, 2] as $weekday) {
            WorkSchedule::create(['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '18:00']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function task(array $attributes = []): Task
    {
        return Task::create($attributes + [
            'project_id' => $this->project->id,
            'title' => 'Task',
            'duration_minutes' => 60,
            'priority' => 3,
            'is_max_priority' => false,
            'is_pinned' => false,
            'status' => 'open',
        ]);
    }

    private function blockOf(Task $task): ScheduledBlock
    {
        return ScheduledBlock::query()->where('task_id', $task->id)->firstOrFail();
    }

    public function test_moved_task_keeps_its_position_on_another_day_even_outside_working_hours(): void
    {
        $task = $this->task(['title' => 'Spostata']);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $task->id, 'start_at' => '2026-06-23T19:30:00+02:00']],
        ])->assertOk()->assertJsonPath('events.0.extendedProps.manual', true);

        $this->assertSame('2026-06-23 19:30', $this->blockOf($task)->start_at->format('Y-m-d H:i'));

        // Un ricalcolo successivo non la sposta.
        $this->postJson('/planner-api/recalculate')->assertOk();
        $this->assertSame('2026-06-23 19:30', $this->blockOf($task)->fresh()->start_at->format('Y-m-d H:i'));
    }

    public function test_manual_position_beats_max_priority_and_other_tasks_are_planned_around_it(): void
    {
        $urgent = $this->task(['title' => 'Urgente', 'is_max_priority' => true]);
        $moved = $this->task(['title' => 'Spostata']);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $moved->id, 'start_at' => '2026-06-22T09:00:00+02:00']],
        ])->assertOk();

        $this->assertSame('09:00', $this->blockOf($moved)->start_at->format('H:i'));
        $this->assertSame('10:00', $this->blockOf($urgent)->start_at->format('H:i'));
    }

    public function test_pinned_appointment_overlapping_a_moved_task_is_rescheduled(): void
    {
        $pinned = $this->task([
            'title' => 'Riunione',
            'is_pinned' => true,
            'pinned_start_at' => Carbon::parse('2026-06-22 10:00'),
        ]);
        $moved = $this->task(['title' => 'Spostata', 'duration_minutes' => 90]);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $moved->id, 'start_at' => '2026-06-22T09:30:00+02:00']],
        ])->assertOk();

        // La task spostata occupa 09:30-11:00; tra 09:00 e 09:30 la riunione
        // da un'ora non entra, quindi va al primo spazio libero dopo.
        $this->assertSame('09:30', $this->blockOf($moved)->start_at->format('H:i'));
        $this->assertSame('11:00', $this->blockOf($pinned)->start_at->format('H:i'));
        $this->assertTrue($pinned->fresh()->is_pinned);
    }

    public function test_moving_a_pinned_task_moves_its_pinned_time_too(): void
    {
        $pinned = $this->task([
            'title' => 'Riunione',
            'is_pinned' => true,
            'pinned_start_at' => Carbon::parse('2026-06-22 10:00'),
        ]);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $pinned->id, 'start_at' => '2026-06-23T15:00:00+02:00']],
        ])->assertOk();

        $pinned->refresh();
        $this->assertSame('2026-06-23 15:00', $pinned->pinned_start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-06-23 15:00', $pinned->manual_start_at->format('Y-m-d H:i'));
    }

    public function test_busy_blocks_can_be_moved_and_tasks_avoid_their_new_position(): void
    {
        $busy = BusyBlock::create([
            'title' => 'Dentista',
            'start_at' => Carbon::parse('2026-06-22 14:00'),
            'end_at' => Carbon::parse('2026-06-22 15:00'),
        ]);
        $task = $this->task();

        $this->postJson('/planner-api/reposition', [
            'busy' => [[
                'id' => $busy->id,
                'start_at' => '2026-06-22T09:00:00+02:00',
                'end_at' => '2026-06-22T10:00:00+02:00',
            ]],
        ])->assertOk();

        $this->assertSame('09:00', $busy->fresh()->start_at->format('H:i'));
        $this->assertSame('10:00', $this->blockOf($task)->start_at->format('H:i'));
    }

    public function test_tasks_can_be_moved_into_the_past_to_log_work_already_done(): void
    {
        $task = $this->task(['title' => 'Fatta ieri']);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $task->id, 'start_at' => '2026-06-21T10:00:00+02:00']],
        ])->assertOk();

        $this->assertSame('2026-06-21 10:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));

        // Il ricalcolo la conserva nel passato, in attesa di essere completata.
        $this->postJson('/planner-api/recalculate')->assertOk();
        $this->assertSame(1, ScheduledBlock::query()->where('task_id', $task->id)->count());
        $this->assertSame('2026-06-21 10:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));
    }

    public function test_a_task_already_in_the_past_can_be_moved_again(): void
    {
        $task = $this->task(['manual_start_at' => Carbon::parse('2026-06-21 10:00')]);
        ScheduledBlock::create([
            'task_id' => $task->id,
            'start_at' => Carbon::parse('2026-06-21 10:00'),
            'end_at' => Carbon::parse('2026-06-21 11:00'),
            'minutes' => 60,
        ]);

        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $task->id, 'start_at' => '2026-06-20T15:00:00+02:00']],
        ])->assertOk();

        $this->assertSame(1, ScheduledBlock::query()->where('task_id', $task->id)->count());
        $this->assertSame('2026-06-20 15:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));
    }

    public function test_busy_blocks_can_be_moved_into_the_past(): void
    {
        $busy = BusyBlock::create([
            'title' => 'Telefonata',
            'start_at' => Carbon::parse('2026-06-22 14:00'),
            'end_at' => Carbon::parse('2026-06-22 15:00'),
        ]);

        $this->postJson('/planner-api/reposition', [
            'busy' => [['id' => $busy->id, 'start_at' => '2026-06-19T09:00:00+02:00', 'end_at' => '2026-06-19T10:00:00+02:00']],
        ])->assertOk();

        $this->assertSame('2026-06-19 09:00', $busy->fresh()->start_at->format('Y-m-d H:i'));
    }

    public function test_reset_brings_a_task_moved_into_the_past_back_to_the_future(): void
    {
        $task = $this->task();
        $this->postJson('/planner-api/reposition', [
            'tasks' => [['id' => $task->id, 'start_at' => '2026-06-21T10:00:00+02:00']],
        ])->assertOk();

        $this->deleteJson("/planner-api/tasks/{$task->id}/manual-position")->assertOk();

        $this->assertSame(1, ScheduledBlock::query()->where('task_id', $task->id)->count());
        $this->assertSame('2026-06-22 09:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));
    }

    public function test_pinning_from_the_task_form_clears_the_manual_position(): void
    {
        $task = $this->task(['manual_start_at' => Carbon::parse('2026-06-22 11:00')]);

        $this->putJson("/planner-api/tasks/{$task->id}", [
            'project_id' => $this->project->id,
            'title' => 'Task',
            'description' => null,
            'duration_minutes' => 60,
            'priority' => 3,
            'deadline' => null,
            'is_max_priority' => false,
            'is_pinned' => true,
            'pinned_start_at' => '2026-06-23 16:00',
            'status' => 'open',
        ])->assertOk();

        $this->assertNull($task->fresh()->manual_start_at);
        $this->assertSame('2026-06-23 16:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));
    }

    public function test_manual_position_can_be_reset_to_automatic(): void
    {
        $task = $this->task(['manual_start_at' => Carbon::parse('2026-06-23 17:00')]);

        $this->deleteJson("/planner-api/tasks/{$task->id}/manual-position")->assertOk();

        $this->assertNull($task->fresh()->manual_start_at);
        $this->assertSame('2026-06-22 09:00', $this->blockOf($task)->start_at->format('Y-m-d H:i'));
    }
}
