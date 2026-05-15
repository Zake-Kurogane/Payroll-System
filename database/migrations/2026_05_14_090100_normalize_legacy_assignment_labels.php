<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'G5 Tagum' => 'Tagum',
            'G5 Davao' => 'Davao',
            'G5-Davao' => 'Davao',
        ];

        foreach ($map as $legacy => $normalized) {
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'assignment_type')) {
                DB::table('employees')->where('assignment_type', $legacy)->update(['assignment_type' => $normalized]);
            }
            if (Schema::hasTable('payroll_runs') && Schema::hasColumn('payroll_runs', 'assignment_filter')) {
                DB::table('payroll_runs')->where('assignment_filter', $legacy)->update(['assignment_filter' => $normalized]);
            }
            if (Schema::hasTable('area_places') && Schema::hasColumn('area_places', 'parent_assignment')) {
                DB::table('area_places')->where('parent_assignment', $legacy)->update(['parent_assignment' => $normalized]);
            }
            if (Schema::hasTable('assignments') && Schema::hasColumn('assignments', 'label')) {
                DB::table('assignments')->where('label', $legacy)->update(['label' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: normalized values are canonical.
    }
};

