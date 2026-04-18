<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $run_code
 * @property string|null $period_month
 * @property string|null $cutoff
 * @property string|null $run_type
 * @property string|null $assignment_filter
 * @property string|null $area_place_filter
 * @property string|null $status
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
