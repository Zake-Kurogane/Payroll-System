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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emp_no')->unique()->after('id');
            $table->string('first_name')->after('emp_no');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->after('middle_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('department')->after('email');
            $table->string('position')->after('department');
            $table->string('employment_type')->after('position');
            $table->enum('assignment_type', ['Tagum', 'Davao', 'Area'])->after('employment_type');
            $table->string('area_place')->nullable()->after('assignment_type');
            $table->decimal('basic_pay', 12, 2)->default(0)->after('area_place');
            $table->decimal('allowance', 12, 2)->default(0)->after('basic_pay');
            $table->string('bank_name')->nullable()->after('allowance');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->enum('payout_method', ['CASH', 'BANK'])->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'emp_no',
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'department',
                'position',
                'employment_type',
                'assignment_type',
                'area_place',
                'basic_pay',
                'allowance',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'payout_method',
            ]);
        });
    }
};
