<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payroll_runs MODIFY cutoff VARCHAR(10) NOT NULL");
        DB::statement("UPDATE payroll_runs SET cutoff = '11-25' WHERE cutoff = '1-15'");
        DB::statement("UPDATE payroll_runs SET cutoff = '26-10' WHERE cutoff = '16-end'");
        DB::statement("ALTER TABLE payroll_runs MODIFY cutoff ENUM('11-25','26-10') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payroll_runs MODIFY cutoff VARCHAR(10) NOT NULL");
        DB::statement("UPDATE payroll_runs SET cutoff = '1-15' WHERE cutoff = '11-25'");
        DB::statement("UPDATE payroll_runs SET cutoff = '16-end' WHERE cutoff = '26-10'");
        DB::statement("ALTER TABLE payroll_runs MODIFY cutoff ENUM('1-15','16-end') NOT NULL");
    }
};
