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
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->enum('alert_type', ['low_stock', 'out_of_stock', 'overstock'])->default('low_stock');
            $table->integer('threshold_quantity');
            $table->boolean('is_active')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'warehouse_id', 'alert_type'], 'unique_stock_alert');
            $table->index(['warehouse_id', 'is_active']);
            $table->index(['alert_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
