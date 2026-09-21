<?php

namespace App\Jobs;

use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\Cekbot\CekbotBotService;
use App\Services\Cekbot\CekbotInboundIngestor;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a single WAHA webhook payload for Cekbot: stores inbound messages,
 * keeps session status in sync, updates delivery acks, and hands new inbound
 * messages to the bot engine for a possible auto-reply.
 *
 * WAHA-specific payload parsing lives here; the actual storing + bot dispatch is
 * delegated to CekbotInboundIngestor, shared with the official Cloud API job.
 */
class ProcessCekbotWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public array $event) {}

    public function handle(CekbotBotService $bot, CekbotInboundIngestor $ingestor): void
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
            'message', 'message.any' => $this->handleMessage($session, $payload, $bot, $ingestor),
            'session.status' => $this->handleSessionStatus($session, $payload),
            'message.ack' => $this->handleAck($session, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleMessage(CekbotSession $session, array $payload, CekbotBotService $bot, CekbotInboundIngestor $ingestor): void
    {
        $fromMe = (bool) ($payload['fromMe'] ?? false);
        $chatId = $fromMe
            ? ($payload['to'] ?? $payload['chatId'] ?? null)
            : ($payload['from'] ?? $payload['chatId'] ?? null);

        if (! $chatId) {
            return;
        }

        $ingestor->ingest(
            $session,
            [
                'provider_message_id' => $payload['id'] ?? null,
                'from_me' => $fromMe,
                'chat_id' => $chatId,
                'name' => $payload['notifyName'] ?? ($payload['_data']['notifyName'] ?? null),
                'type' => $this->resolveType($payload),
                'body' => $this->extractBody($payload),
                'media_url' => $payload['mediaUrl'] ?? ($payload['media']['url'] ?? null),
                'media_mime' => $payload['mimetype'] ?? ($payload['media']['mimetype'] ?? null),
                'timestamp' => isset($payload['timestamp']) ? (int) $payload['timestamp'] : null,
                'is_group' => str_contains((string) $chatId, '@g.us'),
                'payload' => $payload,
            ],
            $bot,
            // GOWS usually omits notifyName; resolve lazily via WAHA only if the
            // conversation still has no name.
            fn () => app(WahaSessionManager::class)->resolveName($session->session_name, $chatId),
        );
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
}
