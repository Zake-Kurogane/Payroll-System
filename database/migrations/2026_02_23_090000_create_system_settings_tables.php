<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_setups')) {
            Schema::create('company_setups', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('company_tin')->nullable();
                $table->string('company_address')->nullable();
                $table->string('company_rdo')->nullable();
                $table->string('company_contact')->nullable();
                $table->string('company_email')->nullable();
                $table->string('payroll_frequency')->default('semi_monthly');
                $table->string('currency')->default('PHP');
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('pay_groups')) {
            Schema::create('pay_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('pay_frequency')->default('semi_monthly');
                $table->string('calendar_name')->nullable();
                $table->time('default_shift_start')->nullable();
                $table->string('default_ot_mode')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_proration_rules')) {
            Schema::create('salary_proration_rules', function (Blueprint $table) {
                $table->id();
                $table->string('salary_mode')->default('prorate_workdays');
                $table->unsignedSmallInteger('work_minutes_per_day')->default(480);
                $table->string('minutes_rounding')->default('per_minute');
                $table->string('money_rounding')->default('2_decimals');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('timekeeping_rules')) {
            Schema::create('timekeeping_rules', function (Blueprint $table) {
                $table->id();
                $table->time('shift_start')->default('07:30:00');
                $table->unsignedSmallInteger('grace_minutes')->default(5);
                $table->string('late_rule_type')->default('rate_based');
                $table->decimal('late_penalty_per_minute', 10, 2)->default(1.00);
                $table->string('late_rounding')->default('none');
                $table->boolean('undertime_enabled')->default(false);
                $table->string('undertime_rule_type')->default('rate_based');
                $table->decimal('undertime_penalty_per_minute', 10, 2)->default(1.00);
                $table->unsignedSmallInteger('work_minutes_per_day')->default(480);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('overtime_rules')) {
            Schema::create('overtime_rules', function (Blueprint $table) {
                $table->id();
                $table->string('ot_mode')->default('flat_rate');
                $table->boolean('require_approval')->default(false);
                $table->decimal('flat_rate', 10, 2)->default(80.00);
                $table->decimal('multiplier', 6, 2)->default(1.25);
                $table->unsignedSmallInteger('work_minutes_per_day')->default(480);
                $table->string('rounding_option')->default('none');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $table) {
                $table->id();
                $table->string('default_no_log_code')->nullable();
                $table->string('default_sunday_code')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('statutory_setups')) {
            Schema::create('statutory_setups', function (Blueprint $table) {
                $table->id();
                $table->json('sss_table')->nullable();
                $table->decimal('ph_ee_percent', 6, 2)->default(2.50);
                $table->decimal('ph_er_percent', 6, 2)->default(2.50);
                $table->decimal('ph_min_cap', 10, 2)->default(0);
                $table->decimal('ph_max_cap', 10, 2)->default(0);
                $table->string('ph_split_rule')->default('monthly');
                $table->decimal('pi_ee_percent', 6, 2)->default(2.00);
                $table->decimal('pi_er_percent', 6, 2)->default(2.00);
                $table->decimal('pi_cap', 10, 2)->default(100.00);
                $table->string('pi_split_rule')->default('monthly');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('withholding_tax_policies')) {
            Schema::create('withholding_tax_policies', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->string('method')->default('table');
                $table->string('pay_frequency')->default('semi_monthly');
                $table->boolean('basis_sss')->default(true);
                $table->boolean('basis_ph')->default(true);
                $table->boolean('basis_pi')->default(true);
                $table->string('timing')->default('monthly');
                $table->decimal('fixed_amount', 10, 2)->default(0);
                $table->decimal('percent', 6, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('withholding_tax_brackets')) {
            Schema::create('withholding_tax_brackets', function (Blueprint $table) {
                $table->id();
                $table->decimal('bracket_from', 12, 2)->default(0);
                $table->decimal('bracket_to', 12, 2)->nullable();
                $table->decimal('base_tax', 12, 2)->default(0);
                $table->decimal('excess_percent', 6, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cash_advance_policies')) {
            Schema::create('cash_advance_policies', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->string('default_method')->default('salary_deduction');
                $table->unsignedSmallInteger('default_term_months')->default(3);
                $table->string('deduct_timing')->default('split');
                $table->unsignedSmallInteger('priority')->default(1);
                $table->string('deduction_method')->default('equal_amortization');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cash_advances')) {
            Schema::create('cash_advances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('amount', 12, 2);
                $table->unsignedSmallInteger('term_months')->default(3);
                $table->date('start_month');
                $table->string('method')->default('salary_deduction');
                $table->decimal('per_cutoff_deduction', 12, 2)->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('cash_advance_policies');
        Schema::dropIfExists('withholding_tax_brackets');
        Schema::dropIfExists('withholding_tax_policies');
        Schema::dropIfExists('statutory_setups');
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('overtime_rules');
        Schema::dropIfExists('timekeeping_rules');
        Schema::dropIfExists('salary_proration_rules');
        Schema::dropIfExists('pay_groups');
        Schema::dropIfExists('company_setups');
    }
};
