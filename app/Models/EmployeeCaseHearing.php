<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCaseHearing extends Model
{
    protected $fillable = [
        'case_id',
        'hearing_date',
        'location',
        'notes',
        'status',
    ];

    protected $casts = [
        'hearing_date' => 'date:Y-m-d',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(EmployeeCase::class, 'case_id');
    }
}
