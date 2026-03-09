<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add parent_assignment column to area_places
        Schema::table('area_places', function (Blueprint $table) {
            $table->string('parent_assignment', 50)->nullable()->after('label');
        });

        // 2. Reset assignments to 3 groups: Davao, Tagum, Field
        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'davao', 'label' => 'Davao', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'tagum', 'label' => 'Tagum', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'field', 'label' => 'Field', 'is_active' => true, 'sort_order' => 3],
        ]);

        // 3. Reset area_places with grouped sub-options
        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            // Davao sub-options
            ['code' => 'g5-davao',          'label' => 'G5-Davao',          'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'ayu-household',     'label' => 'AYU Household',     'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'agri-farm',         'label' => 'Agri-Farm',         'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'stallion-farm',     'label' => 'Stallion Farm',     'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 4],
            ['code' => 'auraland-property', 'label' => 'Auraland Property', 'parent_assignment' => 'Davao', 'is_active' => true, 'sort_order' => 5],
            // Tagum sub-options
            ['code' => 'aura-fortune',      'label' => 'AURA FORTUNE G5 TRADERS CORPORATION', 'parent_assignment' => 'Tagum', 'is_active' => true, 'sort_order' => 1],
            // Field sub-options (area places)
            ['code' => 'buena-gold',        'label' => 'BUENA GOLD TRADING',                        'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao-gold',        'label' => 'DAVAO GOLD TRADING',                        'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'grnscor-gold',      'label' => 'GRNSCOR GOLD TRADING',                      'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'nab-gold',          'label' => 'NAB GOLD TRADING',                          'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 4],
            ['code' => 'ruby-gold',         'label' => 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',  'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 5],
            ['code' => 'south-c-gold',      'label' => 'SOUTH C GOLD TRADING',                      'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 6],
            ['code' => 'twelve-hours',      'label' => 'TWELVE HOURS GOLD TRADING',                 'parent_assignment' => 'Field', 'is_active' => true, 'sort_order' => 7],
        ]);

        // 4. Clear stale employee assignment values (assignment_type was cleared in previous migration)
        // Ensure any stray values are cleared
        DB::table('employees')
            ->whereNotIn('assignment_type', ['Davao', 'Tagum', 'Field'])
            ->update(['assignment_type' => '']);

        // 5. Flush cache
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function down(): void
    {
        Schema::table('area_places', function (Blueprint $table) {
            $table->dropColumn('parent_assignment');
        });

        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'tagum', 'label' => 'Tagum', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao', 'label' => 'Davao', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'area',  'label' => 'Area',  'is_active' => true, 'sort_order' => 3],
        ]);

        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            ['code' => 'buena-gold',   'label' => 'BUENA GOLD TRADING',                        'sort_order' => 1],
            ['code' => 'davao-gold',   'label' => 'DAVAO GOLD TRADING',                        'sort_order' => 2],
            ['code' => 'grnscor-gold', 'label' => 'GRNSCOR GOLD TRADING',                      'sort_order' => 3],
            ['code' => 'nab-gold',     'label' => 'NAB GOLD TRADING',                          'sort_order' => 4],
            ['code' => 'ruby-gold',    'label' => 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',  'sort_order' => 5],
            ['code' => 'south-c-gold', 'label' => 'SOUTH C GOLD TRADING',                      'sort_order' => 6],
            ['code' => 'rtu-building', 'label' => 'RTU BUILDING',                              'sort_order' => 7],
            ['code' => 'twelve-hours', 'label' => 'TWELVE HOURS GOLD TRADING',                 'sort_order' => 8],
        ]);
    }
};
