<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokProductPerformance extends Model
{
    protected $table = 'tiktok_product_performance';

    protected $fillable = [
        'platform_account_id',
        'tiktok_product_id',
        'impressions',
        'clicks',
        'orders',
        'gmv',
        'buyers',
        'conversion_rate',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'gmv' => 'decimal:2',
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
