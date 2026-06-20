<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusyBlock extends Model
{
    protected $fillable = ['title', 'start_at', 'end_at'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
