<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryProrationRule extends Model
{
    protected $fillable = [
        'salary_mode',
        'work_minutes_per_day',
        'minutes_rounding',
        'money_rounding',
    ];
}
