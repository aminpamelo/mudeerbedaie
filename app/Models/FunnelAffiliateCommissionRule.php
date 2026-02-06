<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAffiliateCommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'funnel_product_id',
        'commission_type',
        'commission_value',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function funnelProduct(): BelongsTo
    {
        return $this->belongsTo(FunnelProduct::class);
    }

    // Helpers
    public function isFixed(): bool
    {
        return $this->commission_type === 'fixed';
    }

    public function isPercentage(): bool
    {
        return $this->commission_type === 'percentage';
    }

    /**
     * Calculate commission amount for a given order amount.
     */
    public function calculateCommission(float $orderAmount): float
    {
        if ($this->isFixed()) {
            return (float) $this->commission_value;
        }

        return round($orderAmount * $this->commission_value / 100, 2);
    }
}
