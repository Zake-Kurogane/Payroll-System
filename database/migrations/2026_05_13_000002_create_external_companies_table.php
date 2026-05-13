<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_companies')) {
            Schema::create('external_companies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $excluded = [
            'G5-Davao',
            'Agri-Farm',
            'Auraland Property',
            'Cabantian',
            'Dragon Base',
            'G5-Tagum',
            'G5-Refinery',
            'Mebatas',
        ];

        // Seed from current area places as a one-time bootstrap, minus excluded.
        $names = collect();
        if (Schema::hasTable('area_places')) {
            $names = DB::table('area_places')
                ->where('is_active', true)
                ->pluck('label')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '');
        }

        $names = $names
            ->reject(fn ($name) => in_array($name, $excluded, true))
            ->push('Aura Fortune G5 Traders Corp.')
            ->unique(fn ($name) => mb_strtolower($name))
            ->sort(fn ($a, $b) => strcasecmp((string) $a, (string) $b))
            ->values();

        $now = Carbon::now();
        foreach ($names as $index => $name) {
            DB::table('external_companies')->updateOrInsert(
                ['name' => $name],
                [
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // Force-remove excluded values from external companies table.
        DB::table('external_companies')
            ->whereIn('name', $excluded)
            ->delete();

        // Keep table alphabetically ordered by sort_order.
        $ordered = DB::table('external_companies')
            ->orderBy('name')
            ->get(['id']);
        foreach ($ordered as $i => $row) {
            DB::table('external_companies')
                ->where('id', $row->id)
                ->update([
                    'sort_order' => $i + 1,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_companies');
    }
};
