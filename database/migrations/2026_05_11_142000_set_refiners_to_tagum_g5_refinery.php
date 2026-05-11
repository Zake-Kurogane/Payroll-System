<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        DB::table('employees')
            ->whereRaw('LOWER(TRIM(COALESCE(department, ""))) = ?', ['refiners'])
            ->update([
                'assignment_type' => 'Tagum',
                'area_place' => 'G5 Refinery',
            ]);
    }

    public function down(): void
    {
        // No automatic safe rollback for previous assignment/area values.
    }
};

