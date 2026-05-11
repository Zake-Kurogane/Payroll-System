<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('based_locations')) {
            return;
        }

        $exists = DB::table('based_locations')
            ->whereRaw('LOWER(TRIM(label)) = ?', ['g5 refinery'])
            ->exists();

        if ($exists) {
            DB::table('based_locations')
                ->whereRaw('LOWER(TRIM(label)) = ?', ['g5 refinery'])
                ->update([
                    'label' => 'G5 Refinery',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            return;
        }

        $maxSort = (int) (DB::table('based_locations')->max('sort_order') ?? 0);
        DB::table('based_locations')->insert([
            'label' => 'G5 Refinery',
            'sort_order' => $maxSort + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('based_locations')) {
            return;
        }

        DB::table('based_locations')
            ->whereRaw('LOWER(TRIM(label)) = ?', ['g5 refinery'])
            ->delete();
    }
};

