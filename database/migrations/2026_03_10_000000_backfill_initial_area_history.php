<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find Area employees with an area_place but no history entries yet,
        // and create an initial entry using their hire date (or created_at).
        $employees = DB::table('employees')
            ->where('assignment_type', 'Area')
            ->whereNotNull('area_place')
            ->where('area_place', '<>', '')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('employee_area_histories')
                  ->whereColumn('employee_area_histories.employee_id', 'employees.id');
            })
            ->get(['id', 'area_place', 'date_hired', 'created_at']);

        $now = now();
        foreach ($employees as $emp) {
            $effectiveDate = $emp->date_hired ?? $emp->created_at;
            DB::table('employee_area_histories')->insert([
                'employee_id'    => $emp->id,
                'area_place'     => $emp->area_place,
                'effective_date' => date('Y-m-d', strtotime($effectiveDate)),
                'created_by'     => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — backfilled data is valid history
    }
};
