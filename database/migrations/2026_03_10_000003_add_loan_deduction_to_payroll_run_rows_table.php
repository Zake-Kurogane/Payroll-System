<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_run_rows', 'loan_deduction')) {
                $table->decimal('loan_deduction', 12, 2)->default(0)->after('cash_advance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_run_rows', 'loan_deduction')) {
                $table->dropColumn('loan_deduction');
            }
        });
    }
};
