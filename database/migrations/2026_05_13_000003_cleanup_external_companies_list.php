<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_companies')) {
            return;
        }

        $excluded = [
            'g5-davao',
            'g5 davao',
            'agri-farm',
            'agri farm',
            'auraland property',
            'cabantian',
            'dragon base',
            'g5-tagum',
            'g5 tagum',
            'g5-refinery',
            'g5 refinery',
            'mebatas',
        ];

        $rows = DB::table('external_companies')->get(['id', 'name']);
        foreach ($rows as $row) {
            $normalized = strtolower(trim((string) $row->name));
            if (in_array($normalized, $excluded, true)) {
                DB::table('external_companies')->where('id', $row->id)->delete();
            }
        }

        $existsAuraFortune = DB::table('external_companies')
            ->whereRaw('LOWER(name) = ?', ['aura fortune g5 traders corp.'])
            ->exists();
        if (!$existsAuraFortune) {
            DB::table('external_companies')->insert([
                'name' => 'Aura Fortune G5 Traders Corp.',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ordered = DB::table('external_companies')
            ->orderBy('name')
            ->get(['id']);
        foreach ($ordered as $i => $row) {
            DB::table('external_companies')
                ->where('id', $row->id)
                ->update([
                    'sort_order' => $i + 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};
