<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionPolicy extends Model
{
    protected $fillable = [
        'apply_premiums_to_non_regular',
        'apply_tax_to_non_regular',
        'cap_charges_to_net_pay',
        'carry_forward_unpaid_charges',
        'cap_cash_advance_to_net_pay',
        'loans_before_charges',
        'charges_before_cash_advance',
    ];

    protected $casts = [
        'apply_premiums_to_non_regular' => 'boolean',
        'apply_tax_to_non_regular' => 'boolean',
        'cap_charges_to_net_pay' => 'boolean',
        'carry_forward_unpaid_charges' => 'boolean',
        'cap_cash_advance_to_net_pay' => 'boolean',
        'loans_before_charges' => 'boolean',
        'charges_before_cash_advance' => 'boolean',
    ];
}

