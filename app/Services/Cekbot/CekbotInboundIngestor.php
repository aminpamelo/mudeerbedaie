<?php

namespace App\Services\Cekbot;

use App\Events\Cekbot\CekbotMessageReceived;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Illuminate\Support\Str;

/**
 * Provider-agnostic inbound pipeline for Cekbot.
 *
 * Stores one normalised message and, for genuine inbound, hands it to the bot
 * engine. Shared by the WAHA and Cloud API webhook jobs so an official number
 * gets the exact same behaviour as a WAHA one: dedupe → conversation upsert →
 * message store → realtime event → bot (flows / auto-reply / AI / handover).
 */
class CekbotInboundIngestor
{
    /**
     * @param  array{
     *     provider_message_id: ?string,
     *     from_me: bool,
     *     chat_id: ?string,
     *     name: ?string,
     *     type: string,
     *     body: ?string,
     *     media_url: ?string,
     *     media_mime: ?string,
     *     timestamp: ?int,
     *     is_group: bool,
     *     payload: array<string, mixed>,
     * }  $msg
     * @param  (callable(): ?string)|null  $resolveName  Lazily fetch a display
     *                                                   name only when the conversation has none — avoids a provider API
     *                                                   call on every message. WAHA uses it; Cloud passes `name` directly.
     */
    public function ingest(CekbotSession $session, array $msg, CekbotBotService $bot, ?callable $resolveName = null): void
    {
        $providerId = $msg['provider_message_id'] ?? null;

        // Idempotency — providers retry deliveries.
        if ($providerId && CekbotMessage::query()->where('waha_message_id', $providerId)->exists()) {
            return;
        }

        $chatId = $msg['chat_id'] ?? null;

        if (! $chatId) {
            return;
        }

        // Skip WhatsApp Status / broadcast / channel noise — not real chats.
        if ($chatId === 'status@broadcast' || str_contains($chatId, '@newsletter') || str_ends_with($chatId, '@broadcast')) {
            return;
        }

        $fromMe = (bool) ($msg['from_me'] ?? false);
        $type = $msg['type'] ?? 'text';
        $body = $msg['body'] ?? null;

        $conversation = CekbotConversation::query()->firstOrCreate(
            ['cekbot_session_id' => $session->id, 'chat_id' => $chatId],
            ['is_group' => (bool) ($msg['is_group'] ?? false)],
        );

        // Fill a display name once — provided name, else a lazy provider lookup.
        if (blank($conversation->name)) {
            $name = $msg['name'] ?? null;
            if (blank($name) && $resolveName) {
                $name = $resolveName();
            }
            if (filled($name)) {
                $conversation->name = $name;
            }
        }

        CekbotMessage::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_session_id' => $session->id,
            'waha_message_id' => $providerId,
            'direction' => $fromMe ? CekbotMessage::DIRECTION_OUT : CekbotMessage::DIRECTION_IN,
            'from_me' => $fromMe,
            'type' => $type,
            'body' => $body,
            'media_url' => $msg['media_url'] ?? null,
            'media_mime' => $msg['media_mime'] ?? null,
            'payload' => $msg['payload'] ?? [],
            'sent_at' => isset($msg['timestamp']) ? now()->setTimestamp((int) $msg['timestamp']) : now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($body ?: $this->mediaLabel($type), 255),
            'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
        ])->save();

        CekbotMessageReceived::dispatch($conversation->id, $session->id, $fromMe ? 'out' : 'in');

        // Only genuine inbound (not our own echoed sends) trigger the bot.
        if (! $fromMe) {
            $bot->handleIncoming($conversation->fresh(), $body, $type);
        }
    }

    private function mediaLabel(string $type): string
    {
        return match ($type) {
            'image' => '📷 Gambar',
            'video' => '🎥 Video',
            'audio' => '🎙️ Audio',
            'document' => '📄 Dokumen',
            'location' => '📍 Lokasi',
            'contact' => '👤 Kad hubungan',
            default => '💬 Mesej',
        };
    }
}
