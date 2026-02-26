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
        'sync_status',
        'last_order_sync_at',
        'last_product_sync_at',
        'last_inventory_sync_at',
        'last_error_at',
        'last_error_message',
        'api_version',
        'sync_settings',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'permissions' => 'array',
            'sync_settings' => 'array',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_order_sync_at' => 'datetime',
            'last_product_sync_at' => 'datetime',
            'last_inventory_sync_at' => 'datetime',
            'last_error_at' => 'datetime',
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

    /**
     * Update the sync status for this account.
     */
    public function updateSyncStatus(string $status, ?string $type = null): void
    {
        $data = ['sync_status' => $status];

        if ($type && $status === 'completed') {
            $data["last_{$type}_sync_at"] = now();
            $data['last_sync_at'] = now();
        }

        $this->update($data);
    }

    /**
     * Record a sync error for this account.
     */
    public function recordSyncError(string $message): void
    {
        $this->update([
            'sync_status' => 'error',
            'last_error_at' => now(),
            'last_error_message' => $message,
        ]);
    }

    /**
     * Check if the account is currently syncing.
     */
    public function isSyncing(): bool
    {
        return $this->sync_status === 'syncing';
    }

    /**
     * Check if the account has a recent error.
     */
    public function hasRecentError(int $hours = 24): bool
    {
        return $this->last_error_at && $this->last_error_at->isAfter(now()->subHours($hours));
    }

    /**
     * Get the sync schedules for this account.
     */
    public function syncSchedules(): HasMany
    {
        return $this->hasMany(PlatformSyncSchedule::class);
    }

    /**
     * Check if this is a TikTok Shop account.
     */
    public function isTikTokShop(): bool
    {
        return $this->platform->slug === 'tiktok-shop';
    }
}
