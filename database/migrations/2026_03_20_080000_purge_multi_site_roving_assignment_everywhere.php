<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function isMultiSiteRoving(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $lower = mb_strtolower($value);

        // Normalize separators and remove any non-alphanumeric characters.
        $lower = str_replace(
            ["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}"],
            '-',
            $lower,
        );
        $compact = preg_replace('/[^a-z0-9]+/u', '', $lower) ?? '';

        return str_contains($compact, 'multisite') && str_contains($compact, 'roving');
    }

    public function up(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'assignment_type')) {
            DB::table('employees')
                ->select(['id', 'assignment_type'])
                ->whereNotNull('assignment_type')
                ->where('assignment_type', '<>', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $ids = [];
                    foreach ($rows as $row) {
                        if ($this->isMultiSiteRoving($row->assignment_type ?? null)) {
                            $ids[] = $row->id;
                        }
                    }

                    if (!count($ids)) {
                        return;
                    }

                    DB::table('employees')->whereIn('id', $ids)->update([
                        'assignment_type' => '',
                        'area_place' => null,
                    ]);
                });
        }

        if (Schema::hasTable('assignments')) {
            DB::table('assignments')
                ->whereRaw('LOWER(code) LIKE ?', ['%roving%'])
                ->orWhereRaw('LOWER(label) LIKE ?', ['%roving%'])
                ->delete();
        }

        if (Schema::hasTable('payroll_runs') && Schema::hasColumn('payroll_runs', 'assignment_filter')) {
            DB::table('payroll_runs')
                ->whereNotNull('assignment_filter')
                ->where('assignment_filter', '<>', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    $ids = [];
                    foreach ($rows as $row) {
                        if ($this->isMultiSiteRoving($row->assignment_filter ?? null)) {
                            $ids[] = $row->id;
                        }
                    }

                    if (!count($ids)) {
                        return;
                    }

                    DB::table('payroll_runs')->whereIn('id', $ids)->update([
                        'assignment_filter' => '',
                        'area_place_filter' => null,
                    ]);
                });
        }
    }

    public function down(): void
    {
        // No-op: destructive cleanup.
    }
};

