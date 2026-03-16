<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_advances', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('id');
            }
            if (!Schema::hasColumn('cash_advances', 'balance_remaining')) {
                $table->decimal('balance_remaining', 12, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('cash_advances', 'amount_deducted')) {
                $table->decimal('amount_deducted', 12, 2)->default(0)->after('balance_remaining');
            }
            if (!Schema::hasColumn('cash_advances', 'full_deduct')) {
                $table->boolean('full_deduct')->default(false)->after('method');
            }
        });

        // Backfill balances + reference numbers for existing rows.
        $rows = DB::table('cash_advances')->select(['id', 'amount', 'created_at', 'reference_no', 'balance_remaining', 'amount_deducted'])->get();
        foreach ($rows as $r) {
            $updates = [];
            if ($r->balance_remaining === null || (float) $r->balance_remaining <= 0) {
                $updates['balance_remaining'] = (float) $r->amount;
            }
            if ($r->amount_deducted === null) {
                $updates['amount_deducted'] = 0;
            }
            if (empty($r->reference_no)) {
                $year = $r->created_at ? date('Y', strtotime((string) $r->created_at)) : date('Y');
                $updates['reference_no'] = 'CA-' . $year . '-' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT);
            }
            if ($updates) {
                DB::table('cash_advances')->where('id', $r->id)->update($updates);
            }
        }

        Schema::table('cash_advances', function (Blueprint $table) {
            // Add unique index after backfill to avoid migration failure on existing rows.
            $table->unique('reference_no', 'cash_advances_reference_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            if (Schema::hasColumn('cash_advances', 'reference_no')) {
                $table->dropUnique('cash_advances_reference_no_unique');
                $table->dropColumn('reference_no');
            }
            if (Schema::hasColumn('cash_advances', 'balance_remaining')) {
                $table->dropColumn('balance_remaining');
            }
            if (Schema::hasColumn('cash_advances', 'amount_deducted')) {
                $table->dropColumn('amount_deducted');
            }
            if (Schema::hasColumn('cash_advances', 'full_deduct')) {
                $table->dropColumn('full_deduct');
            }
        });
    }
};

