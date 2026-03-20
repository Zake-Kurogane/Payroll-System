<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $label = 'Multi-Site(Roving)';
        $exists = DB::table('assignments')->where('label', $label)->exists();
        if ($exists) {
            return;
        }

        $now = Carbon::now();
        $maxSort = (int) (DB::table('assignments')->max('sort_order') ?? 0);

        DB::table('assignments')->insert([
            'label' => $label,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('assignments')->where('label', 'Multi-Site(Roving)')->delete();
    }
};

