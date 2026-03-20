<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('label')->unique();
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $labels = [
            'Non Regular Senior Managment',
            'Senior Managemnet',
            'Administrative Support',
            'Accounting/IT/HR',
            'Admin-Dovers',
            'Admin-Drivers',
            'Admin-Maintenance/Utility',
            'Operations-Refiners',
            'Operations-Appraisers',
            'Operations-Smelters',
            'Operation-Plant',
            'AYU Household',
            'AYU- Agri Farm',
            'Stallion Farm',
            'Auraland Property',
        ];

        $existing = DB::table('departments')->pluck('label')->all();
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
            DB::table('departments')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};

