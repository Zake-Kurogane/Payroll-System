<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    protected $fillable = [
        'employee_id',
        'period_month',
        'cutoff',
        'present_days',
        'absent_days',
        'leave_days',
        'paid_days',
        'minutes_late',
        'minutes_undertime',
        'ot_hours',
    ];

    protected $casts = [
        'present_days' => 'decimal:2',
        'absent_days' => 'decimal:2',
        'leave_days' => 'decimal:2',
        'paid_days' => 'decimal:2',
        'ot_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
