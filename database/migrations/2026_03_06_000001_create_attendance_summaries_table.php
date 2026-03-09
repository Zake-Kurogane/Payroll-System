<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period_month', 7);
            $table->enum('cutoff', ['11-25', '26-10']);
            $table->decimal('present_days', 6, 2)->default(0);
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('leave_days', 6, 2)->default(0);
            $table->decimal('paid_days', 6, 2)->default(0);
            $table->unsignedInteger('minutes_late')->default(0);
            $table->unsignedInteger('minutes_undertime')->default(0);
            $table->decimal('ot_hours', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'period_month', 'cutoff'], 'attendance_summaries_emp_period_cutoff_uq');
            $table->index(['period_month', 'cutoff'], 'attendance_summaries_period_cutoff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
