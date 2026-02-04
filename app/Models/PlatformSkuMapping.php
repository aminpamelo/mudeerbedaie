<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSkuMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'product_id',
        'product_variant_id',
        'package_id',
        'platform_sku',
        'platform_product_name',
        'platform_variation_name',
        'is_active',
        'mapping_metadata',
        'last_used_at',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mapping_metadata' => 'array',
            'last_used_at' => 'datetime',
            'usage_count' => 'integer',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    // Helper methods

    /**
     * Get the target entity (Product, ProductVariant, or Package).
     */
    public function getTarget(): Product|ProductVariant|Package
    {
        if ($this->package_id) {
            return $this->package;
        }

        return $this->product_variant_id ? $this->productVariant : $this->product;
    }

    /**
     * @deprecated Use getTarget() instead
     */
    public function getTargetProduct(): Product|ProductVariant
    {
        return $this->product_variant_id ? $this->productVariant : $this->product;
    }

    public function isPackageMapping(): bool
    {
        return $this->package_id !== null;
    }

    public function isProductMapping(): bool
    {
        return $this->product_id !== null && $this->package_id === null;
    }

    public function getDisplayName(): string
    {
        if ($this->isPackageMapping()) {
            return $this->platform_product_name ?: ($this->package?->name ?? 'Unknown Package');
        }

        $productName = $this->platform_product_name ?: ($this->product?->name ?? 'Unknown Product');
        $variationName = $this->platform_variation_name;

        return $variationName ? "{$productName} - {$variationName}" : $productName;
    }

    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByPlatform(Builder $query, int $platformId): Builder
    {
        return $query->where('platform_id', $platformId);
    }

    public function scopeByPlatformAccount(Builder $query, int $platformAccountId): Builder
    {
        return $query->where('platform_account_id', $platformAccountId);
    }

    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('platform_sku', $sku);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByPackage(Builder $query, int $packageId): Builder
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeRecentlyUsed(Builder $query): Builder
    {
        return $query->orderBy('last_used_at', 'desc');
    }

    public function scopeMostUsed(Builder $query): Builder
    {
        return $query->orderBy('usage_count', 'desc');
    }

    // Static methods for finding mappings
    public static function findMapping(int $platformId, ?int $platformAccountId, string $platformSku): ?self
    {
        return static::where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where('platform_sku', $platformSku)
            ->where('is_active', true)
            ->with(['product', 'productVariant', 'package'])
            ->first();
    }

    public static function createMapping(array $data): self
    {
        return static::create($data);
    }

    public static function getUnmappedSkus(int $platformId, ?int $platformAccountId, array $skus): array
    {
        $mappedSkus = static::where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereIn('platform_sku', $skus)
            ->where('is_active', true)
            ->pluck('platform_sku')
            ->toArray();

        return array_diff($skus, $mappedSkus);
    }
}
