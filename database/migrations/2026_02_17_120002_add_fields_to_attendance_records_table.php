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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->after('id');
            $table->date('date')->after('employee_id');
            $table->enum('status', ['Present', 'Late', 'Absent', 'Leave'])->after('date');
            $table->unsignedInteger('minutes_late')->nullable()->after('status');
            $table->unsignedInteger('minutes_undertime')->nullable()->after('minutes_late');
            $table->decimal('ot_hours', 6, 2)->nullable()->after('minutes_undertime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'date',
                'status',
                'minutes_late',
                'minutes_undertime',
                'ot_hours',
            ]);
        });
    }
};
