<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PendingPlatformProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'platform_product_id',
        'platform_sku',
        'name',
        'description',
        'price',
        'original_price',
        'currency',
        'category_id',
        'category_name',
        'brand',
        'main_image_url',
        'images',
        'variants',
        'quantity',
        'suggested_product_id',
        'suggested_variant_id',
        'match_confidence',
        'match_reason',
        'status',
        'reviewed_at',
        'reviewed_by',
        'raw_data',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'variants' => 'array',
            'raw_data' => 'array',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'match_confidence' => 'decimal:2',
            'fetched_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // Relationships
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function suggestedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'suggested_product_id');
    }

    public function suggestedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'suggested_variant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->where('status', 'linked');
    }

    public function scopeCreated(Builder $query): Builder
    {
        return $query->where('status', 'created');
    }

    public function scopeIgnored(Builder $query): Builder
    {
        return $query->where('status', 'ignored');
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('platform_account_id', $accountId);
    }

    public function scopeWithSuggestions(Builder $query): Builder
    {
        return $query->whereNotNull('suggested_product_id');
    }

    public function scopeWithoutSuggestions(Builder $query): Builder
    {
        return $query->whereNull('suggested_product_id');
    }

    public function scopeHighConfidence(Builder $query, float $threshold = 90): Builder
    {
        return $query->where('match_confidence', '>=', $threshold);
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isLinked(): bool
    {
        return $this->status === 'linked';
    }

    public function isIgnored(): bool
    {
        return $this->status === 'ignored';
    }

    public function hasSuggestion(): bool
    {
        return $this->suggested_product_id !== null;
    }

    public function hasHighConfidenceSuggestion(float $threshold = 90): bool
    {
        return $this->hasSuggestion() && ($this->match_confidence ?? 0) >= $threshold;
    }

    // Display helpers
    public function getFormattedPrice(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        return $this->currency.' '.number_format((float) $this->price, 2);
    }

    public function getVariantCount(): int
    {
        return is_array($this->variants) ? count($this->variants) : 0;
    }

    public function hasVariants(): bool
    {
        return $this->getVariantCount() > 0;
    }

    // Actions

    /**
     * Link this pending product to an existing internal product.
     */
    public function linkToProduct(Product $product, ?ProductVariant $variant = null, ?int $userId = null): PlatformSkuMapping
    {
        return DB::transaction(function () use ($product, $variant, $userId) {
            // Create the platform SKU mapping
            $mapping = PlatformSkuMapping::updateOrCreate(
                [
                    'platform_id' => $this->platform_id,
                    'platform_account_id' => $this->platform_account_id,
                    'platform_sku' => $this->platform_sku ?? $this->platform_product_id,
                ],
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'platform_product_name' => $this->name,
                    'is_active' => true,
                    'mapping_metadata' => [
                        'platform_product_id' => $this->platform_product_id,
                        'linked_from_pending' => true,
                        'linked_at' => now()->toIso8601String(),
                        'match_confidence' => $this->match_confidence,
                        'match_reason' => $this->match_reason,
                    ],
                    'last_used_at' => now(),
                ]
            );

            // Update this pending product status
            $this->update([
                'status' => 'linked',
                'reviewed_at' => now(),
                'reviewed_by' => $userId,
            ]);

            return $mapping;
        });
    }

    /**
     * Create a new internal product from this pending product data.
     */
    public function createAsNewProduct(?int $userId = null): Product
    {
        return DB::transaction(function () use ($userId) {
            // Create the new product
            $product = Product::create([
                'name' => $this->name,
                'description' => $this->description,
                'sku' => $this->generateInternalSku(),
                'base_price' => $this->price ?? 0,
                'cost_price' => null,
                'status' => 'draft', // Start as draft for review
                'type' => $this->hasVariants() ? 'variable' : 'simple',
                'track_quantity' => true,
            ]);

            // Create variants if exists
            if ($this->hasVariants()) {
                foreach ($this->variants as $index => $variantData) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $variantData['name'] ?? "Variant {$index}",
                        'sku' => $this->generateVariantSku($product, $index),
                        'price' => $variantData['price'] ?? $this->price,
                        'attributes' => $variantData['attributes'] ?? [],
                        'is_active' => true,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Create the platform SKU mapping
            PlatformSkuMapping::create([
                'platform_id' => $this->platform_id,
                'platform_account_id' => $this->platform_account_id,
                'product_id' => $product->id,
                'platform_sku' => $this->platform_sku ?? $this->platform_product_id,
                'platform_product_name' => $this->name,
                'is_active' => true,
                'mapping_metadata' => [
                    'platform_product_id' => $this->platform_product_id,
                    'created_from_pending' => true,
                    'created_at' => now()->toIso8601String(),
                ],
                'last_used_at' => now(),
            ]);

            // Update this pending product status
            $this->update([
                'status' => 'created',
                'reviewed_at' => now(),
                'reviewed_by' => $userId,
            ]);

            return $product;
        });
    }

    /**
     * Mark this pending product as ignored.
     */
    public function ignore(?int $userId = null): void
    {
        $this->update([
            'status' => 'ignored',
            'reviewed_at' => now(),
            'reviewed_by' => $userId,
        ]);
    }

    /**
     * Reset this pending product back to pending status.
     */
    public function resetToPending(): void
    {
        $this->update([
            'status' => 'pending',
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
    }

    /**
     * Update the match suggestion.
     */
    public function updateSuggestion(?Product $product, ?ProductVariant $variant, ?float $confidence, ?string $reason): void
    {
        $this->update([
            'suggested_product_id' => $product?->id,
            'suggested_variant_id' => $variant?->id,
            'match_confidence' => $confidence,
            'match_reason' => $reason,
        ]);
    }

    // Static methods

    /**
     * Find or create a pending product from TikTok data.
     */
    public static function updateOrCreateFromTikTok(
        PlatformAccount $account,
        array $tiktokProduct,
        ?array $suggestion = null
    ): self {
        $data = [
            'platform_id' => $account->platform_id,
            'platform_account_id' => $account->id,
            'platform_product_id' => $tiktokProduct['id'] ?? $tiktokProduct['product_id'],
            'platform_sku' => $tiktokProduct['skus'][0]['seller_sku'] ?? null,
            'name' => $tiktokProduct['title'] ?? $tiktokProduct['product_name'] ?? $tiktokProduct['name'] ?? 'Unknown Product',
            'description' => $tiktokProduct['description'] ?? null,
            'price' => static::extractPrice($tiktokProduct),
            'original_price' => static::extractOriginalPrice($tiktokProduct),
            'currency' => $tiktokProduct['skus'][0]['price']['currency'] ?? $tiktokProduct['currency'] ?? 'MYR',
            'category_id' => $tiktokProduct['category_id'] ?? null,
            'category_name' => $tiktokProduct['category_name'] ?? null,
            'brand' => $tiktokProduct['brand']['name'] ?? null,
            'main_image_url' => static::extractMainImage($tiktokProduct),
            'images' => static::extractImages($tiktokProduct),
            'variants' => static::extractVariants($tiktokProduct),
            'quantity' => static::extractQuantity($tiktokProduct),
            'raw_data' => $tiktokProduct,
            'fetched_at' => now(),
        ];

        // Add suggestion if provided
        if ($suggestion) {
            $data['suggested_product_id'] = $suggestion['product_id'] ?? null;
            $data['suggested_variant_id'] = $suggestion['variant_id'] ?? null;
            $data['match_confidence'] = $suggestion['confidence'] ?? null;
            $data['match_reason'] = $suggestion['reason'] ?? null;
        }

        return static::updateOrCreate(
            [
                'platform_account_id' => $account->id,
                'platform_product_id' => $data['platform_product_id'],
            ],
            $data
        );
    }

    /**
     * Get count statistics for an account.
     */
    public static function getStatsForAccount(int $accountId): array
    {
        return [
            'pending' => static::forAccount($accountId)->pending()->count(),
            'linked' => static::forAccount($accountId)->linked()->count(),
            'created' => static::forAccount($accountId)->created()->count(),
            'ignored' => static::forAccount($accountId)->ignored()->count(),
            'with_suggestions' => static::forAccount($accountId)->pending()->withSuggestions()->count(),
            'high_confidence' => static::forAccount($accountId)->pending()->highConfidence()->count(),
        ];
    }

    // Private helper methods

    private function generateInternalSku(): string
    {
        $base = 'TT-'.strtoupper(substr(md5($this->platform_product_id), 0, 8));
        $counter = 1;
        $sku = $base;

        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$counter;
            $counter++;
        }

        return $sku;
    }

    private function generateVariantSku(Product $product, int $index): string
    {
        return $product->sku.'-V'.($index + 1);
    }

    private static function extractPrice(array $tiktokProduct): ?float
    {
        // TikTok API can have price in different locations
        if (isset($tiktokProduct['skus'][0]['price']['tax_exclusive_price'])) {
            return (float) $tiktokProduct['skus'][0]['price']['tax_exclusive_price'];
        }
        if (isset($tiktokProduct['skus'][0]['price']['sale_price'])) {
            return (float) $tiktokProduct['skus'][0]['price']['sale_price'];
        }
        if (isset($tiktokProduct['price'])) {
            return (float) $tiktokProduct['price'];
        }
        if (isset($tiktokProduct['sale_price'])) {
            return (float) $tiktokProduct['sale_price'];
        }

        return null;
    }

    private static function extractOriginalPrice(array $tiktokProduct): ?float
    {
        if (isset($tiktokProduct['skus'][0]['price']['original_price'])) {
            return (float) $tiktokProduct['skus'][0]['price']['original_price'];
        }
        if (isset($tiktokProduct['original_price'])) {
            return (float) $tiktokProduct['original_price'];
        }

        return null;
    }

    private static function extractMainImage(array $tiktokProduct): ?string
    {
        if (isset($tiktokProduct['main_images'][0]['url'])) {
            return $tiktokProduct['main_images'][0]['url'];
        }
        if (isset($tiktokProduct['images'][0])) {
            return $tiktokProduct['images'][0];
        }

        return null;
    }

    private static function extractImages(array $tiktokProduct): array
    {
        if (isset($tiktokProduct['main_images'])) {
            return array_map(fn ($img) => $img['url'] ?? $img, $tiktokProduct['main_images']);
        }
        if (isset($tiktokProduct['images'])) {
            return $tiktokProduct['images'];
        }

        return [];
    }

    private static function extractVariants(array $tiktokProduct): array
    {
        if (! isset($tiktokProduct['skus']) || count($tiktokProduct['skus']) <= 1) {
            return [];
        }

        return array_map(function ($sku) {
            return [
                'sku' => $sku['seller_sku'] ?? $sku['id'] ?? null,
                'name' => $sku['sales_attributes'][0]['value_name'] ?? null,
                'price' => $sku['price']['sale_price'] ?? null,
                'quantity' => $sku['inventory'][0]['quantity'] ?? 0,
                'attributes' => array_map(
                    fn ($attr) => ['name' => $attr['name'] ?? '', 'value' => $attr['value_name'] ?? ''],
                    $sku['sales_attributes'] ?? []
                ),
            ];
        }, $tiktokProduct['skus']);
    }

    private static function extractQuantity(array $tiktokProduct): int
    {
        if (isset($tiktokProduct['skus'])) {
            return array_sum(array_map(
                fn ($sku) => $sku['inventory'][0]['quantity'] ?? 0,
                $tiktokProduct['skus']
            ));
        }
        if (isset($tiktokProduct['quantity'])) {
            return (int) $tiktokProduct['quantity'];
        }

        return 0;
    }
}
