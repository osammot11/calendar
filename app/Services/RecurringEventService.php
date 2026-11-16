<?php

namespace App\Services;

use App\Models\RecurringEvent;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringEventService
{
    public function sync(): bool
    {
        $generated = false;
        foreach (RecurringEvent::query()->where('active', true)->pluck('id') as $id) {
            $generated = $this->syncSeries($id) || $generated;
        }

        return $generated;
    }

    public function syncSeries(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $series = RecurringEvent::query()->lockForUpdate()->findOrFail($id);
            if (! $series->active) {
                return false;
            }

            $horizon = $this->horizon($series);
            if ($series->until_at && $series->until_at->lt($horizon)) {
                $horizon = $series->until_at;
            }
            $this->skipDistantPast($series);
            $generated = 0;
            $created = false;

            while ($series->next_at->lte($horizon) && $generated < 250) {
                $at = $series->next_at->copy();
                if ($this->shouldCreate($series, $at)) {
                    $task = Task::query()->firstOrCreate(
                        ['recurring_event_id' => $series->id, 'recurrence_at' => $at],
                        [
                            'project_id' => $series->project_id,
                            'title' => $series->title,
                            'description' => $series->description,
                            'duration_minutes' => $series->duration_minutes,
                            'priority' => 3,
                            'is_max_priority' => false,
                            'is_pinned' => false,
                            'status' => 'open',
                        ],
                    );

                    if ($task->wasRecentlyCreated) {
                        $created = true;
                    }
                }

                $series->next_index++;
                $series->next_at = $this->occurrenceAt($series, $series->next_index);
                $generated++;
            }

            if ($series->isDirty()) {
                $series->save();
            }

            return $created;
        });
    }

    /**
     * Calcola until_at (istante dell'ultima occorrenza consentita) a partire
     * dalla fine scelta dall'utente: numero di ricorrenze o data di fine.
     */
    public function withEnd(array $attributes): array
    {
        if ($attributes['end_mode'] === 'date') {
            return [
                ...$attributes,
                'occurrence_count' => null,
                'until_at' => Carbon::parse($attributes['ends_on'])->endOfDay(),
            ];
        }

        $series = new RecurringEvent($attributes);
        $remaining = (int) $attributes['occurrence_count'];
        $index = 0;
        $at = $this->occurrenceAt($series, $index);

        while (true) {
            if ($this->allowedTimeOfDay($series, $at) && --$remaining === 0) {
                break;
            }

            $at = $this->occurrenceAt($series, ++$index);
        }

        return [
            ...$attributes,
            'ends_on' => null,
            'until_at' => $at,
        ];
    }

    public function updateSeries(RecurringEvent $series, array $attributes): void
    {
        DB::transaction(function () use ($series, $attributes): void {
            $series->tasks()
                ->where('status', 'open')
                ->where('recurrence_at', '>=', now())
                ->delete();

            $series->update([
                ...$attributes,
                'next_index' => 0,
                'next_at' => $attributes['starts_at'],
                'active' => true,
            ]);
        });
    }

    public function stopSeries(RecurringEvent $series): void
    {
        DB::transaction(function () use ($series): void {
            $series->update(['active' => false]);
            $series->tasks()
                ->where('status', 'open')
                ->where('recurrence_at', '>=', now())
                ->delete();
        });
    }

    private function horizon(RecurringEvent $series): Carbon
    {
        return match ($series->interval_unit) {
            'hours' => now()->addWeek(),
            'days' => now()->addDays(90),
            'weeks' => now()->addWeeks(12),
            'months' => now()->addMonthsNoOverflow(12),
        };
    }

    private function skipDistantPast(RecurringEvent $series): void
    {
        $cutoff = match ($series->interval_unit) {
            'hours' => now()->subWeek(),
            'days' => now()->subDays(90),
            'weeks', 'months' => now()->subYear(),
        };

        if ($series->next_at->gte($cutoff)) {
            return;
        }

        $step = match ($series->interval_unit) {
            'hours' => $series->starts_at->diffInHours($cutoff, false) / $series->interval_count,
            'days' => $series->starts_at->diffInDays($cutoff, false) / $series->interval_count,
            'weeks' => $series->starts_at->diffInDays($cutoff, false) / (7 * $series->interval_count),
            'months' => (($cutoff->year - $series->starts_at->year) * 12 + $cutoff->month - $series->starts_at->month) / $series->interval_count,
        };

        $series->next_index = max($series->next_index, (int) floor($step));
        $series->next_at = $this->occurrenceAt($series, $series->next_index);
        while ($series->next_at->lt($cutoff)) {
            $series->next_index++;
            $series->next_at = $this->occurrenceAt($series, $series->next_index);
        }
    }

    private function occurrenceAt(RecurringEvent $series, int $index): Carbon
    {
        $steps = $series->interval_count * $index;

        return match ($series->interval_unit) {
            'hours' => $series->starts_at->copy()->addHours($steps),
            'days' => $series->starts_at->copy()->addDays($steps),
            'weeks' => $series->starts_at->copy()->addWeeks($steps),
            'months' => $series->starts_at->copy()->addMonthsNoOverflow($steps),
        };
    }

    private function shouldCreate(RecurringEvent $series, Carbon $at): bool
    {
        if ($series->start_mode !== 'window') {
            return $at->gte(now());
        }

        if (! $this->allowedTimeOfDay($series, $at)) {
            return false;
        }

        return $at->copy()->setTimeFromTimeString($series->latest_start_time)->gte(now());
    }

    private function allowedTimeOfDay(RecurringEvent $series, Carbon $at): bool
    {
        if ($series->start_mode !== 'window' || $series->interval_unit !== 'hours') {
            return true;
        }

        $time = $at->format('H:i');

        return $time >= $series->starts_at->format('H:i') && $time <= substr($series->latest_start_time, 0, 5);
    }
}
