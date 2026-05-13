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

        $exists = DB::table('area_places')
            ->where('code', 'mebatas')
            ->exists();

        if ($exists) {
            return;
        }

        $maxSort = (int) (DB::table('area_places')
            ->where('parent_assignment', 'Field')
            ->max('sort_order') ?? 0);

        DB::table('area_places')->insert([
            'code' => 'mebatas',
            'label' => 'Mebatas',
            'parent_assignment' => 'Field',
            'is_active' => true,
            'sort_order' => max(1, $maxSort + 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('area_places')) {
            return;
        }

        DB::table('area_places')
            ->where('code', 'mebatas')
            ->delete();
    }
};
