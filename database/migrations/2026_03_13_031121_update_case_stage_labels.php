<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename "decided" → "Sanction"
        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'decided')
            ->update(['label' => 'Sanction', 'sort_order' => 4]);

        // Push "for_decision" out of the active flow
        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'for_decision')
            ->update(['label' => 'For Decision (Legacy)', 'sort_order' => 99]);

        // Ensure "closed" sort is 5
        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'closed')
            ->update(['sort_order' => 5]);
    }

    public function down(): void
    {
        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'decided')
            ->update(['label' => 'Decided', 'sort_order' => 5]);

        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'for_decision')
            ->update(['label' => 'For Decision', 'sort_order' => 4]);

        DB::table('case_lookup_values')
            ->where('type', 'stage')->where('value', 'closed')
            ->update(['sort_order' => 6]);
    }
};
