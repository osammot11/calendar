<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'priority', 'deadline'];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'priority' => 'integer',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
