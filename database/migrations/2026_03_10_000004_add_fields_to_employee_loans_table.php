<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_loans', 'loan_no')) {
                $table->string('loan_no')->nullable()->after('id');
            }
            if (!Schema::hasColumn('employee_loans', 'principal_amount')) {
                $table->decimal('principal_amount', 12, 2)->default(0)->after('notes');
            }
            if (!Schema::hasColumn('employee_loans', 'interest_amount')) {
                $table->decimal('interest_amount', 12, 2)->default(0)->after('principal_amount');
            }
            if (!Schema::hasColumn('employee_loans', 'total_payable')) {
                $table->decimal('total_payable', 12, 2)->default(0)->after('interest_amount');
            }
            if (!Schema::hasColumn('employee_loans', 'approval_date')) {
                $table->date('approval_date')->nullable()->after('total_payable');
            }
            if (!Schema::hasColumn('employee_loans', 'release_date')) {
                $table->date('release_date')->nullable()->after('approval_date');
            }
            if (!Schema::hasColumn('employee_loans', 'deduction_frequency')) {
                $table->string('deduction_frequency')->default('every_cutoff')->after('release_date');
            }
            if (!Schema::hasColumn('employee_loans', 'deduction_method')) {
                $table->string('deduction_method')->default('fixed_months')->after('deduction_frequency');
            }
            if (!Schema::hasColumn('employee_loans', 'per_cutoff_amount')) {
                $table->decimal('per_cutoff_amount', 12, 2)->nullable()->after('deduction_method');
            }
            if (!Schema::hasColumn('employee_loans', 'amount_deducted')) {
                $table->decimal('amount_deducted', 12, 2)->default(0)->after('per_cutoff_amount');
            }
            if (!Schema::hasColumn('employee_loans', 'balance_remaining')) {
                $table->decimal('balance_remaining', 12, 2)->default(0)->after('amount_deducted');
            }
            if (!Schema::hasColumn('employee_loans', 'auto_deduct')) {
                $table->boolean('auto_deduct')->default(true)->after('balance_remaining');
            }
            if (!Schema::hasColumn('employee_loans', 'allow_partial')) {
                $table->boolean('allow_partial')->default(true)->after('auto_deduct');
            }
            if (!Schema::hasColumn('employee_loans', 'carry_forward')) {
                $table->boolean('carry_forward')->default(true)->after('allow_partial');
            }
            if (!Schema::hasColumn('employee_loans', 'stop_on_zero')) {
                $table->boolean('stop_on_zero')->default(true)->after('carry_forward');
            }
            if (!Schema::hasColumn('employee_loans', 'priority_order')) {
                $table->unsignedInteger('priority_order')->default(100)->after('stop_on_zero');
            }
            if (!Schema::hasColumn('employee_loans', 'status')) {
                $table->string('status')->default('draft')->after('priority_order');
            }
            if (!Schema::hasColumn('employee_loans', 'source')) {
                $table->string('source')->nullable()->after('status');
            }
            if (!Schema::hasColumn('employee_loans', 'recovery_type')) {
                $table->string('recovery_type')->nullable()->after('source');
            }
            if (!Schema::hasColumn('employee_loans', 'approved_by')) {
                $table->string('approved_by')->nullable()->after('recovery_type');
            }
            if (!Schema::hasColumn('employee_loans', 'encoded_by')) {
                $table->string('encoded_by')->nullable()->after('approved_by');
            }
        });

        Schema::table('employee_loans', function (Blueprint $table) {
            $table->index(['employee_id', 'status'], 'loan_emp_status_idx');
            $table->index(['loan_type', 'status'], 'loan_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropIndex('loan_emp_status_idx');
            $table->dropIndex('loan_type_status_idx');
        });

        Schema::table('employee_loans', function (Blueprint $table) {
            $cols = [
                'loan_no',
                'principal_amount',
                'interest_amount',
                'total_payable',
                'approval_date',
                'release_date',
                'deduction_frequency',
                'deduction_method',
                'per_cutoff_amount',
                'amount_deducted',
                'balance_remaining',
                'auto_deduct',
                'allow_partial',
                'carry_forward',
                'stop_on_zero',
                'priority_order',
                'status',
                'source',
                'recovery_type',
                'approved_by',
                'encoded_by',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('employee_loans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
