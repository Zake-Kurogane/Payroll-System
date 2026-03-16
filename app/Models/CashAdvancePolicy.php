<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvancePolicy extends Model
{
    protected $fillable = [
        'enabled',
        'default_method',
        'default_term_months',
        'regular_default_term_months',
        'probationary_term_months',
        'trainee_term_months',
        'trainee_force_full_deduct',
        'allow_full_deduct',
        'max_payback_months',
        'deduct_timing',
        'priority',
        'deduction_method',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'trainee_force_full_deduct' => 'boolean',
        'allow_full_deduct' => 'boolean',
    ];
}
