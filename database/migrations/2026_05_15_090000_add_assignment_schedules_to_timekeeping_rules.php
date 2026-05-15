<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timekeeping_rules') && !Schema::hasColumn('timekeeping_rules', 'assignment_schedules')) {
            Schema::table('timekeeping_rules', function (Blueprint $table) {
                $table->json('assignment_schedules')->nullable()->after('work_minutes_per_day');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timekeeping_rules') && Schema::hasColumn('timekeeping_rules', 'assignment_schedules')) {
            Schema::table('timekeeping_rules', function (Blueprint $table) {
                $table->dropColumn('assignment_schedules');
            });
        }
    }
};
