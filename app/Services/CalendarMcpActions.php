<?php

namespace App\Services;

use App\Models\BusyBlock;
use App\Models\Project;
use App\Models\ScheduledBlock;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarMcpActions
{
    private const EDITABLE_TASK_FIELDS = [
        'title', 'project_id', 'description', 'duration_minutes', 'priority', 'deadline',
        'is_max_priority', 'is_pinned', 'pinned_start_at', 'earliest_start_date', 'earliest_start_time', 'latest_end_time',
    ];

    public function __construct(
        private readonly SchedulerService $scheduler,
        private readonly TaskInput $taskInput,
        private readonly RecurringEventService $recurringEvents,
    ) {}

    public function projects(): array
    {
        return ['projects' => Project::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'priority' => $project->priority,
                'color' => $project->color,
                'deadline' => $project->deadline?->toDateString(),
            ])->all()];
    }

    public function tasks(array $input): array
    {
        $filters = Validator::make($input, [
            'status' => ['sometimes', Rule::in(['open', 'done', 'all'])],
            'project_id' => ['sometimes', 'integer', Rule::exists('projects', 'id')],
        ])->validate();

        $tasks = Task::query()
            ->with(['project', 'scheduledBlocks' => fn ($query) => $query->orderBy('start_at')])
            ->when(($filters['status'] ?? 'open') !== 'all', fn ($query) => $query->where('status', $filters['status'] ?? 'open'))
            ->when(isset($filters['project_id']), fn ($query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit(100)
            ->get();

        return ['tasks' => $tasks->map(fn (Task $task) => $this->taskDetails($task))->all()];
    }

    public function agenda(array $input): array
    {
        $dates = Validator::make($input, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        $from = Carbon::parse($dates['from'])->startOfDay();
        $to = Carbon::parse($dates['to'])->endOfDay();
        if ($from->diffInDays($to) > 31) {
            throw ValidationException::withMessages(['to' => 'L\'intervallo massimo e 31 giorni.']);
        }

        $tasks = ScheduledBlock::query()
            ->with('task.project')
            ->where('start_at', '<=', $to)
            ->where('end_at', '>=', $from)
            ->orderBy('start_at')
            ->get()
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

        $busy = BusyBlock::query()
            ->where('start_at', '<=', $to)
            ->where('end_at', '>=', $from)
            ->orderBy('start_at')
            ->get()
            ->map(fn (BusyBlock $block) => [
                'type' => 'busy',
                'id' => $block->id,
                'title' => $block->title,
                'start_at' => $block->start_at->toIso8601String(),
                'end_at' => $block->end_at->toIso8601String(),
            ]);

        return ['events' => $tasks->concat($busy)->sortBy('start_at')->values()->all()];
    }

    public function createTask(array $input): array
    {
        $attributes = $this->taskInput->validate(array_replace([
            'description' => null,
            'duration_minutes' => 30,
            'priority' => 3,
            'deadline' => null,
            'is_max_priority' => false,
            'is_pinned' => false,
            'pinned_start_at' => null,
            'earliest_start_date' => null,
            'earliest_start_time' => null,
            'latest_end_time' => null,
            'status' => 'open',
        ], Arr::only($input, self::EDITABLE_TASK_FIELDS)));

        $task = Task::create($attributes);
        $this->scheduler->scheduleTask($task);

        return ['task' => $this->taskDetails($task->load('project', 'scheduledBlocks'))];
    }

    public function updateTask(array $input): array
    {
        $taskId = Validator::make($input, ['task_id' => ['required', 'integer', 'exists:tasks,id']])->validate()['task_id'];
        $task = Task::query()->findOrFail($taskId);
        $changes = Arr::only($input, self::EDITABLE_TASK_FIELDS);
        $attributes = $this->taskInput->validate(array_replace($this->taskInput->current($task), $changes));

        $task->update($attributes);
        $this->scheduler->scheduleTask($task);

        return ['task' => $this->taskDetails($task->load('project', 'scheduledBlocks'))];
    }

    public function completeTask(int $taskId): array
    {
        $task = Task::query()->findOrFail($taskId);
        if ($task->status !== 'done') {
            $block = $task->scheduledBlocks()->orderBy('start_at')->first();
            $task->update([
                'status' => 'done',
                'completed_at' => now(),
                'worked_at' => $block && $block->start_at->lte(now()) ? $block->start_at : now(),
            ]);
            $this->scheduler->scheduleTask($task);
        }

        return ['task' => $this->taskDetails($task->load('project', 'scheduledBlocks'))];
    }

    public function recalculatePlanning(): array
    {
        $this->recurringEvents->sync();
        $this->scheduler->recalculate();

        return [
            'recalculated' => true,
            'scheduled_open_tasks' => Task::query()
                ->where('status', 'open')
                ->whereHas('scheduledBlocks', fn ($query) => $query->where('end_at', '>=', now()))
                ->count(),
            'unscheduled_open_tasks' => Task::query()
                ->where('status', 'open')
                ->whereDoesntHave('scheduledBlocks')
                ->count(),
        ];
    }

    private function taskDetails(Task $task): array
    {
        $task->loadMissing('project', 'scheduledBlocks');
        $block = $task->scheduledBlocks->sortBy('start_at')->first();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'project' => $task->project->name,
            'duration_minutes' => $task->duration_minutes,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString(),
            'is_max_priority' => $task->is_max_priority,
            'is_pinned' => $task->is_pinned,
            'pinned_start_at' => $task->pinned_start_at?->toIso8601String(),
            'earliest_start_date' => $task->earliest_start_date?->toDateString(),
            'earliest_start_time' => $task->earliest_start_time,
            'latest_end_time' => $task->latest_end_time,
            'status' => $task->status,
            'scheduled_start_at' => $block?->start_at->toIso8601String(),
            'scheduled_end_at' => $block?->end_at->toIso8601String(),
        ];
    }
}
