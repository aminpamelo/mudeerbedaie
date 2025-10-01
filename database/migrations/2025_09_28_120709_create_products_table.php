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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->unique()->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->enum('type', ['simple', 'variable'])->default('simple');
            $table->boolean('track_quantity')->default(true);
            $table->integer('min_quantity')->default(0);
            $table->json('dimensions')->nullable(); // weight, length, width, height
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
