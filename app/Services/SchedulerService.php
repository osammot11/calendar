<?php

namespace App\Services;

use App\Models\BusyBlock;
use App\Models\DateWorkOverride;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SchedulerService
{
    private const SLOT_MINUTES = 15;
    private const MIN_WEEKS = 12;
    private const MAX_WEEKS = 52;

    public function recalculate(): void
    {
        $unresolvedPastTaskIds = ScheduledBlock::query()
            ->where('end_at', '<', now())
            ->whereHas('task', fn ($query) => $query->where('status', 'open'))
            ->pluck('task_id')
            ->unique();

        $this->deleteRecalculableBlocks();

        $tasks = $this->orderedTasks($unresolvedPastTaskIds);
        if ($tasks->isEmpty()) {
            return;
        }

        $scheduledTaskIds = collect();

        $weeks = self::MIN_WEEKS;

        while ($scheduledTaskIds->count() < $tasks->count() && $weeks <= self::MAX_WEEKS) {
            $this->deleteRecalculableBlocks();
            $pinnedBlocks = $this->schedulePinnedTasks($tasks);
            $scheduledTaskIds = $pinnedBlocks->pluck('task_id');

            foreach ($this->availableSlots($weeks, $pinnedBlocks) as $slot) {
                foreach ($tasks as $task) {
                    if ($scheduledTaskIds->contains($task->id)) {
                        continue;
                    }

                    $minutes = $this->roundToSlot($task->duration_minutes);
                    $slotMinutes = $slot['start']->diffInMinutes($slot['end']);
                    if ($slotMinutes < $minutes) {
                        continue;
                    }

                    $end = $slot['start']->copy()->addMinutes($minutes);

                    ScheduledBlock::create([
                        'task_id' => $task->id,
                        'start_at' => $slot['start'],
                        'end_at' => $end,
                        'minutes' => $minutes,
                    ]);

                    $scheduledTaskIds->push($task->id);
                    $slot['start'] = $end;

                    if ($slot['start']->gte($slot['end'])) {
                        break;
                    }
                }
            }

            if ($scheduledTaskIds->count() === $tasks->count() || $weeks >= self::MAX_WEEKS) {
                break;
            }

            $weeks += 4;
        }
    }

    public function scheduleTask(Task $task): void
    {
        $task->loadMissing('project');

        ScheduledBlock::query()
            ->where('task_id', $task->id)
            ->where('end_at', '>=', now())
            ->delete();

        if ($task->status !== 'open') {
            return;
        }

        if (
            ScheduledBlock::query()
                ->where('task_id', $task->id)
                ->where('end_at', '<', now())
                ->exists()
        ) {
            return;
        }

        if ($task->is_pinned && $task->pinned_start_at) {
            $this->schedulePinnedTask($task);
            return;
        }

        $minutes = $this->roundToSlot($task->duration_minutes);
        $weeks = self::MIN_WEEKS;

        while ($weeks <= self::MAX_WEEKS) {
            foreach ($this->availableSlots($weeks, $this->existingScheduledBlocks($task)) as $slot) {
                if ($slot['start']->diffInMinutes($slot['end']) < $minutes) {
                    continue;
                }

                ScheduledBlock::create([
                    'task_id' => $task->id,
                    'start_at' => $slot['start'],
                    'end_at' => $slot['start']->copy()->addMinutes($minutes),
                    'minutes' => $minutes,
                ]);

                return;
            }

            $weeks += 4;
        }
    }

    private function deleteRecalculableBlocks(): void
    {
        ScheduledBlock::query()
            ->where('end_at', '>=', now())
            ->delete();
    }

    private function schedulePinnedTasks(Collection $tasks): Collection
    {
        return $tasks
            ->filter(fn (Task $task) => $task->is_pinned && $task->pinned_start_at)
            ->map(function (Task $task) {
                $block = $this->schedulePinnedTask($task);

                return [
                    'task_id' => $task->id,
                    'start_at' => $block->start_at,
                    'end_at' => $block->end_at,
                ];
            })
            ->values();
    }

    private function schedulePinnedTask(Task $task): ScheduledBlock
    {
        $minutes = $this->roundToSlot($task->duration_minutes);
        $start = $task->pinned_start_at->copy();
        $end = $start->copy()->addMinutes($minutes);

        return ScheduledBlock::updateOrCreate([
            'task_id' => $task->id,
        ], [
            'start_at' => $start,
            'end_at' => $end,
            'minutes' => $minutes,
        ]);
    }

    private function orderedTasks(Collection $excludedTaskIds): Collection
    {
        return Task::query()
            ->with('project')
            ->where('status', 'open')
            ->whereNotIn('id', $excludedTaskIds)
            ->get()
            ->sort(function (Task $a, Task $b) {
                foreach ([
                    $b->is_max_priority <=> $a->is_max_priority,
                    $b->project->priority <=> $a->project->priority,
                    $this->deadlineTimestamp($a) <=> $this->deadlineTimestamp($b),
                    $b->priority <=> $a->priority,
                    $a->created_at <=> $b->created_at,
                ] as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function availableSlots(int $weeks, Collection $pinnedBlocks): array
    {
        $start = now()->startOfDay();
        $end = now()->addWeeks($weeks)->endOfDay();
        $period = CarbonPeriod::create($start, $end);

        $schedules = WorkSchedule::query()->orderBy('start_time')->get()->groupBy('weekday');
        $overrides = DateWorkOverride::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (DateWorkOverride $override) => $override->date->toDateString());
        $busyBlocks = BusyBlock::query()
            ->where('end_at', '>=', $start)
            ->where('start_at', '<=', $end)
            ->orderBy('start_at')
            ->get();
        $occupiedBlocks = $busyBlocks
            ->map(fn (BusyBlock $block) => [
                'start_at' => $block->start_at,
                'end_at' => $block->end_at,
            ])
            ->concat($pinnedBlocks)
            ->sortBy('start_at')
            ->values();

        $slots = [];

        foreach ($period as $date) {
            $dateKey = $date->toDateString();
            $rows = $overrides->has($dateKey)
                ? $overrides->get($dateKey)
                : $schedules->get($date->dayOfWeekIso, collect());

            foreach ($rows as $row) {
                $slotStart = Carbon::parse($dateKey.' '.$row->start_time);
                $slotEnd = Carbon::parse($dateKey.' '.$row->end_time);

                if ($slotEnd->lte(now())) {
                    continue;
                }

                if ($slotStart->lt(now())) {
                    $slotStart = $this->roundUp(now());
                }

                foreach ($this->subtractBusyBlocks($slotStart, $slotEnd, $occupiedBlocks) as $slot) {
                    if ($slot['start']->lt($slot['end'])) {
                        $slots[] = $slot;
                    }
                }
            }
        }

        usort($slots, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $slots;
    }

    private function existingScheduledBlocks(Task $task): Collection
    {
        return ScheduledBlock::query()
            ->where('task_id', '!=', $task->id)
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->get()
            ->map(fn (ScheduledBlock $block) => [
                'task_id' => $block->task_id,
                'start_at' => $block->start_at,
                'end_at' => $block->end_at,
            ]);
    }

    private function subtractBusyBlocks(Carbon $start, Carbon $end, Collection $busyBlocks): array
    {
        $slots = [['start' => $start->copy(), 'end' => $end->copy()]];

        foreach ($busyBlocks as $busy) {
            $busyStart = $busy['start_at'];
            $busyEnd = $busy['end_at'];

            $slots = collect($slots)->flatMap(function (array $slot) use ($busyStart, $busyEnd) {
                if ($busyEnd->lte($slot['start']) || $busyStart->gte($slot['end'])) {
                    return [$slot];
                }

                $parts = [];
                if ($busyStart->gt($slot['start'])) {
                    $parts[] = ['start' => $slot['start'], 'end' => min($busyStart, $slot['end'])];
                }
                if ($busyEnd->lt($slot['end'])) {
                    $parts[] = ['start' => max($busyEnd, $slot['start']), 'end' => $slot['end']];
                }

                return $parts;
            })->values()->all();
        }

        return $slots;
    }

    private function deadlineTimestamp(Task $task): int
    {
        $dates = collect([$task->deadline, $task->project->deadline])->filter();

        return $dates->isEmpty()
            ? PHP_INT_MAX
            : $dates->min()->startOfDay()->timestamp;
    }

    private function roundToSlot(int $minutes): int
    {
        return (int) ceil($minutes / self::SLOT_MINUTES) * self::SLOT_MINUTES;
    }

    private function roundUp(Carbon $date): Carbon
    {
        $minutes = $date->minute + ($date->second > 0 ? 1 : 0);
        $rounded = (int) ceil($minutes / self::SLOT_MINUTES) * self::SLOT_MINUTES;

        return $date->copy()->minute(0)->second(0)->addMinutes($rounded);
    }
}
