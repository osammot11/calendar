<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'duration_minutes',
        'earliest_start_date',
        'earliest_start_time',
        'latest_end_time',
        'priority',
        'deadline',
        'is_max_priority',
        'is_pinned',
        'pinned_start_at',
        'manual_start_at',
        'status',
        'completed_at',
        'worked_at',
        'recurring_event_id',
        'recurrence_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'earliest_start_date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'priority' => 'integer',
            'is_max_priority' => 'boolean',
            'is_pinned' => 'boolean',
            'pinned_start_at' => 'datetime',
            'manual_start_at' => 'datetime',
            'completed_at' => 'datetime',
            'worked_at' => 'datetime',
            'recurrence_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Fissare (o spostare) un appuntamento da modal o MCP e una scelta
        // esplicita di orario: annulla l'eventuale posizionamento manuale,
        // salvo quando e il riposizionamento stesso a impostarli insieme.
        static::saving(function (Task $task): void {
            if (
                $task->manual_start_at
                && $task->is_pinned
                && ($task->isDirty('is_pinned') || $task->isDirty('pinned_start_at'))
                && ! $task->isDirty('manual_start_at')
            ) {
                $task->manual_start_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scheduledBlocks(): HasMany
    {
        return $this->hasMany(ScheduledBlock::class);
    }

    public function recurringEvent(): BelongsTo
    {
        return $this->belongsTo(RecurringEvent::class);
    }
}
