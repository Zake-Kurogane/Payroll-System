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
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete()->after('id');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->after('payroll_run_id');
            $table->string('payslip_no')->unique()->after('employee_id');
            $table->timestamp('generated_at')->nullable()->after('payslip_no');
            $table->enum('release_status', ['Draft', 'Released'])->default('Draft')->after('generated_at');
            $table->enum('delivery_status', ['Not Sent', 'Queued', 'Sent', 'Failed'])->default('Not Sent')->after('release_status');
            $table->timestamp('sent_at')->nullable()->after('delivery_status');
            $table->string('pdf_path')->nullable()->after('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['payroll_run_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'payroll_run_id',
                'employee_id',
                'payslip_no',
                'generated_at',
                'release_status',
                'delivery_status',
                'sent_at',
                'pdf_path',
            ]);
        });
    }
};
