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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_position', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->primary(['employee_id', 'position_id']);
        });

        $now = Carbon::now();
        $names = [
            'Internal Auditor',
            'HR Officer',
            'Payroll Officer',
            'Executive Secretary',
            'Bookkeeper Officer',
            'External Assistant',
            'Gold Supervisor',
            'Appraiser',
            'Plant Manager',
            'Station Manager',
            'General Manager',
            'Property Assistant',
            'Monitoring Assistant',
            'Personal Assistant to the Boss',
            'Rental Assistant',
            'IT Technician',
            'QB Encoder',
            'IT Technical Supervisor',
            'Operation Support Assistant',
            'IT Programmer',
            'Inventory & Procurement',
            'HR Assistant',
            'CCTV Technician',
            'Cashier',
            'Liaison & Procurement',
            'Dover',
            'Driver',
            'Maintenance',
            'Utility',
            'Refiner',
            'Smelter',
            'CIP Technician',
            'General Maintenance',
            'Clerk',
            'Howo Driver',
            'House Helper',
            'Gardener',
            'Cook',
            'Gate Guard',
            'Farm Caretaker',
            'Lot Caretaker',
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

        DB::table('positions')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_position');
        Schema::dropIfExists('positions');
    }
};

