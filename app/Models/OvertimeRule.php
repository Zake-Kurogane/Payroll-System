<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRule extends Model
{
    protected $fillable = [
        'ot_mode',
        'require_approval',
        'flat_rate',
        'multiplier',
        'rounding',
    ];

    protected $casts = [
        'require_approval' => 'boolean',
        'flat_rate' => 'decimal:2',
        'multiplier' => 'decimal:2',
    ];
}
