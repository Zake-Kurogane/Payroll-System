<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('employment_status_id')
                ->nullable()
                ->after('status')
                ->constrained('employment_statuses');
        });

        $statusMap = DB::table('employment_statuses')
            ->select('id', 'label')
            ->get()
            ->mapWithKeys(fn ($row) => [strtolower($row->label) => $row->id])
            ->all();

        foreach ($statusMap as $label => $id) {
            DB::table('employees')
                ->whereRaw('LOWER(status) = ?', [$label])
                ->update(['employment_status_id' => $id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['employment_status_id']);
            $table->dropColumn('employment_status_id');
        });
    }
};
