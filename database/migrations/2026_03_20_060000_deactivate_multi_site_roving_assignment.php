<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assignments')
            ->where('code', 'multi-site-roving')
            ->orWhere('label', 'Multi-Site (Roving)')
            ->update(['is_active' => false]);

        Cache::flush();
    }

    public function down(): void
    {
        DB::table('assignments')
            ->where('code', 'multi-site-roving')
            ->orWhere('label', 'Multi-Site (Roving)')
            ->update(['is_active' => true]);

        Cache::flush();
    }
};

