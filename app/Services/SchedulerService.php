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
use Illuminate\Support\Facades\DB;

class SchedulerService
{
    private const SLOT_MINUTES = 5;
    private const MAX_WEEKS = 52;

    public function recalculate(): void
    {
        DB::transaction(function (): void {
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

            $pinnedBlocks = $this->schedulePinnedTasks($tasks);
            $scheduledTaskIds = array_flip($pinnedBlocks->pluck('task_id')->all());

            // Una sola passata sull'intero orizzonte: l'assegnazione greedy
            // "primo slot utile" da lo stesso risultato che si otteneva
            // allargando l'orizzonte di 4 settimane alla volta e rifacendo tutto.
            $slots = $this->availableSlots(self::MAX_WEEKS, $pinnedBlocks);
            $rows = [];
            $timestamp = now();

            foreach ($tasks as $task) {
                if (isset($scheduledTaskIds[$task->id])) {
                    continue;
                }

                $minutes = $this->roundToSlot($task->duration_minutes);
                $latestStart = $this->latestRecurringStart($task);

                foreach ($slots as $index => $slot) {
                    if ($latestStart && $slot['start']->gt($latestStart)) {
                        break;
                    }

                    $start = $this->eligibleStart($task, $slot);
                    $end = $start->copy()->addMinutes($minutes);

                    if (($latestStart && $start->gt($latestStart)) || $end->gt($this->eligibleEnd($task, $slot))) {
                        continue;
                    }

                    $rows[] = [
                        'task_id' => $task->id,
                        'start_at' => $start,
                        'end_at' => $end,
                        'minutes' => $minutes,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    $remaining = [];
                    if ($slot['start']->lt($start)) {
                        $remaining[] = ['start' => $slot['start'], 'end' => $start];
                    }
                    if ($end->lt($slot['end'])) {
                        $remaining[] = ['start' => $end, 'end' => $slot['end']];
                    }

                    array_splice($slots, $index, 1, $remaining);
                    break;
                }
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                ScheduledBlock::insert($chunk);
            }
        });
    }

    public function scheduleTask(Task $task): void
    {
        $task->loadMissing('project', 'recurringEvent');

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

        if ($this->isFixedRecurring($task)) {
            $this->scheduleFixedRecurringTask($task);
            return;
        }

        $minutes = $this->roundToSlot($task->duration_minutes);
        $latestStart = $this->latestRecurringStart($task);

        foreach ($this->availableSlots(self::MAX_WEEKS, $this->existingScheduledBlocks($task)) as $slot) {
            if ($latestStart && $slot['start']->gt($latestStart)) {
                return;
            }

            $start = $this->eligibleStart($task, $slot);
            $end = $start->copy()->addMinutes($minutes);

            if (($latestStart && $start->gt($latestStart)) || $end->gt($this->eligibleEnd($task, $slot))) {
                continue;
            }

            ScheduledBlock::create([
                'task_id' => $task->id,
                'start_at' => $start,
                'end_at' => $end,
                'minutes' => $minutes,
            ]);

            return;
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
            ->filter(fn (Task $task) => ($task->is_pinned && $task->pinned_start_at) || $this->isFixedRecurring($task))
            ->map(function (Task $task) {
                $block = $this->isFixedRecurring($task)
                    ? $this->scheduleFixedRecurringTask($task)
                    : $this->schedulePinnedTask($task);

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

    private function scheduleFixedRecurringTask(Task $task): ScheduledBlock
    {
        $start = $task->recurrence_at->copy();

        return ScheduledBlock::updateOrCreate(
            ['task_id' => $task->id],
            [
                'start_at' => $start,
                'end_at' => $start->copy()->addMinutes($this->roundToSlot($task->duration_minutes)),
                'minutes' => $this->roundToSlot($task->duration_minutes),
            ],
        );
    }

    private function isFixedRecurring(Task $task): bool
    {
        return $task->recurrence_at && $task->recurringEvent?->start_mode === 'fixed';
    }

    private function latestRecurringStart(Task $task): ?Carbon
    {
        if (! $task->recurrence_at || $task->recurringEvent?->start_mode !== 'window') {
            return null;
        }

        return $task->recurrence_at->copy()->setTimeFromTimeString($task->recurringEvent->latest_start_time);
    }

    private function orderedTasks(Collection $excludedTaskIds): Collection
    {
        return Task::query()
            ->with('project', 'recurringEvent')
            ->where('status', 'open')
            ->whereNotIn('id', $excludedTaskIds)
            ->get()
            ->sort(function (Task $a, Task $b) {
                foreach ([
                    $b->is_max_priority <=> $a->is_max_priority,
                    (bool) $b->recurring_event_id <=> (bool) $a->recurring_event_id,
                    $this->projectDeadlineTimestamp($a) <=> $this->projectDeadlineTimestamp($b),
                    $this->taskDeadlineTimestamp($a) <=> $this->taskDeadlineTimestamp($b),
                    $b->project->priority <=> $a->project->priority,
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
            ->values()
            ->all();
        $first = 0;
        $count = count($occupiedBlocks);

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

                // I blocchi gia terminati prima di questo slot non servono piu:
                // gli slot sono generati in ordine cronologico.
                while ($first < $count && $occupiedBlocks[$first]['end_at']->lte($slotStart)) {
                    $first++;
                }

                foreach ($this->subtractBusyBlocks($slotStart, $slotEnd, $occupiedBlocks, $first) as $slot) {
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

    private function eligibleStart(Task $task, array $slot): Carbon
    {
        $start = $slot['start']->copy();

        if ($task->earliest_start_date && $task->earliest_start_date->copy()->startOfDay()->gt($start)) {
            $start = $task->earliest_start_date->copy()->startOfDay();
        }

        if ($task->recurrence_at && $task->recurrence_at->gt($start)) {
            $start = $task->recurrence_at->copy();
        }

        if ($task->earliest_start_time) {
            $earliest = Carbon::parse($start->toDateString().' '.$task->earliest_start_time);
            if ($earliest->gt($start)) {
                $start = $this->roundUp($earliest);
            }
        }

        return $start;
    }

    private function eligibleEnd(Task $task, array $slot): Carbon
    {
        $end = $slot['end']->copy();

        if ($task->latest_end_time) {
            $latest = Carbon::parse($end->toDateString().' '.$task->latest_end_time);
            if ($latest->lt($end)) {
                $end = $latest;
            }
        }

        return $end;
    }

    /**
     * @param  array<int, array{start_at: Carbon, end_at: Carbon}>  $busyBlocks  ordinati per start_at
     */
    private function subtractBusyBlocks(Carbon $start, Carbon $end, array $busyBlocks, int $offset = 0): array
    {
        $slots = [];
        $cursor = $start->copy();

        for ($i = $offset, $count = count($busyBlocks); $i < $count; $i++) {
            $busy = $busyBlocks[$i];
            if ($busy['start_at']->gte($end)) {
                break;
            }
            if ($busy['end_at']->lte($cursor)) {
                continue;
            }
            if ($busy['start_at']->gt($cursor)) {
                $slots[] = ['start' => $cursor, 'end' => $busy['start_at']->copy()];
            }
            if ($busy['end_at']->gt($cursor)) {
                $cursor = $busy['end_at']->copy();
            }
            if ($cursor->gte($end)) {
                return $slots;
            }
        }

        $slots[] = ['start' => $cursor, 'end' => $end->copy()];

        return $slots;
    }

    private function projectDeadlineTimestamp(Task $task): int
    {
        if (! $task->project->deadline) {
            return PHP_INT_MAX;
        }

        return $task->project->deadline->startOfDay()->timestamp;
    }

    private function taskDeadlineTimestamp(Task $task): int
    {
        if (! $task->deadline) {
            return PHP_INT_MAX;
        }

        return $task->deadline->startOfDay()->timestamp;
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
