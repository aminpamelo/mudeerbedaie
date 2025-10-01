<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'alert_type',
        'threshold_quantity',
        'is_active',
        'email_notifications',
        'last_triggered_at',
        'last_resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_quantity' => 'integer',
            'is_active' => 'boolean',
            'email_notifications' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_resolved_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasEmailNotifications(): bool
    {
        return $this->email_notifications;
    }

    public function isLowStockAlert(): bool
    {
        return $this->alert_type === 'low_stock';
    }

    public function isOutOfStockAlert(): bool
    {
        return $this->alert_type === 'out_of_stock';
    }

    public function isOverstockAlert(): bool
    {
        return $this->alert_type === 'overstock';
    }

    public function isTriggered(): bool
    {
        return ! is_null($this->last_triggered_at) &&
               (is_null($this->last_resolved_at) || $this->last_triggered_at > $this->last_resolved_at);
    }

    public function isResolved(): bool
    {
        return ! is_null($this->last_resolved_at) &&
               (is_null($this->last_triggered_at) || $this->last_resolved_at > $this->last_triggered_at);
    }

    public function shouldTrigger(int $currentQuantity): bool
    {
        return match ($this->alert_type) {
            'low_stock' => $currentQuantity <= $this->threshold_quantity && $currentQuantity > 0,
            'out_of_stock' => $currentQuantity <= 0,
            'overstock' => $currentQuantity >= $this->threshold_quantity,
            default => false,
        };
    }

    public function trigger(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    public function resolve(): void
    {
        $this->update(['last_resolved_at' => now()]);
    }

    public function getAlertTypeColorAttribute(): string
    {
        return match ($this->alert_type) {
            'low_stock' => 'yellow',
            'out_of_stock' => 'red',
            'overstock' => 'blue',
            default => 'gray',
        };
    }

    public function getFormattedAlertTypeAttribute(): string
    {
        return match ($this->alert_type) {
            'low_stock' => 'Low Stock',
            'out_of_stock' => 'Out of Stock',
            'overstock' => 'Overstock',
            default => ucfirst(str_replace('_', ' ', $this->alert_type)),
        };
    }

    public function getStatusAttribute(): string
    {
        if ($this->isTriggered()) {
            return 'triggered';
        }

        if ($this->isResolved()) {
            return 'resolved';
        }

        return 'inactive';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'triggered' => 'red',
            'resolved' => 'green',
            'inactive' => 'gray',
            default => 'gray',
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTriggered($query)
    {
        return $query->whereNotNull('last_triggered_at')
            ->where(function ($q) {
                $q->whereNull('last_resolved_at')
                    ->orWhereColumn('last_triggered_at', '>', 'last_resolved_at');
            });
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('last_resolved_at')
            ->where(function ($q) {
                $q->whereNull('last_triggered_at')
                    ->orWhereColumn('last_resolved_at', '>', 'last_triggered_at');
            });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeWithEmailNotifications($query)
    {
        return $query->where('email_notifications', true);
    }
}
