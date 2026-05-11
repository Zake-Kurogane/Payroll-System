<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'area_place')) {
            return;
        }

        DB::table('employees')
            ->where('area_place', 'AURA FORTUNE G5 TRADERS CORPORATION')
            ->update(['area_place' => 'G5 Tagum']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'area_place')) {
            return;
        }

        DB::table('employees')
            ->where('area_place', 'G5 Tagum')
            ->update(['area_place' => 'AURA FORTUNE G5 TRADERS CORPORATION']);
    }
};

