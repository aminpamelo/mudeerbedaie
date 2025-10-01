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
        Schema::create('product_order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Who added the note

            // Note details
            $table->enum('type', ['customer', 'internal', 'system', 'payment', 'shipping']);
            $table->text('message');
            $table->boolean('is_visible_to_customer')->default(false);

            // System notes metadata
            $table->string('system_action')->nullable(); // For system-generated notes
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'type']);
            $table->index(['order_id', 'is_visible_to_customer']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_notes');
    }
};
