<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'emp_no',
        'first_name',
        'middle_name',
        'last_name',
        'status',
        'employment_status_id',
        'birthday',
        'mobile',
        'address',
        'address_province',
        'address_city',
        'address_barangay',
        'address_street',
        'email',
        'department',
        'based_location',
        'position',
        'employment_type',
        'pay_type',
        'date_hired',
        'assignment_type',
        'area_place',
        'external_area',
        'external_position_id',
        'basic_pay',
        'allowance',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'payout_method',
        'sss',
        'philhealth',
        'pagibig',
        'tin',
        'force_statutory_premiums',
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'allowance' => 'decimal:2',
        'birthday' => 'date:Y-m-d',
        'date_hired' => 'date:Y-m-d',
        'force_statutory_premiums' => 'boolean',
    ];

    public function getAssignmentTypeAttribute($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $lower = mb_strtolower($value);
        $lower = str_replace(
            ["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}"],
            '-',
            $lower,
        );
        $compact = preg_replace('/[^a-z0-9]+/u', '', $lower) ?? '';
        if (str_contains($compact, 'multisite') && str_contains($compact, 'roving')) {
            return '';
        }

        return $value;
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'employee_position');
    }

    public function externalPosition(): BelongsTo
    {
        return $this->belongsTo(ExternalPosition::class, 'external_position_id');
    }

    public function payrollRunRows(): HasMany
    {
        return $this->hasMany(PayrollRunRow::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function areaHistories(): HasMany
    {
        return $this->hasMany(EmployeeAreaHistory::class)->orderByDesc('effective_date');
    }

    public function resolveAreaForDate(string $date): ?string
    {
        $history = $this->areaHistories()
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
        return $history?->area_place ?? $this->area_place;
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(EmployeeDisciplinaryRecord::class)->latest('issued_at')->latest('id');
    }
}
