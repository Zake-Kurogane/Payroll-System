<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deduction_policies', function (Blueprint $table) {
            $table->id();

            // Non-regular employees (probationary/trainees)
            $table->boolean('apply_premiums_to_non_regular')->default(false);
            $table->boolean('apply_tax_to_non_regular')->default(false);

            // Charges/shortages behavior
            $table->boolean('cap_charges_to_net_pay')->default(true);
            $table->boolean('carry_forward_unpaid_charges')->default(true);

            // Cash advance behavior
            $table->boolean('cap_cash_advance_to_net_pay')->default(true);

            // Deduction ordering switches (future-proofing)
            $table->boolean('loans_before_charges')->default(true);
            $table->boolean('charges_before_cash_advance')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deduction_policies');
    }
};

