<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete()->after('id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->after('payroll_run_id');

            $table->decimal('basic_pay_cutoff', 12, 2)->default(0)->after('employee_id');
            $table->decimal('allowance_cutoff', 12, 2)->default(0)->after('basic_pay_cutoff');
            $table->decimal('ot_hours', 6, 2)->default(0)->after('allowance_cutoff');
            $table->decimal('ot_pay', 12, 2)->default(0)->after('ot_hours');
            $table->decimal('gross', 12, 2)->default(0)->after('ot_pay');

            $table->decimal('sss_ee', 12, 2)->default(0)->after('gross');
            $table->decimal('philhealth_ee', 12, 2)->default(0)->after('sss_ee');
            $table->decimal('pagibig_ee', 12, 2)->default(0)->after('philhealth_ee');
            $table->decimal('tax', 12, 2)->default(0)->after('pagibig_ee');
            $table->decimal('attendance_deduction', 12, 2)->default(0)->after('tax');
            $table->decimal('other_deductions', 12, 2)->default(0)->after('attendance_deduction');
            $table->decimal('cash_advance', 12, 2)->default(0)->after('other_deductions');
            $table->decimal('deductions_total', 12, 2)->default(0)->after('cash_advance');
            $table->decimal('net_pay', 12, 2)->default(0)->after('deductions_total');

            $table->decimal('sss_er', 12, 2)->default(0)->after('net_pay');
            $table->decimal('philhealth_er', 12, 2)->default(0)->after('sss_er');
            $table->decimal('pagibig_er', 12, 2)->default(0)->after('philhealth_er');
            $table->decimal('employer_share_total', 12, 2)->default(0)->after('pagibig_er');

            $table->enum('pay_method', ['CASH', 'BANK'])->default('CASH')->after('employer_share_total');
            $table->string('bank_account_masked')->nullable()->after('pay_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            $table->dropForeign(['payroll_run_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'payroll_run_id',
                'employee_id',
                'basic_pay_cutoff',
                'allowance_cutoff',
                'ot_hours',
                'ot_pay',
                'gross',
                'sss_ee',
                'philhealth_ee',
                'pagibig_ee',
                'tax',
                'attendance_deduction',
                'other_deductions',
                'cash_advance',
                'deductions_total',
                'net_pay',
                'sss_er',
                'philhealth_er',
                'pagibig_er',
                'employer_share_total',
                'pay_method',
                'bank_account_masked',
            ]);
        });
    }
};
