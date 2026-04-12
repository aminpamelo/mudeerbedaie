<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokCreatorContent extends Model
{
    protected $fillable = [
        'tiktok_creator_id',
        'content_id',
        'platform_account_id',
        'creator_video_id',
        'tiktok_product_id',
        'views',
        'likes',
        'comments',
        'shares',
        'gmv',
        'orders',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'gmv' => 'decimal:2',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TiktokCreator::class, 'tiktok_creator_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }
}
