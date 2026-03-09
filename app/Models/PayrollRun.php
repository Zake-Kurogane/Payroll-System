<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'run_code',
        'period_month',
        'cutoff',
        'run_type',
        'assignment_filter',
        'area_place_filter',
        'status',
        'created_by',
        'locked_at',
        'released_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function displayLabel(): string
    {
        $loc = match(true) {
            $this->assignment_filter === 'Field' && !!$this->area_place_filter
                => 'Area (' . $this->area_place_filter . ')',
            !empty($this->assignment_filter) && $this->assignment_filter !== 'All'
                => $this->assignment_filter,
            default => 'All',
        };
        return ($this->run_type ?? 'External') . ' · ' . $loc;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PayrollRunRow::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
