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

    protected $fillable = [
        'session_name',
        'label',
        'phone_number',
        'status',
        'engine',
        'notes',
        'created_by',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
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
     * Whether the linked number is authenticated and ready to send/receive.
     */
    public function isWorking(): bool
    {
        return $this->status === self::STATUS_WORKING;
    }
}
