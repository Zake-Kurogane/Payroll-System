<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionSchedule extends Model
{
    protected $fillable = [
        'deduction_case_id',
        'employee_id',
        'due_month',
        'due_cutoff',
        'amount',
        'status',
        'payroll_run_id',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function deductionCase(): BelongsTo
    {
        return $this->belongsTo(DeductionCase::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
