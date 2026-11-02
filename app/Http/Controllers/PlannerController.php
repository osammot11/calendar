<?php

namespace App\Http\Controllers;

use App\Models\BusyBlock;
use App\Models\DateWorkOverride;
use App\Models\Project;
use App\Models\RecurringEvent;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\WorkSchedule;
use App\Services\SchedulerService;
use App\Services\RecurringEventService;
use App\Services\TaskInput;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlannerController extends Controller
{
    public function __construct(
        private readonly SchedulerService $scheduler,
        private readonly TaskInput $taskInput,
        private readonly RecurringEventService $recurringEvents,
    ) {}

    public function app(): View
    {
        return view('planner');
    }

    public function bootstrap(): JsonResponse
    {
        $generated = $this->recurringEvents->sync();
        if ($generated || (Task::query()->where('status', 'open')->exists() && ScheduledBlock::query()->doesntExist())) {
            $this->scheduler->recalculate();
        }

        return response()->json([
            'user' => Auth::user()->only(['name', 'email']),
            'projects' => Project::query()->orderByDesc('priority')->orderBy('name')->get(),
            'recurringEvents' => RecurringEvent::query()->orderBy('title')->get(),
            'tasks' => Task::query()->with('project')->latest()->get(),
            'workSchedules' => WorkSchedule::query()->orderBy('weekday')->orderBy('start_time')->get(),
            'dateOverrides' => DateWorkOverride::query()->orderByDesc('date')->orderBy('start_time')->limit(60)->get(),
            'busyBlocks' => BusyBlock::query()->orderBy('start_at')->get(),
            'events' => $this->events(),
            'pastEvents' => $this->pastEvents(),
            'unscheduledTasks' => Task::query()
                ->with('project')
                ->where('status', 'open')
                ->whereDoesntHave('scheduledBlocks')
                ->get(),
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        Project::create($this->validateProject($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function updateProject(Request $request, Project $project): JsonResponse
    {
        $project->update($this->validateProject($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function deleteProject(Project $project): JsonResponse
    {
        $project->delete();
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function storeTask(Request $request): JsonResponse
    {
        $attributes = $this->validateTask($request);
        $attributes['completed_at'] = $attributes['status'] === 'done' ? now() : null;
        $task = Task::create($attributes);
        if ($task->status === 'done') {
            $task->update(['worked_at' => $this->workedAtFor($task)]);
        }
        $this->scheduler->scheduleTask($task);

        return $this->bootstrap();
    }

    public function updateTask(Request $request, Task $task): JsonResponse
    {
        $attributes = $this->validateTask($request);
        $attributes['completed_at'] = $attributes['status'] === 'done'
            ? ($task->completed_at ?? now())
            : null;
        $attributes['worked_at'] = $attributes['status'] === 'done'
            ? ($task->worked_at ?? $this->workedAtFor($task))
            : null;
        $task->update($attributes);
        $this->scheduler->scheduleTask($task);

        return $this->bootstrap();
    }

    public function deleteTask(Task $task): JsonResponse
    {
        if ($task->recurring_event_id) {
            if ($task->status === 'done') {
                throw ValidationException::withMessages(['task' => 'Un\'occorrenza completata non puo essere saltata.']);
            }
            $task->update(['status' => 'skipped']);
            $task->scheduledBlocks()->delete();
        } else {
            $task->delete();
        }

        return $this->bootstrap();
    }

    public function storeRecurringEvent(Request $request): JsonResponse
    {
        $attributes = $this->validateRecurringEvent($request);
        $series = RecurringEvent::create([
            ...$attributes,
            'next_at' => $attributes['starts_at'],
            'next_index' => 0,
        ]);
        $this->recurringEvents->syncSeries($series->id);
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function updateRecurringEvent(Request $request, RecurringEvent $recurringEvent): JsonResponse
    {
        $this->recurringEvents->updateSeries($recurringEvent, $this->validateRecurringEvent($request));
        $this->recurringEvents->syncSeries($recurringEvent->id);
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function deleteRecurringEvent(RecurringEvent $recurringEvent): JsonResponse
    {
        $this->recurringEvents->stopSeries($recurringEvent);
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function completeTask(Task $task): JsonResponse
    {
        if ($task->status === 'open') {
            $block = $task->scheduledBlocks()->orderBy('start_at')->first();
            $task->update([
                'status' => 'done',
                'completed_at' => now(),
                'worked_at' => $block && $block->start_at->lte(now()) ? $block->start_at : now(),
            ]);
            $this->scheduler->scheduleTask($task);
        }

        return $this->bootstrap();
    }

    public function storeBusyBlock(Request $request): JsonResponse
    {
        BusyBlock::create($this->validateBusyBlock($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function updateBusyBlock(Request $request, BusyBlock $busyBlock): JsonResponse
    {
        $busyBlock->update($this->validateBusyBlock($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function deleteBusyBlock(BusyBlock $busyBlock): JsonResponse
    {
        $busyBlock->delete();
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function storeDateOverride(Request $request): JsonResponse
    {
        $validated = $this->validateTimeRows($request, ['date' => ['required', 'date']]);

        DateWorkOverride::query()->where('date', $validated['date'])->delete();
        foreach ($validated['rows'] as $row) {
            DateWorkOverride::create([
                'date' => $validated['date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
            ]);
        }
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function storeWorkSchedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7'],
            'days.*.enabled' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['days'] as $day) {
            if (! $day['enabled']) {
                continue;
            }

            if (! isset($day['start_time'], $day['end_time']) || $day['end_time'] <= $day['start_time']) {
                throw ValidationException::withMessages([
                    'days' => 'Ogni giorno attivo deve avere un orario di fine successivo all’orario di inizio.',
                ]);
            }
        }

        WorkSchedule::query()->delete();
        foreach ($validated['days'] as $day) {
            if (! $day['enabled']) {
                continue;
            }

            WorkSchedule::create([
                'weekday' => $day['weekday'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
            ]);
        }
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function recalculate(): JsonResponse
    {
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function completePastEvent(ScheduledBlock $scheduledBlock): JsonResponse
    {
        $scheduledBlock->task->update([
            'status' => 'done',
            'completed_at' => now(),
            'worked_at' => $scheduledBlock->task->worked_at ?? $this->workedAtFor($scheduledBlock->task, $scheduledBlock),
        ]);

        return $this->bootstrap();
    }

    public function reschedulePastEvent(ScheduledBlock $scheduledBlock): JsonResponse
    {
        if ($scheduledBlock->task->recurring_event_id) {
            $original = $scheduledBlock->task;
            $original->update(['status' => 'skipped']);
            $scheduledBlock->delete();
            $task = Task::create([
                'project_id' => $original->project_id,
                'title' => $original->title,
                'description' => $original->description,
                'duration_minutes' => $original->duration_minutes,
                'priority' => 3,
                'is_max_priority' => false,
                'is_pinned' => false,
                'status' => 'open',
            ]);
            $this->scheduler->scheduleTask($task);

            return $this->bootstrap();
        }

        $scheduledBlock->task->update([
            'status' => 'open',
            'completed_at' => null,
            'worked_at' => null,
            'is_pinned' => false,
            'pinned_start_at' => null,
        ]);
        $scheduledBlock->delete();
        $this->scheduler->scheduleTask($scheduledBlock->task);

        return $this->bootstrap();
    }

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['required', 'integer', 'between:1,5'],
            'deadline' => ['nullable', 'date'],
        ]);
    }

    private function validateRecurringEvent(Request $request): array
    {
        $attributes = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:2400', 'multiple_of:5'],
            'start_mode' => ['required', Rule::in(['fixed', 'window'])],
            'starts_at' => ['required', 'date'],
            'latest_start_time' => ['nullable', 'required_if:start_mode,window', 'prohibited_if:start_mode,fixed', 'date_format:H:i'],
            'interval_count' => ['required', 'integer', 'min:1', 'max:365'],
            'interval_unit' => ['required', Rule::in(['hours', 'days', 'weeks', 'months'])],
        ]);

        if (
            $attributes['start_mode'] === 'window'
            && $attributes['latest_start_time'] <= Carbon::parse($attributes['starts_at'])->format('H:i')
        ) {
            throw ValidationException::withMessages([
                'latest_start_time' => 'La fine della fascia deve essere successiva all\'inizio.',
            ]);
        }

        return $attributes;
    }

    private function workedAtFor(Task $task, ?ScheduledBlock $block = null): \Carbon\CarbonInterface
    {
        $block ??= $task->scheduledBlocks()->orderBy('start_at')->first();

        return $block && $block->start_at->lte(now()) ? $block->start_at : now();
    }

    private function validateTask(Request $request): array
    {
        return $this->taskInput->validate($request->all());
    }

    private function validateBusyBlock(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ]);
    }

    private function validateTimeRows(Request $request, array $extraRules = []): array
    {
        $validated = $request->validate($extraRules + [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.start_time' => ['required', 'date_format:H:i'],
            'rows.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        foreach ($validated['rows'] as $row) {
            if ($row['end_time'] <= $row['start_time']) {
                throw ValidationException::withMessages([
                    'rows' => 'Ogni fascia deve avere un orario di fine successivo all’orario di inizio.',
                ]);
            }
        }

        return $validated;
    }

    private function events(): array
    {
        $scheduled = ScheduledBlock::query()
            ->with('task.project')
            ->orderBy('start_at')
            ->get()
            ->map(fn (ScheduledBlock $block) => [
                'id' => 'task-'.$block->id,
                'title' => $block->task->title,
                'start' => $block->start_at->toIso8601String(),
                'end' => $block->end_at->toIso8601String(),
                'backgroundColor' => $block->task->project->color,
                'borderColor' => $block->task->project->color,
                'extendedProps' => [
                    'type' => 'task',
                    'scheduled_block_id' => $block->id,
                    'task_id' => $block->task->id,
                    'project_id' => $block->task->project->id,
                    'project' => $block->task->project->name,
                    'priority' => $block->task->priority,
                    'max' => $block->task->is_max_priority,
                    'pinned' => $block->task->is_pinned,
                    'pinned_start_at' => $block->task->pinned_start_at?->toIso8601String(),
                    'description' => $block->task->description,
                    'duration_minutes' => $block->task->duration_minutes,
                    'deadline' => $block->task->deadline?->toDateString(),
                    'status' => $block->task->status,
                    'recurring_event_id' => $block->task->recurring_event_id,
                    'recurrence_at' => $block->task->recurrence_at?->toIso8601String(),
                ],
            ]);

        $busy = BusyBlock::query()
            ->orderBy('start_at')
            ->get()
            ->map(fn (BusyBlock $block) => [
                'id' => 'busy-'.$block->id,
                'title' => $block->title,
                'start' => $block->start_at->toIso8601String(),
                'end' => $block->end_at->toIso8601String(),
                'backgroundColor' => '#625b71',
                'borderColor' => '#625b71',
                'extendedProps' => [
                    'type' => 'busy',
                    'busy_id' => $block->id,
                ],
            ]);

        return $scheduled->concat($busy)->values()->all();
    }

    private function pastEvents(): array
    {
        return ScheduledBlock::query()
            ->with('task.project')
            ->where('end_at', '<', now())
            ->whereHas('task', fn ($query) => $query->where('status', 'open'))
            ->orderByDesc('end_at')
            ->get()
            ->map(fn (ScheduledBlock $block) => [
                'id' => $block->id,
                'title' => $block->task->title,
                'start' => $block->start_at->toIso8601String(),
                'end' => $block->end_at->toIso8601String(),
                'minutes' => $block->minutes,
                'task' => [
                    'id' => $block->task->id,
                    'title' => $block->task->title,
                    'priority' => $block->task->priority,
                    'duration_minutes' => $block->task->duration_minutes,
                    'is_max_priority' => $block->task->is_max_priority,
                ],
                'project' => [
                    'id' => $block->task->project->id,
                    'name' => $block->task->project->name,
                    'color' => $block->task->project->color,
                ],
            ])
            ->values()
            ->all();
    }
}
