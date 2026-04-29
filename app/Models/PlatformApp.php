<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PlatformApp extends Model
{
    use HasFactory;

    public const CATEGORY_MULTI_CHANNEL = 'multi_channel';

    public const CATEGORY_ANALYTICS_REPORTING = 'analytics_reporting';

    public const CATEGORY_AFFILIATE = 'affiliate';

    public const CATEGORY_CUSTOMER_SERVICE = 'customer_service';

    protected $fillable = [
        'platform_id',
        'slug',
        'name',
        'category',
        'app_key',
        'encrypted_app_secret',
        'redirect_uri',
        'scopes',
        'is_active',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_app_secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(PlatformApiCredential::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getAppSecret(): ?string
    {
        if (! $this->encrypted_app_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_app_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setAppSecret(string $secret): void
    {
        $this->encrypted_app_secret = Crypt::encryptString($secret);
    }
}
