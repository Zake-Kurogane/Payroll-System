<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('area_places', 'parent_assignment')) {
            Schema::table('area_places', function (Blueprint $table) {
                $table->string('parent_assignment', 50)->nullable()->after('label');
            });
        }

        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'davao', 'label' => 'Davao', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'tagum', 'label' => 'Tagum', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'field', 'label' => 'Field', 'is_active' => true, 'sort_order' => 3],
        ]);

        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            // Davao
            ['code' => 'g5-davao',          'label' => 'G5-Davao',          'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'ayu-household',     'label' => 'AYU Household',     'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'agri-farm',         'label' => 'Agri-Farm',         'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'stallion-farm',     'label' => 'Stallion Farm',     'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 4],
            ['code' => 'auraland-property', 'label' => 'Auraland Property', 'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 5],
            // Tagum
            ['code' => 'aura-fortune',      'label' => 'AURA FORTUNE G5 TRADERS CORPORATION', 'parent_assignment' => 'Tagum', 'is_active' => true, 'sort_order' => 1],
            // Field
            ['code' => 'buena-gold',        'label' => 'BUENA GOLD TRADING',                        'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao-gold',        'label' => 'DAVAO GOLD TRADING',                        'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'grnscor-gold',      'label' => 'GRNSCOR GOLD TRADING',                      'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'nab-gold',          'label' => 'NAB GOLD TRADING',                          'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 4],
            ['code' => 'ruby-gold',         'label' => 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',  'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 5],
            ['code' => 'south-c-gold',      'label' => 'SOUTH C GOLD TRADING',                      'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 6],
            ['code' => 'twelve-hours',      'label' => 'TWELVE HOURS GOLD TRADING',                 'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 7],
        ]);

        DB::table('employees')
            ->where('assignment_type', 'Area')
            ->update(['assignment_type' => 'Field']);

        DB::table('payroll_runs')
            ->where('assignment_filter', 'Area')
            ->update(['assignment_filter' => 'Field']);

        \Illuminate\Support\Facades\Cache::flush();
    }

    public function down(): void
    {
        // Best-effort rollback to original groups
        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'tagum', 'label' => 'Tagum', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao', 'label' => 'Davao', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'area',  'label' => 'Area',  'is_active' => true, 'sort_order' => 3],
        ]);

        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            ['code' => 'laak',       'label' => 'Laak',       'is_active' => true, 'sort_order' => 1],
            ['code' => 'maragusan',  'label' => 'Maragusan',  'is_active' => true, 'sort_order' => 2],
            ['code' => 'pantukan',   'label' => 'Pantukan',   'is_active' => true, 'sort_order' => 3],
            ['code' => 'nabunturan', 'label' => 'Nabunturan', 'is_active' => true, 'sort_order' => 4],
        ]);

        DB::table('employees')
            ->where('assignment_type', 'Field')
            ->update(['assignment_type' => 'Area']);

        DB::table('payroll_runs')
            ->where('assignment_filter', 'Field')
            ->update(['assignment_filter' => 'Area']);
    }
};
