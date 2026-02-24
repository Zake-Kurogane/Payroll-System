<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimekeepingRule extends Model
{
    protected $fillable = [
        'shift_start',
        'grace_minutes',
        'late_rule_type',
        'late_penalty_per_minute',
        'late_rounding',
        'undertime_enabled',
        'undertime_rule_type',
        'undertime_penalty_per_minute',
        'work_minutes_per_day',
    ];
}
