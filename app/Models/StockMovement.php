<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncoming(): bool
    {
        return in_array($this->type, ['in', 'adjustment']) && $this->quantity > 0;
    }

    public function isOutgoing(): bool
    {
        return in_array($this->type, ['out', 'adjustment']) && $this->quantity < 0;
    }

    public function isAdjustment(): bool
    {
        return $this->type === 'adjustment';
    }

    public function isTransfer(): bool
    {
        return $this->type === 'transfer';
    }

    public function getAbsoluteQuantityAttribute(): int
    {
        return abs($this->quantity);
    }

    public function getTotalValueAttribute(): float
    {
        return $this->absolute_quantity * ($this->unit_cost ?? 0);
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'in' => 'green',
            'out' => 'red',
            'adjustment' => 'yellow',
            'transfer' => 'blue',
            default => 'gray',
        };
    }

    public function getFormattedTypeAttribute(): string
    {
        return match ($this->type) {
            'in' => 'Stock In',
            'out' => 'Stock Out',
            'adjustment' => 'Adjustment',
            'transfer' => 'Transfer',
            default => ucfirst($this->type),
        };
    }

    public function getDisplayQuantityAttribute(): string
    {
        $prefix = $this->quantity >= 0 ? '+' : '';

        return $prefix.number_format($this->quantity);
    }

    public function getFormattedReferenceAttribute(): array
    {
        if (! $this->reference_type && ! $this->reference_id) {
            return [
                'type' => null,
                'label' => '-',
                'variant' => 'outline',
                'icon' => null,
                'url' => null,
                'clickable' => false,
            ];
        }

        if ($this->reference_type === 'initial_stock') {
            return [
                'type' => 'initial_stock',
                'label' => 'Initial Stock',
                'variant' => 'info',
                'icon' => 'cube',
                'url' => null,
                'clickable' => false,
            ];
        }

        if ($this->reference_type === 'manual_adjustment') {
            return [
                'type' => 'manual_adjustment',
                'label' => 'Manual Adjustment',
                'variant' => 'warning',
                'icon' => 'wrench',
                'url' => null,
                'clickable' => false,
            ];
        }

        // Handle model class references
        if (str_contains($this->reference_type, '\\')) {
            $className = class_basename($this->reference_type);

            return match ($className) {
                'ProductOrder' => [
                    'type' => 'order',
                    'label' => 'Order #'.$this->reference_id,
                    'variant' => 'primary',
                    'icon' => 'shopping-cart',
                    'url' => $this->reference_id ? route('admin.orders.show', $this->reference_id) : null,
                    'clickable' => true,
                ],
                'Purchase' => [
                    'type' => 'purchase',
                    'label' => 'Purchase #'.$this->reference_id,
                    'variant' => 'success',
                    'icon' => 'truck',
                    'url' => null,
                    'clickable' => false,
                ],
                'Transfer' => [
                    'type' => 'transfer',
                    'label' => 'Transfer #'.$this->reference_id,
                    'variant' => 'info',
                    'icon' => 'arrow-right',
                    'url' => null,
                    'clickable' => false,
                ],
                'Adjustment' => [
                    'type' => 'adjustment',
                    'label' => 'Adjustment #'.$this->reference_id,
                    'variant' => 'warning',
                    'icon' => 'adjustments-horizontal',
                    'url' => null,
                    'clickable' => false,
                ],
                default => [
                    'type' => 'unknown',
                    'label' => $className.' #'.$this->reference_id,
                    'variant' => 'outline',
                    'icon' => 'document',
                    'url' => null,
                    'clickable' => false,
                ]
            };
        }

        // Fallback for other reference types
        return [
            'type' => $this->reference_type,
            'label' => ucfirst(str_replace('_', ' ', $this->reference_type)).($this->reference_id ? ' #'.$this->reference_id : ''),
            'variant' => 'outline',
            'icon' => 'tag',
            'url' => null,
            'clickable' => false,
        ];
    }

    public function scopeIncoming($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('quantity', '<', 0);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByReference($query, $referenceType, $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
