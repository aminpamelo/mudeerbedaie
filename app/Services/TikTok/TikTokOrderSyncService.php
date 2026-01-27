<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Carbon\Carbon;
use EcomPHP\TiktokShop\Resources\Order;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TikTokOrderSyncService
{
    private const PROGRESS_CACHE_PREFIX = 'tiktok_sync_progress_';

    private const PROGRESS_TTL = 600; // 10 minutes

    public function __construct(
        private TikTokClientFactory $clientFactory,
        private TikTokAuthService $authService
    ) {}

    /**
     * Get the cache key for sync progress.
     */
    public static function getProgressCacheKey(int $accountId): string
    {
        return self::PROGRESS_CACHE_PREFIX.$accountId;
    }

    /**
     * Get current sync progress for an account.
     */
    public static function getSyncProgress(int $accountId): ?array
    {
        return Cache::get(self::getProgressCacheKey($accountId));
    }

    /**
     * Initialize sync progress tracking.
     */
    private function initProgress(PlatformAccount $account, int $totalOrders): void
    {
        Cache::put(self::getProgressCacheKey($account->id), [
            'status' => 'syncing',
            'total' => $totalOrders,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'current_order' => null,
            'started_at' => now()->toIso8601String(),
            'percentage' => 0,
        ], self::PROGRESS_TTL);
    }

    /**
     * Update sync progress.
     */
    private function updateProgress(PlatformAccount $account, array $updates): void
    {
        $progress = Cache::get(self::getProgressCacheKey($account->id), []);
        $progress = array_merge($progress, $updates);

        // Calculate percentage
        if (isset($progress['total']) && $progress['total'] > 0) {
            $progress['percentage'] = min(100, round(($progress['processed'] / $progress['total']) * 100));
        }

        Cache::put(self::getProgressCacheKey($account->id), $progress, self::PROGRESS_TTL);
    }

    /**
     * Mark sync as complete.
     */
    private function completeProgress(PlatformAccount $account, array $result): void
    {
        Cache::put(self::getProgressCacheKey($account->id), [
            'status' => 'completed',
            'total' => $result['synced'] + $result['failed'],
            'processed' => $result['synced'] + $result['failed'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'current_order' => null,
            'completed_at' => now()->toIso8601String(),
            'percentage' => 100,
        ], 60); // Keep completed status for 1 minute
    }

    /**
     * Clear sync progress (on error or cancellation).
     */
    public static function clearProgress(int $accountId): void
    {
        Cache::forget(self::getProgressCacheKey($accountId));
    }

    /**
     * Sync orders from TikTok Shop for a platform account.
     *
     * @param  array{
     *     create_time_from?: int,
     *     create_time_to?: int,
     *     update_time_from?: int,
     *     update_time_to?: int,
     *     order_status?: string,
     *     page_size?: int
     * }  $filters
     * @return array{synced: int, created: int, updated: int, failed: int, errors: array}
     */
    public function syncOrders(PlatformAccount $account, array $filters = []): array
    {
        $result = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Check if token needs refresh (only if expiring within 1 hour)
        if ($this->authService->needsTokenRefresh($account)) {
            Log::info('[TikTok Order Sync] Refreshing token before sync', [
                'account_id' => $account->id,
            ]);

            if (! $this->authService->refreshToken($account)) {
                // Token refresh failed, but the current token might still be valid
                // Check if we have a credential that's not yet expired
                $credential = $account->credentials()
                    ->where('credential_type', 'oauth_token')
                    ->where('is_active', true)
                    ->first();

                if (! $credential || $credential->isExpired()) {
                    // No valid credential - can't proceed
                    $result['errors'][] = 'Failed to refresh access token and no valid token available';

                    return $result;
                }

                // Token not expired yet, try to proceed with existing token
                Log::warning('[TikTok Order Sync] Token refresh failed but existing token still valid, proceeding', [
                    'account_id' => $account->id,
                    'expires_at' => $credential->expires_at?->toIso8601String(),
                ]);
            }
        }

        try {
            $client = $this->clientFactory->createClientForAccount($account);
            $orders = $this->fetchOrders($client->Order, $filters);

            $totalOrders = count($orders);

            Log::info('[TikTok Order Sync] Fetched orders from API', [
                'account_id' => $account->id,
                'order_count' => $totalOrders,
            ]);

            // Initialize progress tracking
            $this->initProgress($account, $totalOrders);

            foreach ($orders as $index => $orderData) {
                $maxRetries = 3;
                $retryDelay = 100; // milliseconds

                // Update current order being processed
                $this->updateProgress($account, [
                    'current_order' => $orderData['id'] ?? 'unknown',
                    'processed' => $index,
                ]);

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $isNew = $this->syncSingleOrder($account, $orderData);
                        $result['synced']++;

                        if ($isNew) {
                            $result['created']++;
                        } else {
                            $result['updated']++;
                        }

                        // Update progress after successful sync
                        $this->updateProgress($account, [
                            'processed' => $index + 1,
                            'created' => $result['created'],
                            'updated' => $result['updated'],
                        ]);

                        break; // Success, exit retry loop
                    } catch (Exception $e) {
                        $isLockError = str_contains($e->getMessage(), 'database is locked') ||
                                       str_contains($e->getMessage(), 'SQLITE_BUSY');

                        if ($isLockError && $attempt < $maxRetries) {
                            Log::warning('[TikTok Order Sync] Database lock, retrying', [
                                'order_id' => $orderData['id'] ?? 'unknown',
                                'attempt' => $attempt,
                            ]);
                            usleep($retryDelay * 1000 * $attempt); // Exponential backoff

                            continue;
                        }

                        $result['failed']++;
                        $result['errors'][] = "Order {$orderData['id']}: ".$e->getMessage();

                        // Update progress with failure
                        $this->updateProgress($account, [
                            'processed' => $index + 1,
                            'failed' => $result['failed'],
                        ]);

                        Log::error('[TikTok Order Sync] Failed to sync order', [
                            'account_id' => $account->id,
                            'order_id' => $orderData['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                        break;
                    }
                }
            }

            // Mark sync as complete
            $this->completeProgress($account, $result);

            // Update last sync timestamp
            $account->update([
                'last_sync_at' => now(),
                'last_order_sync_at' => now(),
                'metadata' => array_merge($account->metadata ?? [], [
                    'last_order_sync_result' => $result,
                ]),
            ]);

            Log::info('[TikTok Order Sync] Sync completed', [
                'account_id' => $account->id,
                'result' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();

            Log::error('[TikTok Order Sync] Sync failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $result;
        }
    }

    /**
     * Fetch orders from TikTok API with pagination.
     *
     * @return array<array>
     */
    private function fetchOrders(Order $orderApi, array $filters = []): array
    {
        $allOrders = [];
        $pageSize = $filters['page_size'] ?? 50;
        $pageToken = null;

        // Build search parameters
        $searchParams = $this->buildSearchParams($filters);

        do {
            $query = ['page_size' => $pageSize];
            if ($pageToken) {
                $query['page_token'] = $pageToken;
            }

            $response = $orderApi->getOrderList($query, $searchParams);

            if (! isset($response['orders'])) {
                Log::warning('[TikTok Order Sync] No orders in response', [
                    'response_keys' => array_keys($response),
                ]);
                break;
            }

            $orders = $response['orders'];
            $allOrders = array_merge($allOrders, $orders);

            // Get next page token
            $pageToken = $response['next_page_token'] ?? null;

            Log::debug('[TikTok Order Sync] Fetched page', [
                'orders_in_page' => count($orders),
                'total_orders' => count($allOrders),
                'has_next_page' => ! empty($pageToken),
            ]);
        } while ($pageToken && count($allOrders) < 1000); // Limit to 1000 orders per sync

        return $allOrders;
    }

    /**
     * Build search parameters for the order list API.
     */
    private function buildSearchParams(array $filters): array
    {
        $params = [];

        // Time filters - default to last 7 days if not specified
        if (isset($filters['create_time_from'])) {
            $params['create_time_ge'] = $filters['create_time_from'];
        }
        if (isset($filters['create_time_to'])) {
            $params['create_time_lt'] = $filters['create_time_to'];
        }
        if (isset($filters['update_time_from'])) {
            $params['update_time_ge'] = $filters['update_time_from'];
        }
        if (isset($filters['update_time_to'])) {
            $params['update_time_lt'] = $filters['update_time_to'];
        }

        // If no time filters, default to last 7 days
        if (empty($params)) {
            $params['create_time_ge'] = now()->subDays(7)->timestamp;
            $params['create_time_lt'] = now()->timestamp;
        }

        // Order status filter
        if (isset($filters['order_status'])) {
            $params['order_status'] = $filters['order_status'];
        }

        return $params;
    }

    /**
     * Sync a single order from TikTok data.
     *
     * @return bool True if created, false if updated
     */
    public function syncSingleOrder(PlatformAccount $account, array $orderData): bool
    {
        $platformOrderId = $orderData['id'] ?? null;

        if (! $platformOrderId) {
            throw new Exception('Order data missing ID');
        }

        return DB::transaction(function () use ($account, $orderData, $platformOrderId) {
            // Check if order already exists
            $existingOrder = ProductOrder::where('platform_order_id', $platformOrderId)
                ->where('platform_account_id', $account->id)
                ->first();

            $isNew = $existingOrder === null;

            $orderAttributes = $this->mapOrderData($account, $orderData);

            if ($existingOrder) {
                // Preserve manually edited phone number
                // If the existing phone differs from what TikTok provides, it was manually edited
                $tiktokPhone = $orderAttributes['customer_phone'] ?? null;
                $existingPhone = $existingOrder->customer_phone;

                if ($existingPhone && $existingPhone !== $tiktokPhone) {
                    // Phone was manually edited, don't overwrite it
                    unset($orderAttributes['customer_phone']);

                    Log::debug('[TikTok Order Sync] Preserving manually edited phone', [
                        'order_id' => $existingOrder->id,
                        'existing_phone' => $existingPhone,
                        'tiktok_phone' => $tiktokPhone,
                    ]);
                }

                // Update existing order
                $existingOrder->update($orderAttributes);
                $order = $existingOrder;

                Log::debug('[TikTok Order Sync] Updated existing order', [
                    'order_id' => $order->id,
                    'platform_order_id' => $platformOrderId,
                ]);
            } else {
                // Create new order
                $orderAttributes['order_number'] = ProductOrder::generateOrderNumber();
                $order = ProductOrder::create($orderAttributes);

                Log::debug('[TikTok Order Sync] Created new order', [
                    'order_id' => $order->id,
                    'platform_order_id' => $platformOrderId,
                ]);
            }

            // Sync order items
            $this->syncOrderItems($order, $orderData['line_items'] ?? []);

            // Add system note about sync
            if ($isNew) {
                $order->addSystemNote('Order imported from TikTok Shop');
            } else {
                $order->addSystemNote('Order updated from TikTok Shop sync');
            }

            return $isNew;
        });
    }

    /**
     * Map TikTok order data to ProductOrder attributes.
     */
    private function mapOrderData(PlatformAccount $account, array $data): array
    {
        // Map TikTok status to our status
        $status = $this->mapOrderStatus($data['status'] ?? 'UNKNOWN');

        // Parse payment info
        $payment = $data['payment'] ?? [];

        // Parse recipient address
        $recipientAddress = $data['recipient_address'] ?? [];
        $shippingAddress = $this->mapShippingAddress($recipientAddress);

        // Calculate totals
        $subtotal = $this->parseAmount($payment['sub_total'] ?? $payment['subtotal'] ?? '0');
        $shippingCost = $this->parseAmount($payment['shipping_fee'] ?? '0');
        $totalAmount = $this->parseAmount($payment['total_amount'] ?? $payment['grand_total'] ?? '0');

        // Parse timestamps
        $orderDate = isset($data['create_time'])
            ? Carbon::createFromTimestamp($data['create_time'])
            : now();
        $paidTime = isset($data['paid_time'])
            ? Carbon::createFromTimestamp($data['paid_time'])
            : null;
        $rtsTime = isset($data['rts_time'])
            ? Carbon::createFromTimestamp($data['rts_time'])
            : null;

        return [
            'platform_id' => $account->platform_id,
            'platform_account_id' => $account->id,
            'platform_order_id' => $data['id'],
            'platform_order_number' => $data['id'],
            'status' => $status,
            'order_type' => 'product',
            'source' => 'tiktok_shop',
            'source_reference' => $data['id'],
            'currency' => $account->currency ?? 'MYR',
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total_amount' => $totalAmount,
            'order_date' => $orderDate,
            'paid_time' => $paidTime,
            'rts_time' => $rtsTime,

            // Customer info
            'buyer_username' => $data['buyer_uid'] ?? null,
            'customer_name' => $recipientAddress['name'] ?? null,
            'customer_phone' => $this->maskPhoneNumber($recipientAddress['phone_number'] ?? null),
            'shipping_address' => $shippingAddress,

            // Discounts
            'sku_platform_discount' => $this->parseAmount($payment['platform_discount'] ?? '0'),
            'sku_seller_discount' => $this->parseAmount($payment['seller_discount'] ?? '0'),
            'shipping_fee_seller_discount' => $this->parseAmount($payment['shipping_fee_seller_discount'] ?? '0'),
            'shipping_fee_platform_discount' => $this->parseAmount($payment['shipping_fee_platform_discount'] ?? '0'),
            'original_shipping_fee' => $this->parseAmount($payment['original_shipping_fee'] ?? '0'),

            // Fulfillment info
            'fulfillment_type' => $data['fulfillment_type'] ?? null,
            'warehouse_name' => $data['warehouse_id'] ?? null,
            'delivery_option' => $data['delivery_option_name'] ?? null,
            'shipping_provider' => $data['shipping_provider'] ?? null,
            'tracking_id' => $data['tracking_number'] ?? null,
            'payment_method' => $payment['payment_method_name'] ?? $payment['payment_method'] ?? null,

            // Messages
            'buyer_message' => $data['buyer_message'] ?? null,
            'seller_note' => $data['seller_note'] ?? null,

            // Cancellation
            'cancel_by' => $data['cancel_user'] ?? null,
            'cancel_reason' => $data['cancel_reason'] ?? null,
            'cancelled_at' => isset($data['cancel_time'])
                ? Carbon::createFromTimestamp($data['cancel_time'])
                : null,

            // Shipped/delivered timestamps
            'shipped_at' => isset($data['ship_time'])
                ? Carbon::createFromTimestamp($data['ship_time'])
                : null,
            'delivered_at' => isset($data['delivery_time'])
                ? Carbon::createFromTimestamp($data['delivery_time'])
                : null,

            // Store raw platform data
            'platform_data' => $data,
        ];
    }

    /**
     * Map TikTok order status to our status.
     */
    private function mapOrderStatus(string $tiktokStatus): string
    {
        return match ($tiktokStatus) {
            Order::STATUS_UNPAID => 'pending',
            Order::STATUS_AWAITING_SHIPMENT => 'confirmed',
            Order::STATUS_AWAITING_COLLECTION => 'processing',
            Order::STATUS_PARTIALLY_SHIPPING => 'processing',
            Order::STATUS_IN_TRANSIT => 'shipped',
            Order::STATUS_DELIVERED => 'delivered',
            Order::STATUS_COMPLETED => 'completed',
            Order::STATUS_CANCELLED => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Map recipient address to our format.
     */
    private function mapShippingAddress(array $address): array
    {
        return [
            'name' => $address['name'] ?? null,
            'phone' => $this->maskPhoneNumber($address['phone_number'] ?? null),
            'address_line1' => $address['address_detail'] ?? $address['full_address'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? $address['region'] ?? null,
            'postal_code' => $address['postal_code'] ?? $address['zipcode'] ?? null,
            'country' => $address['region_code'] ?? null,
            'district_info' => $address['district_info'] ?? [],
        ];
    }

    /**
     * Sync order items from TikTok data.
     */
    private function syncOrderItems(ProductOrder $order, array $lineItems): void
    {
        // Get existing item platform SKUs
        $existingSkus = $order->items->pluck('platform_sku')->filter()->toArray();
        $newSkus = [];

        foreach ($lineItems as $item) {
            $platformSku = $item['sku_id'] ?? $item['id'] ?? uniqid('tiktok_item_');
            $newSkus[] = $platformSku;

            $itemAttributes = [
                'order_id' => $order->id,
                'platform_sku' => $platformSku,
                'platform_product_name' => $item['product_name'] ?? $item['sku_name'] ?? 'Unknown Product',
                'platform_variation_name' => $item['sku_name'] ?? null,
                'product_name' => $item['product_name'] ?? $item['sku_name'] ?? 'Unknown Product',
                'variant_name' => $item['sku_name'] ?? null,
                'sku' => $item['seller_sku'] ?? $platformSku,
                'quantity_ordered' => (int) ($item['quantity'] ?? 1),
                'unit_price' => $this->parseAmount($item['sale_price'] ?? $item['sku_sale_price'] ?? '0'),
                'total_price' => $this->parseAmount($item['sku_subtotal_after_discount'] ?? $item['item_total'] ?? '0'),
                'unit_original_price' => $this->parseAmount($item['original_price'] ?? $item['sku_original_price'] ?? '0'),
                'subtotal_before_discount' => $this->parseAmount($item['sku_subtotal_before_discount'] ?? '0'),
                'platform_discount' => $this->parseAmount($item['sku_platform_discount_total'] ?? '0'),
                'seller_discount' => $this->parseAmount($item['sku_seller_discount'] ?? '0'),
                'item_metadata' => [
                    'tiktok_product_id' => $item['product_id'] ?? null,
                    'tiktok_sku_id' => $item['sku_id'] ?? null,
                    'sku_image' => $item['sku_image'] ?? null,
                    'sku_type' => $item['sku_type'] ?? null,
                ],
            ];

            // Update or create item
            $existingItem = $order->items()
                ->where('platform_sku', $platformSku)
                ->first();

            if ($existingItem) {
                $existingItem->update($itemAttributes);
            } else {
                ProductOrderItem::create($itemAttributes);
            }
        }

        // Remove items that no longer exist in TikTok (careful with this)
        // Only remove if we have items to compare
        if (! empty($newSkus) && ! empty($existingSkus)) {
            $skusToRemove = array_diff($existingSkus, $newSkus);
            if (! empty($skusToRemove)) {
                $order->items()->whereIn('platform_sku', $skusToRemove)->delete();
            }
        }
    }

    /**
     * Get order details by IDs from TikTok API.
     *
     * @param  array<string>  $orderIds
     * @return array<array>
     */
    public function getOrderDetails(PlatformAccount $account, array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        try {
            $client = $this->clientFactory->createClientForAccount($account);
            $response = $client->Order->getOrderDetail($orderIds);

            return $response['orders'] ?? [];
        } catch (Exception $e) {
            Log::error('[TikTok Order Sync] Failed to get order details', [
                'account_id' => $account->id,
                'order_ids' => $orderIds,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh a single order from TikTok.
     */
    public function refreshOrder(ProductOrder $order): bool
    {
        if (! $order->platform_order_id || ! $order->platformAccount) {
            throw new Exception('Order is not a TikTok platform order');
        }

        $account = $order->platformAccount;
        $orderDetails = $this->getOrderDetails($account, [$order->platform_order_id]);

        if (empty($orderDetails)) {
            throw new Exception('Order not found in TikTok');
        }

        $this->syncSingleOrder($account, $orderDetails[0]);

        return true;
    }

    /**
     * Parse amount from TikTok (can be string or array with currency).
     */
    private function parseAmount(string|array|null $amount): float
    {
        if ($amount === null) {
            return 0.0;
        }

        if (is_array($amount)) {
            // TikTok sometimes returns {amount: "10.00", currency: "MYR"}
            $amount = $amount['amount'] ?? $amount['value'] ?? '0';
        }

        return (float) $amount;
    }

    /**
     * Mask phone number for privacy (keep last 4 digits).
     * Also normalizes TikTok's phone format from (+60)111*****44 to 60111*****44
     */
    private function maskPhoneNumber(?string $phone): ?string
    {
        if (! $phone || strlen($phone) < 4) {
            return $phone;
        }

        // TikTok may already mask the phone number
        if (str_contains($phone, '*')) {
            // Clean up TikTok's format: (+60)111*****44 -> 60111*****44
            // Remove parentheses and plus sign
            $phone = str_replace(['(', ')', '+'], '', $phone);

            return $phone;
        }

        $length = strlen($phone);
        $masked = str_repeat('*', $length - 4).substr($phone, -4);

        return $masked;
    }

    /**
     * Get sync statistics for an account.
     */
    public function getSyncStatistics(PlatformAccount $account): array
    {
        $orders = ProductOrder::where('platform_account_id', $account->id);

        return [
            'total_orders' => $orders->count(),
            'pending_orders' => (clone $orders)->where('status', 'pending')->count(),
            'confirmed_orders' => (clone $orders)->where('status', 'confirmed')->count(),
            'shipped_orders' => (clone $orders)->where('status', 'shipped')->count(),
            'delivered_orders' => (clone $orders)->where('status', 'delivered')->count(),
            'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'last_sync_at' => $account->last_order_sync_at,
            'last_sync_result' => $account->metadata['last_order_sync_result'] ?? null,
        ];
    }
}
