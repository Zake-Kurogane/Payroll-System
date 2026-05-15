<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_assignment_status_rules', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_type', 100);
            $table->string('status', 100);
            $table->boolean('is_allowed')->default(true);
            $table->string('message', 255)->nullable();
            $table->string('default_sunday_status', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['assignment_type', 'status']);
            $table->index(['assignment_type', 'sort_order']);
        });

        $now = now();
        DB::table('attendance_assignment_status_rules')->insert([
            [
                'assignment_type' => 'Field',
                'status' => 'RNR',
                'is_allowed' => true,
                'message' => null,
                'default_sunday_status' => 'RNR',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assignment_type' => 'Field',
                'status' => 'Day Off',
                'is_allowed' => false,
                'message' => 'Day Off is only allowed for Davao/Tagum employees. Use RNR for Field.',
                'default_sunday_status' => null,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assignment_type' => 'Field',
                'status' => 'Paid Leave',
                'is_allowed' => false,
                'message' => 'Paid Leave is not applicable for Field employees.',
                'default_sunday_status' => null,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assignment_type' => 'Davao',
                'status' => 'RNR',
                'is_allowed' => false,
                'message' => 'RNR is only allowed for Field employees. Use Day Off for Davao/Tagum.',
                'default_sunday_status' => 'OFF',
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'assignment_type' => 'Tagum',
                'status' => 'RNR',
                'is_allowed' => false,
                'message' => 'RNR is only allowed for Field employees. Use Day Off for Davao/Tagum.',
                'default_sunday_status' => 'OFF',
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_assignment_status_rules');
    }
};

