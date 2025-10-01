<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformCustomer;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TikTokOrderProcessor
{
    protected Platform $platform;

    protected PlatformAccount $account;

    protected array $fieldMapping;

    protected array $productMappings;

    public function __construct(Platform $platform, PlatformAccount $account, array $fieldMapping, array $productMappings)
    {
        $this->platform = $platform;
        $this->account = $account;
        $this->fieldMapping = $fieldMapping;
        $this->productMappings = $productMappings;
    }

    /**
     * Process a single CSV row into product orders (unified system)
     * Note: Expects already-mapped data from the CSV importer
     */
    public function processOrderRow(array $mappedData): array
    {
        return DB::transaction(function () use ($mappedData) {
            // Parse and clean the already-mapped data
            $mappedData = $this->parseOrderData($mappedData);

            // Apply product mapping
            if (isset($mappedData['product_name'])) {
                $productName = trim($mappedData['product_name']);
                if (isset($this->productMappings[$productName])) {
                    $mapping = $this->productMappings[$productName];
                    $mappedData['internal_product'] = Product::find($mapping['product_id']);
                    if (isset($mapping['variant_id'])) {
                        $mappedData['internal_variant'] = ProductVariant::find($mapping['variant_id']);
                    }
                }
            }

            // Validate required data
            $this->validateOrderData($mappedData);

            // Find or create platform customer (still needed for reference)
            $platformCustomer = $this->findOrCreatePlatformCustomer($mappedData);

            // Create or update product order with platform data
            $productOrder = $this->createOrUpdateProductOrder($mappedData, $platformCustomer);

            // Create product order items
            $productOrderItems = $this->createProductOrderItems($productOrder, $mappedData);

            // Check for package matches
            $packagePurchase = $this->detectAndCreatePackagePurchase($productOrder, $productOrderItems);

            // Update SKU mapping usage statistics
            $this->updateSkuMappingUsage($mappedData);

            return [
                'product_order' => $productOrder,
                'product_order_items' => $productOrderItems,
                'package_purchase' => $packagePurchase,
                'platform_customer' => $platformCustomer,
            ];
        });
    }

    /**
     * Map CSV row data to system fields
     */
    protected function mapCsvRowData(array $csvRow): array
    {
        $mapped = [];

        // Map basic fields using field mapping
        foreach ($this->fieldMapping as $systemField => $csvColumn) {
            if ($csvColumn && isset($csvRow[$csvColumn])) {
                $mapped[$systemField] = trim($csvRow[$csvColumn]);
            }
        }

        // Parse and clean data
        $mapped = $this->parseOrderData($mapped);

        // Apply product mapping
        if (isset($mapped['product_name'])) {
            $productName = trim($mapped['product_name']);
            if (isset($this->productMappings[$productName])) {
                $mapping = $this->productMappings[$productName];
                $mapped['internal_product'] = Product::find($mapping['product_id']);
                if (isset($mapping['variant_id'])) {
                    $mapped['internal_variant'] = ProductVariant::find($mapping['variant_id']);
                }
            }
        }

        return $mapped;
    }

    /**
     * Parse and clean order data
     */
    protected function parseOrderData(array $mapped): array
    {
        // Parse dates - TikTok uses d/m/Y H:i:s format (e.g., "14/09/2025 12:44:51")
        $dateFields = ['created_time', 'paid_time', 'rts_time', 'shipped_time', 'delivered_time', 'cancelled_time'];
        foreach ($dateFields as $field) {
            if (isset($mapped[$field]) && ! empty($mapped[$field])) {
                try {
                    // Try TikTok format first: d/m/Y H:i:s
                    $mapped[$field] = Carbon::createFromFormat('d/m/Y H:i:s', $mapped[$field]);
                } catch (\Exception $e) {
                    // Fallback to general Carbon parser
                    try {
                        $mapped[$field] = Carbon::parse($mapped[$field]);
                    } catch (\Exception $e2) {
                        Log::warning("Failed to parse date field {$field}: {$mapped[$field]}");
                        $mapped[$field] = null;
                    }
                }
            } else {
                // Set empty strings and missing fields to null for datetime columns
                $mapped[$field] = null;
            }
        }

        // Parse numeric fields
        $numericFields = [
            'quantity', 'order_amount', 'sku_subtotal_after_discount', 'shipping_fee_after_discount',
            'taxes', 'sku_platform_discount', 'sku_seller_discount', 'shipping_fee_seller_discount',
            'shipping_fee_platform_discount', 'payment_platform_discount', 'original_shipping_fee', 'weight_kg',
        ];

        foreach ($numericFields as $field) {
            if (isset($mapped[$field]) && ! empty($mapped[$field])) {
                $mapped[$field] = (float) str_replace(',', '', $mapped[$field]);
            } else {
                $mapped[$field] = 0;
            }
        }

        // Ensure quantity is at least 1
        if (isset($mapped['quantity']) && $mapped['quantity'] < 1) {
            $mapped['quantity'] = 1;
        }

        return $mapped;
    }

    /**
     * Validate essential order data
     */
    protected function validateOrderData(array $mappedData): void
    {
        $required = ['order_id', 'product_name', 'quantity'];

        foreach ($required as $field) {
            if (empty($mappedData[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }

        if (! is_numeric($mappedData['quantity']) || $mappedData['quantity'] < 1) {
            throw new \InvalidArgumentException("Invalid quantity: {$mappedData['quantity']}");
        }
    }

    /**
     * Find or create platform customer
     */
    protected function findOrCreatePlatformCustomer(array $mappedData): PlatformCustomer
    {
        $customerData = [
            'username' => $mappedData['buyer_username'] ?? null,
            'name' => $mappedData['customer_name'] ?? $mappedData['recipient'] ?? null,
            'phone' => $mappedData['customer_phone'] ?? $mappedData['phone'] ?? null,
            'country' => $mappedData['country'] ?? null,
            'state' => $mappedData['state'] ?? null,
            'city' => $mappedData['city'] ?? $mappedData['post_town'] ?? null,
            'postal_code' => $mappedData['postal_code'] ?? $mappedData['zipcode'] ?? null,
        ];

        return PlatformCustomer::findOrCreateFromOrderData(
            $customerData,
            $this->platform->id,
            $this->account->id
        );
    }

    /**
     * Create or update product order with platform data
     */
    protected function createOrUpdateProductOrder(array $mappedData, PlatformCustomer $platformCustomer): ProductOrder
    {
        // Check if order already exists
        $existingOrder = ProductOrder::where('platform_id', $this->platform->id)
            ->where('platform_account_id', $this->account->id)
            ->where('platform_order_id', $mappedData['order_id'])
            ->first();

        $orderData = [
            // Basic order fields
            'order_number' => 'TT-'.$mappedData['order_id'],
            'order_type' => 'retail',
            'source' => 'platform_import',
            'source_reference' => $mappedData['order_id'],
            'status' => $this->mapTikTokStatus($mappedData),
            'currency' => 'MYR',

            // Platform integration
            'platform_id' => $this->platform->id,
            'platform_account_id' => $this->account->id,
            'platform_order_id' => $mappedData['order_id'],
            'platform_order_number' => $mappedData['order_id'],
            'reference_number' => $mappedData['reference_number'] ?? null,
            'tracking_id' => $mappedData['tracking_id'] ?? null,
            'package_id' => $mappedData['package_id'] ?? null,
            'buyer_username' => $mappedData['buyer_username'] ?? null,

            // Customer info
            'customer_name' => $mappedData['customer_name'] ?? $mappedData['recipient'] ?? null,
            'customer_phone' => $mappedData['customer_phone'] ?? $mappedData['phone'] ?? null,
            'shipping_address' => [
                'country' => $mappedData['country'] ?? null,
                'state' => $mappedData['state'] ?? null,
                'city' => $mappedData['city'] ?? $mappedData['post_town'] ?? null,
                'postal_code' => $mappedData['postal_code'] ?? $mappedData['zipcode'] ?? null,
                'detail_address' => $mappedData['detail_address'] ?? null,
                'additional_info' => $mappedData['additional_address_information'] ?? null,
            ],

            // Pricing
            'subtotal' => $mappedData['sku_subtotal_after_discount'] ?? 0,
            'shipping_cost' => $mappedData['shipping_fee_after_discount'] ?? 0,
            'tax_amount' => $mappedData['taxes'] ?? 0,
            'discount_amount' => ($mappedData['sku_platform_discount'] ?? 0) + ($mappedData['sku_seller_discount'] ?? 0),
            'total_amount' => $mappedData['order_amount'] ?? 0,

            // Platform discount breakdown
            'sku_platform_discount' => $mappedData['sku_platform_discount'] ?? 0,
            'sku_seller_discount' => $mappedData['sku_seller_discount'] ?? 0,
            'shipping_fee_seller_discount' => $mappedData['shipping_fee_seller_discount'] ?? 0,
            'shipping_fee_platform_discount' => $mappedData['shipping_fee_platform_discount'] ?? 0,
            'payment_platform_discount' => $mappedData['payment_platform_discount'] ?? 0,
            'original_shipping_fee' => $mappedData['original_shipping_fee'] ?? 0,

            // Dates
            'order_date' => $mappedData['created_time'] ?? now(),
            'paid_time' => $mappedData['paid_time'] ?? null,
            'rts_time' => $mappedData['rts_time'] ?? null,
            'confirmed_at' => $mappedData['paid_time'] ?? null,
            'shipped_at' => $mappedData['shipped_time'] ?? null,
            'delivered_at' => $mappedData['delivered_time'] ?? null,
            'cancelled_at' => $mappedData['cancelled_time'] ?? null,

            // Platform logistics
            'fulfillment_type' => $mappedData['fulfillment_type'] ?? null,
            'warehouse_name' => $mappedData['warehouse_name'] ?? null,
            'delivery_option' => $mappedData['delivery_option'] ?? null,
            'shipping_provider' => $mappedData['shipping_provider_name'] ?? null,
            'payment_method' => $mappedData['payment_method'] ?? null,
            'weight_kg' => $mappedData['weight_kg'] ?? null,

            // Notes and messages
            'buyer_message' => $mappedData['buyer_message'] ?? null,
            'seller_note' => $mappedData['seller_note'] ?? null,
            'customer_notes' => $mappedData['buyer_message'] ?? null,
            'internal_notes' => $mappedData['seller_note'] ?? null,

            // Cancellation
            'cancel_by' => $mappedData['cancel_by'] ?? null,
            'cancel_reason' => $mappedData['cancel_reason'] ?? null,

            // Status tracking
            'checked_status' => $mappedData['checked_status'] ?? 'unchecked',
            'checked_marked_by' => $mappedData['checked_marked_by'] ?? null,

            // Store complete platform data
            'platform_data' => $mappedData,
        ];

        if ($existingOrder) {
            $existingOrder->update($orderData);

            return $existingOrder;
        } else {
            return ProductOrder::create($orderData);
        }
    }

    /**
     * Create product order items
     */
    protected function createProductOrderItems(ProductOrder $productOrder, array $mappedData): array
    {
        // Remove existing items for this order
        $productOrder->items()->delete();

        $items = [];

        // For TikTok, each CSV row represents one order item
        $quantity = (int) $mappedData['quantity'];
        $subtotalAfterDiscount = (float) ($mappedData['sku_subtotal_after_discount'] ?? 0);
        $unitPrice = $quantity > 0 ? $subtotalAfterDiscount / $quantity : 0;

        $itemData = [
            'order_id' => $productOrder->id,

            // Standard product fields
            'product_name' => $mappedData['product_name'],
            'variant_name' => $mappedData['variation'] ?? null,
            'sku' => $mappedData['sku'] ?? $mappedData['product_name'],
            'quantity_ordered' => $quantity,
            'returned_quantity' => (int) ($mappedData['returned_quantity'] ?? 0),
            'unit_price' => $unitPrice,
            'total_price' => $subtotalAfterDiscount,

            // Platform-specific fields
            'platform_sku' => $mappedData['sku'] ?? $mappedData['product_name'],
            'platform_product_name' => $mappedData['product_name'],
            'platform_variation_name' => $mappedData['variation'] ?? null,
            'platform_category' => $mappedData['product_category'] ?? null,
            'platform_discount' => (float) ($mappedData['sku_platform_discount'] ?? 0),
            'seller_discount' => (float) ($mappedData['sku_seller_discount'] ?? 0),
            'unit_original_price' => (float) ($mappedData['unit_price'] ?? $unitPrice),
            'subtotal_before_discount' => (float) ($mappedData['subtotal_before_discount'] ?? $subtotalAfterDiscount),

            // Logistics
            'item_weight_kg' => (float) ($mappedData['weight_kg'] ?? 0),
            'fulfillment_status' => $this->mapFulfillmentStatus($mappedData),
            'item_shipped_at' => $mappedData['shipped_time'] ?? null,
            'item_delivered_at' => $mappedData['delivered_time'] ?? null,

            // Metadata
            'item_metadata' => $mappedData,
        ];

        // Link to internal product if mapped
        if (isset($mappedData['internal_product'])) {
            $itemData['product_id'] = $mappedData['internal_product']->id;
            $itemData['product_name'] = $mappedData['internal_product']->name;
        }

        if (isset($mappedData['internal_variant'])) {
            $itemData['product_variant_id'] = $mappedData['internal_variant']->id;
        }

        $items[] = ProductOrderItem::create($itemData);

        return $items;
    }

    /**
     * Update SKU mapping usage statistics
     */
    protected function updateSkuMappingUsage(array $mappedData): void
    {
        if (! isset($mappedData['product_name'])) {
            return;
        }

        $productName = trim($mappedData['product_name']);
        $platformSku = $mappedData['sku'] ?? $productName;

        // Find SKU mapping
        $mapping = PlatformSkuMapping::where('platform_id', $this->platform->id)
            ->where('platform_account_id', $this->account->id)
            ->where('platform_sku', $platformSku)
            ->first();

        if ($mapping) {
            $mapping->increment('usage_count');
            $mapping->update(['last_used_at' => now()]);
        }
    }

    /**
     * Map TikTok status to system status
     */
    protected function mapTikTokStatus(array $mappedData): string
    {
        // Determine status based on timestamps and cancellation
        if (! empty($mappedData['cancelled_time'])) {
            return 'cancelled';
        }

        if (! empty($mappedData['delivered_time'])) {
            return 'delivered';
        }

        if (! empty($mappedData['shipped_time'])) {
            return 'shipped';
        }

        if (! empty($mappedData['rts_time'])) {
            return 'processing';
        }

        if (! empty($mappedData['paid_time'])) {
            return 'confirmed';
        }

        return 'pending';
    }

    /**
     * Map fulfillment status for order items
     */
    protected function mapFulfillmentStatus(array $mappedData): string
    {
        if (! empty($mappedData['delivered_time'])) {
            return 'delivered';
        }

        if (! empty($mappedData['shipped_time'])) {
            return 'shipped';
        }

        if (! empty($mappedData['rts_time'])) {
            return 'ready_to_ship';
        }

        return 'pending';
    }

    /**
     * Map platform status to product order status
     */
    protected function mapToProductOrderStatus(string $platformStatus): string
    {
        return match ($platformStatus) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Detect if product order items match any existing packages and create package purchase
     */
    protected function detectAndCreatePackagePurchase(ProductOrder $productOrder, array $productOrderItems): ?PackagePurchase
    {
        // Only detect packages if all order items are mapped to products
        $mappedItems = [];

        foreach ($productOrderItems as $item) {
            if (! $item->product_id) {
                // Skip package detection if any items are unmapped
                return null;
            }

            $mappedItems[] = [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity_ordered,
                'unit_price' => $item->unit_price,
            ];
        }

        // Find packages that might match the order items
        $potentialPackages = Package::active()
            ->with(['products'])
            ->get();

        foreach ($potentialPackages as $package) {
            if ($this->doesOrderMatchPackage($mappedItems, $package)) {
                return $this->createPackagePurchaseFromOrder($productOrder, $package, $mappedItems);
            }
        }

        return null;
    }

    /**
     * Check if order items match a specific package
     */
    protected function doesOrderMatchPackage(array $orderItems, Package $package): bool
    {
        $packageItems = $package->products()->get();

        // Quick check: different number of unique products
        $orderProductIds = collect($orderItems)->pluck('product_id')->unique();
        $packageProductIds = $packageItems->pluck('id')->unique();

        if ($orderProductIds->count() !== $packageProductIds->count()) {
            return false;
        }

        // Check each package item has matching order item
        foreach ($packageItems as $packageProduct) {
            $packageQuantity = $packageProduct->pivot->quantity;
            $packageVariantId = $packageProduct->pivot->product_variant_id;

            $matchingOrderItem = collect($orderItems)->first(function ($orderItem) use ($packageProduct, $packageVariantId) {
                return $orderItem['product_id'] == $packageProduct->id
                    && $orderItem['product_variant_id'] == $packageVariantId;
            });

            if (! $matchingOrderItem || $matchingOrderItem['quantity'] < $packageQuantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create package purchase from product order
     */
    protected function createPackagePurchaseFromOrder(ProductOrder $productOrder, Package $package, array $orderItems): PackagePurchase
    {
        // Calculate total price from order items
        $totalPrice = collect($orderItems)->sum(function ($item) {
            return $item['unit_price'] * $item['quantity'];
        });

        $purchaseData = [
            'purchase_number' => 'PKG-'.$productOrder->platform_order_id,
            'package_id' => $package->id,
            'user_id' => null, // Platform orders don't have internal users initially
            'customer_name' => $productOrder->customer_name,
            'customer_email' => $productOrder->guest_email,
            'customer_phone' => $productOrder->customer_phone ?? '',
            'total_amount' => $totalPrice,
            'package_price' => $package->price,
            'savings_amount' => max(0, $totalPrice - $package->price),
            'status' => $this->mapToPackagePurchaseStatus($productOrder->status),
            'product_order_id' => $productOrder->id,
            'purchased_at' => $productOrder->order_date,
            'payment_status' => $productOrder->isPaid() ? 'paid' : 'pending',
            'payment_method' => $productOrder->payment_method ?? 'platform',
            'notes' => "Auto-created from platform order {$productOrder->display_order_id}",
            'metadata' => [
                'platform' => $productOrder->platform?->name,
                'platform_account' => $productOrder->platformAccount?->name,
                'original_order_id' => $productOrder->display_order_id,
                'detection_method' => 'automatic',
                'detected_at' => now()->toISOString(),
            ],
        ];

        $packagePurchase = PackagePurchase::create($purchaseData);

        Log::info('Package purchase created from product order', [
            'package_purchase_id' => $packagePurchase->id,
            'product_order_id' => $productOrder->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'total_amount' => $totalPrice,
        ]);

        return $packagePurchase;
    }

    /**
     * Map platform order status to package purchase status
     */
    protected function mapToPackagePurchaseStatus(string $platformStatus): string
    {
        return match ($platformStatus) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}
