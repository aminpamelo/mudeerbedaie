<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProduct extends Model
{
    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'product_id',
        'platform_product_id',
        'name',
        'status',
        'sync_enabled',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
