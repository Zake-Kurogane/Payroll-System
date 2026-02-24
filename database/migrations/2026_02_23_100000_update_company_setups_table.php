<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_setups')) {
            return;
        }

        Schema::table('company_setups', function (Blueprint $table) {
            if (!Schema::hasColumn('company_setups', 'company_name')) {
                $table->string('company_name')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'company_tin')) {
                $table->string('company_tin')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'company_address')) {
                $table->string('company_address')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'company_rdo')) {
                $table->string('company_rdo')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'company_contact')) {
                $table->string('company_contact')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'company_email')) {
                $table->string('company_email')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'payroll_frequency')) {
                $table->string('payroll_frequency')->nullable()->default('semi_monthly');
            }
            if (!Schema::hasColumn('company_setups', 'currency')) {
                $table->string('currency')->nullable()->default('PHP');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty: avoid destructive drops in existing installs.
    }
};
