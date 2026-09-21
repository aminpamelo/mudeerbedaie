<?php

namespace App\Services\Cekbot;

use App\Events\Cekbot\CekbotMessageReceived;
use App\Models\CekbotAutoReply;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use Illuminate\Support\Str;

/**
 * The Cekbot auto-reply engine.
 *
 * Decides how to respond to an inbound message and sends the reply through the
 * number's configured provider (WAHA or the official Cloud API) via
 * CekbotOutbound. Reply priority: matching rule → first-message welcome → AI
 * fallback (Fasa 4) → default reply. Human-handled conversations are skipped
 * (Fasa 6).
 */
class CekbotBotService
{
    public function __construct(
        private CekbotOutbound $out,
        private CekbotAiResponder $ai,
        private CekbotCheckService $checks,
        private CekbotFlowService $flow,
    ) {}

    public function handleIncoming(CekbotConversation $conversation, ?string $body, string $type): void
    {
        $conversation->loadMissing('session.botSetting');
        $session = $conversation->session;
        $settings = $session?->botSetting;

        if (! $settings || ! $settings->bot_enabled) {
            return;
        }

        // Test mode: only reply to whitelisted numbers (avoid blasting everyone).
        if (! $settings->repliesTo($conversation->chat_id)) {
            return;
        }

        if ($conversation->is_group && ! $settings->reply_to_groups) {
            return;
        }

        // A conversation taken over by a human pauses the bot (Fasa 6).
        if ($conversation->handed_over_at !== null) {
            return;
        }

        // Guided sales-funnel flows take precedence over keyword/AI replies:
        // if a flow starts or advances, it owns this turn.
        if ($this->flow->handle($conversation, (string) $body, $type, $this)) {
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
        // Outside business hours → away message (takes precedence when set).
        if (! $settings->isWithinBusinessHours() && filled($settings->away_message)) {
            return $settings->away_message;
        }

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
        $result = $this->out->sendText($session, $conversation->chat_id, $reply);

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

        CekbotMessageReceived::dispatch($conversation->id, $session->id, 'out');
    }

    /**
     * Send an image (by public URL) as a bot reply, recording it as an outbound
     * image message. Used e.g. for the transfer QR poster.
     */
    public function sendImageReply(CekbotConversation $conversation, string $url, ?string $caption = null): void
    {
        $session = $conversation->session;
        $result = $this->out->sendImage($session, $conversation->chat_id, $url, $caption);

        CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $session->id,
            'waha_message_id' => $result['message_id'] ?? null,
            'direction' => CekbotMessage::DIRECTION_OUT,
            'from_me' => true,
            'type' => 'image',
            'body' => $caption,
            'media_url' => $url,
            'ack' => $result['success'] ? 'sent' : 'failed',
            'sent_by_user_id' => null,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($caption ?: '📷 Gambar', 255),
        ]);

        CekbotMessageReceived::dispatch($conversation->id, $session->id, 'out');
    }
}
