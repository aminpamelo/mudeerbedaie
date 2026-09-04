<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotMessage extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotMessageFactory> */
    use HasFactory;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'cekbot_conversation_id',
        'cekbot_session_id',
        'waha_message_id',
        'direction',
        'from_me',
        'type',
        'body',
        'media_url',
        'media_mime',
        'ack',
        'payload',
        'sent_by_user_id',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CekbotConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CekbotConversation::class, 'cekbot_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @param  Builder<CekbotMessage>  $query
     * @return Builder<CekbotMessage>
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_IN);
    }
}
