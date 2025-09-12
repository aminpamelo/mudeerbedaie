<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->enum('payout_status', ['unpaid', 'included_in_payslip', 'paid'])
                ->default('unpaid')
                ->after('verifier_role');

            // Add index for performance
            $table->index(['payout_status', 'verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex(['payout_status', 'verified_at']);
            $table->dropColumn('payout_status');
        });
    }
};
