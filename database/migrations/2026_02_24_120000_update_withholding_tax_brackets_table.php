<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withholding_tax_brackets', function (Blueprint $table) {
            if (!Schema::hasColumn('withholding_tax_brackets', 'pay_frequency')) {
                $table->string('pay_frequency')->nullable()->after('id');
            }
            if (!Schema::hasColumn('withholding_tax_brackets', 'bracket_no')) {
                $table->unsignedInteger('bracket_no')->nullable()->after('pay_frequency');
            }
            if (!Schema::hasColumn('withholding_tax_brackets', 'compensation_range')) {
                $table->string('compensation_range')->nullable()->after('bracket_no');
            }
            if (!Schema::hasColumn('withholding_tax_brackets', 'prescribed_withholding_tax')) {
                $table->string('prescribed_withholding_tax')->nullable()->after('compensation_range');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withholding_tax_brackets', function (Blueprint $table) {
            if (Schema::hasColumn('withholding_tax_brackets', 'prescribed_withholding_tax')) {
                $table->dropColumn('prescribed_withholding_tax');
            }
            if (Schema::hasColumn('withholding_tax_brackets', 'compensation_range')) {
                $table->dropColumn('compensation_range');
            }
            if (Schema::hasColumn('withholding_tax_brackets', 'bracket_no')) {
                $table->dropColumn('bracket_no');
            }
            if (Schema::hasColumn('withholding_tax_brackets', 'pay_frequency')) {
                $table->dropColumn('pay_frequency');
            }
        });
    }
};
