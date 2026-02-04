<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Models\PendingPlatformProduct;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TikTokProductSyncService
{
    private const PROGRESS_CACHE_PREFIX = 'tiktok_product_sync_progress_';

    private const PROGRESS_TTL = 600; // 10 minutes

    public function __construct(
        private TikTokClientFactory $clientFactory,
        private ProductMatchingService $matchingService
    ) {}

    /**
     * Sync products from TikTok Shop.
     */
    public function syncProducts(PlatformAccount $account, array $options = []): ProductSyncResult
    {
        Log::info('[TikTok Product Sync] Starting sync', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'options' => $options,
        ]);

        $result = new ProductSyncResult;

        try {
            // Fetch products from TikTok
            $products = $this->fetchProducts($account, $options);
            $result->total = count($products);

            Log::info('[TikTok Product Sync] Fetched products', [
                'account_id' => $account->id,
                'count' => $result->total,
            ]);

            // Initialize progress
            $this->initProgress($account, $result->total);

            // Process each product
            foreach ($products as $index => $tiktokProduct) {
                try {
                    $processResult = $this->processProduct($account, $tiktokProduct);

                    match ($processResult) {
                        'auto_linked' => $result->autoLinked++,
                        'queued' => $result->queuedForReview++,
                        'already_linked' => $result->alreadyLinked++,
                        'skipped' => $result->skipped++,
                        default => null,
                    };

                    // Update progress
                    $this->updateProgress($account, $index + 1, $result);
                } catch (Exception $e) {
                    $result->failed++;
                    $result->errors[] = sprintf(
                        'Product %s: %s',
                        $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? 'unknown',
                        $e->getMessage()
                    );

                    Log::error('[TikTok Product Sync] Failed to process product', [
                        'account_id' => $account->id,
                        'product_id' => $tiktokProduct['id'] ?? $tiktokProduct['product_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Complete progress
            $this->completeProgress($account, $result);

            // Update account sync timestamp
            $account->update([
                'last_product_sync_at' => now(),
                'metadata' => array_merge($account->metadata ?? [], [
                    'last_product_sync_result' => [
                        'total' => $result->total,
                        'auto_linked' => $result->autoLinked,
                        'queued' => $result->queuedForReview,
                        'already_linked' => $result->alreadyLinked,
                        'failed' => $result->failed,
                        'synced_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            Log::info('[TikTok Product Sync] Sync completed', [
                'account_id' => $account->id,
                'result' => $result->toArray(),
            ]);
        } catch (Exception $e) {
            Log::error('[TikTok Product Sync] Sync failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            $result->errors[] = $e->getMessage();

            throw $e;
        }

        return $result;
    }

    /**
     * Fetch all products from TikTok API with pagination.
     */
    public function fetchProducts(PlatformAccount $account, array $options = []): Collection
    {
        $client = $this->clientFactory->createClientForAccount($account);
        $products = collect();

        $pageSize = $options['page_size'] ?? 50;
        $pageToken = null;
        $maxPages = $options['max_pages'] ?? 20; // Safety limit
        $page = 0;

        do {
            $page++;

            try {
                $params = [
                    'page_size' => $pageSize,
                ];

                if ($pageToken) {
                    $params['page_token'] = $pageToken;
                }

                // Add status filter if specified
                if (isset($options['status'])) {
                    $params['status'] = $options['status'];
                }

                $response = $client->Product->searchProducts($params);

                if (! isset($response['products'])) {
                    break;
                }

                $pageProducts = $response['products'];
                $products = $products->merge($pageProducts);

                // Get next page token
                $pageToken = $response['next_page_token'] ?? null;

                Log::debug('[TikTok Product Sync] Fetched page', [
                    'account_id' => $account->id,
                    'page' => $page,
                    'products_in_page' => count($pageProducts),
                    'total_so_far' => $products->count(),
                    'has_more' => ! empty($pageToken),
                ]);
            } catch (Exception $e) {
                Log::error('[TikTok Product Sync] Failed to fetch products page', [
                    'account_id' => $account->id,
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        } while ($pageToken && $page < $maxPages);

        return $products;
    }

    /**
     * Process a single product through the matching engine.
     */
    public function processProduct(PlatformAccount $account, array $tiktokProduct): string
    {
        $productId = $tiktokProduct['id'] ?? $tiktokProduct['product_id'];

        // Check if already linked (existing mapping)
        $existingMapping = PlatformSkuMapping::where('platform_account_id', $account->id)
            ->where(function ($query) use ($tiktokProduct) {
                $query->whereJsonContains('mapping_metadata->platform_product_id', $tiktokProduct['id'] ?? $tiktokProduct['product_id'])
                    ->orWhere('platform_sku', $tiktokProduct['skus'][0]['seller_sku'] ?? null);
            })
            ->where('is_active', true)
            ->exists();

        if ($existingMapping) {
            // Update the pending product if it exists but is already linked
            PendingPlatformProduct::where('platform_account_id', $account->id)
                ->where('platform_product_id', $productId)
                ->update(['status' => 'linked']);

            return 'already_linked';
        }

        // Try to find a match
        $match = $this->matchingService->findMatch($tiktokProduct, $account);

        if ($match && $this->matchingService->shouldAutoLink($match)) {
            // Auto-link the product
            return $this->autoLinkProduct($account, $tiktokProduct, $match);
        }

        // Queue for review with suggestion if available
        $suggestion = $match && $this->matchingService->shouldSuggest($match)
            ? $match->toArray()
            : null;

        $this->queueForReview($account, $tiktokProduct, $suggestion);

        return 'queued';
    }

    /**
     * Auto-link a product to an internal product.
     */
    private function autoLinkProduct(
        PlatformAccount $account,
        array $tiktokProduct,
        MatchResult $match
    ): string {
        $productId = $tiktokProduct['id'] ?? $tiktokProduct['product_id'];
        $platformSku = $tiktokProduct['skus'][0]['seller_sku'] ?? $productId;

        DB::transaction(function () use ($account, $tiktokProduct, $match, $productId, $platformSku) {
            // Create the mapping
            PlatformSkuMapping::updateOrCreate(
                [
                    'platform_id' => $account->platform_id,
                    'platform_account_id' => $account->id,
                    'platform_sku' => $platformSku,
                ],
                [
                    'product_id' => $match->product?->id,
                    'product_variant_id' => $match->variant?->id,
                    'package_id' => $match->package?->id,
                    'platform_product_name' => $tiktokProduct['product_name'] ?? $tiktokProduct['name'] ?? null,
                    'is_active' => true,
                    'mapping_metadata' => [
                        'platform_product_id' => $productId,
                        'auto_linked' => true,
                        'linked_at' => now()->toIso8601String(),
                        'match_confidence' => $match->confidence,
                        'match_reason' => $match->matchReason,
                        'linked_type' => $match->isPackageMatch() ? 'package' : 'product',
                    ],
                    'last_used_at' => now(),
                ]
            );

            // Update pending product if it exists
            PendingPlatformProduct::where('platform_account_id', $account->id)
                ->where('platform_product_id', $productId)
                ->update([
                    'status' => 'linked',
                    'suggested_product_id' => $match->product?->id,
                    'suggested_variant_id' => $match->variant?->id,
                    'suggested_package_id' => $match->package?->id,
                    'match_confidence' => $match->confidence,
                    'match_reason' => $match->matchReason,
                    'reviewed_at' => now(),
                ]);
        });

        $linkedName = $match->isPackageMatch()
            ? $match->package->name
            : $match->product->name;

        Log::info('[TikTok Product Sync] Auto-linked product', [
            'account_id' => $account->id,
            'platform_product_id' => $productId,
            'linked_to' => $linkedName,
            'linked_type' => $match->isPackageMatch() ? 'package' : 'product',
            'confidence' => $match->confidence,
        ]);

        return 'auto_linked';
    }

    /**
     * Queue a product for manual review.
     */
    public function queueForReview(
        PlatformAccount $account,
        array $tiktokProduct,
        ?array $suggestion = null
    ): PendingPlatformProduct {
        return PendingPlatformProduct::updateOrCreateFromTikTok($account, $tiktokProduct, $suggestion);
    }

    /**
     * Get product details from TikTok API.
     */
    public function getProductDetails(PlatformAccount $account, string $productId): ?array
    {
        try {
            $client = $this->clientFactory->createClientForAccount($account);
            $response = $client->Product->getProductDetail($productId);

            return $response['data'] ?? null;
        } catch (Exception $e) {
            Log::error('[TikTok Product Sync] Failed to get product details', [
                'account_id' => $account->id,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get product categories from TikTok.
     */
    public function getCategories(PlatformAccount $account): array
    {
        try {
            $client = $this->clientFactory->createClientForAccount($account);
            $response = $client->Product->getCategories();

            return $response['data']['categories'] ?? [];
        } catch (Exception $e) {
            Log::error('[TikTok Product Sync] Failed to get categories', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    // Progress tracking methods

    /**
     * Get sync progress for an account.
     */
    public static function getSyncProgress(int $accountId): ?array
    {
        return Cache::get(self::getProgressCacheKey($accountId));
    }

    /**
     * Clear sync progress cache.
     */
    public static function clearProgress(int $accountId): void
    {
        Cache::forget(self::getProgressCacheKey($accountId));
    }

    private static function getProgressCacheKey(int $accountId): string
    {
        return self::PROGRESS_CACHE_PREFIX.$accountId;
    }

    private function initProgress(PlatformAccount $account, int $totalProducts): void
    {
        Cache::put(self::getProgressCacheKey($account->id), [
            'status' => 'syncing',
            'total' => $totalProducts,
            'processed' => 0,
            'auto_linked' => 0,
            'queued' => 0,
            'already_linked' => 0,
            'failed' => 0,
            'current_product' => null,
            'started_at' => now()->toIso8601String(),
            'percentage' => 0,
        ], self::PROGRESS_TTL);
    }

    private function updateProgress(PlatformAccount $account, int $processed, ProductSyncResult $result): void
    {
        $total = $result->total > 0 ? $result->total : 1;

        Cache::put(self::getProgressCacheKey($account->id), [
            'status' => 'syncing',
            'total' => $result->total,
            'processed' => $processed,
            'auto_linked' => $result->autoLinked,
            'queued' => $result->queuedForReview,
            'already_linked' => $result->alreadyLinked,
            'failed' => $result->failed,
            'current_product' => null,
            'started_at' => now()->toIso8601String(),
            'percentage' => round(($processed / $total) * 100),
        ], self::PROGRESS_TTL);
    }

    private function completeProgress(PlatformAccount $account, ProductSyncResult $result): void
    {
        Cache::put(self::getProgressCacheKey($account->id), [
            'status' => 'completed',
            'total' => $result->total,
            'processed' => $result->total,
            'auto_linked' => $result->autoLinked,
            'queued' => $result->queuedForReview,
            'already_linked' => $result->alreadyLinked,
            'failed' => $result->failed,
            'current_product' => null,
            'completed_at' => now()->toIso8601String(),
            'percentage' => 100,
        ], self::PROGRESS_TTL);
    }
}

/**
 * Data class representing product sync result.
 */
class ProductSyncResult
{
    public int $total = 0;

    public int $autoLinked = 0;

    public int $queuedForReview = 0;

    public int $alreadyLinked = 0;

    public int $skipped = 0;

    public int $failed = 0;

    public array $errors = [];

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'auto_linked' => $this->autoLinked,
            'queued_for_review' => $this->queuedForReview,
            'already_linked' => $this->alreadyLinked,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
