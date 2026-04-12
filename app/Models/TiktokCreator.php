<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokCreator extends Model
{
    protected $fillable = [
        'platform_account_id',
        'creator_user_id',
        'handle',
        'display_name',
        'avatar_url',
        'country_code',
        'follower_count',
        'total_gmv',
        'total_orders',
        'total_commission',
        'raw_response',
        'performance_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'total_gmv' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'raw_response' => 'array',
            'performance_fetched_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function creatorContents(): HasMany
    {
        return $this->hasMany(TiktokCreatorContent::class);
    }

    public function affiliateOrders(): HasMany
    {
        return $this->hasMany(TiktokAffiliateOrder::class);
    }
}
