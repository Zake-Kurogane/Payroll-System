<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['employee_id', 'date'], 'att_records_employee_date_idx');
            $table->index(['date'], 'att_records_date_idx');
        });

        Schema::table('payroll_run_rows', function (Blueprint $table) {
            $table->index(['payroll_run_id'], 'payroll_run_rows_run_idx');
            $table->index(['payroll_run_id', 'employee_id'], 'payroll_run_rows_run_emp_idx');
        });

        Schema::table('payroll_run_row_overrides', function (Blueprint $table) {
            $table->index(['payroll_run_id', 'employee_id'], 'payroll_run_row_overrides_run_emp_idx');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->index(['payroll_run_id'], 'payslips_run_idx');
            $table->index(['payroll_run_id', 'employee_id'], 'payslips_run_emp_idx');
            $table->index(['employee_id'], 'payslips_emp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('att_records_employee_date_idx');
            $table->dropIndex('att_records_date_idx');
        });

        Schema::table('payroll_run_rows', function (Blueprint $table) {
            $table->dropIndex('payroll_run_rows_run_idx');
            $table->dropIndex('payroll_run_rows_run_emp_idx');
        });

        Schema::table('payroll_run_row_overrides', function (Blueprint $table) {
            $table->dropIndex('payroll_run_row_overrides_run_emp_idx');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex('payslips_run_idx');
            $table->dropIndex('payslips_run_emp_idx');
            $table->dropIndex('payslips_emp_idx');
        });
    }
};
