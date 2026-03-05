<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advance_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_payback_months')->default(6)->after('default_term_months');
        });
    }

    public function down(): void
    {
        Schema::table('cash_advance_policies', function (Blueprint $table) {
            $table->dropColumn('max_payback_months');
        });
    }
};
