<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithholdingTaxPolicy extends Model
{
    protected $fillable = [
        'enabled',
        'method',
        'pay_frequency',
        'basis_sss',
        'basis_ph',
        'basis_pi',
        'timing',
        'split_rule',
        'fixed_amount',
        'percent',
        'wt_table_source',
        'wt_table_imported_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'basis_sss' => 'boolean',
        'basis_ph' => 'boolean',
        'basis_pi' => 'boolean',
        'fixed_amount' => 'float',
        'percent' => 'float',
    ];
}
