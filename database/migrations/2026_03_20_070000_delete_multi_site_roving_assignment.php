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
            ->orWhere('label', 'Multi-Site(Roving)')
            ->delete();

        Cache::flush();
    }

    public function down(): void
    {
        // Intentionally no-op: use the earlier migration(s) that add/ensure Multi-Site (Roving) if needed.
        Cache::flush();
    }
};

