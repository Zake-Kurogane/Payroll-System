<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (!Schema::hasColumn('statutory_setups', 'sss_ee_percent')) {
                $table->decimal('sss_ee_percent', 6, 2)->default(5.00)->after('sss_split_rule');
            }
            if (!Schema::hasColumn('statutory_setups', 'sss_er_percent')) {
                $table->decimal('sss_er_percent', 6, 2)->default(10.00)->after('sss_ee_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (Schema::hasColumn('statutory_setups', 'sss_er_percent')) {
                $table->dropColumn('sss_er_percent');
            }
            if (Schema::hasColumn('statutory_setups', 'sss_ee_percent')) {
                $table->dropColumn('sss_ee_percent');
            }
        });
    }
};
