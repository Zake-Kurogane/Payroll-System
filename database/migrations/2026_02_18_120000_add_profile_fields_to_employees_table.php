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
            $table->string('status')->nullable()->after('last_name');
            $table->date('birthday')->nullable()->after('status');
            $table->string('mobile')->nullable()->after('birthday');
            $table->string('address')->nullable()->after('mobile');
            $table->string('pay_type')->nullable()->after('employment_type');
            $table->date('date_hired')->nullable()->after('pay_type');
            $table->string('sss')->nullable()->after('date_hired');
            $table->string('philhealth')->nullable()->after('sss');
            $table->string('pagibig')->nullable()->after('philhealth');
            $table->string('tin')->nullable()->after('pagibig');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'birthday',
                'mobile',
                'address',
                'pay_type',
                'date_hired',
                'sss',
                'philhealth',
                'pagibig',
                'tin',
            ]);
        });
    }
};
