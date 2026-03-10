<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoan extends Model
{
    protected $fillable = [
        'employee_id',
        'loan_type',
        'lender',
        'monthly_amortization',
        'start_month',
        'start_cutoff',
        'term_months',
        'status',
        'reference_no',
        'notes',
        'loan_no',
        'principal_amount',
        'interest_amount',
        'total_payable',
        'approval_date',
        'release_date',
        'deduction_frequency',
        'deduction_method',
        'per_cutoff_amount',
        'amount_deducted',
        'balance_remaining',
        'auto_deduct',
        'allow_partial',
        'carry_forward',
        'stop_on_zero',
        'priority_order',
        'source',
        'recovery_type',
        'approved_by',
        'encoded_by',
    ];

    protected $casts = [
        'monthly_amortization' => 'decimal:2',
        'term_months' => 'integer',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'per_cutoff_amount' => 'decimal:2',
        'amount_deducted' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'auto_deduct' => 'boolean',
        'allow_partial' => 'boolean',
        'carry_forward' => 'boolean',
        'stop_on_zero' => 'boolean',
        'priority_order' => 'integer',
        'approval_date' => 'date',
        'release_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeLoanSchedule::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeLoanHistory::class);
    }
}
