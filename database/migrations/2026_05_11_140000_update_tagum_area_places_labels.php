<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('area_places')) {
            return;
        }

        // Rename existing Tagum label
        DB::table('area_places')
            ->where('code', 'aura-fortune')
            ->update([
                'label' => 'G5 Tagum',
                'parent_assignment' => 'Tagum',
            ]);

        // Add G5 Refinery under Tagum (if not existing)
        $exists = DB::table('area_places')
            ->where('code', 'g5-refinery')
            ->exists();

        if (!$exists) {
            $maxSort = (int) (DB::table('area_places')
                ->where('parent_assignment', 'Tagum')
                ->max('sort_order') ?? 0);

            DB::table('area_places')->insert([
                'code' => 'g5-refinery',
                'label' => 'G5 Refinery',
                'parent_assignment' => 'Tagum',
                'is_active' => true,
                'sort_order' => max(1, $maxSort + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('area_places')) {
            return;
        }

        DB::table('area_places')
            ->where('code', 'aura-fortune')
            ->update([
                'label' => 'AURA FORTUNE G5 TRADERS CORPORATION',
            ]);

        DB::table('area_places')
            ->where('code', 'g5-refinery')
            ->delete();
    }
};

