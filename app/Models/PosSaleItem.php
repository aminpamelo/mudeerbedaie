<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PosSaleItem extends Model
{
    /** @use HasFactory<\Database\Factories\PosSaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_sale_id',
        'itemable_type',
        'itemable_id',
        'product_variant_id',
        'class_id',
        'item_name',
        'variant_name',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function isProduct(): bool
    {
        return $this->itemable_type === Product::class;
    }

    public function isPackage(): bool
    {
        return $this->itemable_type === Package::class;
    }

    public function isCourse(): bool
    {
        return $this->itemable_type === Course::class;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->variant_name) {
            return $this->item_name.' - '.$this->variant_name;
        }

        return $this->item_name;
    }

    /**
     * Deduct stock for this sale item (products and packages only).
     */
    public function deductStock(): void
    {
        if ($this->isProduct()) {
            $this->deductProductStock();
        } elseif ($this->isPackage()) {
            $this->deductPackageStock();
        }
    }

    protected function deductProductStock(): void
    {
        $product = $this->itemable;

        if (! $product || ! $product->shouldTrackQuantity()) {
            return;
        }

        $stockLevel = $product->stockLevels()->first();

        if ($stockLevel) {
            $quantityBefore = $stockLevel->quantity;
            $stockLevel->decrement('quantity', $this->quantity);
            $stockLevel->refresh();

            $stockLevel->update(['last_movement_at' => now()]);

            StockMovement::create([
                'product_id' => $product->id,
                'product_variant_id' => $this->product_variant_id,
                'warehouse_id' => $stockLevel->warehouse_id,
                'type' => 'out',
                'quantity' => -$this->quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'unit_cost' => $stockLevel->average_cost ?? 0,
                'reference_type' => PosSale::class,
                'reference_id' => $this->pos_sale_id,
                'notes' => "POS sale (Sale #{$this->sale->sale_number})",
                'metadata' => [
                    'pos_sale_item_id' => $this->id,
                    'product_name' => $product->name,
                ],
            ]);
        }
    }

    protected function deductPackageStock(): void
    {
        $package = $this->itemable;

        if (! $package || ! $package->track_stock) {
            return;
        }

        foreach ($package->products as $product) {
            $requiredQuantity = $product->pivot->quantity * $this->quantity;
            $stockLevel = $product->stockLevels()->first();

            if ($stockLevel) {
                $quantityBefore = $stockLevel->quantity;
                $stockLevel->decrement('quantity', $requiredQuantity);
                $stockLevel->refresh();

                $stockLevel->update(['last_movement_at' => now()]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $product->pivot->product_variant_id ?? null,
                    'warehouse_id' => $stockLevel->warehouse_id,
                    'type' => 'out',
                    'quantity' => -$requiredQuantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $stockLevel->quantity,
                    'unit_cost' => $stockLevel->average_cost ?? 0,
                    'reference_type' => PosSale::class,
                    'reference_id' => $this->pos_sale_id,
                    'notes' => "POS package sale: {$package->name} (Sale #{$this->sale->sale_number})",
                    'metadata' => [
                        'pos_sale_item_id' => $this->id,
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'product_quantity_in_package' => $product->pivot->quantity,
                    ],
                ]);
            }
        }
    }
}
