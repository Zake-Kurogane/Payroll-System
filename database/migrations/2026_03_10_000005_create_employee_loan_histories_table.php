<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->string('period_month', 7)->nullable();
            $table->string('cutoff', 5)->nullable();
            $table->decimal('scheduled_amount', 12, 2)->default(0);
            $table->decimal('deducted_amount', 12, 2)->default(0);
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('status')->default('deducted');
            $table->string('remarks')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'employee_loan_id'], 'loan_hist_emp_idx');
            $table->index(['payroll_run_id'], 'loan_hist_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_histories');
    }
};
