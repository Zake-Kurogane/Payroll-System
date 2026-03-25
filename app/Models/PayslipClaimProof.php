<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayslipClaimProof extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'uploaded_by_user_id',
        'original_name',
        'mime',
        'size_bytes',
        'storage_path',
        'processed_at',
        'processed_summary',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'processed_summary' => 'array',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PayslipClaim::class, 'payslip_claim_proof_id');
    }
}

