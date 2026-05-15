<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_code_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_code_settings', 'paid_leave_cap_days')) {
                $table->unsignedInteger('paid_leave_cap_days')->default(5)->after('default_sunday_code');
            }
            if (!Schema::hasColumn('attendance_code_settings', 'template_codes')) {
                $table->json('template_codes')->nullable()->after('paid_leave_cap_days');
            }
            if (!Schema::hasColumn('attendance_code_settings', 'no_time_statuses')) {
                $table->json('no_time_statuses')->nullable()->after('template_codes');
            }
            if (!Schema::hasColumn('attendance_code_settings', 'time_tracked_statuses')) {
                $table->json('time_tracked_statuses')->nullable()->after('no_time_statuses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_code_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_code_settings', 'time_tracked_statuses')) {
                $table->dropColumn('time_tracked_statuses');
            }
            if (Schema::hasColumn('attendance_code_settings', 'no_time_statuses')) {
                $table->dropColumn('no_time_statuses');
            }
            if (Schema::hasColumn('attendance_code_settings', 'template_codes')) {
                $table->dropColumn('template_codes');
            }
            if (Schema::hasColumn('attendance_code_settings', 'paid_leave_cap_days')) {
                $table->dropColumn('paid_leave_cap_days');
            }
        });
    }
};

