<?php

namespace App\Services\Cekbot;

use App\Models\CekbotAutoReply;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Support\Str;

/**
 * The Cekbot auto-reply engine.
 *
 * Decides how to respond to an inbound message and sends the reply through
 * WAHA. Reply priority: matching rule → first-message welcome → AI fallback
 * (Fasa 4) → default reply. Human-handled conversations are skipped (Fasa 6).
 */
class CekbotBotService
{
    public function __construct(
        private WahaSessionManager $waha,
        private CekbotAiResponder $ai,
        private CekbotCheckService $checks,
    ) {}

    public function handleIncoming(CekbotConversation $conversation, ?string $body, string $type): void
    {
        $conversation->loadMissing('session.botSetting');
        $session = $conversation->session;
        $settings = $session?->botSetting;

        if (! $settings || ! $settings->bot_enabled) {
            return;
        }

        if ($conversation->is_group && ! $settings->reply_to_groups) {
            return;
        }

        // A conversation taken over by a human pauses the bot (Fasa 6).
        if ($conversation->handed_over_at !== null) {
            return;
        }

        $reply = $this->decideReply($conversation, $settings, (string) $body);

        if ($reply === null || trim($reply) === '') {
            return;
        }

        $this->sendReply($conversation, $reply);
    }

    /**
     * Work out the reply text, or null to stay silent.
     */
    private function decideReply(CekbotConversation $conversation, \App\Models\CekbotBotSetting $settings, string $body): ?string
    {
        $text = trim($body);

        // 0. System checks (order status, etc.) — answered with live data.
        if ($settings->checks_enabled && $text !== '') {
            $checkReply = $this->checks->check($text, $conversation);
            if (filled($checkReply)) {
                return $checkReply;
            }
        }

        // 1. First matching active rule (by priority).
        if ($text !== '') {
            $rule = $conversation->session->autoReplies()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
                ->first(fn (CekbotAutoReply $r) => $r->matches($text));

            if ($rule) {
                return $rule->reply_body;
            }
        }

        // 2. Welcome message on the first inbound message of a conversation.
        $inboundCount = $conversation->messages()->where('direction', CekbotMessage::DIRECTION_IN)->count();
        if ($inboundCount <= 1 && filled($settings->welcome_message)) {
            return $settings->welcome_message;
        }

        // 3. AI fallback (Fasa 4).
        if ($settings->ai_enabled && $text !== '') {
            $aiReply = $this->ai->reply($conversation, $text, $settings);
            if (filled($aiReply)) {
                return $aiReply;
            }
        }

        // 4. Default catch-all reply.
        if (filled($settings->default_reply)) {
            return $settings->default_reply;
        }

        return null;
    }

    /**
     * Send a bot reply and record it as an outbound message (no sender user).
     */
    public function sendReply(CekbotConversation $conversation, string $reply): void
    {
        $session = $conversation->session;
        $result = $this->waha->sendText($session->session_name, $conversation->chat_id, $reply);

        CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $session->id,
            'waha_message_id' => $result['message_id'] ?? null,
            'direction' => CekbotMessage::DIRECTION_OUT,
            'from_me' => true,
            'type' => 'text',
            'body' => $reply,
            'ack' => $result['success'] ? 'sent' : 'failed',
            'sent_by_user_id' => null,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($reply, 255),
        ]);
    }
}
