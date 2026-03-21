<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_positions')) {
            return;
        }

        $name = 'Smelter/Appraiser';
        $exists = DB::table('external_positions')->where('name', $name)->exists();
        if ($exists) {
            return;
        }

        $now = Carbon::now();
        $maxSort = (int) (DB::table('external_positions')->max('sort_order') ?? 0);

        DB::table('external_positions')->insert([
            'name' => $name,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('external_positions')) {
            return;
        }
        DB::table('external_positions')->where('name', 'Smelter/Appraiser')->delete();
    }
};

