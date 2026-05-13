<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('positions')) {
            return;
        }

        $name = 'Accounting Assistant';
        $exists = DB::table('positions')->where('name', $name)->exists();
        if ($exists) {
            return;
        }

        $maxSort = (int) (DB::table('positions')->max('sort_order') ?? 0);
        DB::table('positions')->insert([
            'name' => $name,
            'is_active' => true,
            'sort_order' => max(1, $maxSort + 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('positions')) {
            return;
        }

        DB::table('positions')->where('name', 'Accounting Assistant')->delete();
    }
};
