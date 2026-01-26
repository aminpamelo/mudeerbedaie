<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'funnel_id',
        'session_id',
        'product_order_id',
        'step_id',
        'order_type',
        'funnel_revenue',
        'upsells_offered',
        'upsells_accepted',
        'downsells_offered',
        'downsells_accepted',
        'bumps_offered',
        'bumps_accepted',
    ];

    protected function casts(): array
    {
        return [
            'funnel_revenue' => 'decimal:2',
        ];
    }

    // Relationships
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FunnelSession::class, 'session_id');
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'step_id');
    }

    // Type helpers
    public function isMainOrder(): bool
    {
        return $this->order_type === 'main';
    }

    public function isUpsell(): bool
    {
        return $this->order_type === 'upsell';
    }

    public function isDownsell(): bool
    {
        return $this->order_type === 'downsell';
    }

    public function isBump(): bool
    {
        return $this->order_type === 'bump';
    }

    // Offer tracking
    public function recordUpsellOffered(): void
    {
        $this->increment('upsells_offered');
    }

    public function recordUpsellAccepted(): void
    {
        $this->increment('upsells_accepted');
    }

    public function recordDownsellOffered(): void
    {
        $this->increment('downsells_offered');
    }

    public function recordDownsellAccepted(): void
    {
        $this->increment('downsells_accepted');
    }

    public function recordBumpOffered(): void
    {
        $this->increment('bumps_offered');
    }

    public function recordBumpAccepted(): void
    {
        $this->increment('bumps_accepted');
    }

    // Conversion rates
    public function getUpsellConversionRate(): float
    {
        if ($this->upsells_offered === 0) {
            return 0;
        }

        return round(($this->upsells_accepted / $this->upsells_offered) * 100, 2);
    }

    public function getDownsellConversionRate(): float
    {
        if ($this->downsells_offered === 0) {
            return 0;
        }

        return round(($this->downsells_accepted / $this->downsells_offered) * 100, 2);
    }

    public function getBumpConversionRate(): float
    {
        if ($this->bumps_offered === 0) {
            return 0;
        }

        return round(($this->bumps_accepted / $this->bumps_offered) * 100, 2);
    }

    public function getFormattedRevenue(): string
    {
        return 'RM '.number_format($this->funnel_revenue, 2);
    }

    // Scopes
    public function scopeMain($query)
    {
        return $query->where('order_type', 'main');
    }

    public function scopeUpsells($query)
    {
        return $query->where('order_type', 'upsell');
    }

    public function scopeDownsells($query)
    {
        return $query->where('order_type', 'downsell');
    }

    public function scopeBumps($query)
    {
        return $query->where('order_type', 'bump');
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }
}
