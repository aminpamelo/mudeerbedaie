<?php

namespace App\Jobs;

use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\Cekbot\CekbotBotService;
use App\Services\WhatsApp\WahaSessionManager;
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

        // Skip WhatsApp Status / broadcast / channel noise — not real chats.
        if ($chatId === 'status@broadcast' || str_contains($chatId, '@newsletter') || str_ends_with($chatId, '@broadcast')) {
            return;
        }

        $isGroup = str_contains((string) $chatId, '@g.us');
        $type = $this->resolveType($payload);
        $body = $this->extractBody($payload);

        $conversation = CekbotConversation::query()->firstOrCreate(
            ['cekbot_session_id' => $session->id, 'chat_id' => $chatId],
            ['is_group' => $isGroup],
        );

        // Fill a display name once — notifyName if present, else resolve via WAHA
        // (GOWS usually omits notifyName; contacts/groups endpoints have it).
        if (blank($conversation->name)) {
            $name = $payload['notifyName'] ?? ($payload['_data']['notifyName'] ?? null);
            if (blank($name)) {
                $name = app(WahaSessionManager::class)->resolveName($session->session_name, $chatId);
            }
            if (filled($name)) {
                $conversation->name = $name;
            }
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
            'last_message_preview' => Str::limit($body ?: $this->mediaLabel($type), 255),
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

    /**
     * Derive a message type from the payload (text / image / video / audio /
     * document / location / contact).
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveType(array $payload): string
    {
        if (! empty($payload['location'])) {
            return 'location';
        }

        if (! empty($payload['vCards'])) {
            return 'contact';
        }

        if (! empty($payload['hasMedia']) || ! empty($payload['media']['mimetype'])) {
            $mime = (string) ($payload['media']['mimetype'] ?? $payload['mimetype'] ?? '');

            return match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                $mime !== '' => 'document',
                default => 'document',
            };
        }

        $type = (string) ($payload['type'] ?? 'text');

        return in_array($type, ['chat', 'text'], true) ? 'text' : $type;
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
