<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::getSchemaBuilder();
        $table = null;

        if ($schema->hasTable('payroll_calendar_settings')) {
            $table = 'payroll_calendar_settings';
        } elseif ($schema->hasTable('payroll_calendars')) {
            $table = 'payroll_calendars';
        } else {
            return;
        }

        // Guard against mismatched schemas.
        $required = [
            'pay_date_a',
            'pay_date_b',
            'cutoff_a_from',
            'cutoff_a_to',
            'cutoff_b_from',
            'cutoff_b_to',
            'work_mon_sat',
            'pay_on_prev_workday_if_sunday',
        ];
        foreach ($required as $col) {
            if (!$schema->hasColumn($table, $col)) {
                return;
            }
        }

        $row = DB::table($table)->orderBy('id')->first();
        $data = [
            'pay_date_a' => 15,
            'pay_date_b' => 'EOM',
            'cutoff_a_from' => 11,
            'cutoff_a_to' => 25,
            'cutoff_b_from' => 26,
            'cutoff_b_to' => 10,
            'work_mon_sat' => 1,
            'pay_on_prev_workday_if_sunday' => 1,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table($table)->where('id', $row->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table($table)->insert($data);
        }
    }

    public function down(): void
    {
        // No-op: avoid overwriting user changes.
    }
};
