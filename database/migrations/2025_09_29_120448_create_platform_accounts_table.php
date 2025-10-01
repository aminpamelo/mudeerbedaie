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
        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Account owner
            $table->string('name'); // Account display name
            $table->string('account_id')->nullable(); // Platform's internal account ID
            $table->string('seller_center_id')->nullable(); // Seller center ID (TikTok Shop)
            $table->string('business_manager_id')->nullable(); // Business manager ID (Facebook)
            $table->string('shop_id')->nullable(); // Shop ID (Shopee)
            $table->string('store_id')->nullable(); // Store ID (other platforms)
            $table->string('email')->nullable(); // Account email
            $table->string('phone')->nullable(); // Account phone
            $table->string('country_code', 2)->nullable(); // Store country
            $table->string('currency', 3)->nullable(); // Store currency
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Platform-specific data
            $table->json('permissions')->nullable(); // Account permissions/scopes
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Token expiration
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync_orders')->default(true);
            $table->boolean('auto_sync_products')->default(false);
            $table->timestamps();

            $table->unique(['platform_id', 'account_id']);
            $table->index(['platform_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index(['last_sync_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_accounts');
    }
};
