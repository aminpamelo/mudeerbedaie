<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokFinanceTransaction extends Model
{
    protected $fillable = [
        'platform_account_id',
        'statement_id',
        'tiktok_order_id',
        'transaction_type',
        'order_amount',
        'seller_revenue',
        'affiliate_commission',
        'platform_commission',
        'shipping_fee',
        'order_created_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'decimal:2',
            'seller_revenue' => 'decimal:2',
            'affiliate_commission' => 'decimal:2',
            'platform_commission' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'raw_response' => 'array',
            'order_created_at' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(TiktokFinanceStatement::class, 'statement_id');
    }
}
