<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollCalendarSetting extends Model
{
    protected $fillable = [
        'pay_date_a',
        'pay_date_b',
        'cutoff_a_from',
        'cutoff_a_to',
        'cutoff_b_from',
        'cutoff_b_to',
        'work_mon_sat',
        'pay_on_prev_workday_if_sunday',
    ];

    protected $casts = [
        'work_mon_sat' => 'boolean',
        'pay_on_prev_workday_if_sunday' => 'boolean',
    ];
}
