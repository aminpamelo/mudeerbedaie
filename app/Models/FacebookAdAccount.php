<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAdAccount extends Model
{
    /** @use HasFactory<\Database\Factories\FacebookAdAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'facebook_ad_connection_id',
        'account_id',
        'name',
        'currency',
        'account_status',
        'monitor_synced_at',
        'monitor_sync_error',
        'daily_budget',
        'lifetime_budgets',
        'campaign_budgets',
    ];

    protected function casts(): array
    {
        return [
            'monitor_synced_at' => 'datetime',
            'daily_budget' => 'decimal:2',
            'campaign_budgets' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookAdConnection::class, 'facebook_ad_connection_id');
    }

    public function levelInsights(): HasMany
    {
        return $this->hasMany(FacebookAdLevelInsight::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FacebookAdRule::class);
    }

    /**
     * Whether the parent connection's token may write (pause, budget, launch).
     */
    public function canManageAds(): bool
    {
        return (bool) $this->getRelationValue('connection')?->can_manage_ads;
    }

    public function insights(): HasMany
    {
        return $this->hasMany(FacebookAdInsight::class);
    }

    /**
     * Accounts under Business Manager connections owned by the given user
     * (a fighter's self-linked BMs).
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('connection', fn (Builder $c) => $c->where('user_id', $userId));
    }

    /**
     * Accounts the Monitor Iklan syncs: active on Meta and under a connected Business Manager.
     */
    public function scopeMonitorable(Builder $query): Builder
    {
        return $query->where('account_status', '1')
            ->whereHas('connection', fn (Builder $c) => $c->where('status', 'connected'));
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
