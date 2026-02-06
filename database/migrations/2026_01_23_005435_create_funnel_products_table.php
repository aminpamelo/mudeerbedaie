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
        Schema::create('funnel_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_step_id')->constrained('funnel_steps')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->enum('type', ['main', 'bump', 'upsell', 'downsell'])->default('main');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('funnel_price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->enum('billing_interval', ['weekly', 'monthly', 'yearly'])->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['funnel_step_id', 'type']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_products');
    }
};
