<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A campaign → ad set → creative → ad chain created from the Studio.
 * Keeps the Facebook ids so a partially failed launch can be traced.
 */
class FacebookAdLaunch extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'funnel_id',
        'user_id',
        'name',
        'objective',
        'campaign_id',
        'adset_id',
        'creative_id',
        'ad_id',
        'status',
        'error',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
