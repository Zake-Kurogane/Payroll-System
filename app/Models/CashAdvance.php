<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'term_months',
        'start_month',
        'method',
        'per_cutoff_deduction',
        'status',
    ];
}
