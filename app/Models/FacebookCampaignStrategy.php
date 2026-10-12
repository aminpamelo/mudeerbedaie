<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tags one Facebook campaign (by its Graph id) with the strategy it runs.
 */
class FacebookCampaignStrategy extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'facebook_ad_strategy_id',
        'campaign_id',
        'campaign_name',
        'assigned_by',
    ];

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(FacebookAdStrategy::class, 'facebook_ad_strategy_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }
}
