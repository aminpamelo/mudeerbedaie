<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'sku',
        'barcode',
        'base_price',
        'cost_price',
        'category_id',
        'status',
        'type',
        'track_quantity',
        'min_quantity',
        'dimensions',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'track_quantity' => 'boolean',
            'min_quantity' => 'integer',
            'dimensions' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->where('type', 'image')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductMedia::class)->where('is_primary', true)->where('type', 'image');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('sort_order');
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
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    public function isSimple(): bool
    {
        return $this->type === 'simple';
    }

    public function shouldTrackQuantity(): bool
    {
        return $this->track_quantity;
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stockLevels()->sum('available_quantity');
    }

    public function getPriceAttribute(): float
    {
        return $this->base_price;
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'RM '.number_format($this->base_price, 2);
    }

    public function getFormattedCostPriceAttribute(): string
    {
        return 'RM '.number_format($this->cost_price, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'inactive' => 'gray',
            'draft' => 'yellow',
            default => 'gray',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeTrackingQuantity($query)
    {
        return $query->where('track_quantity', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Stock management methods for order system
    public function checkStockAvailability(int $quantity, ?int $warehouseId = null): bool
    {
        if (! $this->shouldTrackQuantity()) {
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
            ->whereNull('product_variant_id') // Only direct product mappings
            ->where('is_active', true)
            ->first();

        return $mapping?->platform_sku;
    }

    public function hasPlatformMapping(int $platformId, ?int $platformAccountId = null): bool
    {
        return $this->platformSkuMappings()
            ->where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->whereNull('product_variant_id')
            ->where('is_active', true)
            ->exists();
    }

    public function createPlatformMapping(int $platformId, string $platformSku, array $additionalData = []): PlatformSkuMapping
    {
        return $this->platformSkuMappings()->create(array_merge([
            'platform_id' => $platformId,
            'product_variant_id' => null, // This is a direct product mapping
            'platform_sku' => $platformSku,
            'platform_product_name' => $additionalData['platform_product_name'] ?? $this->name,
        ], $additionalData));
    }

    public static function findByPlatformSku(int $platformId, string $platformSku, ?int $platformAccountId = null): ?self
    {
        $mapping = PlatformSkuMapping::where('platform_id', $platformId)
            ->when($platformAccountId, fn ($q) => $q->where('platform_account_id', $platformAccountId))
            ->where('platform_sku', $platformSku)
            ->whereNull('product_variant_id')
            ->where('is_active', true)
            ->first();

        return $mapping?->product;
    }

    public function getAllPlatformSkus(int $platformId, ?int $platformAccountId = null): array
    {
        // Get both direct product mappings and variant mappings
        $directMapping = $this->getPlatformSku($platformId, $platformAccountId);
        $variantMappings = $this->variants()
            ->with(['platformSkuMappings' => function ($q) use ($platformId, $platformAccountId) {
                $q->where('platform_id', $platformId)
                    ->when($platformAccountId, fn ($subQ) => $subQ->where('platform_account_id', $platformAccountId))
                    ->where('is_active', true);
            }])
            ->get()
            ->flatMap(fn ($variant) => $variant->platformSkuMappings->pluck('platform_sku'))
            ->toArray();

        $allSkus = array_filter(array_merge(
            $directMapping ? [$directMapping] : [],
            $variantMappings
        ));

        return array_unique($allSkus);
    }
}
