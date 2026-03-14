<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCaseSanction extends Model
{
    protected $fillable = [
        'case_id',
        'employee_id',
        'sanction_type',
        'days_suspended',
        'effective_from',
        'effective_to',
        'status',
        'remarks',
    ];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'effective_to' => 'date:Y-m-d',
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
