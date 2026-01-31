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
        if (Schema::hasTable('funnel_affiliate_commission_rules')) {
            return;
        }

        Schema::create('funnel_affiliate_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_product_id')->constrained('funnel_products')->cascadeOnDelete();
            $table->enum('commission_type', ['fixed', 'percentage']);
            $table->decimal('commission_value', 10, 2);
            $table->timestamps();

            $table->unique(['funnel_id', 'funnel_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_affiliate_commission_rules');
    }
};
