<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'gender')) {
                    $table->string('gender', 20)->nullable()->after('birthday');
                }
                if (!Schema::hasColumn('employees', 'marital_status')) {
                    $table->string('marital_status', 20)->nullable()->after('gender');
                }
            });
        }

        if (Schema::hasTable('employment_types')) {
            $exists = DB::table('employment_types')
                ->whereRaw('LOWER(TRIM(label)) = ?', ['ojt'])
                ->exists();

            if (!$exists) {
                $maxSort = (int) (DB::table('employment_types')->max('sort_order') ?? 0);
                DB::table('employment_types')->insert([
                    'label' => 'OJT',
                    'sort_order' => $maxSort + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employment_types')) {
            DB::table('employment_types')
                ->whereRaw('LOWER(TRIM(label)) = ?', ['ojt'])
                ->delete();
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'marital_status')) {
                    $table->dropColumn('marital_status');
                }
                if (Schema::hasColumn('employees', 'gender')) {
                    $table->dropColumn('gender');
                }
            });
        }
    }
};
