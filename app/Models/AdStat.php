<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdStat extends Model
{
    protected $fillable = [
        'ad_campaign_id',
        'impressions',
        'clicks',
        'spend',
        'conversions',
        'fetched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spend' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Get the ad campaign this stat belongs to
     */
    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    /**
     * Get the click-through rate as a percentage
     */
    public function getCtrAttribute(): float
    {
        if ($this->impressions === 0) {
            return 0;
        }

        return round($this->clicks / $this->impressions * 100, 2);
    }
}
