<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DateWorkOverride extends Model
{
    protected $fillable = ['date', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
