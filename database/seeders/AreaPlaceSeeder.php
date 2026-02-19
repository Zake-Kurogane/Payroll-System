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
            ['code' => 'laak', 'label' => 'Laak', 'sort_order' => 1],
            ['code' => 'maragusan', 'label' => 'Maragusan', 'sort_order' => 2],
            ['code' => 'pantukan', 'label' => 'Pantukan', 'sort_order' => 3],
            ['code' => 'nabunturan', 'label' => 'Nabunturan', 'sort_order' => 4],
        ]);
    }
}
