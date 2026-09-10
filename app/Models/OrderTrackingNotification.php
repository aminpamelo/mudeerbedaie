<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackingNotification extends Model
{
    /** @use HasFactory<\Database\Factories\OrderTrackingNotificationFactory> */
    use HasFactory;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP_WAHA = 'whatsapp_waha';

    public const CHANNEL_WHATSAPP_META = 'whatsapp_meta';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_QUEUED = 'queued';

    protected $fillable = [
        'product_order_id',
        'channel',
        'recipient',
        'status',
        'message',
        'provider_message_id',
        'error',
        'meta',
        'sent_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProductOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Human-friendly channel label for the UI.
     */
    public function channelLabel(): string
    {
        return match ($this->channel) {
            self::CHANNEL_EMAIL => 'E-mel',
            self::CHANNEL_WHATSAPP_WAHA => 'WhatsApp (Cekbot)',
            self::CHANNEL_WHATSAPP_META => 'WhatsApp (Rasmi)',
            default => ucfirst((string) $this->channel),
        };
    }
}
