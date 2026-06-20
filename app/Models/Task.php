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
        'priority',
        'deadline',
        'is_max_priority',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'duration_minutes' => 'integer',
            'priority' => 'integer',
            'is_max_priority' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scheduledBlocks(): HasMany
    {
        return $this->hasMany(ScheduledBlock::class);
    }
}
