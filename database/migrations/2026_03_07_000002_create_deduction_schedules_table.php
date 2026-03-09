<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('due_month', 7);       // YYYY-MM
            $table->string('due_cutoff', 5);      // '11-25' | '26-10'
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('scheduled'); // 'scheduled' | 'applied' | 'void'
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_schedules');
    }
};
