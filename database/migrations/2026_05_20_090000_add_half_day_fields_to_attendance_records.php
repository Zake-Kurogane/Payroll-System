<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'half_day_part')) {
                $table->string('half_day_part', 30)->nullable()->after('status');
            }
            if (!Schema::hasColumn('attendance_records', 'half_day_type')) {
                $table->string('half_day_type', 50)->nullable()->after('half_day_part');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_records', 'half_day_type')) {
                $table->dropColumn('half_day_type');
            }
            if (Schema::hasColumn('attendance_records', 'half_day_part')) {
                $table->dropColumn('half_day_part');
            }
        });
    }
};

