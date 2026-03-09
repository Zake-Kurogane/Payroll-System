<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaPlace extends Model
{
    protected $fillable = ['code', 'label', 'parent_assignment', 'is_active', 'sort_order'];
}
