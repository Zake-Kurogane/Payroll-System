<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('external_position_id')
                ->nullable()
                ->after('external_area')
                ->constrained('external_positions')
                ->nullOnDelete();
        });

        $now = Carbon::now();
        $names = [
            'Sales Manager',
            'Bookkeeper',
            'Gold Supervisor/Appraiser',
            'Proprietor/Owner',
            'General Manager',
            'Accounting Clerk',
            'Computer Technician',
            'Inventory Clerk',
            'Cashier',
            'Appraiser',
            'Utility',
            'Smelter',
            'Company Driver',
            'Driver',
            'Cook',
        ];

        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = [
                'name' => trim($name),
                'is_active' => true,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('external_positions')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_position_id');
        });
        Schema::dropIfExists('external_positions');
    }
};

