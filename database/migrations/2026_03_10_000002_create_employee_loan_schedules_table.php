<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('due_month', 7);
            $table->string('due_cutoff', 5);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('scheduled');
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'due_month', 'due_cutoff', 'status'], 'loan_sched_lookup');
            $table->index(['employee_loan_id', 'status'], 'loan_sched_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_schedules');
    }
};
