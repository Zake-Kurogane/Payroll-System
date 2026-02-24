<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_calendars');
    }

    public function down(): void
    {
        // No-op: intentionally not recreating unused table.
    }
};
