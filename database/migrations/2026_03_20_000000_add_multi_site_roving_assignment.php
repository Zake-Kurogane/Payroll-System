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
        $label = 'Multi-Site (Roving)';
        $code = 'multi-site-roving';
        $exists = DB::table('assignments')->where('code', $code)->orWhere('label', $label)->exists();
        if ($exists) {
            return;
        }

        $now = Carbon::now();
        $maxSort = (int) (DB::table('assignments')->max('sort_order') ?? 0);

        DB::table('assignments')->insert([
            'code' => $code,
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
        DB::table('assignments')->where('code', 'multi-site-roving')->orWhere('label', 'Multi-Site (Roving)')->delete();
    }
};
