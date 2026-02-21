<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columns = array_filter([
            'emp_no',
            'emp_name',
            'first_name',
            'middle_name',
            'last_name',
        ], fn ($col) => Schema::hasColumn('attendance_records', $col));

        if (empty($columns)) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'emp_no')) {
                $table->string('emp_no')->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('attendance_records', 'emp_name')) {
                $table->string('emp_name')->nullable()->after('emp_no');
            }
            if (!Schema::hasColumn('attendance_records', 'first_name')) {
                $table->string('first_name')->nullable()->after('emp_name');
            }
            if (!Schema::hasColumn('attendance_records', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('attendance_records', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_name');
            }
        });
    }
};
