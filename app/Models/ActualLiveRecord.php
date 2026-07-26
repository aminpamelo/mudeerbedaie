<?php

namespace App\Models;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActualLiveRecord extends Model
{
    use HasFactory;

    /**
     * Restrict a query to the source the shop verifies from. Verification runs off
     * the TikTok API ONLY by default — uploaded-CSV lives are excluded from
     * candidate finding, calendar suggestions and auto-verify. Only the explicit
     * opt-in livehost.verify_source = 'all' brings CSV lives back in.
     */
    public function scopeVerificationSource(Builder $query): Builder
    {
        if (app(SettingsService::class)->get('livehost.verify_source', 'api') !== 'all') {
            $query->where('source', 'api_sync');
        }

        return $query;
    }

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
