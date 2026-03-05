<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_records MODIFY status ENUM('Present','Late','Absent','Leave','Unpaid Leave','RNR','Paid Leave','Half-day','Day Off','Holiday','LOA')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE attendance_records MODIFY status ENUM('Present','Late','Absent','Leave','Unpaid Leave','Paid Leave','Half-day','Day Off','Holiday','LOA')");
    }
};
