<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasedLocation extends Model
{
    protected $fillable = ['label', 'sort_order', 'is_active'];
}

