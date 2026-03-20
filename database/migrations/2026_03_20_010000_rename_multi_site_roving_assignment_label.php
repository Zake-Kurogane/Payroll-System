<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $old = 'Multi-Site(Roving)';
        $new = 'Multi-Site (Roving)';
        $code = 'multi-site-roving';

        // If old exists and new doesn't, rename.
        $hasOld = DB::table('assignments')->where('label', $old)->exists();
        $hasNew = DB::table('assignments')->where('code', $code)->orWhere('label', $new)->exists();

        if ($hasOld && !$hasNew) {
            DB::table('assignments')->where('label', $old)->update([
                'code' => $code,
                'label' => $new,
                'updated_at' => Carbon::now(),
            ]);
            DB::table('employees')->where('assignment_type', $old)->update([
                'assignment_type' => $new,
            ]);
            return;
        }

        // If neither exists (fresh DB but older migration removed/changed), ensure new exists.
        if (!$hasNew) {
            $now = Carbon::now();
            $maxSort = (int) (DB::table('assignments')->max('sort_order') ?? 0);
            DB::table('assignments')->insert([
                'code' => $code,
                'label' => $new,
                'is_active' => true,
                'sort_order' => $maxSort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $old = 'Multi-Site(Roving)';
        $new = 'Multi-Site (Roving)';
        $code = 'multi-site-roving';

        DB::table('assignments')->where('code', $code)->orWhere('label', $new)->update([
            'label' => $old,
            'updated_at' => Carbon::now(),
        ]);
        DB::table('employees')->where('assignment_type', $new)->update([
            'assignment_type' => $old,
        ]);
    }
};
