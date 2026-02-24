<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withholding_tax_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('withholding_tax_policies', 'wt_table_source')) {
                $table->string('wt_table_source')->nullable()->after('percent');
            }
            if (!Schema::hasColumn('withholding_tax_policies', 'wt_table_imported_at')) {
                $table->timestamp('wt_table_imported_at')->nullable()->after('wt_table_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withholding_tax_policies', function (Blueprint $table) {
            if (Schema::hasColumn('withholding_tax_policies', 'wt_table_imported_at')) {
                $table->dropColumn('wt_table_imported_at');
            }
            if (Schema::hasColumn('withholding_tax_policies', 'wt_table_source')) {
                $table->dropColumn('wt_table_source');
            }
        });
    }
};
