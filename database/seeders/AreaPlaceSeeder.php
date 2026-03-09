<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaPlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('area_places')->insert([
            // Davao sub-options
            ['code' => 'g5-davao',          'label' => 'G5-Davao',          'parent_assignment' => 'Davao', 'sort_order' => 1],
            ['code' => 'ayu-household',     'label' => 'AYU Household',     'parent_assignment' => 'Davao', 'sort_order' => 2],
            ['code' => 'agri-farm',         'label' => 'Agri-Farm',         'parent_assignment' => 'Davao', 'sort_order' => 3],
            ['code' => 'stallion-farm',     'label' => 'Stallion Farm',     'parent_assignment' => 'Davao', 'sort_order' => 4],
            ['code' => 'auraland-property', 'label' => 'Auraland Property', 'parent_assignment' => 'Davao', 'sort_order' => 5],
            // Tagum sub-options
            ['code' => 'aura-fortune',       'label' => 'AURA FORTUNE G5 TRADERS CORPORATION', 'parent_assignment' => 'Tagum', 'sort_order' => 1],
            ['code' => 'rtu-building-tagum', 'label' => 'RTU BUILDING',      'parent_assignment' => 'Tagum', 'sort_order' => 2],
            // Field sub-options (area places)
            ['code' => 'buena-gold',   'label' => 'BUENA GOLD TRADING',                        'parent_assignment' => 'Field', 'sort_order' => 1],
            ['code' => 'davao-gold',   'label' => 'DAVAO GOLD TRADING',                        'parent_assignment' => 'Field', 'sort_order' => 2],
            ['code' => 'grnscor-gold', 'label' => 'GRNSCOR GOLD TRADING',                      'parent_assignment' => 'Field', 'sort_order' => 3],
            ['code' => 'nab-gold',     'label' => 'NAB GOLD TRADING',                          'parent_assignment' => 'Field', 'sort_order' => 4],
            ['code' => 'ruby-gold',    'label' => 'RUBY GOLD BUYING AND GENERAL MERCHANDISE',  'parent_assignment' => 'Field', 'sort_order' => 5],
            ['code' => 'south-c-gold', 'label' => 'SOUTH C GOLD TRADING',                      'parent_assignment' => 'Field', 'sort_order' => 6],
            ['code' => 'rtu-building', 'label' => 'RTU BUILDING',                              'parent_assignment' => 'Field', 'sort_order' => 7],
            ['code' => 'twelve-hours', 'label' => 'TWELVE HOURS GOLD TRADING',                 'parent_assignment' => 'Field', 'sort_order' => 8],
        ]);
    }
}
