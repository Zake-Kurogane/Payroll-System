<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentStatus extends Model
{
    protected $fillable = ['code', 'label', 'is_active', 'sort_order'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
