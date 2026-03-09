<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('assignments')->insert([
            ['code' => 'davao', 'label' => 'Davao', 'sort_order' => 1],
            ['code' => 'tagum', 'label' => 'Tagum', 'sort_order' => 2],
            ['code' => 'field', 'label' => 'Field', 'sort_order' => 3],
        ]);
    }
}
