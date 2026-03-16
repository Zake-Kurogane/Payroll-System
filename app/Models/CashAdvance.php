<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvance extends Model
{
    protected $fillable = [
        'reference_no',
        'employee_id',
        'amount',
        'balance_remaining',
        'amount_deducted',
        'term_months',
        'start_month',
        'method',
        'full_deduct',
        'per_cutoff_deduction',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'amount_deducted' => 'decimal:2',
        'per_cutoff_deduction' => 'decimal:2',
        'start_month' => 'date:Y-m-d',
        'full_deduct' => 'boolean',
    ];
}
