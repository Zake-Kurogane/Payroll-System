<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_run_rows', 'other_deductions')) {
                $table->dropColumn('other_deductions');
            }
        });

        Schema::table('payroll_run_row_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_run_row_overrides', 'other_deductions')) {
                $table->dropColumn('other_deductions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_run_rows', 'other_deductions')) {
                $table->decimal('other_deductions', 12, 2)->default(0)->after('attendance_deduction');
            }
        });

        Schema::table('payroll_run_row_overrides', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_run_row_overrides', 'other_deductions')) {
                $table->decimal('other_deductions', 12, 2)->nullable()->after('ot_override_hours');
            }
        });
    }
};
