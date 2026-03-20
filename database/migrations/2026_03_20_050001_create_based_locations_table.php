<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('based_locations')) {
            Schema::create('based_locations', function (Blueprint $table) {
                $table->id();
                $table->string('label')->unique();
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $labels = [
            'G5-Davao',
            'G5-Tagum',
            'Multi-Site (Roving)',
            'Mebatas-Delta Plant',
            'Dragon Base',
            'Cabantian',
            'AQ Caretaker',
            'Berjemo Farm',
            'Langub Farm',
            'Lanang Properties',
            'Auraland Property',
        ];

        $existing = DB::table('based_locations')->pluck('label')->all();
        $existingLower = array_flip(array_map(fn ($v) => strtolower(trim((string) $v)), $existing));

        $rows = [];
        $order = 1;
        foreach ($labels as $label) {
            $key = strtolower(trim($label));
            if (isset($existingLower[$key])) {
                $order++;
                continue;
            }
            $rows[] = [
                'label' => $label,
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $order++;
        }

        if (count($rows)) {
            DB::table('based_locations')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('based_locations');
    }
};

