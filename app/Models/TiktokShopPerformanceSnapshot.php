<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokShopPerformanceSnapshot extends Model
{
    protected $fillable = [
        'platform_account_id',
        'total_orders',
        'total_gmv',
        'total_buyers',
        'total_video_views',
        'total_product_impressions',
        'conversion_rate',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'total_gmv' => 'decimal:2',
            'conversion_rate' => 'decimal:4',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
