<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ad's Meta performance for one day (ad-level insights), synced every few minutes
 * for the Funnel Studio "Monitor Iklan".
 */
class FacebookAdLevelInsight extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'date',
        'campaign_id',
        'campaign_name',
        'objective',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'link_clicks',
        'landing_page_views',
        'video_views',
        'thruplays',
        'purchases',
        'purchase_value',
    ];

    protected function casts(): array
    {
        return [
            'spend' => 'decimal:2',
            'purchase_value' => 'decimal:2',
        ];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }
}
