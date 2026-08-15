<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAdAccount extends Model
{
    protected $fillable = [
        'facebook_ad_connection_id',
        'account_id',
        'name',
        'currency',
        'account_status',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookAdConnection::class, 'facebook_ad_connection_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(FacebookAdInsight::class);
    }

    /**
     * Funnels whose settings declare this ad account as their traffic source.
     */
    public function linkedFunnelsCount(): int
    {
        return Funnel::query()
            ->where('settings->ads->facebook_ad_account_id', $this->id)
            ->count();
    }
}
