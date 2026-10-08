<?php

namespace App\Services\Cekbot;

use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Provider-agnostic outbound sender for Cekbot.
 *
 * Every Cekbot send (bot auto-reply, flow reply, manual inbox reply) goes
 * through here. Based on the number's `provider` it routes to WAHA (unofficial)
 * or Meta's official Cloud API, and normalises both to the same result shape
 * ({success, message_id, error}) so callers stay provider-blind.
 */
class CekbotOutbound
{
    public function __construct(private WahaSessionManager $waha) {}

    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendText(CekbotSession $session, string $chatId, string $text): array
    {
        if ($session->isCloudApi()) {
            return $this->normalize(
                $this->cloudProvider($session)->send(self::toPhone($chatId), $text)
            );
        }

        return $this->waha->sendText($session->session_name, $chatId, $text);
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendImage(CekbotSession $session, string $chatId, string $url, ?string $caption = null): array
    {
        if ($session->isCloudApi()) {
            return $this->normalize(
                $this->cloudProvider($session)->sendImage(self::toPhone($chatId), $url, $caption)
            );
        }

        return $this->waha->sendImage($session->session_name, $chatId, $url, $caption);
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendVideo(CekbotSession $session, string $chatId, string $url, ?string $caption = null, string $mimetype = 'video/mp4'): array
    {
        if ($session->isCloudApi()) {
            return $this->normalize(
                $this->cloudProvider($session)->sendVideo(self::toPhone($chatId), $url, $caption)
            );
        }

        return $this->waha->sendVideo($session->session_name, $chatId, $url, $caption, $mimetype);
    }

    /**
     * Show "typing…" before a bot reply. The Cloud API needs the inbound
     * message being answered; without one it is skipped. Best-effort.
     */
    public function showTyping(CekbotSession $session, string $chatId, ?string $inboundMessageId = null): void
    {
        if ($session->isCloudApi()) {
            if (filled($inboundMessageId)) {
                $this->cloudProvider($session)->sendTypingIndicator($inboundMessageId);
            }

            return;
        }

        $this->waha->startTyping((string) $session->session_name, $chatId);
    }

    /**
     * Build the Cloud API client from a number's stored credentials.
     */
    public function cloudProvider(CekbotSession $session): MetaCloudProvider
    {
        return new MetaCloudProvider(
            phoneNumberId: (string) $session->phone_number_id,
            accessToken: (string) $session->access_token,
            apiVersion: $session->resolvedApiVersion(),
        );
    }

    /**
     * Download an inbound media message's file (e.g. a payment receipt). WAHA
     * exposes a URL on its own host; the Cloud API sends a media id that must be
     * resolved to a short-lived URL with the number's access token.
     *
     * @return array{body: string, mime: string}|null
     */
    public function fetchInboundMedia(CekbotSession $session, CekbotMessage $message): ?array
    {
        if (! $session->isCloudApi()) {
            return filled($message->media_url) ? $this->waha->fetchMediaBody($message->media_url) : null;
        }

        $payload = $message->payload ?? [];
        $mediaId = data_get($payload, ($payload['type'] ?? 'image').'.id');

        if (blank($mediaId) || blank($session->access_token)) {
            return null;
        }

        try {
            $meta = Http::withToken((string) $session->access_token)->timeout(20)
                ->get('https://graph.facebook.com/'.$session->resolvedApiVersion().'/'.$mediaId);

            $url = $meta->json('url');
            if (! $meta->successful() || blank($url)) {
                return null;
            }

            $file = Http::withToken((string) $session->access_token)->timeout(30)->get($url);

            if (! $file->successful()) {
                return null;
            }

            return [
                'body' => $file->body(),
                'mime' => $meta->json('mime_type') ?: ($file->header('Content-Type') ?: 'application/octet-stream'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert a WAHA chat id ("60123456789@c.us") — or a bare number — into the
     * digits-only international form the Cloud API expects as `to`.
     */
    public static function toPhone(string $chatId): string
    {
        return ltrim(Str::before($chatId, '@'), '+');
    }

    /**
     * The Cloud provider returns {success, message_id, message, error}; keep only
     * the fields Cekbot's callers read so both transports look identical.
     *
     * @param  array<string, mixed>  $result
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    private function normalize(array $result): array
    {
        return [
            'success' => (bool) ($result['success'] ?? false),
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }
}
