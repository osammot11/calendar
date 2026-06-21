<?php

namespace Tests\Unit;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\WorkSchedule;
use App\Services\SchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_project_priority_beats_task_priority(): void
    {
        $this->workday(1, '09:00', '12:00');
        $lowProject = $this->project('Low', 1);
        $highProject = $this->project('High', 5);
        $lowProjectTask = $this->task($lowProject, 'High task priority', 5);
        $highProjectTask = $this->task($highProject, 'High project priority', 1);

        app(SchedulerService::class)->recalculate();

        $first = ScheduledBlock::query()->orderBy('start_at')->first();

        $this->assertSame($highProjectTask->id, $first->task_id);
        $this->assertNotSame($lowProjectTask->id, $first->task_id);
    }

    public function test_max_priority_flag_beats_project_priority(): void
    {
        $this->workday(1, '09:00', '12:00');
        $lowProject = $this->project('Low', 1);
        $highProject = $this->project('High', 5);
        $maxTask = $this->task($lowProject, 'Urgent', 1, true);
        $this->task($highProject, 'Important project', 5);

        app(SchedulerService::class)->recalculate();

        $first = ScheduledBlock::query()->orderBy('start_at')->first();

        $this->assertSame($maxTask->id, $first->task_id);
    }

    public function test_task_is_not_split_across_days(): void
    {
        $this->workday(1, '09:00', '10:00');
        $this->workday(2, '09:00', '10:00');
        $project = $this->project('No split', 3);
        $task = $this->task($project, 'Long task', 3, false, 90);

        app(SchedulerService::class)->recalculate();

        $this->assertDatabaseMissing(ScheduledBlock::class, [
            'task_id' => $task->id,
        ]);
    }

    public function test_busy_block_is_avoided(): void
    {
        $this->workday(1, '09:00', '11:00');
        $project = $this->project('Busy', 3);
        $task = $this->task($project, 'After meeting', 3, false, 60);
        BusyBlock::create([
            'title' => 'Meeting',
            'start_at' => Carbon::parse('2026-06-22 09:00:00'),
            'end_at' => Carbon::parse('2026-06-22 10:00:00'),
        ]);

        app(SchedulerService::class)->recalculate();

        $block = ScheduledBlock::query()->where('task_id', $task->id)->first();

        $this->assertSame('10:00', $block->start_at->format('H:i'));
    }

    public function test_unresolved_past_tasks_are_not_shifted_forward(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 10:00:00'));

        $this->workday(1, '09:00', '12:00');
        $project = $this->project('Past events', 3);
        $pastTask = $this->task($project, 'Already elapsed', 3, false, 30);
        $futureTask = $this->task($project, 'Still schedulable', 3, false, 60);
        ScheduledBlock::create([
            'task_id' => $pastTask->id,
            'start_at' => Carbon::parse('2026-06-22 09:00:00'),
            'end_at' => Carbon::parse('2026-06-22 09:30:00'),
            'minutes' => 30,
        ]);

        app(SchedulerService::class)->recalculate();

        $this->assertSame(1, ScheduledBlock::query()->where('task_id', $pastTask->id)->count());
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $pastTask->id,
            'start_at' => '2026-06-22 09:00:00',
            'end_at' => '2026-06-22 09:30:00',
        ]);
        $this->assertDatabaseMissing(ScheduledBlock::class, [
            'task_id' => $pastTask->id,
            'start_at' => '2026-06-22 10:00:00',
        ]);
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $futureTask->id,
            'start_at' => '2026-06-22 10:00:00',
        ]);
    }

    public function test_pinned_task_is_scheduled_at_fixed_time_and_blocks_auto_tasks(): void
    {
        $this->workday(1, '09:00', '12:00');
        $project = $this->project('Pinned', 3);
        $pinned = $this->task($project, 'Fixed work', 3, false, 60, [
            'is_pinned' => true,
            'pinned_start_at' => Carbon::parse('2026-06-22 09:30:00'),
        ]);
        $automatic = $this->task($project, 'Automatic work', 3, false, 60);

        app(SchedulerService::class)->recalculate();

        $pinnedBlock = ScheduledBlock::query()->where('task_id', $pinned->id)->first();
        $automaticBlock = ScheduledBlock::query()->where('task_id', $automatic->id)->first();

        $this->assertSame('09:30', $pinnedBlock->start_at->format('H:i'));
        $this->assertSame('10:30', $pinnedBlock->end_at->format('H:i'));
        $this->assertTrue(
            $automaticBlock->end_at->lte($pinnedBlock->start_at)
                || $automaticBlock->start_at->gte($pinnedBlock->end_at)
        );
    }

    private function workday(int $weekday, string $start, string $end): void
    {
        WorkSchedule::create([
            'weekday' => $weekday,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    private function project(string $name, int $priority): Project
    {
        return Project::create([
            'name' => $name,
            'color' => '#6750a4',
            'priority' => $priority,
        ]);
    }

    private function task(Project $project, string $title, int $priority, bool $max = false, int $minutes = 60, array $overrides = []): Task
    {
        return Task::create($overrides + [
            'project_id' => $project->id,
            'title' => $title,
            'duration_minutes' => $minutes,
            'priority' => $priority,
            'is_max_priority' => $max,
            'is_pinned' => false,
            'status' => 'open',
        ]);
    }
}
