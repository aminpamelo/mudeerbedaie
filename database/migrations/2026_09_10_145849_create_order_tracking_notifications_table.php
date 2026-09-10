<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->string('channel'); // email | whatsapp_waha | whatsapp_meta
            $table->string('recipient')->nullable(); // email address or phone number used
            $table->string('status')->default('queued'); // sent | failed | queued
            $table->text('message')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_order_id', 'created_at'], 'otn_order_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking_notifications');
    }
};
