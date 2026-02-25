<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (!Schema::hasColumn('statutory_setups', 'pi_ee_percent_low')) {
                $table->decimal('pi_ee_percent_low', 6, 2)->default(1.00)->after('pi_ee_percent');
            }
            if (!Schema::hasColumn('statutory_setups', 'pi_ee_threshold')) {
                $table->decimal('pi_ee_threshold', 10, 2)->default(1500.00)->after('pi_ee_percent_low');
            }
        });
    }

    public function down(): void
    {
        Schema::table('statutory_setups', function (Blueprint $table) {
            if (Schema::hasColumn('statutory_setups', 'pi_ee_threshold')) {
                $table->dropColumn('pi_ee_threshold');
            }
            if (Schema::hasColumn('statutory_setups', 'pi_ee_percent_low')) {
                $table->dropColumn('pi_ee_percent_low');
            }
        });
    }
};
