<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokAffiliateOrder extends Model
{
    protected $fillable = [
        'platform_account_id',
        'tiktok_creator_id',
        'tiktok_order_id',
        'creator_user_id',
        'tiktok_product_id',
        'order_status',
        'order_amount',
        'commission_amount',
        'commission_rate',
        'collaboration_type',
        'order_created_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'raw_response' => 'array',
            'order_created_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TiktokCreator::class, 'tiktok_creator_id');
    }
}
