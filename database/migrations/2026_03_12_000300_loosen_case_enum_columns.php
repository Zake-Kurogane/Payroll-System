<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change employee_cases.status from ENUM → VARCHAR
        // DB::statement is used because Blueprint::change() on ENUM → string
        // requires doctrine/dbal and still leaves the ENUM constraint on some drivers.
        DB::statement("ALTER TABLE employee_cases MODIFY status VARCHAR(100) NOT NULL DEFAULT 'reported'");

        // Change employee_case_sanctions.sanction_type from ENUM → VARCHAR
        DB::statement("ALTER TABLE employee_case_sanctions MODIFY sanction_type VARCHAR(100) NOT NULL DEFAULT 'none'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_cases MODIFY status ENUM('reported','nte_issued','for_hearing','for_decision','decided','closed') NOT NULL DEFAULT 'reported'");
        DB::statement("ALTER TABLE employee_case_sanctions MODIFY sanction_type ENUM('none','verbal_reprimand','written_reprimand','suspension','resignation','termination') NOT NULL DEFAULT 'none'");
    }
};
