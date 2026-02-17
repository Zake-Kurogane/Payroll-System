<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'emp_no',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'department',
        'position',
        'employment_type',
        'assignment_type',
        'area_place',
        'basic_pay',
        'allowance',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'payout_method',
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'allowance' => 'decimal:2',
    ];

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function payrollRunRows(): HasMany
    {
        return $this->hasMany(PayrollRunRow::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
