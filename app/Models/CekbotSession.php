<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CekbotSession extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotSessionFactory> */
    use HasFactory;

    /**
     * WAHA session lifecycle states, mirrored from the engine so the UI can
     * key off a known set rather than raw strings.
     */
    public const STATUS_WORKING = 'WORKING';

    public const STATUS_SCAN_QR = 'SCAN_QR_CODE';

    public const STATUS_STARTING = 'STARTING';

    public const STATUS_STOPPED = 'STOPPED';

    public const STATUS_FAILED = 'FAILED';

    /**
     * Local sentinel used when the WAHA server has no matching session yet
     * (created locally but never connected) or is unreachable.
     */
    public const STATUS_UNKNOWN = 'UNKNOWN';

    /**
     * Transport a number sends/receives through. WAHA is the unofficial
     * self-hosted engine (QR-linked); Cloud API is Meta's official WhatsApp
     * Business Cloud API (credential-linked, no QR).
     */
    public const PROVIDER_WAHA = 'waha';

    public const PROVIDER_CLOUD_API = 'cloud_api';

    protected $fillable = [
        'session_name',
        'label',
        'phone_number',
        'status',
        'engine',
        'notes',
        'created_by',
        'last_synced_at',
        'provider',
        'phone_number_id',
        'waba_id',
        'access_token',
        'app_secret',
        'verify_token',
        'api_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
        ];
    }

    /**
     * Whether this number runs on Meta's official WhatsApp Cloud API.
     */
    public function isCloudApi(): bool
    {
        return $this->provider === self::PROVIDER_CLOUD_API;
    }

    /**
     * Whether this number runs on the unofficial WAHA engine (the default).
     */
    public function isWaha(): bool
    {
        return $this->provider !== self::PROVIDER_CLOUD_API;
    }

    /**
     * Graph API version for this number, falling back to the global default.
     */
    public function resolvedApiVersion(): string
    {
        return $this->api_version
            ?: (config('services.whatsapp.meta.api_version') ?: 'v21.0');
    }

    /**
     * App secret used to verify inbound Cloud API webhook signatures. Per-number
     * override, else the global Meta app secret (settings → config).
     */
    public function resolvedAppSecret(): ?string
    {
        return $this->app_secret
            ?: (app(\App\Services\SettingsService::class)->get('meta_app_secret')
                ?: (config('services.whatsapp.meta.app_secret') ?: null));
    }

    /**
     * Verify token Meta echoes during webhook subscription. Per-number override,
     * else the global Meta verify token (settings → config).
     */
    public function resolvedVerifyToken(): ?string
    {
        return $this->verify_token
            ?: (app(\App\Services\SettingsService::class)->get('meta_verify_token')
                ?: (config('services.whatsapp.meta.verify_token') ?: null));
    }

    /**
     * The staff member who added this number.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasOne<CekbotBotSetting, $this>
     */
    public function botSetting(): HasOne
    {
        return $this->hasOne(CekbotBotSetting::class);
    }

    /**
     * @return HasMany<CekbotAutoReply, $this>
     */
    public function autoReplies(): HasMany
    {
        return $this->hasMany(CekbotAutoReply::class);
    }

    /**
     * Guided sales-funnel flows configured for this number.
     *
     * @return HasMany<CekbotFlow, $this>
     */
    public function flows(): HasMany
    {
        return $this->hasMany(CekbotFlow::class);
    }

    /**
     * Whether the linked number is authenticated and ready to send/receive.
     */
    public function isWorking(): bool
    {
        return $this->status === self::STATUS_WORKING;
    }
}
