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
        if (Schema::hasTable('upsell_commission_payout_sessions')) {
            return;
        }

        Schema::create('upsell_commission_payout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upsell_commission_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('paid_revenue', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->timestamps();

            $table->unique(['upsell_commission_payout_id', 'class_session_id'], 'payout_session_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsell_commission_payout_sessions');
    }
};
