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
        Schema::create('platform_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_account_id')->nullable()->constrained()->onDelete('cascade');

            // Platform customer identifiers
            $table->string('platform_customer_id')->nullable()->index();
            $table->string('username')->nullable()->index(); // Platform username/handle

            // Customer information
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();

            // Additional platform-specific data
            $table->json('addresses')->nullable(); // Multiple addresses if supported
            $table->json('customer_metadata')->nullable(); // Platform-specific customer data
            $table->json('preferences')->nullable(); // Customer preferences/settings

            // Customer statistics
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();

            // Customer status
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->boolean('is_verified')->default(false);

            // Tracking
            $table->timestamp('last_sync_at')->nullable();
            $table->json('sync_metadata')->nullable();

            $table->timestamps();

            // Unique constraints
            $table->unique(['platform_id', 'platform_account_id', 'platform_customer_id'], 'unique_platform_customer_id');
            $table->unique(['platform_id', 'platform_account_id', 'username'], 'unique_platform_username');

            // Indexes for performance
            $table->index(['platform_account_id', 'status']);
            $table->index(['email']);
            $table->index(['phone']);
            $table->index(['country', 'state']);
            $table->index(['total_orders', 'total_spent']);
            $table->index(['last_order_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_customers');
    }
};
