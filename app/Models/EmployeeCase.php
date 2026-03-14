<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCase extends Model
{
    protected $fillable = [
        'case_no',
        'case_type',
        'date_reported',
        'incident_date',
        'location',
        'title',
        'description',
        'status',
        'reported_by_employee_id',
        'reported_by_name',
        'created_by',
    ];

    protected $casts = [
        'date_reported' => 'date:Y-m-d',
        'incident_date' => 'date:Y-m-d',
    ];

    public function parties(): HasMany
    {
        return $this->hasMany(EmployeeCaseParty::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeCaseDocument::class, 'case_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(EmployeeCaseHearing::class, 'case_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(EmployeeCaseDecision::class, 'case_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(EmployeeCaseSanction::class, 'case_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by_employee_id');
    }
}
