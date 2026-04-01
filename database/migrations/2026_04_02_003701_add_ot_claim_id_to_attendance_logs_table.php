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
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('ot_claim_id')
                ->nullable()
                ->after('remarks')
                ->constrained('overtime_claim_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\OvertimeClaimRequest::class, 'ot_claim_id');
            $table->dropColumn('ot_claim_id');
        });
    }
};
