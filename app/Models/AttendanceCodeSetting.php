<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCodeSetting extends Model
{
    protected $fillable = [
        'default_no_log_code',
        'default_sunday_code',
        'paid_leave_cap_days',
        'template_codes',
        'no_time_statuses',
        'time_tracked_statuses',
    ];

    protected $casts = [
        'template_codes' => 'array',
        'no_time_statuses' => 'array',
        'time_tracked_statuses' => 'array',
    ];
}
