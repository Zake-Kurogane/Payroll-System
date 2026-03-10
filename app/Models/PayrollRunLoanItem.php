<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunLoanItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'employee_loan_id',
        'employee_loan_schedule_id',
        'scheduled_amount',
        'deducted_amount',
        'balance_before',
        'balance_after',
        'status',
    ];

    protected $casts = [
        'scheduled_amount' => 'decimal:2',
        'deducted_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoanSchedule::class, 'employee_loan_schedule_id');
    }
}
