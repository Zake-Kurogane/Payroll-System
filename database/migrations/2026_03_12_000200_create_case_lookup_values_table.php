<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_lookup_values', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();   // 'stage' | 'sanction'
            $table->string('value', 100)->unique();
            $table->string('label', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('case_lookup_values')->insert([
            // Stages
            ['type' => 'stage', 'value' => 'reported',      'label' => 'Reported',       'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'stage', 'value' => 'nte_issued',    'label' => 'NTE Issued',      'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'stage', 'value' => 'for_hearing',   'label' => 'For Hearing',     'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'stage', 'value' => 'for_decision',  'label' => 'For Decision',    'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'stage', 'value' => 'decided',       'label' => 'Decided',         'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'stage', 'value' => 'closed',        'label' => 'Closed',          'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],

            // Sanctions
            ['type' => 'sanction', 'value' => 'none',              'label' => 'None',              'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'sanction', 'value' => 'verbal_reprimand',  'label' => 'Verbal Reprimand',  'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'sanction', 'value' => 'written_reprimand', 'label' => 'Written Reprimand', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'sanction', 'value' => 'suspension',        'label' => 'Suspension',        'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'sanction', 'value' => 'resignation',       'label' => 'Resignation',       'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'sanction', 'value' => 'termination',       'label' => 'Termination',       'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('case_lookup_values');
    }
};
