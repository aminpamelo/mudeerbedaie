<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotConversationNote extends Model
{
    protected $fillable = [
        'cekbot_conversation_id',
        'user_id',
        'body',
    ];

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
