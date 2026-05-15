<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_deduction_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_deduction_policies', 'field_daily_divisor')) {
                $table->unsignedInteger('field_daily_divisor')->default(30)->after('charges_before_cash_advance');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'non_field_daily_divisor')) {
                $table->unsignedInteger('non_field_daily_divisor')->default(26)->after('field_daily_divisor');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'field_unpaid_statuses')) {
                $table->json('field_unpaid_statuses')->nullable()->after('non_field_daily_divisor');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'field_unpaid_absent_and_leave')) {
                $table->boolean('field_unpaid_absent_and_leave')->default(true)->after('field_unpaid_statuses');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'field_absent_deduction_exempt')) {
                $table->boolean('field_absent_deduction_exempt')->default(true)->after('field_unpaid_absent_and_leave');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'external_runs_allow_all_assignments')) {
                $table->boolean('external_runs_allow_all_assignments')->default(false)->after('field_absent_deduction_exempt');
            }
            if (!Schema::hasColumn('payroll_deduction_policies', 'split_employees_by_run_type_when_assignment_specific')) {
                $table->boolean('split_employees_by_run_type_when_assignment_specific')->default(true)->after('external_runs_allow_all_assignments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deduction_policies', function (Blueprint $table) {
            foreach ([
                'split_employees_by_run_type_when_assignment_specific',
                'external_runs_allow_all_assignments',
                'field_absent_deduction_exempt',
                'field_unpaid_absent_and_leave',
                'field_unpaid_statuses',
                'non_field_daily_divisor',
                'field_daily_divisor',
            ] as $col) {
                if (Schema::hasColumn('payroll_deduction_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

