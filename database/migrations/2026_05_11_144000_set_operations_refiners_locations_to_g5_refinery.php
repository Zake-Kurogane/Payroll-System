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

        $updates = [
            'assignment_type' => 'Tagum',
            'area_place' => 'G5 Refinery',
        ];

        if (Schema::hasColumn('employees', 'based_location')) {
            $updates['based_location'] = 'G5 Refinery';
        }

        DB::table('employees')
            ->whereRaw('LOWER(TRIM(COALESCE(department, ""))) = ?', ['operations-refiners'])
            ->update($updates);
    }

    public function down(): void
    {
        // No safe rollback for prior assignment/area/based values.
    }
};

