<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'based_location')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('based_location', 255)->nullable()->after('department');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'based_location')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('based_location');
            });
        }
    }
};

