<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'paid_leave_units')) {
                $table->decimal('paid_leave_units', 4, 2)->default(0)->after('status');
            }
        });

        // Backfill historical full paid leave rows.
        DB::table('attendance_records')
            ->where('status', 'Paid Leave')
            ->where(function ($q) {
                $q->whereNull('paid_leave_units')->orWhere('paid_leave_units', 0);
            })
            ->update(['paid_leave_units' => 1.0]);
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_records', 'paid_leave_units')) {
                $table->dropColumn('paid_leave_units');
            }
        });
    }
};

