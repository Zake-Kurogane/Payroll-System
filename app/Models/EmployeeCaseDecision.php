<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCaseDecision extends Model
{
    protected $fillable = [
        'case_id',
        'decision_date',
        'decision_summary',
        'decision_by',
        'nte_ignored',
        'remarks',
    ];

    protected $casts = [
        'decision_date' => 'date:Y-m-d',
        'nte_ignored' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(EmployeeCase::class, 'case_id');
    }
}
