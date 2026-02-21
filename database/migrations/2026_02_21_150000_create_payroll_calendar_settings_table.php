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
        Schema::create('payroll_calendar_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('pay_date_a')->default(15);
            $table->string('pay_date_b', 10)->default('EOM');
            $table->unsignedTinyInteger('cutoff_a_from')->default(11);
            $table->unsignedTinyInteger('cutoff_a_to')->default(25);
            $table->unsignedTinyInteger('cutoff_b_from')->default(26);
            $table->unsignedTinyInteger('cutoff_b_to')->default(10);
            $table->boolean('work_mon_sat')->default(true);
            $table->boolean('pay_on_prev_workday_if_sunday')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_calendar_settings');
    }
};
