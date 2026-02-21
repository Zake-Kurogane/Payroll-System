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
        Schema::create('overtime_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('ot_mode', ['flat_rate', 'rate_based'])->default('flat_rate');
            $table->boolean('require_approval')->default(false);
            $table->decimal('flat_rate', 10, 2)->default(80);
            $table->decimal('multiplier', 6, 2)->default(1.25);
            $table->string('rounding', 20)->default('none');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_rules');
    }
};
