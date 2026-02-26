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
        Schema::table('platform_accounts', function (Blueprint $table) {
            // Sync status tracking
            $table->string('sync_status')->default('idle')->after('auto_sync_products');
            $table->timestamp('last_order_sync_at')->nullable()->after('sync_status');
            $table->timestamp('last_product_sync_at')->nullable()->after('last_order_sync_at');
            $table->timestamp('last_inventory_sync_at')->nullable()->after('last_product_sync_at');

            // Error tracking
            $table->timestamp('last_error_at')->nullable()->after('last_inventory_sync_at');
            $table->text('last_error_message')->nullable()->after('last_error_at');

            // API-specific settings
            $table->string('api_version')->nullable()->after('last_error_message');
            $table->json('sync_settings')->nullable()->after('api_version');

            // Add index for sync status queries
            $table->index(['platform_id', 'sync_status']);
            $table->index(['platform_id', 'auto_sync_orders']);
        });

        // Create API logs table for debugging
        Schema::create('tiktok_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->string('endpoint');
            $table->string('method', 10)->default('GET');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['platform_account_id', 'created_at']);
            $table->index(['endpoint', 'status_code']);
        });

        // Create sync schedules table
        Schema::create('platform_sync_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->string('sync_type'); // orders, products, inventory
            $table->unsignedInteger('interval_minutes')->default(15);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['platform_account_id', 'sync_type']);
            $table->index(['is_active', 'next_run_at']);
        });

        // Create webhook events log
        Schema::create('tiktok_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type');
            $table->string('event_id')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['platform_account_id', 'event_type']);
            $table->index(['status', 'created_at']);
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_webhook_events');
        Schema::dropIfExists('platform_sync_schedules');
        Schema::dropIfExists('tiktok_api_logs');

        Schema::table('platform_accounts', function (Blueprint $table) {
            $table->dropIndex(['platform_id', 'sync_status']);
            $table->dropIndex(['platform_id', 'auto_sync_orders']);

            $table->dropColumn([
                'sync_status',
                'last_order_sync_at',
                'last_product_sync_at',
                'last_inventory_sync_at',
                'last_error_at',
                'last_error_message',
                'api_version',
                'sync_settings',
            ]);
        });
    }
};
