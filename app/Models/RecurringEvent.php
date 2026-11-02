<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringEvent extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'duration_minutes',
        'start_mode',
        'starts_at',
        'latest_start_time',
        'interval_count',
        'interval_unit',
        'next_index',
        'next_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'starts_at' => 'datetime',
            'next_at' => 'datetime',
            'interval_count' => 'integer',
            'next_index' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
