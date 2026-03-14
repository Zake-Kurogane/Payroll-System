<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no')->unique();
            $table->enum('case_type', ['incident_report', 'spot_report'])->default('incident_report');
            $table->date('date_reported')->nullable();
            $table->date('incident_date')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['reported', 'nte_issued', 'for_hearing', 'for_decision', 'decided', 'closed'])->default('reported');
            $table->foreignId('reported_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('reported_by_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_case_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('employee_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['complainant', 'respondent', 'witness', 'other'])->default('respondent');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['case_id', 'role']);
        });

        Schema::create('employee_case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('employee_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('document_type', ['ir', 'spot_report', 'nte', 'hearing', 'decision', 'sanction_notice', 'other'])->default('other');
            $table->string('file_path');
            $table->string('file_name');
            $table->date('date_uploaded')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['case_id', 'document_type']);
        });

        Schema::create('employee_case_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('employee_cases')->cascadeOnDelete();
            $table->date('hearing_date')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'missed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });

        Schema::create('employee_case_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('employee_cases')->cascadeOnDelete();
            $table->date('decision_date')->nullable();
            $table->text('decision_summary')->nullable();
            $table->string('decision_by')->nullable();
            $table->boolean('nte_ignored')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_case_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('employee_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('sanction_type', ['none', 'verbal_reprimand', 'written_reprimand', 'suspension', 'resignation', 'termination'])->default('none');
            $table->unsignedInteger('days_suspended')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['case_id', 'sanction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_case_sanctions');
        Schema::dropIfExists('employee_case_decisions');
        Schema::dropIfExists('employee_case_hearings');
        Schema::dropIfExists('employee_case_documents');
        Schema::dropIfExists('employee_case_parties');
        Schema::dropIfExists('employee_cases');
    }
};
