<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledBlock extends Model
{
    protected $fillable = ['task_id', 'start_at', 'end_at', 'minutes'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
