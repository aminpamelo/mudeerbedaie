<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily TikTok Shop performance (GMV, orders, units…) per shop, synced from
 * the Analytics API so it matches Seller Center → Analytics → Key metrics.
 */
class TiktokShopDailyPerformance extends Model
{
    /** @use HasFactory<\Database\Factories\TiktokShopDailyPerformanceFactory> */
    use HasFactory;

    protected $fillable = [
        'platform_account_id',
        'date',
        'gmv',
        'gmv_live',
        'gmv_video',
        'gmv_product_card',
        'orders',
        'sku_orders',
        'units_sold',
        'buyers',
        'product_page_views',
        'product_impressions',
        'avg_order_value',
        'refunds',
        'cancellations_and_returns',
        'currency',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'gmv' => 'decimal:2',
            'gmv_live' => 'decimal:2',
            'gmv_video' => 'decimal:2',
            'gmv_product_card' => 'decimal:2',
            'avg_order_value' => 'decimal:2',
            'refunds' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
