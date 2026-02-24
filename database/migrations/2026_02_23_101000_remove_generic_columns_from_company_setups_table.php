<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_setups')) {
            return;
        }

        Schema::table('company_setups', function (Blueprint $table) {
            if (Schema::hasColumn('company_setups', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('company_setups', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('company_setups', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('company_setups', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::hasColumn('company_setups', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('company_setups', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_setups')) {
            return;
        }

        Schema::table('company_setups', function (Blueprint $table) {
            if (!Schema::hasColumn('company_setups', 'code')) {
                $table->string('code')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'meta')) {
                $table->json('meta')->nullable();
            }
            if (!Schema::hasColumn('company_setups', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('company_setups', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
        });
    }
};
