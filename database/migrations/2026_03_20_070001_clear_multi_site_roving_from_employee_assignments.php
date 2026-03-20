<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->whereIn('assignment_type', ['Multi-Site (Roving)', 'Multi-Site(Roving)'])
            ->update([
                'assignment_type' => '',
                'area_place' => null,
            ]);

        if (DB::getSchemaBuilder()->hasTable('payroll_runs')) {
            DB::table('payroll_runs')
                ->whereIn('assignment_filter', ['Multi-Site (Roving)', 'Multi-Site(Roving)'])
                ->update([
                    'assignment_filter' => '',
                    'area_place_filter' => null,
                ]);
        }

        Cache::flush();
    }

    public function down(): void
    {
        // No safe automatic rollback for employee assignments.
        Cache::flush();
    }
};

