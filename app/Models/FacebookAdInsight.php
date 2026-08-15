<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = one campaign's performance for one day on one ad account,
 * as pulled from the Marketing API insights endpoint.
 */
class FacebookAdInsight extends Model
{
    protected $fillable = [
        'facebook_ad_account_id',
        'date',
        'campaign_id',
        'campaign_name',
        'spend',
        'impressions',
        'clicks',
        'reach',
        'cpm',
        'cpc',
        'ctr',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
        ];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'facebook_ad_account_id');
    }
}
