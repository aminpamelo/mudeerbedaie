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
        Schema::create('funnel_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('funnel_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('step_id');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('cart_data');
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('abandoned_at')->nullable();
            $table->enum('recovery_status', ['pending', 'sent', 'recovered', 'expired'])->default('pending');
            $table->unsignedInteger('recovery_emails_sent')->default(0);
            $table->timestamp('recovered_at')->nullable();
            $table->foreignId('product_order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['recovery_status', 'abandoned_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_carts');
    }
};
