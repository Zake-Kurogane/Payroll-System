<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunRowOverride extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'ot_override_on',
        'ot_override_hours',
        'cash_advance',
        'adjustments',
    ];

    protected $casts = [
        'ot_override_on' => 'boolean',
        'ot_override_hours' => 'decimal:2',
        'cash_advance' => 'decimal:2',
        'adjustments' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
