<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $code = 'auraland-resource-assets-inc';
        $label = 'Auraland Resource & Assets Incorporated';
        $parent = 'Field';

        $row = DB::table('area_places')
            ->where('code', $code)
            ->orWhere('label', $label)
            ->first();

        if ($row) {
            DB::table('area_places')->where('id', $row->id)->update([
                'code' => $code,
                'label' => $label,
                'parent_assignment' => $parent,
                'is_active' => true,
            ]);
            Cache::flush();
            return;
        }

        $maxSort = (int) (DB::table('area_places')
            ->where('parent_assignment', $parent)
            ->max('sort_order') ?? 0);

        DB::table('area_places')->insert([
            'code' => $code,
            'label' => $label,
            'parent_assignment' => $parent,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
    }

    public function down(): void
    {
        DB::table('area_places')->where('code', 'auraland-resource-assets-inc')->delete();
        Cache::flush();
    }
};

