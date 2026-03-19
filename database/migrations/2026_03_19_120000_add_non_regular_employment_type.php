<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employment_types')) return;

        $exists = DB::table('employment_types')
            ->whereRaw('LOWER(TRIM(label)) = ?', ['non-regular'])
            ->exists();

        if ($exists) return;

        $maxSort = (int) (DB::table('employment_types')->max('sort_order') ?? 0);
        DB::table('employment_types')->insert([
            'label' => 'Non-regular',
            'sort_order' => $maxSort + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('employment_types')) return;
        DB::table('employment_types')
            ->whereRaw('LOWER(TRIM(label)) = ?', ['non-regular'])
            ->delete();
    }
};

