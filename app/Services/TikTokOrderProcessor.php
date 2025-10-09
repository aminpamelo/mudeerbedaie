<?php

namespace App\Services;

use App\Helpers\PhoneNumberHelper;
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
use App\Models\Student;
use App\Models\User;
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
     * Find or create student based on phone number
     * Only creates/updates student if phone number is unmasked and valid
     *
     * @param  array  $mappedData  Order data containing customer info
     * @return Student|null Student instance if found/created, null if phone is masked/invalid
     */
    protected function findOrCreateStudent(array $mappedData): ?Student
    {
        $phoneNumber = $mappedData['customer_phone'] ?? $mappedData['phone'] ?? null;
        $customerName = $mappedData['customer_name'] ?? $mappedData['recipient'] ?? null;

        // Skip if no phone number or customer name
        if (empty($phoneNumber) || empty($customerName)) {
            return null;
        }

        // Check if phone number is masked (contains asterisks)
        if (PhoneNumberHelper::isMasked($phoneNumber)) {
            Log::info('Skipping student creation - phone number is masked', [
                'phone' => $phoneNumber,
                'order_id' => $mappedData['order_id'] ?? null,
            ]);

            return null;
        }

        // Normalize phone number for consistent lookup
        $normalizedPhone = PhoneNumberHelper::normalize($phoneNumber);

        if (empty($normalizedPhone)) {
            Log::warning('Could not normalize phone number for student lookup', [
                'original_phone' => $phoneNumber,
                'order_id' => $mappedData['order_id'] ?? null,
            ]);

            return null;
        }

        // Try to find existing student by normalized phone number
        $student = Student::where('phone', $normalizedPhone)->first();

        if ($student) {
            // Update student info if we have better data (unmasked)
            $this->updateStudentIfNeeded($student, $mappedData, $normalizedPhone);

            Log::info('Found existing student for order', [
                'student_id' => $student->id,
                'phone' => $normalizedPhone,
                'order_id' => $mappedData['order_id'] ?? null,
            ]);

            return $student;
        }

        // Create new student if not found
        return $this->createStudentFromOrder($mappedData, $normalizedPhone, $customerName);
    }

    /**
     * Create a new student from order data
     */
    protected function createStudentFromOrder(array $mappedData, string $normalizedPhone, string $customerName): Student
    {
        // Create user first
        $user = User::create([
            'name' => $customerName,
            'email' => $this->generateEmailFromPhone($normalizedPhone),
            'password' => bcrypt(str()->random(16)), // Random password, user needs to reset
            'email_verified_at' => null, // Not verified since auto-created
        ]);

        // Create student record
        $student = Student::create([
            'user_id' => $user->id,
            'phone' => $normalizedPhone,
            'address' => $this->formatAddressFromOrder($mappedData),
            'status' => 'active',
        ]);

        Log::info('Created new student from TikTok order', [
            'student_id' => $student->id,
            'user_id' => $user->id,
            'phone' => $normalizedPhone,
            'name' => $customerName,
            'order_id' => $mappedData['order_id'] ?? null,
        ]);

        return $student;
    }

    /**
     * Update student information if new data is better (unmasked)
     */
    protected function updateStudentIfNeeded(Student $student, array $mappedData, string $normalizedPhone): void
    {
        $updates = [];

        // Update phone if different (normalized comparison)
        if ($student->phone !== $normalizedPhone) {
            $updates['phone'] = $normalizedPhone;
        }

        // Update address if we have new unmasked address data
        $newAddress = $this->formatAddressFromOrder($mappedData);
        if (! empty($newAddress) && $newAddress !== $student->address) {
            // Only update if new address is not masked
            if (! PhoneNumberHelper::isMasked($newAddress)) {
                $updates['address'] = $newAddress;
            }
        }

        // Update user name if we have unmasked name
        $customerName = $mappedData['customer_name'] ?? $mappedData['recipient'] ?? null;
        if ($customerName && ! PhoneNumberHelper::isMasked($customerName) && $student->user) {
            if ($student->user->name !== $customerName) {
                $student->user->update(['name' => $customerName]);
            }
        }

        if (! empty($updates)) {
            $student->update($updates);

            Log::info('Updated student information from order', [
                'student_id' => $student->id,
                'updates' => array_keys($updates),
                'order_id' => $mappedData['order_id'] ?? null,
            ]);
        }
    }

    /**
     * Generate email from phone number for auto-created users
     */
    protected function generateEmailFromPhone(string $normalizedPhone): string
    {
        return "student_{$normalizedPhone}@mudeerbedaie.local";
    }

    /**
     * Format address from order data
     */
    protected function formatAddressFromOrder(array $mappedData): ?string
    {
        $addressParts = array_filter([
            $mappedData['detail_address'] ?? null,
            $mappedData['city'] ?? $mappedData['post_town'] ?? null,
            $mappedData['state'] ?? null,
            $mappedData['postal_code'] ?? $mappedData['zipcode'] ?? null,
            $mappedData['country'] ?? null,
        ]);

        return ! empty($addressParts) ? implode(', ', $addressParts) : null;
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

        // Find or create student based on phone number (only if unmasked)
        $student = $this->findOrCreateStudent($mappedData);

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

            // Student link (only set if we found/created a student)
            'student_id' => $student?->id,

            // Customer info - Smart update logic applied
            'customer_name' => $this->getSmartUpdateValue(
                $existingOrder?->customer_name,
                $mappedData['customer_name'] ?? $mappedData['recipient'] ?? null
            ),
            'customer_phone' => $this->getSmartUpdatePhoneValue(
                $existingOrder?->customer_phone,
                $mappedData['customer_phone'] ?? $mappedData['phone'] ?? null
            ),
            'shipping_address' => $this->getSmartUpdateAddress(
                $existingOrder?->shipping_address ?? [],
                [
                    'country' => $mappedData['country'] ?? null,
                    'state' => $mappedData['state'] ?? null,
                    'city' => $mappedData['city'] ?? $mappedData['post_town'] ?? null,
                    'postal_code' => $mappedData['postal_code'] ?? $mappedData['zipcode'] ?? null,
                    'detail_address' => $mappedData['detail_address'] ?? null,
                    'additional_info' => $mappedData['additional_address_information'] ?? null,
                ]
            ),

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

    /**
     * Smart field update logic - only update if current value is masked
     *
     * @param  mixed  $existingValue  The current value in database
     * @param  mixed  $newValue  The new value from CSV import
     * @return mixed Returns the appropriate value based on masking detection
     */
    protected function getSmartUpdateValue($existingValue, $newValue)
    {
        // If no existing value, always use new value
        if (empty($existingValue)) {
            return $newValue;
        }

        // If new value is empty, keep existing value
        if (empty($newValue)) {
            return $existingValue;
        }

        // Check if existing value contains masking symbols (*)
        if ($this->isMaskedValue($existingValue)) {
            // Current data is masked, allow update with new value
            return $newValue;
        }

        // Check if new value is masked
        if ($this->isMaskedValue($newValue)) {
            // New data is masked but existing is clean, keep existing (manually corrected data)
            return $existingValue;
        }

        // Both values are unmasked, prefer new value (latest data from platform)
        return $newValue;
    }

    /**
     * Smart phone update logic with normalization
     * Normalizes phone numbers and handles masked values intelligently
     *
     * @param  mixed  $existingValue  The current phone value in database
     * @param  mixed  $newValue  The new phone value from CSV import
     * @return mixed Returns normalized phone or appropriate value based on masking
     */
    protected function getSmartUpdatePhoneValue($existingValue, $newValue)
    {
        // If no existing value and new value is valid, normalize and use it
        if (empty($existingValue)) {
            $normalized = PhoneNumberHelper::normalize($newValue);

            return $normalized ?? $newValue;
        }

        // If new value is empty, keep existing value
        if (empty($newValue)) {
            return $existingValue;
        }

        // Check if existing value is masked
        if (PhoneNumberHelper::isMasked($existingValue)) {
            // Current data is masked, normalize and use new value if valid
            $normalized = PhoneNumberHelper::normalize($newValue);

            return $normalized ?? $newValue;
        }

        // Check if new value is masked
        if (PhoneNumberHelper::isMasked($newValue)) {
            // New data is masked but existing is clean, keep existing
            return $existingValue;
        }

        // Both values are unmasked - normalize both and compare
        $normalizedExisting = PhoneNumberHelper::normalize($existingValue);
        $normalizedNew = PhoneNumberHelper::normalize($newValue);

        // If they're the same after normalization, keep existing format
        if ($normalizedExisting === $normalizedNew) {
            return $existingValue;
        }

        // Different numbers - prefer new normalized value (latest data)
        return $normalizedNew ?? $newValue;
    }

    /**
     * Smart address update logic - preserves unmasked address fields
     *
     * @param  array  $existingAddress  Current shipping address
     * @param  array  $newAddress  New shipping address from CSV
     * @return array Merged address with smart field updates
     */
    protected function getSmartUpdateAddress(array $existingAddress, array $newAddress): array
    {
        // If no existing address, return new address
        if (empty($existingAddress)) {
            return $newAddress;
        }

        $mergedAddress = [];

        foreach ($newAddress as $field => $newValue) {
            $existingValue = $existingAddress[$field] ?? null;
            $mergedAddress[$field] = $this->getSmartUpdateValue($existingValue, $newValue);
        }

        return $mergedAddress;
    }

    /**
     * Check if a value contains TikTok masking symbols
     * TikTok masks personal data with asterisks (*) like: (+60)148****88, John***
     *
     * @param  mixed  $value  The value to check
     * @return bool True if value appears to be masked
     */
    protected function isMaskedValue($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Check for common masking patterns:
        // 1. Contains asterisks (*) - most common
        // 2. Multiple consecutive asterisks (****)
        // 3. Phone number patterns with masking: (+60)148****88
        // 4. Name patterns with masking: John*** or ***n Doe

        return str_contains($value, '*');
    }
}
