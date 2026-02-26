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
        Schema::create('funnel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('funnel_sessions')->cascadeOnDelete();
            $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('step_id');
            $table->enum('order_type', ['main', 'upsell', 'downsell', 'bump'])->default('main');
            $table->decimal('funnel_revenue', 10, 2);
            $table->unsignedInteger('upsells_offered')->default(0);
            $table->unsignedInteger('upsells_accepted')->default(0);
            $table->unsignedInteger('downsells_offered')->default(0);
            $table->unsignedInteger('downsells_accepted')->default(0);
            $table->unsignedInteger('bumps_offered')->default(0);
            $table->unsignedInteger('bumps_accepted')->default(0);
            $table->timestamps();

            $table->index(['session_id', 'order_type']);
            $table->index('funnel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_orders');
    }
};
