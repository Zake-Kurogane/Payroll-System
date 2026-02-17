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
        Schema::table('payslip_adjustments', function (Blueprint $table) {
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete()->after('id');
            $table->enum('type', ['earning', 'deduction'])->after('payslip_id');
            $table->string('name')->after('type');
            $table->decimal('amount', 12, 2)->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslip_adjustments', function (Blueprint $table) {
            $table->dropForeign(['payslip_id']);
            $table->dropColumn([
                'payslip_id',
                'type',
                'name',
                'amount',
            ]);
        });
    }
};
