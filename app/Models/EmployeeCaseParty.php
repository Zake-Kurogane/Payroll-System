<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCaseParty extends Model
{
    protected $fillable = [
        'case_id',
        'employee_id',
        'role',
        'remarks',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(EmployeeCase::class, 'case_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
