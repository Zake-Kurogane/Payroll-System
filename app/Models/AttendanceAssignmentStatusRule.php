<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAssignmentStatusRule extends Model
{
    protected $fillable = [
        'assignment_type',
        'status',
        'is_allowed',
        'message',
        'default_sunday_status',
        'sort_order',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];
}

