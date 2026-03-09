<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');               // 'shortage' | 'charge'
            $table->text('description')->nullable();
            $table->decimal('amount_total', 12, 2);
            $table->string('plan_type');           // 'one_time' | 'installment'
            $table->unsignedInteger('installment_count')->nullable();
            $table->string('start_month', 7);     // YYYY-MM
            $table->string('start_cutoff', 5);    // '11-25' | '26-10'
            $table->string('status')->default('active'); // 'active' | 'closed'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_cases');
    }
};
