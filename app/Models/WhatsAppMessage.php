<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsAppMessageFactory> */
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'direction',
        'wamid',
        'type',
        'body',
        'media_url',
        'media_mime_type',
        'media_filename',
        'template_name',
        'status',
        'status_updated_at',
        'error_code',
        'error_message',
        'sent_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WhatsAppConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @param  Builder<WhatsAppMessage>  $query
     * @return Builder<WhatsAppMessage>
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * @param  Builder<WhatsAppMessage>  $query
     * @return Builder<WhatsAppMessage>
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }
}
