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
        'fixed_amount',
        'percent',
        'wt_table_source',
        'wt_table_imported_at',
    ];
}
