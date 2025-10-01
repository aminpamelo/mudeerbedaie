<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformApiCredential extends Model
{
    protected $fillable = [
        'platform_id',
        'platform_account_id',
        'credential_type',
        'name',
        'encrypted_value',
        'encrypted_refresh_token',
        'metadata',
        'scopes',
        'expires_at',
        'last_used_at',
        'is_active',
        'auto_refresh',
    ];

    protected $hidden = [
        'encrypted_value',
        'encrypted_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
            'auto_refresh' => 'boolean',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
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

    public function getValue(): ?string
    {
        if (! $this->encrypted_value) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setValue(string $value): void
    {
        $this->encrypted_value = Crypt::encryptString($value);
    }

    public function getRefreshToken(): ?string
    {
        if (! $this->encrypted_refresh_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_refresh_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setRefreshToken(?string $token): void
    {
        $this->encrypted_refresh_token = $token ? Crypt::encryptString($token) : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->expires_at && $this->expires_at->isBefore(now()->addDays($days));
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
