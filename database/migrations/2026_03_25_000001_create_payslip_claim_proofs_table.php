<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_claim_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_path');
            $table->timestamp('processed_at')->nullable();
            $table->json('processed_summary')->nullable();
            $table->timestamps();

            $table->index(['payroll_run_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_claim_proofs');
    }
};

