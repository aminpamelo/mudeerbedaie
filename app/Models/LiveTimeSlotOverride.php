<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A per-creator-account slot override effective over a date range. While active
 * for a date it replaces the account's normal slots; its slot times live on
 * {@see LiveTimeSlot} rows tagged with this override's id.
 */
class LiveTimeSlotOverride extends Model
{
    protected $fillable = [
        'live_account_id',
        'effective_from',
        'effective_until',
        'label',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function liveAccount(): BelongsTo
    {
        return $this->belongsTo(LiveAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LiveTimeSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(LiveTimeSlot::class, 'override_id');
    }

    /** Active overrides whose window intersects [$from, $to]. */
    public function scopeTouchingRange(Builder $query, string $from, string $to): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $to)
            ->where(function (Builder $q) use ($from) {
                $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $from);
            });
    }

    /** Does this override cover the given Y-m-d date? */
    public function coversDate(string $date): bool
    {
        return $this->effective_from->toDateString() <= $date
            && ($this->effective_until === null || $this->effective_until->toDateString() >= $date);
    }
}
