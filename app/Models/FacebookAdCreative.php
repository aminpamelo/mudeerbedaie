<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The creative behind one ad: thumbnail, copy, format and the underlying asset
 * (video / image / post) so ads reusing one asset are grouped as one creative.
 */
class FacebookAdCreative extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'ad_id',
        'creative_id',
        'asset_key',
        'format',
        'status',
        'title',
        'body',
        'cta',
        'permalink',
        'thumbnail_url',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }
}
