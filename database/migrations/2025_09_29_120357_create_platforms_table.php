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
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // TikTok, Facebook, Shopee, etc.
            $table->string('slug')->unique(); // tiktok, facebook, shopee
            $table->string('display_name'); // Display name for UI
            $table->text('description')->nullable(); // Platform description
            $table->string('website_url')->nullable(); // Platform website
            $table->string('api_base_url')->nullable(); // API base URL
            $table->string('logo_url')->nullable(); // Platform logo
            $table->string('color_primary')->nullable(); // Brand primary color
            $table->string('color_secondary')->nullable(); // Brand secondary color
            $table->enum('type', ['marketplace', 'social_media', 'custom'])->default('marketplace');
            $table->json('features')->nullable(); // Supported features (orders, products, webhooks, etc.)
            $table->json('required_credentials')->nullable(); // Required API credential fields
            $table->json('settings')->nullable(); // Platform-specific settings
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_orders')->default(true);
            $table->boolean('supports_products')->default(true);
            $table->boolean('supports_webhooks')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
