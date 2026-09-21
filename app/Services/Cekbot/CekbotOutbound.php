<?php

namespace App\Services\Cekbot;

use App\Models\CekbotSession;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsApp\WahaSessionManager;
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
