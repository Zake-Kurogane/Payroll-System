<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    protected $fillable = ['label', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
