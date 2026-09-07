<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotBroadcastRecipient extends Model
{
    protected $fillable = [
        'cekbot_broadcast_id',
        'cekbot_conversation_id',
        'status',
        'error',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CekbotBroadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(CekbotBroadcast::class, 'cekbot_broadcast_id');
    }

    /**
     * @return BelongsTo<CekbotConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CekbotConversation::class, 'cekbot_conversation_id');
    }
}
