<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayGroup extends Model
{
    protected $fillable = [
        'name',
        'pay_frequency',
        'calendar',
        'default_shift_start',
        'default_ot_mode',
    ];
}
