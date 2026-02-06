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
        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->string('itemable_type');
            $table->unsignedBigInteger('itemable_id');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('item_name');
            $table->string('variant_name')->nullable();
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['itemable_type', 'itemable_id']);
            $table->index('pos_sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
    }
};
