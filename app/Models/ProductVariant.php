<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'attributes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'attributes' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    public function platformSkuMappings(): HasMany
    {
        return $this->hasMany(PlatformSkuMapping::class);
    }

    public function platformOrderItems(): HasMany
    {
        return $this->hasMany(PlatformOrderItem::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getPriceAttribute($value): float
    {
        return $value ?? $this->product->base_price;
    }

    public function getCostPriceAttribute($value): float
    {
        return $value ?? $this->product->cost_price;
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->price ?? $this->product->base_price;
    }

    public function getEffectiveCostPriceAttribute(): float
    {
        return $this->cost_price ?? $this->product->cost_price;
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'RM '.number_format($this->effective_price, 2);
    }

    public function getFormattedCostPriceAttribute(): string
    {
        return 'RM '.number_format($this->effective_cost_price, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->product->name.' - '.$this->name;
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stockLevels()->sum('available_quantity');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByAttribute($query, $attributeName, $attributeValue)
    {
        return $query->whereJsonContains('attributes', [$attributeName => $attributeValue]);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Stock management methods for order system
    public function checkStockAvailability(int $quantity, ?int $warehouseId = null): bool
    {
        if (! $this->product->shouldTrackQuantity()) {
            return true;
        }

        $query = $this->stockLevels();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $availableStock = $query->sum('available_quantity');

        return $availableStock >= $quantity;
    }

    public function getStockQuantity(?int $warehouseId = null): int
    {
        $query = $this->stockLevels();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->sum('available_quantity');
    }

    public function getTotalStock(?int $warehouseId = null): int
    {
        $query = $this->stockLevels();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->sum('quantity');
    }

    public function getReservedQuantity(?int $warehouseId = null): int
    {
        $query = $this->stockLevels();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->sum('reserved_quantity');
    }

    // Platform SKU mapping methods
    public function getPlatformSku(int $platformId, ?int $platformAccountId = null): ?string
    {
        $mapping = $this->platformSkuMappings()
            ->where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where('is_active', true)
            ->first();

        return $mapping?->platform_sku;
    }

    public function hasPlatformMapping(int $platformId, ?int $platformAccountId = null): bool
    {
        return $this->platformSkuMappings()
            ->where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where('is_active', true)
            ->exists();
    }

    public function createPlatformMapping(int $platformId, string $platformSku, array $additionalData = []): PlatformSkuMapping
    {
        return $this->platformSkuMappings()->create(array_merge([
            'platform_id' => $platformId,
            'product_id' => $this->product_id,
            'platform_sku' => $platformSku,
            'platform_product_name' => $additionalData['platform_product_name'] ?? $this->product->name,
            'platform_variation_name' => $additionalData['platform_variation_name'] ?? $this->name,
        ], $additionalData));
    }

    public static function findByPlatformSku(int $platformId, string $platformSku, ?int $platformAccountId = null): ?self
    {
        $mapping = PlatformSkuMapping::where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where('platform_sku', $platformSku)
            ->where('is_active', true)
            ->first();

        return $mapping?->productVariant;
    }
}
