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
        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('product_order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->string('customer_name')->nullable();
            // Normalised, digits-only international number actually sent to (e.g. 60123456789).
            $table->string('phone');
            // pending | sent | delivered | read | failed | skipped
            $table->string('status')->default('pending');
            $table->string('wamid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_campaign_id', 'status']);
            $table->index('wamid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_recipients');
    }
};
