<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'original_price',
        'discount_type',
        'discount_value',
        'status',
        'start_date',
        'end_date',
        'max_purchases',
        'purchased_count',
        'track_stock',
        'default_warehouse_id',
        'featured_image',
        'gallery_images',
        'meta_title',
        'meta_description',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'track_stock' => 'boolean',
            'gallery_images' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });

        static::updating(function ($package) {
            if ($package->isDirty('name') && empty($package->slug)) {
                $package->slug = Str::slug($package->name);
            }
        });
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'itemable', 'package_items')
            ->withPivot(['quantity', 'product_variant_id', 'warehouse_id', 'custom_price', 'original_price', 'sort_order'])
            ->orderBy('package_items.sort_order');
    }

    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'itemable', 'package_items')
            ->withPivot(['custom_price', 'original_price', 'sort_order'])
            ->orderBy('package_items.sort_order');
    }

    public function classes(): MorphToMany
    {
        return $this->morphedByMany(ClassModel::class, 'itemable', 'package_items')
            ->withPivot(['custom_price', 'original_price', 'sort_order'])
            ->orderBy('package_items.sort_order');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    public function completedPurchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class)->where('status', 'completed');
    }

    // Status helper methods
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->isWithinDateRange() && ! $this->isPurchaseLimitReached();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isWithinDateRange(): bool
    {
        $now = now()->toDateString();

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    public function isPurchaseLimitReached(): bool
    {
        return $this->max_purchases && $this->purchased_count >= $this->max_purchases;
    }

    // Stock management methods
    public function checkStockAvailability(?int $warehouseId = null): array
    {
        $stockCheck = [
            'available' => true,
            'issues' => [],
            'details' => [],
        ];

        if (! $this->track_stock) {
            return $stockCheck;
        }

        foreach ($this->products as $product) {
            $requiredQuantity = $product->pivot->quantity;
            $variantId = $product->pivot->product_variant_id;
            $packageWarehouseId = $warehouseId ?? $product->pivot->warehouse_id ?? $this->default_warehouse_id;

            // Check if this is a variant purchase
            if ($variantId) {
                $variant = ProductVariant::find($variantId);
                if (! $variant) {
                    $stockCheck['available'] = false;
                    $stockCheck['issues'][] = "Product variant not found for {$product->name}";

                    continue;
                }
            }

            $availableStock = $product->getStockQuantity($packageWarehouseId);

            $stockCheck['details'][] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_id' => $variantId,
                'required_quantity' => $requiredQuantity,
                'available_quantity' => $availableStock,
                'warehouse_id' => $packageWarehouseId,
                'sufficient' => $availableStock >= $requiredQuantity,
            ];

            if ($availableStock < $requiredQuantity) {
                $stockCheck['available'] = false;
                $stockCheck['issues'][] = "Insufficient stock for {$product->name}. Required: {$requiredQuantity}, Available: {$availableStock}";
            }
        }

        return $stockCheck;
    }

    public function reserveStock(?int $warehouseId = null): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        $stockCheck = $this->checkStockAvailability($warehouseId);

        if (! $stockCheck['available']) {
            return false;
        }

        // Reserve stock for each product
        foreach ($this->products as $product) {
            $requiredQuantity = $product->pivot->quantity;
            $packageWarehouseId = $warehouseId ?? $product->pivot->warehouse_id ?? $this->default_warehouse_id;

            // Find the stock level record
            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $packageWarehouseId)
                ->first();

            if ($stockLevel) {
                $stockLevel->increment('reserved_quantity', $requiredQuantity);
                $stockLevel->decrement('available_quantity', $requiredQuantity);
            }
        }

        return true;
    }

    public function deductStock(PackagePurchase $purchase): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        foreach ($this->products as $product) {
            $requiredQuantity = $product->pivot->quantity;
            $packageWarehouseId = $purchase->getWarehouseForProduct($product->id) ?? $this->default_warehouse_id;

            // Find the stock level record
            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $packageWarehouseId)
                ->first();

            if ($stockLevel) {
                // Deduct from total quantity and reserved quantity
                $stockLevel->decrement('quantity', $requiredQuantity);
                $stockLevel->decrement('reserved_quantity', $requiredQuantity);

                // Create stock movement record
                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $packageWarehouseId,
                    'type' => 'sale',
                    'quantity_change' => -$requiredQuantity,
                    'quantity_after' => $stockLevel->quantity,
                    'reference_type' => PackagePurchase::class,
                    'reference_id' => $purchase->id,
                    'notes' => "Package sale: {$this->name} (#{$purchase->purchase_number})",
                    'metadata' => [
                        'package_id' => $this->id,
                        'package_name' => $this->name,
                        'purchase_id' => $purchase->id,
                    ],
                ]);
            }
        }

        return true;
    }

    public function releaseReservedStock(?int $warehouseId = null): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        foreach ($this->products as $product) {
            $requiredQuantity = $product->pivot->quantity;
            $packageWarehouseId = $warehouseId ?? $product->pivot->warehouse_id ?? $this->default_warehouse_id;

            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $packageWarehouseId)
                ->first();

            if ($stockLevel) {
                $stockLevel->decrement('reserved_quantity', $requiredQuantity);
                $stockLevel->increment('available_quantity', $requiredQuantity);
            }
        }

        return true;
    }

    // Pricing methods
    public function calculateOriginalPrice(): float
    {
        $total = 0;

        foreach ($this->products as $product) {
            $productPrice = $product->pivot->custom_price ?? $product->base_price;
            $total += $productPrice * $product->pivot->quantity;
        }

        foreach ($this->courses as $course) {
            $coursePrice = $course->pivot->custom_price ?? ($course->feeSettings->fee_amount ?? 0);
            $total += $coursePrice;
        }

        foreach ($this->classes as $class) {
            $classPrice = $class->pivot->custom_price ?? ($class->course?->feeSettings->fee_amount ?? 0);
            $total += $classPrice;
        }

        return $total;
    }

    public function calculateSavings(): float
    {
        return max(0, $this->calculateOriginalPrice() - $this->price);
    }

    public function getSavingsPercentage(): float
    {
        $originalPrice = $this->calculateOriginalPrice();

        if ($originalPrice <= 0) {
            return 0;
        }

        return round((($originalPrice - $this->price) / $originalPrice) * 100, 2);
    }

    // Utility methods
    public function getItemCount(): int
    {
        return $this->items()->count();
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getCourseCount(): int
    {
        return $this->courses()->count();
    }

    public function getClassCount(): int
    {
        return $this->classes()->count();
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'RM '.number_format($this->price, 2);
    }

    public function getFormattedOriginalPriceAttribute(): string
    {
        return 'RM '.number_format($this->calculateOriginalPrice(), 2);
    }

    public function getFormattedSavingsAttribute(): string
    {
        return 'RM '.number_format($this->calculateSavings(), 2);
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        $now = now()->toDateString();

        return $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('max_purchases')
                    ->orWhereRaw('purchased_count < max_purchases');
            });
    }

    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }
}
