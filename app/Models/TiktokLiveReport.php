<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokLiveReport extends Model
{
    protected $fillable = [
        'import_id',
        'tiktok_creator_id',
        'creator_nickname',
        'creator_display_name',
        'launched_time',
        'duration_seconds',
        'gmv_myr',
        'live_attributed_gmv_myr',
        'products_added',
        'products_sold',
        'sku_orders',
        'items_sold',
        'unique_customers',
        'avg_price_myr',
        'click_to_order_rate',
        'viewers',
        'views',
        'avg_view_duration_sec',
        'comments',
        'shares',
        'likes',
        'new_followers',
        'product_impressions',
        'product_clicks',
        'ctr',
        'matched_live_session_id',
        'raw_row_json',
    ];

    protected function casts(): array
    {
        return [
            'launched_time' => 'datetime',
            'gmv_myr' => 'decimal:2',
            'live_attributed_gmv_myr' => 'decimal:2',
            'avg_price_myr' => 'decimal:2',
            'click_to_order_rate' => 'decimal:2',
            'ctr' => 'decimal:2',
            'raw_row_json' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(TiktokReportImport::class, 'import_id');
    }

    public function matchedLiveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'matched_live_session_id');
    }
}
