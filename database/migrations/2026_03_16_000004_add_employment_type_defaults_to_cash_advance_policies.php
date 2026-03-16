<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advance_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_advance_policies', 'regular_default_term_months')) {
                $table->unsignedSmallInteger('regular_default_term_months')->default(3)->after('default_term_months');
            }
            if (!Schema::hasColumn('cash_advance_policies', 'probationary_term_months')) {
                $table->unsignedSmallInteger('probationary_term_months')->default(1)->after('regular_default_term_months');
            }
            if (!Schema::hasColumn('cash_advance_policies', 'trainee_term_months')) {
                $table->unsignedSmallInteger('trainee_term_months')->default(1)->after('probationary_term_months');
            }
            if (!Schema::hasColumn('cash_advance_policies', 'trainee_force_full_deduct')) {
                $table->boolean('trainee_force_full_deduct')->default(true)->after('trainee_term_months');
            }
            if (!Schema::hasColumn('cash_advance_policies', 'allow_full_deduct')) {
                $table->boolean('allow_full_deduct')->default(true)->after('trainee_force_full_deduct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_advance_policies', function (Blueprint $table) {
            foreach ([
                'regular_default_term_months',
                'probationary_term_months',
                'trainee_term_months',
                'trainee_force_full_deduct',
                'allow_full_deduct',
            ] as $col) {
                if (Schema::hasColumn('cash_advance_policies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

