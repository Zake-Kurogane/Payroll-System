<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('area_places')) {
            DB::table('area_places')
                ->where('label', 'G5 Davao')
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'area_place')) {
            DB::table('employees')
                ->where('area_place', 'G5 Davao')
                ->update(['area_place' => 'G5-Davao']);
        }

        if (Schema::hasTable('attendance_records') && Schema::hasColumn('attendance_records', 'area_place')) {
            DB::table('attendance_records')
                ->where('area_place', 'G5 Davao')
                ->update(['area_place' => 'G5-Davao']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('area_places')) {
            DB::table('area_places')
                ->where('label', 'G5 Davao')
                ->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'area_place')) {
            DB::table('employees')
                ->where('area_place', 'G5-Davao')
                ->update(['area_place' => 'G5 Davao']);
        }

        if (Schema::hasTable('attendance_records') && Schema::hasColumn('attendance_records', 'area_place')) {
            DB::table('attendance_records')
                ->where('area_place', 'G5-Davao')
                ->update(['area_place' => 'G5 Davao']);
        }
    }
};

