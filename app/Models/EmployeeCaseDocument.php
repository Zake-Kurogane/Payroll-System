<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCaseDocument extends Model
{
    protected $fillable = [
        'case_id',
        'employee_id',
        'document_type',
        'file_path',
        'file_name',
        'date_uploaded',
        'uploaded_by',
    ];

    protected $casts = [
        'date_uploaded' => 'date:Y-m-d',
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
