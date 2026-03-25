<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payslip_claim_proof_id')->nullable()->constrained('payslip_claim_proofs')->nullOnDelete();
            $table->decimal('ink_ratio', 7, 5)->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['payroll_run_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_claims');
    }
};

