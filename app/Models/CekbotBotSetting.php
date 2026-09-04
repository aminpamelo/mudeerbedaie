<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotBotSetting extends Model
{
    protected $fillable = [
        'cekbot_session_id',
        'bot_enabled',
        'reply_to_groups',
        'checks_enabled',
        'welcome_message',
        'default_reply',
        'ai_enabled',
        'ai_system_prompt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_enabled' => 'boolean',
            'reply_to_groups' => 'boolean',
            'checks_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CekbotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CekbotSession::class, 'cekbot_session_id');
    }
}
