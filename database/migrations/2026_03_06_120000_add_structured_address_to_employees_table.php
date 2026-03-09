<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('address_province')->nullable()->after('address');
            $table->string('address_city')->nullable()->after('address_province');
            $table->string('address_barangay')->nullable()->after('address_city');
            $table->string('address_street')->nullable()->after('address_barangay');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['address_province', 'address_city', 'address_barangay', 'address_street']);
        });
    }
};
