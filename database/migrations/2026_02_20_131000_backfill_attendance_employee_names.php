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
        DB::statement("
            UPDATE attendance_records ar
            JOIN employees e ON e.id = ar.employee_id
            SET
                ar.emp_no = COALESCE(ar.emp_no, e.emp_no),
                ar.first_name = COALESCE(ar.first_name, e.first_name),
                ar.middle_name = COALESCE(ar.middle_name, e.middle_name),
                ar.last_name = COALESCE(ar.last_name, e.last_name),
                ar.emp_name = COALESCE(ar.emp_name, CONCAT(e.last_name, ', ', e.first_name, IF(e.middle_name IS NULL OR e.middle_name = '', '', CONCAT(' ', e.middle_name))))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback: backfill only
    }
};
