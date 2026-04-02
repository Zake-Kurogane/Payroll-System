<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslip_claims', function (Blueprint $table) {
            // 'confirmed' | 'needs_review' | null (unclaimed or manually toggled)
            $table->string('review_status', 20)->nullable()->after('ink_ratio');
            // Scan confidence score 0.000–1.000
            $table->decimal('confidence', 4, 3)->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('payslip_claims', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'confidence']);
        });
    }
};
