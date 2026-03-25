<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipClaim extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'claimed_at',
        'claimed_by_user_id',
        'payslip_claim_proof_id',
        'ink_ratio',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'ink_ratio' => 'decimal:5',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(PayslipClaimProof::class, 'payslip_claim_proof_id');
    }
}

