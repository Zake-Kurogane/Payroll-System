<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_types')) {
            Schema::create('loan_types', function (Blueprint $table) {
                $table->id();
                $table->string('label')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Seed defaults if table is empty (prevents hardcoded dropdowns in the Loans page).
        if (DB::table('loan_types')->count() === 0) {
            $now = now();
            $defaults = [
                'SSS Salary Loan',
                'SSS Calamity Loan',
                'SSS Housing Loan',
                'HDMF MPL',
                'HDMF Calamity Loan',
                'HDMF Housing Loan',
                'Company Loan',
                'Other',
            ];

            DB::table('loan_types')->insert(array_map(
                fn ($label, $i) => [
                    'label' => $label,
                    'sort_order' => ($i + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $defaults,
                array_keys($defaults),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_types');
    }
};

