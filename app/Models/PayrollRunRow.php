<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunRow extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_pay_cutoff',
        'allowance_cutoff',
        'ot_hours',
        'ot_pay',
        'gross',
        'sss_ee',
        'philhealth_ee',
        'pagibig_ee',
        'tax',
        'attendance_deduction',
        'other_deductions',
        'cash_advance',
        'deductions_total',
        'net_pay',
        'sss_er',
        'philhealth_er',
        'pagibig_er',
        'employer_share_total',
        'pay_method',
        'bank_account_masked',
    ];

    protected $casts = [
        'basic_pay_cutoff' => 'decimal:2',
        'allowance_cutoff' => 'decimal:2',
        'ot_hours' => 'decimal:2',
        'ot_pay' => 'decimal:2',
        'gross' => 'decimal:2',
        'sss_ee' => 'decimal:2',
        'philhealth_ee' => 'decimal:2',
        'pagibig_ee' => 'decimal:2',
        'tax' => 'decimal:2',
        'attendance_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'cash_advance' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'sss_er' => 'decimal:2',
        'philhealth_er' => 'decimal:2',
        'pagibig_er' => 'decimal:2',
        'employer_share_total' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
