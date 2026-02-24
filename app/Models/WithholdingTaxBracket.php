<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithholdingTaxBracket extends Model
{
    protected $fillable = [
        'pay_frequency',
        'bracket_no',
        'compensation_range',
        'prescribed_withholding_tax',
        'bracket_from',
        'bracket_to',
        'base_tax',
        'excess_percent',
        'sort_order',
    ];
}
