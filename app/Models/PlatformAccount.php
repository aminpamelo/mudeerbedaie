<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'user_id',
        'name',
        'account_id',
        'seller_center_id',
        'business_manager_id',
        'shop_id',
        'store_id',
        'email',
        'phone',
        'country_code',
        'currency',
        'description',
        'metadata',
        'permissions',
        'connected_at',
        'last_sync_at',
        'expires_at',
        'is_active',
        'auto_sync_orders',
        'auto_sync_products',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'permissions' => 'array',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'auto_sync_orders' => 'boolean',
            'auto_sync_products' => 'boolean',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liveHosts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'live_host_platform_account')
            ->where('role', 'live_host')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(PlatformApiCredential::class);
    }

    public function liveSchedules(): HasMany
    {
        return $this->hasMany(LiveSchedule::class);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->expires_at && $this->expires_at->isBefore(now()->addDays($days));
    }

    public function markAsConnected(): void
    {
        $this->update([
            'connected_at' => now(),
            'is_active' => true,
        ]);
    }

    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->platform->display_name})";
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'active';
    }
}
