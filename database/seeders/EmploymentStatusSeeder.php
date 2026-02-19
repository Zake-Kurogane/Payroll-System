<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmploymentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('employment_statuses')->insert([
            ['code' => 'active', 'label' => 'Active', 'sort_order' => 1],
            ['code' => 'inactive', 'label' => 'Inactive', 'sort_order' => 2],
            ['code' => 'resigned', 'label' => 'Resigned', 'sort_order' => 3],
        ]);
    }
}
