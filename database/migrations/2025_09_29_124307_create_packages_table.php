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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            // Pricing
            $table->decimal('price', 10, 2); // Package selling price
            $table->decimal('original_price', 10, 2)->nullable(); // Sum of individual item prices
            $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('discount_value', 10, 2)->default(0);

            // Status and availability
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('max_purchases')->nullable(); // Limit total sales
            $table->integer('purchased_count')->default(0); // Track total purchases

            // Stock and warehouse management
            $table->boolean('track_stock')->default(true); // Whether to check product stock
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');

            // Media and display
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();

            // SEO and metadata
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('metadata')->nullable(); // Additional settings

            // Management
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index(['status', 'start_date', 'end_date']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
