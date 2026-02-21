<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'code' => 'L',
                'description' => 'Late',
                'counts_as_present' => true,
                'counts_as_paid' => true,
                'affects_deductions' => false,
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'HOL',
                'description' => 'Holiday',
                'counts_as_present' => true,
                'counts_as_paid' => true,
                'affects_deductions' => false,
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'LOA',
                'description' => 'LOA',
                'counts_as_present' => false,
                'counts_as_paid' => false,
                'affects_deductions' => false,
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('attendance_codes')->updateOrInsert(
                ['code' => $row['code']],
                $row
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendance_codes')->whereIn('code', ['L', 'HOL', 'LOA'])->delete();
    }
};
