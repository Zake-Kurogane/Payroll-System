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
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->string('run_code')->unique()->after('id');
            $table->string('period_month', 7)->after('run_code');
            $table->enum('cutoff', ['1-15', '16-end'])->after('period_month');
            $table->enum('assignment_filter', ['All', 'Tagum', 'Davao', 'Area'])->after('cutoff');
            $table->enum('status', ['Draft', 'Locked', 'Released'])->default('Draft')->after('assignment_filter');
            $table->foreignId('created_by')->constrained('users')->after('status');
            $table->timestamp('locked_at')->nullable()->after('created_by');
            $table->timestamp('released_at')->nullable()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'run_code',
                'period_month',
                'cutoff',
                'assignment_filter',
                'status',
                'created_by',
                'locked_at',
                'released_at',
            ]);
        });
    }
};
