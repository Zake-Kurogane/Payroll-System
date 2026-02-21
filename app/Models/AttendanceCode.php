<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'counts_as_present',
        'counts_as_paid',
        'affects_deductions',
        'notes',
    ];

    protected $casts = [
        'counts_as_present' => 'boolean',
        'counts_as_paid' => 'boolean',
        'affects_deductions' => 'boolean',
    ];
}
