<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionCase extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'description',
        'amount_total',
        'plan_type',
        'installment_count',
        'start_month',
        'start_cutoff',
        'status',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DeductionSchedule::class);
    }
}
