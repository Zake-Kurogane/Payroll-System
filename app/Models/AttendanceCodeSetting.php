<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCodeSetting extends Model
{
    protected $fillable = [
        'default_no_log_code',
        'default_sunday_code',
    ];
}
