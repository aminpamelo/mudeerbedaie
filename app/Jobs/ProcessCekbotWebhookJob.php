<?php

namespace App\Jobs;

use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\Cekbot\CekbotBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Processes a single WAHA webhook payload for Cekbot: stores inbound messages,
 * keeps session status in sync, updates delivery acks, and hands new inbound
 * messages to the bot engine for a possible auto-reply.
 */
class ProcessCekbotWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public array $event) {}

    public function handle(CekbotBotService $bot): void
    {
        $event = $this->event;
        $type = $event['event'] ?? null;
        $sessionName = $event['session'] ?? null;
        $payload = $event['payload'] ?? [];

        if (! $sessionName) {
            return;
        }

        $session = CekbotSession::query()->where('session_name', $sessionName)->first();

        if (! $session) {
            return;
        }

        match ($type) {
            'message', 'message.any' => $this->handleMessage($session, $payload, $bot),
            'session.status' => $this->handleSessionStatus($session, $payload),
            'message.ack' => $this->handleAck($session, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleMessage(CekbotSession $session, array $payload, CekbotBotService $bot): void
    {
        $wahaId = $payload['id'] ?? null;

        // Idempotency — WAHA can retry deliveries.
        if ($wahaId && CekbotMessage::query()->where('waha_message_id', $wahaId)->exists()) {
            return;
        }

        $fromMe = (bool) ($payload['fromMe'] ?? false);
        $chatId = $fromMe
            ? ($payload['to'] ?? $payload['chatId'] ?? null)
            : ($payload['from'] ?? $payload['chatId'] ?? null);

        if (! $chatId) {
            return;
        }

        $isGroup = str_contains((string) $chatId, '@g.us');
        $body = $this->extractBody($payload);
        $type = $this->normaliseType($payload['type'] ?? 'text');

        $conversation = CekbotConversation::query()->firstOrCreate(
            ['cekbot_session_id' => $session->id, 'chat_id' => $chatId],
            ['is_group' => $isGroup, 'name' => $payload['notifyName'] ?? null],
        );

        if (! $conversation->name && ! empty($payload['notifyName'])) {
            $conversation->name = $payload['notifyName'];
        }

        CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $session->id,
            'waha_message_id' => $wahaId,
            'direction' => $fromMe ? CekbotMessage::DIRECTION_OUT : CekbotMessage::DIRECTION_IN,
            'from_me' => $fromMe,
            'type' => $type,
            'body' => $body,
            'media_url' => $payload['mediaUrl'] ?? ($payload['media']['url'] ?? null),
            'media_mime' => $payload['mimetype'] ?? ($payload['media']['mimetype'] ?? null),
            'payload' => $payload,
            'sent_at' => isset($payload['timestamp']) ? now()->setTimestamp((int) $payload['timestamp']) : now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($body ?: '['.$type.']', 255),
            'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
        ])->save();

        // Only genuine inbound (not our own echoed sends) trigger the bot.
        if (! $fromMe) {
            $bot->handleIncoming($conversation->fresh(), $body, $type);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSessionStatus(CekbotSession $session, array $payload): void
    {
        $status = $payload['status'] ?? null;

        if ($status) {
            $session->update([
                'status' => $status,
                'last_synced_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleAck(CekbotSession $session, array $payload): void
    {
        $wahaId = $payload['id'] ?? null;
        $ack = $payload['ackName'] ?? ($payload['ack'] ?? null);

        if ($wahaId && $ack !== null) {
            CekbotMessage::query()
                ->where('waha_message_id', $wahaId)
                ->update(['ack' => (string) $ack]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBody(array $payload): ?string
    {
        return $payload['body']
            ?? ($payload['text'] ?? ($payload['caption'] ?? null));
    }

    private function normaliseType(string $type): string
    {
        return match ($type) {
            'chat', 'text' => 'text',
            default => $type,
        };
    }
}
