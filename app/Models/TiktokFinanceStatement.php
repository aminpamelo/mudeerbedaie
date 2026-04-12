<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokFinanceStatement extends Model
{
    protected $fillable = [
        'platform_account_id',
        'tiktok_statement_id',
        'statement_type',
        'total_amount',
        'order_amount',
        'commission_amount',
        'shipping_fee',
        'platform_fee',
        'currency',
        'status',
        'statement_time',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'raw_response' => 'array',
            'statement_time' => 'datetime',
        ];
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TiktokFinanceTransaction::class, 'statement_id');
    }
}
