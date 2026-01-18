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
        // Check if old table exists and new table doesn't
        if (Schema::hasTable('kedai_buku_pricing') && ! Schema::hasTable('agent_pricing')) {
            Schema::rename('kedai_buku_pricing', 'agent_pricing');
        }

        // If neither exists, create the new table
        if (! Schema::hasTable('agent_pricing')) {
            Schema::create('agent_pricing', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('price', 10, 2)->comment('Special price for this agent');
                $table->integer('min_quantity')->default(1)->comment('Minimum order quantity for this price');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Unique composite index to prevent duplicate pricing for same agent+product+quantity
                $table->unique(['agent_id', 'product_id', 'min_quantity'], 'agent_pricing_unique');
                // Additional indexes for faster lookups
                $table->index('agent_id');
                $table->index('product_id');
                $table->index('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('agent_pricing') && ! Schema::hasTable('kedai_buku_pricing')) {
            Schema::rename('agent_pricing', 'kedai_buku_pricing');
        }
    }
};
