<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KedaiBukuPricing extends Model
{
    protected $table = 'kedai_buku_pricing';

    protected $fillable = [
        'agent_id',
        'product_id',
        'price',
        'min_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'min_quantity' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to filter active pricing entries.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by agent.
     */
    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope to filter by product.
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Get the discount percentage compared to original product price.
     */
    public function getDiscountPercentageAttribute(): float
    {
        if (! $this->product || $this->product->price <= 0) {
            return 0;
        }

        return round((1 - ($this->price / $this->product->price)) * 100, 2);
    }
}
