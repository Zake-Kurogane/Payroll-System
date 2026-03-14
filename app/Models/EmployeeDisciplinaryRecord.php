<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDisciplinaryRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'reference',
        'issued_at',
        'remarks',
    ];

    protected $casts = [
        'issued_at' => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
