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
        Schema::create('pay_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('pay_frequency')->default('Semi-monthly');
            $table->string('calendar')->default('Payroll Calendar');
            $table->time('default_shift_start')->nullable();
            $table->enum('default_ot_mode', ['flat_rate', 'rate_based'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_groups');
    }
};
