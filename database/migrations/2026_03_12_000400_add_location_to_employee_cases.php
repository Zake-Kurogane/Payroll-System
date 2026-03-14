<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_cases', function (Blueprint $table) {
            $table->string('location')->nullable()->after('incident_date');
        });
    }

    public function down(): void
    {
        Schema::table('employee_cases', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
