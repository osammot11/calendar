<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskInput
{
    public function validate(array $input): array
    {
        $attributes = Validator::make($input, [
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:2400', 'multiple_of:5'],
            'earliest_start_time' => ['nullable', 'date_format:H:i', 'prohibited_if:is_pinned,true'],
            'latest_end_time' => ['nullable', 'date_format:H:i', 'prohibited_if:is_pinned,true'],
            'priority' => ['required', 'integer', 'between:1,5'],
            'deadline' => ['nullable', 'date'],
            'is_max_priority' => ['required', 'boolean'],
            'is_pinned' => ['required', 'boolean'],
            'pinned_start_at' => ['nullable', 'required_if:is_pinned,true', 'date'],
            'status' => ['required', Rule::in(['open', 'done'])],
        ])->validate();

        if (
            ! empty($attributes['earliest_start_time'])
            && ! empty($attributes['latest_end_time'])
            && $attributes['latest_end_time'] <= $attributes['earliest_start_time']
        ) {
            throw ValidationException::withMessages([
                'latest_end_time' => 'L\'orario limite di fine deve essere successivo a quello di inizio.',
            ]);
        }

        return $attributes;
    }

    public function current(Task $task): array
    {
        return [
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'duration_minutes' => $task->duration_minutes,
            'earliest_start_time' => $task->earliest_start_time ? substr($task->earliest_start_time, 0, 5) : null,
            'latest_end_time' => $task->latest_end_time ? substr($task->latest_end_time, 0, 5) : null,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toDateString(),
            'is_max_priority' => $task->is_max_priority,
            'is_pinned' => $task->is_pinned,
            'pinned_start_at' => $task->pinned_start_at?->format('Y-m-d H:i:s'),
            'status' => $task->status,
        ];
    }
}
