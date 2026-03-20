<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $code = 'multi-site-roving';
        $label = 'Multi-Site (Roving)';
        $oldLabel = 'Multi-Site(Roving)';

        $row = DB::table('assignments')
            ->where('code', $code)
            ->orWhere('label', $label)
            ->orWhere('label', $oldLabel)
            ->first();

        if ($row) {
            DB::table('assignments')->where('id', $row->id)->update([
                'code' => $code,
                'label' => $label,
                'is_active' => true,
                'updated_at' => Carbon::now(),
            ]);

            DB::table('employees')->where('assignment_type', $oldLabel)->update([
                'assignment_type' => $label,
            ]);

            return;
        }

        $now = Carbon::now();
        $maxSort = (int) (DB::table('assignments')->max('sort_order') ?? 0);

        DB::table('assignments')->insert([
            'code' => $code,
            'label' => $label,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('assignments')->where('code', 'multi-site-roving')->delete();
        DB::table('employees')->where('assignment_type', 'Multi-Site (Roving)')->update([
            'assignment_type' => '',
        ]);
    }
};

