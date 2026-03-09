<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Change assignment_type ENUM → VARCHAR on employees table
        DB::statement("ALTER TABLE employees MODIFY COLUMN assignment_type VARCHAR(100) NOT NULL DEFAULT ''");

        // 2. Change assignment_filter ENUM → VARCHAR on payroll_runs table
        DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN assignment_filter VARCHAR(100) NOT NULL DEFAULT ''");

        // 3. Replace assignments lookup data
        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'g5-davao',          'label' => 'G5-Davao',                              'is_active' => true, 'sort_order' => 1],
            ['code' => 'ayu-household',     'label' => 'AYU Household',                         'is_active' => true, 'sort_order' => 2],
            ['code' => 'agri-farm',         'label' => 'Agri-Farm',                             'is_active' => true, 'sort_order' => 3],
            ['code' => 'stallion-farm',     'label' => 'Stallion Farm',                         'is_active' => true, 'sort_order' => 4],
            ['code' => 'auraland-property', 'label' => 'Auraland Property',                     'is_active' => true, 'sort_order' => 5],
            ['code' => 'aura-fortune',      'label' => 'AURA FORTUNE G5 TRADERS CORPORATION',   'is_active' => true, 'sort_order' => 6],
        ]);

        // 4. Replace area_places lookup data
        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            ['code' => 'buena-gold',   'label' => 'BUENA GOLD TRADING',                        'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao-gold',   'label' => 'DAVAO GOLD TRADING',                        'is_active' => true, 'sort_order' => 2],
            ['code' => 'grnscor-gold', 'label' => 'GRNSCOR GOLD TRADING',                      'is_active' => true, 'sort_order' => 3],
            ['code' => 'nab-gold',     'label' => 'NAB GOLD TRADING',                          'is_active' => true, 'sort_order' => 4],
            ['code' => 'ruby-gold',    'label' => 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',  'is_active' => true, 'sort_order' => 5],
            ['code' => 'south-c-gold', 'label' => 'SOUTH C GOLD TRADING',                      'is_active' => true, 'sort_order' => 6],
            ['code' => 'rtu-building', 'label' => 'RTU BUILDING',                              'is_active' => true, 'sort_order' => 7],
            ['code' => 'twelve-hours', 'label' => 'TWELVE HOURS GOLD TRADING',                 'is_active' => true, 'sort_order' => 8],
        ]);

        // 5. Null out old assignment_type values on employees that no longer exist
        DB::table('employees')
            ->whereNotIn('assignment_type', [
                'G5-Davao', 'AYU Household', 'Agri-Farm',
                'Stallion Farm', 'Auraland Property', 'AURA FORTUNE G5 TRADERS CORPORATION',
            ])
            ->update(['assignment_type' => '']);

        // 6. Null out old area_place values on employees that no longer exist
        DB::table('employees')
            ->whereNotIn('area_place', [
                'BUENA GOLD TRADING', 'DAVAO GOLD TRADING', 'GRNSCOR GOLD TRADING',
                'NAB GOLD TRADING', 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',
                'SOUTH C GOLD TRADING', 'RTU BUILDING', 'TWELVE HOURS GOLD TRADING',
            ])
            ->whereNotNull('area_place')
            ->update(['area_place' => null]);

        // 7. Null out old external_area values on employees that no longer exist
        DB::table('employees')
            ->whereNotIn('external_area', [
                'BUENA GOLD TRADING', 'DAVAO GOLD TRADING', 'GRNSCOR GOLD TRADING',
                'NAB GOLD TRADING', 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',
                'SOUTH C GOLD TRADING', 'RTU BUILDING', 'TWELVE HOURS GOLD TRADING',
            ])
            ->whereNotNull('external_area')
            ->update(['external_area' => null]);

        // 8. Flush Laravel cache (file-based)
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function down(): void
    {
        // Restore original ENUM values (best effort — data may be incompatible)
        DB::statement("ALTER TABLE employees MODIFY COLUMN assignment_type ENUM('Tagum','Davao','Area') NOT NULL");
        DB::statement("ALTER TABLE payroll_runs MODIFY COLUMN assignment_filter ENUM('All','Tagum','Davao','Area') NOT NULL");

        DB::table('assignments')->truncate();
        DB::table('assignments')->insert([
            ['code' => 'tagum', 'label' => 'Tagum', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'davao', 'label' => 'Davao', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'area',  'label' => 'Area',  'is_active' => true, 'sort_order' => 3],
        ]);

        DB::table('area_places')->truncate();
        DB::table('area_places')->insert([
            ['code' => 'laak',        'label' => 'Laak',        'is_active' => true, 'sort_order' => 1],
            ['code' => 'maragusan',   'label' => 'Maragusan',   'is_active' => true, 'sort_order' => 2],
            ['code' => 'pantukan',    'label' => 'Pantukan',    'is_active' => true, 'sort_order' => 3],
            ['code' => 'nabunturan',  'label' => 'Nabunturan',  'is_active' => true, 'sort_order' => 4],
        ]);
    }
};
