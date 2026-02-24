<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvancePolicy extends Model
{
    protected $fillable = [
        'enabled',
        'default_method',
        'default_term_months',
        'deduct_timing',
        'priority',
        'deduction_method',
    ];
}
