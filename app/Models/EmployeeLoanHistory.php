<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanHistory extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'employee_id',
        'payroll_run_id',
        'period_month',
        'cutoff',
        'scheduled_amount',
        'deducted_amount',
        'balance_before',
        'balance_after',
        'status',
        'remarks',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'scheduled_amount' => 'decimal:2',
        'deducted_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
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
