<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (!Schema::hasColumn('statutory_setups', 'sss_split_rule')) {
                $table->string('sss_split_rule')->default('monthly')->after('sss_table');
            }
        });

        Schema::table('withholding_tax_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('withholding_tax_policies', 'split_rule')) {
                $table->string('split_rule')->default('monthly')->after('timing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (Schema::hasColumn('statutory_setups', 'sss_split_rule')) {
                $table->dropColumn('sss_split_rule');
            }
        });

        Schema::table('withholding_tax_policies', function (Blueprint $table) {
            if (Schema::hasColumn('withholding_tax_policies', 'split_rule')) {
                $table->dropColumn('split_rule');
            }
        });
    }
};
