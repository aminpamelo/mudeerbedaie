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
        Schema::create('product_order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->onDelete('cascade');
            $table->enum('type', ['billing', 'shipping']);

            // Personal details
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();

            // Address details
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->default('Malaysia');

            // Additional info
            $table->text('delivery_instructions')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_addresses');
    }
};
