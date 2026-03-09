<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'position',
        'employment_type',
        'pay_type',
        'date_hired',
        'assignment_type',
        'area_place',
        'external_area',
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
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'allowance' => 'decimal:2',
        'birthday' => 'date:Y-m-d',
        'date_hired' => 'date:Y-m-d',
    ];

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
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
}
