<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_run_rows', 'charges_deduction')) {
                $table->decimal('charges_deduction', 12, 2)->default(0)->after('attendance_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_rows', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_run_rows', 'charges_deduction')) {
                $table->dropColumn('charges_deduction');
            }
        });
    }
};

