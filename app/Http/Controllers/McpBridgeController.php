<?php

namespace App\Http\Controllers;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use App\Services\SchedulerService;
use App\Services\TaskInput;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class McpBridgeController extends Controller
{
    public function __construct(private readonly SchedulerService $scheduler, private readonly TaskInput $taskInput) {}

    public function projects(): JsonResponse
    {
        return response()->json([
            'projects' => Project::query()->orderByDesc('priority')->orderBy('name')->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'priority' => $project->priority,
                    'deadline' => $project->deadline?->toDateString(),
                ]),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'done', 'all'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ]);

        $tasks = Task::query()->with(['project', 'scheduledBlocks' => fn ($query) => $query->orderBy('start_at')])
            ->when(($filter['status'] ?? 'open') !== 'all', fn ($query) => $query->where('status', $filter['status'] ?? 'open'))
            ->when(isset($filter['project_id']), fn ($query) => $query->where('project_id', $filter['project_id']))
            ->orderByDesc('is_max_priority')->orderByDesc('priority')->orderBy('created_at')
            ->limit(100)->get()->map(fn (Task $task) => $this->taskSummary($task));

        return response()->json(['tasks' => $tasks, 'limit' => 100]);
    }

    public function agenda(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $start = Carbon::parse($range['from'])->startOfDay();
        $end = Carbon::parse($range['to'])->endOfDay();
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages(['to' => 'Intervallo massimo: 31 giorni.']);
        }

        $scheduled = ScheduledBlock::query()->with('task.project')
            ->where('start_at', '<=', $end)->where('end_at', '>=', $start)
            ->orderBy('start_at')->limit(200)->get()
            ->map(fn (ScheduledBlock $block) => [
                'type' => 'task',
                'task_id' => $block->task_id,
                'title' => $block->task->title,
                'project' => $block->task->project->name,
                'status' => $block->task->status,
                'is_pinned' => $block->task->is_pinned,
                'start_at' => $block->start_at->toIso8601String(),
                'end_at' => $block->end_at->toIso8601String(),
            ]);

        $busy = BusyBlock::query()->where('start_at', '<=', $end)->where('end_at', '>=', $start)
            ->orderBy('start_at')->limit(200)->get()
            ->map(fn (BusyBlock $block) => [
                'type' => 'busy',
                'title' => $block->title,
                'start_at' => $block->start_at->toIso8601String(),
                'end_at' => $block->end_at->toIso8601String(),
            ]);

        return response()->json([
            'timezone' => config('app.timezone'),
            'from' => $range['from'],
            'to' => $range['to'],
            'events' => $scheduled->concat($busy)->sortBy('start_at')->values(),
        ]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $attributes = $this->taskInput->validate(array_replace([
            'description' => null,
            'duration_minutes' => 60,
            'priority' => 3,
            'deadline' => null,
            'is_max_priority' => false,
            'is_pinned' => false,
            'pinned_start_at' => null,
            'status' => 'open',
        ], $request->all()));
        $attributes['completed_at'] = $attributes['status'] === 'done' ? now() : null;
        $task = Task::create($attributes);
        $this->scheduler->scheduleTask($task);

        return response()->json(['task' => $this->taskSummary($task->load(['project', 'scheduledBlocks']))], 201);
    }

    public function updateTask(Request $request, Task $task): JsonResponse
    {
        $changes = Arr::only($request->all(), [
            'project_id', 'title', 'description', 'duration_minutes', 'priority', 'deadline',
            'is_max_priority', 'is_pinned', 'pinned_start_at', 'earliest_start_time', 'latest_end_time',
        ]);
        if ($changes === []) {
            throw ValidationException::withMessages(['task' => 'Nessun campo modificabile fornito.']);
        }

        $input = array_replace($this->taskInput->current($task), $changes);
        if (! $input['is_pinned']) {
            $input['pinned_start_at'] = null;
        }
        if ($input['is_pinned']) {
            $input['earliest_start_time'] = null;
            $input['latest_end_time'] = null;
        }

        $task->update($this->taskInput->validate($input));
        $this->scheduler->scheduleTask($task);

        return response()->json(['task' => $this->taskSummary($task->load(['project', 'scheduledBlocks']))]);
    }

    public function completeTask(Task $task): JsonResponse
    {
        if ($task->status !== 'done') {
            $task->update(['status' => 'done', 'completed_at' => now()]);
            $this->scheduler->scheduleTask($task);
        }

        return response()->json(['task' => $this->taskSummary($task->load(['project', 'scheduledBlocks']))]);
    }

    private function taskSummary(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description ? Str::limit($task->description, 1000) : null,
            'project_id' => $task->project_id,
            'project' => $task->project->name,
            'duration_minutes' => $task->duration_minutes,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString(),
            'is_max_priority' => $task->is_max_priority,
            'is_pinned' => $task->is_pinned,
            'pinned_start_at' => $task->pinned_start_at?->toIso8601String(),
            'earliest_start_time' => $task->earliest_start_time ? substr($task->earliest_start_time, 0, 5) : null,
            'latest_end_time' => $task->latest_end_time ? substr($task->latest_end_time, 0, 5) : null,
            'status' => $task->status,
            'scheduled' => $task->scheduledBlocks->map(fn (ScheduledBlock $block) => [
                'start_at' => $block->start_at->toIso8601String(),
                'end_at' => $block->end_at->toIso8601String(),
            ])->values(),
        ];
    }
}
