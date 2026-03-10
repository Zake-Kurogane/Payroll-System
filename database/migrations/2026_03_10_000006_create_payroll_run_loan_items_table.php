<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('employee_loan_schedule_id')->nullable()->constrained('employee_loan_schedules')->nullOnDelete();
            $table->decimal('scheduled_amount', 12, 2)->default(0);
            $table->decimal('deducted_amount', 12, 2)->default(0);
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id'], 'payroll_loan_run_emp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_loan_items');
    }
};
