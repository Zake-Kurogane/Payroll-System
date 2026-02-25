<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_run_row_overrides')) {
            Schema::create('payroll_run_row_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->boolean('ot_override_on')->default(false);
                $table->decimal('ot_override_hours', 6, 2)->nullable();
                $table->decimal('other_deductions', 12, 2)->nullable();
                $table->decimal('cash_advance', 12, 2)->nullable();
                $table->json('adjustments')->nullable();
                $table->timestamps();

                $table->unique(['payroll_run_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_row_overrides');
    }
};
