<?php

namespace App\Http\Controllers;

use App\Models\BusyBlock;
use App\Models\DateWorkOverride;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Models\WorkSchedule;
use App\Services\SchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlannerController extends Controller
{
    public function __construct(private readonly SchedulerService $scheduler)
    {
    }

    public function app(): View
    {
        return view('planner');
    }

    public function bootstrap(): JsonResponse
    {
        if (Task::query()->where('status', 'open')->exists() && ScheduledBlock::query()->doesntExist()) {
            $this->scheduler->recalculate();
        }

        return response()->json([
            'user' => Auth::user()->only(['name', 'email']),
            'projects' => Project::query()->orderByDesc('priority')->orderBy('name')->get(),
            'tasks' => Task::query()->with('project')->latest()->get(),
            'workSchedules' => WorkSchedule::query()->orderBy('weekday')->orderBy('start_time')->get(),
            'dateOverrides' => DateWorkOverride::query()->orderByDesc('date')->orderBy('start_time')->limit(60)->get(),
            'busyBlocks' => BusyBlock::query()->where('end_at', '>=', now()->subWeek())->orderBy('start_at')->get(),
            'events' => $this->events(),
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
        Task::create($this->validateTask($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function updateTask(Request $request, Task $task): JsonResponse
    {
        $task->update($this->validateTask($request));
        $this->scheduler->recalculate();

        return $this->bootstrap();
    }

    public function deleteTask(Task $task): JsonResponse
    {
        $task->delete();
        $this->scheduler->recalculate();

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

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['required', 'integer', 'between:1,5'],
            'deadline' => ['nullable', 'date'],
        ]);
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:2400'],
            'priority' => ['required', 'integer', 'between:1,5'],
            'deadline' => ['nullable', 'date'],
            'is_max_priority' => ['required', 'boolean'],
            'is_pinned' => ['required', 'boolean'],
            'pinned_start_at' => ['nullable', 'required_if:is_pinned,true', 'date'],
            'status' => ['required', Rule::in(['open', 'done'])],
        ]);
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
            ->where('end_at', '>=', now()->subWeek())
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
                ],
            ]);

        $busy = BusyBlock::query()
            ->where('end_at', '>=', now()->subWeek())
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
}
