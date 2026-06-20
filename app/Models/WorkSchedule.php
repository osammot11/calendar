<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $fillable = ['weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
