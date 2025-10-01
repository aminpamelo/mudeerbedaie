<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
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

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function getFormattedAddressAttribute(): string
    {
        $address = $this->address;
        if (! $address) {
            return '';
        }

        $parts = [];
        if (! empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (! empty($address['city'])) {
            $parts[] = $address['city'];
        }
        if (! empty($address['state'])) {
            $parts[] = $address['state'];
        }
        if (! empty($address['postal_code'])) {
            $parts[] = $address['postal_code'];
        }
        if (! empty($address['country'])) {
            $parts[] = $address['country'];
        }

        return implode(', ', $parts);
    }

    public function getTotalProductsAttribute(): int
    {
        return $this->stockLevels()->distinct('product_id')->count('product_id');
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    public function getTotalValueAttribute(): float
    {
        return $this->stockLevels()->with('product')->get()->sum(function ($stockLevel) {
            return $stockLevel->quantity * $stockLevel->average_cost;
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
