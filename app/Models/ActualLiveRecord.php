<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActualLiveRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_account_id', 'source', 'source_record_id', 'import_id',
        'creator_platform_user_id', 'creator_handle',
        'launched_time', 'ended_time', 'duration_seconds',
        'gmv_myr', 'live_attributed_gmv_myr',
        'viewers', 'views', 'comments', 'shares', 'likes', 'new_followers',
        'products_added', 'products_sold', 'items_sold', 'sku_orders',
        'unique_customers', 'avg_price_myr', 'click_to_order_rate', 'ctr',
        'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'launched_time' => 'datetime',
            'ended_time' => 'datetime',
            'gmv_myr' => 'decimal:2',
            'live_attributed_gmv_myr' => 'decimal:2',
            'avg_price_myr' => 'decimal:2',
            'click_to_order_rate' => 'decimal:4',
            'ctr' => 'decimal:4',
            'raw_json' => 'array',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function matchedLiveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class, 'matched_actual_live_record_id');
    }
}
