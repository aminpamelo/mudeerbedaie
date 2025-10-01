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
        Schema::table('product_orders', function (Blueprint $table) {
            // Platform integration fields
            $table->unsignedBigInteger('platform_id')->nullable()->after('order_number');
            $table->unsignedBigInteger('platform_account_id')->nullable()->after('platform_id');
            $table->string('platform_order_id')->nullable()->after('platform_account_id');
            $table->string('platform_order_number')->nullable()->after('platform_order_id');
            $table->string('tracking_id')->nullable()->after('platform_order_number');
            $table->string('package_id')->nullable()->after('tracking_id');
            $table->string('buyer_username')->nullable()->after('package_id');
            $table->string('reference_number')->nullable()->after('buyer_username');

            // Add source field to track order origin
            $table->enum('source', ['manual', 'platform_import', 'cart', 'api'])->default('manual')->after('order_type');
            $table->string('source_reference')->nullable()->after('source');

            // Platform discount breakdown fields
            $table->decimal('sku_platform_discount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('sku_seller_discount', 10, 2)->default(0)->after('sku_platform_discount');
            $table->decimal('shipping_fee_seller_discount', 10, 2)->default(0)->after('sku_seller_discount');
            $table->decimal('shipping_fee_platform_discount', 10, 2)->default(0)->after('shipping_fee_seller_discount');
            $table->decimal('payment_platform_discount', 10, 2)->default(0)->after('shipping_fee_platform_discount');
            $table->decimal('original_shipping_fee', 10, 2)->default(0)->after('payment_platform_discount');

            // Additional customer fields
            $table->string('customer_name')->nullable()->after('guest_email');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->json('shipping_address')->nullable()->after('customer_phone');

            // Timeline fields
            $table->timestamp('paid_time')->nullable()->after('confirmed_at');
            $table->timestamp('rts_time')->nullable()->after('paid_time'); // Ready to ship

            // Platform logistics
            $table->string('fulfillment_type')->nullable()->after('delivered_at');
            $table->string('warehouse_name')->nullable()->after('fulfillment_type');
            $table->string('delivery_option')->nullable()->after('warehouse_name');
            $table->string('shipping_provider')->nullable()->after('delivery_option');
            $table->string('payment_method')->nullable()->after('shipping_provider');
            $table->decimal('weight_kg', 10, 3)->nullable()->after('payment_method');

            // Platform notes and messages
            $table->text('buyer_message')->nullable()->after('customer_notes');
            $table->text('seller_note')->nullable()->after('buyer_message');

            // Cancellation details
            $table->string('cancel_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancel_by');

            // Status tracking
            $table->enum('checked_status', ['checked', 'unchecked'])->default('unchecked')->after('status');
            $table->string('checked_marked_by')->nullable()->after('checked_status');

            // Store complete platform data
            $table->json('platform_data')->nullable()->after('metadata');

            // Foreign keys
            $table->foreign('platform_id')->references('id')->on('platforms')->onDelete('set null');
            $table->foreign('platform_account_id')->references('id')->on('platform_accounts')->onDelete('set null');

            // Indexes for performance
            $table->index('platform_order_id');
            $table->index('tracking_id');
            $table->index('buyer_username');
            $table->index('reference_number');
            $table->index('source');
            $table->index(['platform_id', 'platform_order_id']);
            $table->index('paid_time');
            $table->index('rts_time');
        });

        Schema::table('product_order_items', function (Blueprint $table) {
            // Platform-specific product fields
            $table->string('platform_sku')->nullable()->after('sku');
            $table->string('platform_product_name')->nullable()->after('product_name');
            $table->string('platform_variation_name')->nullable()->after('variant_name');
            $table->string('platform_category')->nullable()->after('platform_variation_name');

            // Platform discounts per item
            $table->decimal('platform_discount', 10, 2)->default(0)->after('total_price');
            $table->decimal('seller_discount', 10, 2)->default(0)->after('platform_discount');
            $table->decimal('unit_original_price', 10, 2)->nullable()->after('unit_price');
            $table->decimal('subtotal_before_discount', 10, 2)->nullable()->after('seller_discount');

            // Quantity tracking for returns/cancellations
            $table->integer('returned_quantity')->default(0)->after('quantity_cancelled');
            $table->integer('quantity_affected')->default(0)->after('returned_quantity')->comment('For partial fulfillment');

            // Item-level logistics
            $table->decimal('item_weight_kg', 10, 3)->nullable()->after('product_snapshot');
            $table->string('fulfillment_status')->nullable()->after('item_weight_kg');
            $table->timestamp('item_shipped_at')->nullable()->after('fulfillment_status');
            $table->timestamp('item_delivered_at')->nullable()->after('item_shipped_at');

            // Store platform-specific attributes
            $table->json('product_attributes')->nullable()->after('item_delivered_at');
            $table->json('item_metadata')->nullable()->after('product_attributes');

            // Indexes
            $table->index('platform_sku');
            $table->index('fulfillment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['platform_account_id']);

            // Drop indexes
            $table->dropIndex(['platform_order_id']);
            $table->dropIndex(['tracking_id']);
            $table->dropIndex(['buyer_username']);
            $table->dropIndex(['reference_number']);
            $table->dropIndex(['source']);
            $table->dropIndex(['platform_id', 'platform_order_id']);
            $table->dropIndex(['paid_time']);
            $table->dropIndex(['rts_time']);

            // Drop columns
            $table->dropColumn([
                'platform_id',
                'platform_account_id',
                'platform_order_id',
                'platform_order_number',
                'tracking_id',
                'package_id',
                'buyer_username',
                'reference_number',
                'source',
                'source_reference',
                'sku_platform_discount',
                'sku_seller_discount',
                'shipping_fee_seller_discount',
                'shipping_fee_platform_discount',
                'payment_platform_discount',
                'original_shipping_fee',
                'customer_name',
                'customer_phone',
                'shipping_address',
                'paid_time',
                'rts_time',
                'fulfillment_type',
                'warehouse_name',
                'delivery_option',
                'shipping_provider',
                'payment_method',
                'weight_kg',
                'buyer_message',
                'seller_note',
                'cancel_by',
                'cancel_reason',
                'checked_status',
                'checked_marked_by',
                'platform_data',
            ]);
        });

        Schema::table('product_order_items', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['platform_sku']);
            $table->dropIndex(['fulfillment_status']);

            // Drop columns
            $table->dropColumn([
                'platform_sku',
                'platform_product_name',
                'platform_variation_name',
                'platform_category',
                'platform_discount',
                'seller_discount',
                'unit_original_price',
                'subtotal_before_discount',
                'returned_quantity',
                'quantity_affected',
                'item_weight_kg',
                'fulfillment_status',
                'item_shipped_at',
                'item_delivered_at',
                'product_attributes',
                'item_metadata',
            ]);
        });
    }
};
