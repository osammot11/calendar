<?php

namespace Tests\Feature;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringEventTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fixed_daily_series_generates_distinct_occurrences_and_can_be_stopped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();

        $this->postJson('/planner-api/recurring-events', $this->payload($project))
            ->assertOk()
            ->assertJsonPath('recurringEvents.0.title', 'Rituale');

        $series = RecurringEvent::firstOrFail();
        $first = $series->tasks()->orderBy('recurrence_at')->firstOrFail();
        $second = $series->tasks()->orderBy('recurrence_at')->skip(1)->firstOrFail();
        $this->assertSame('2026-11-02 09:00:00', $first->recurrence_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 09:00:00', $second->recurrence_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas(ScheduledBlock::class, [
            'task_id' => $first->id,
            'start_at' => '2026-11-02 09:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00'));
        $this->postJson("/planner-api/tasks/{$first->id}/complete")
            ->assertOk();
        $this->assertSame('done', $first->refresh()->status);
        $this->assertSame('open', $second->refresh()->status);

        $this->deleteJson("/planner-api/tasks/{$second->id}")->assertOk();
        $this->assertSame('skipped', $second->refresh()->status);
        $this->getJson('/planner-api/bootstrap')->assertOk();
        $this->assertSame(1, $series->tasks()->where('recurrence_at', $second->recurrence_at)->count());

        $this->deleteJson("/planner-api/recurring-events/{$series->id}")->assertOk();
        $this->assertFalse($series->refresh()->active);
        $this->assertSame('done', $first->refresh()->status);
        $this->assertFalse($series->tasks()->where('status', 'open')->where('recurrence_at', '>=', now())->exists());
    }

    public function test_window_recurring_ranks_after_max_priority_and_before_normal_tasks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();
        WorkSchedule::create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '13:00']);

        $max = $this->task($project, 'Massima', true);
        $normal = $this->task($project, 'Normale');

        $this->postJson('/planner-api/recurring-events', array_replace($this->payload($project), [
            'start_mode' => 'window',
            'latest_start_time' => '11:00',
        ]))->assertOk();

        $recurring = Task::query()->whereNotNull('recurring_event_id')->orderBy('recurrence_at')->firstOrFail();
        $this->assertSame('09:00', $max->scheduledBlocks()->firstOrFail()->start_at->format('H:i'));
        $this->assertSame('10:00', $recurring->scheduledBlocks()->firstOrFail()->start_at->format('H:i'));
        $this->assertSame('10:30', $normal->scheduledBlocks()->firstOrFail()->start_at->format('H:i'));
    }

    public function test_window_occurrence_is_not_scheduled_after_latest_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();
        WorkSchedule::create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);
        BusyBlock::create([
            'title' => 'Occupato',
            'start_at' => '2026-11-02 09:00:00',
            'end_at' => '2026-11-02 12:00:00',
        ]);

        $this->postJson('/planner-api/recurring-events', array_replace($this->payload($project), [
            'start_mode' => 'window',
            'latest_start_time' => '11:00',
        ]))->assertOk();

        $first = Task::query()->whereNotNull('recurring_event_id')->orderBy('recurrence_at')->firstOrFail();
        $this->assertFalse($first->scheduledBlocks()->exists());
    }

    public function test_hourly_weekly_and_monthly_intervals_keep_their_anchor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-30 08:00'));
        $project = $this->project();
        $service = app(RecurringEventService::class);

        foreach ([
            ['hours', 3, '2026-01-30 09:00:00', '2026-01-30 12:00:00'],
            ['weeks', 2, '2026-01-30 09:00:00', '2026-02-13 09:00:00'],
            ['months', 1, '2026-01-31 09:00:00', '2026-02-28 09:00:00'],
        ] as [$unit, $count, $start, $next]) {
            $series = RecurringEvent::create([
                ...$this->payload($project),
                'starts_at' => $start,
                'interval_unit' => $unit,
                'interval_count' => $count,
                'next_at' => $start,
            ]);
            $service->syncSeries($series->id);
            $dates = $series->tasks()->orderBy('recurrence_at')->limit(2)->pluck('recurrence_at');

            $this->assertSame($start, Carbon::parse($dates[0])->format('Y-m-d H:i:s'));
            $this->assertSame($next, Carbon::parse($dates[1])->format('Y-m-d H:i:s'));
        }
    }

    public function test_rescheduling_a_past_recurring_occurrence_does_not_recreate_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();
        WorkSchedule::create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '13:00']);

        $this->postJson('/planner-api/recurring-events', $this->payload($project))->assertOk();
        $original = Task::query()->whereNotNull('recurring_event_id')->orderBy('recurrence_at')->firstOrFail();
        $block = $original->scheduledBlocks()->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00'));
        $this->postJson("/planner-api/past-events/{$block->id}/reschedule")->assertOk();

        $this->assertSame('skipped', $original->refresh()->status);
        $this->assertFalse($original->scheduledBlocks()->exists());
        $this->assertSame(1, Task::query()->whereNull('recurring_event_id')->where('title', 'Rituale')->count());
        $this->getJson('/planner-api/bootstrap')->assertOk();
        $this->assertSame(1, Task::query()->where('recurring_event_id', $original->recurring_event_id)->where('recurrence_at', $original->recurrence_at)->count());
    }

    public function test_editing_a_series_preserves_past_occurrences_and_replaces_future_ones(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();
        $this->postJson('/planner-api/recurring-events', $this->payload($project))->assertOk();

        $series = RecurringEvent::firstOrFail();
        $past = $series->tasks()->orderBy('recurrence_at')->firstOrFail();
        $future = $series->tasks()->orderBy('recurrence_at')->skip(1)->firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00'));
        $this->putJson("/planner-api/recurring-events/{$series->id}", array_replace($this->payload($project), [
            'title' => 'Rituale nuovo',
            'starts_at' => '2026-11-03 11:00',
        ]))->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $past->id, 'title' => 'Rituale']);
        $this->assertDatabaseMissing('tasks', ['id' => $future->id]);
        $this->assertTrue($series->tasks()->where('title', 'Rituale nuovo')->where('recurrence_at', '2026-11-03 11:00:00')->exists());
    }

    public function test_creating_a_fixed_series_does_not_backfill_past_occurrences(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00'));
        $this->actingAs(User::factory()->create());
        $project = $this->project();

        $this->postJson('/planner-api/recurring-events', $this->payload($project))->assertOk();

        $series = RecurringEvent::firstOrFail();
        $this->assertFalse($series->tasks()->where('recurrence_at', '2026-11-02 09:00:00')->exists());
        $this->assertTrue($series->tasks()->where('recurrence_at', '2026-11-03 09:00:00')->exists());
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Lavoro', 'color' => '#006a6a', 'priority' => 3]);
    }

    private function task(Project $project, string $title, bool $max = false): Task
    {
        return Task::create([
            'project_id' => $project->id,
            'title' => $title,
            'duration_minutes' => 60,
            'priority' => 3,
            'is_max_priority' => $max,
            'is_pinned' => false,
            'status' => 'open',
        ]);
    }

    private function payload(Project $project): array
    {
        return [
            'project_id' => $project->id,
            'title' => 'Rituale',
            'description' => null,
            'duration_minutes' => 30,
            'start_mode' => 'fixed',
            'starts_at' => '2026-11-02 09:00',
            'latest_start_time' => null,
            'interval_count' => 1,
            'interval_unit' => 'days',
        ];
    }
}
