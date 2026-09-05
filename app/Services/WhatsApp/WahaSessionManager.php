<?php

namespace App\Services\WhatsApp;

use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client over the WAHA (WhatsApp HTTP API) *session* endpoints.
 *
 * Where {@see WahaProvider} sends messages through a single configured
 * session, this manager drives the full multi-session lifecycle used by the
 * Cekbot admin page: list / create / start / stop / logout / restart / delete
 * sessions and fetch the QR code for linking a phone number.
 *
 * One WAHA session == one WhatsApp number. Credentials come from the same
 * admin settings that {@see WhatsAppManager} uses (waha_api_url / waha_api_key).
 */
class WahaSessionManager
{
    private string $apiUrl;

    private string $apiKey;

    public function __construct(SettingsService $settings)
    {
        // Cekbot has its own WAHA connection so it can point at a local Docker
        // instance for testing while the legacy shared settings stay on prod.
        $url = $settings->get('cekbot_waha_url') ?: $settings->get('waha_api_url', config('services.waha.api_url', ''));

        $key = $settings->get('cekbot_waha_key');
        if ($key === null) {
            $key = $settings->get('waha_api_key', config('services.waha.api_key', ''));
        }

        $this->apiUrl = rtrim((string) $url, '/');
        $this->apiKey = (string) $key;
    }

    /**
     * Whether the WAHA server URL is configured. The API key is optional —
     * a local WAHA run with WAHA_NO_API_KEY=True needs none.
     */
    public function isConfigured(): bool
    {
        return $this->apiUrl !== '';
    }

    /**
     * The configured server URL (for display only — never leak the key).
     */
    public function serverUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Server version/tier info, or null when unreachable.
     *
     * @return array<string, mixed>|null
     */
    public function version(): ?array
    {
        try {
            $response = $this->client()->get('/api/version');

            return $response->successful() ? (array) $response->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether this server currently allows session-scoped writes (create,
     * QR/auth, lifecycle) for the configured key.
     *
     * Probes a read-only endpoint (`GET /api/{probe}/auth/qr`) on a throwaway
     * session name: a healthy server answers 404/422 (session not found), while
     * a server that gates the write/session-scoped API answers 403 Forbidden.
     * Capability-based (not tier string) so it self-heals the moment the server
     * config is fixed. Unknown/unreachable is treated as allowed (optimistic).
     */
    public function writesAllowed(): bool
    {
        try {
            $response = $this->client()
                ->accept('application/json')
                ->get('/api/'.rawurlencode('__cekbot_probe__').'/auth/qr');

            return $response->status() !== 403;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Fetch a media file hosted on the WAHA server (for proxying to the browser).
     * SSRF-guarded: only URLs on the configured WAHA host are fetched.
     *
     * @return array{body: string, mime: string}|null
     */
    public function fetchMediaBody(string $url): ?array
    {
        if ($this->apiUrl === '' || ! str_starts_with($url, $this->apiUrl)) {
            return null;
        }

        try {
            $headers = $this->apiKey !== '' ? ['X-Api-Key' => $this->apiKey] : [];
            $response = Http::withHeaders($headers)->timeout(25)->get($url);

            if (! $response->successful()) {
                return null;
            }

            return [
                'body' => $response->body(),
                'mime' => $response->header('Content-Type') ?: 'application/octet-stream',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve a human display name for a chat id — a contact's name/pushname, or
     * a group's subject. Returns null when unavailable. GOWS often omits
     * notifyName on the webhook, so we look it up here.
     */
    public function resolveName(string $sessionName, string $chatId): ?string
    {
        try {
            if (str_contains($chatId, '@g.us')) {
                $response = $this->client()->get('/api/'.rawurlencode($sessionName).'/groups/'.rawurlencode($chatId));

                return $response->successful()
                    ? $this->cleanName($response->json()['Name'] ?? $response->json()['name'] ?? $response->json()['subject'] ?? null)
                    : null;
            }

            $response = $this->client()->get('/api/contacts', ['session' => $sessionName, 'contactId' => $chatId]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return $this->cleanName(($data['name'] ?: null) ?? ($data['pushname'] ?: null));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cleanName(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : null;
    }

    /**
     * List every session with its live status. Includes STOPPED sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(): array
    {
        $response = $this->client()->get('/api/sessions', ['all' => 'true']);

        if (! $response->successful()) {
            throw $this->error('list sessions', $response->status(), $response->json());
        }

        return array_values((array) $response->json());
    }

    /**
     * Fetch a single session's status + connected number, or null if it does
     * not exist on the server (404).
     *
     * @return array<string, mixed>|null
     */
    public function getSession(string $name): ?array
    {
        $response = $this->client()->get('/api/sessions/'.rawurlencode($name));

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw $this->error('get session', $response->status(), $response->json());
        }

        return (array) $response->json();
    }

    /**
     * Create a new session and (by default) boot it so it moves toward
     * SCAN_QR_CODE.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function createSession(string $name, array $config = [], bool $start = true): array
    {
        $response = $this->client()->post('/api/sessions', [
            'name' => $name,
            'start' => $start,
            'config' => $config ?: (object) [],
        ]);

        if (! $response->successful()) {
            throw $this->error('create session', $response->status(), $response->json());
        }

        return (array) $response->json();
    }

    public function startSession(string $name): void
    {
        $this->action($name, 'start');
    }

    public function stopSession(string $name): void
    {
        $this->action($name, 'stop');
    }

    /**
     * Log out: drops the stored credentials so the next start requires a fresh
     * QR scan. Used to unlink / swap the number on a session.
     */
    public function logoutSession(string $name): void
    {
        $this->action($name, 'logout');
    }

    public function restartSession(string $name): void
    {
        $this->action($name, 'restart');
    }

    /**
     * Delete a session entirely (logout + stop + remove from the list).
     */
    public function deleteSession(string $name): void
    {
        $response = $this->client()->delete('/api/sessions/'.rawurlencode($name));

        // A missing session is already "deleted" from our point of view.
        if (! $response->successful() && $response->status() !== 404) {
            throw $this->error('delete session', $response->status(), $response->json());
        }
    }

    /**
     * Get the QR code to scan, as a data URI, or null when the session is not
     * in a scannable state (already linked, starting, etc.).
     *
     * QR works on every engine (GOWS/NOWEB/WEBJS); the screenshot endpoint does
     * not, so this is the portable path.
     */
    public function getQrDataUri(string $name): ?string
    {
        $response = $this->client()
            ->accept('application/json')
            ->get('/api/'.rawurlencode($name).'/auth/qr');

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (is_array($data) && ! empty($data['data'])) {
            $mime = $data['mimetype'] ?? 'image/png';

            return 'data:'.$mime.';base64,'.$data['data'];
        }

        // Some versions return a raw image even with the JSON Accept header.
        $body = $response->body();
        if ($body !== '' && str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            return 'data:'.$response->header('Content-Type').';base64,'.base64_encode($body);
        }

        return null;
    }

    /**
     * Request a phone-number pairing code as an alternative to the QR scan.
     * The user enters it in WhatsApp › Linked Devices › Link with phone number.
     *
     * @return array<string, mixed>
     */
    public function requestPairingCode(string $name, string $phoneNumber): array
    {
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);

        $response = $this->client()->post('/api/'.rawurlencode($name).'/auth/request-code', [
            'phoneNumber' => $digits,
        ]);

        if (! $response->successful()) {
            throw $this->error('request pairing code', $response->status(), $response->json());
        }

        return (array) ($response->json() ?: []);
    }

    /**
     * Send a text message from a specific session to a chat id.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendText(string $sessionName, string $chatId, string $text): array
    {
        try {
            $response = $this->client()->post('/api/sendText', [
                'session' => $sessionName,
                'chatId' => $this->chatId($chatId),
                'text' => $text,
            ]);

            $data = $response->json();

            if ($response->successful() && ! empty($data['id'])) {
                return ['success' => true, 'message_id' => is_array($data['id']) ? ($data['id']['_serialized'] ?? null) : $data['id'], 'error' => null];
            }

            return ['success' => false, 'message_id' => null, 'error' => $this->error('send text', $response->status(), $data)->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert a recipient into a WAHA chat id (pass through existing ids).
     */
    private function chatId(string $recipient): string
    {
        if (str_contains($recipient, '@')) {
            return $recipient;
        }

        return preg_replace('/[^0-9]/', '', $recipient).'@c.us';
    }

    /**
     * Ensure a session exists and is running, creating it if necessary.
     * Returns the resulting session payload (status + me).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function ensureStarted(string $name, array $config = []): array
    {
        $session = $this->getSession($name);

        if ($session === null) {
            return $this->createSession($name, $config, true);
        }

        if (($session['status'] ?? null) === 'STOPPED') {
            $this->startSession($name);

            return $this->getSession($name) ?? $session;
        }

        return $session;
    }

    /**
     * POST a lifecycle action under /api/sessions/{name}/{action}.
     */
    private function action(string $name, string $action): void
    {
        $response = $this->client()->post('/api/sessions/'.rawurlencode($name).'/'.$action);

        if (! $response->successful()) {
            throw $this->error($action.' session', $response->status(), $response->json());
        }
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WAHA server URL or API key is not configured.');
        }

        return Http::baseUrl($this->apiUrl)
            ->withHeaders($this->apiKey !== '' ? ['X-Api-Key' => $this->apiKey] : [])
            ->timeout(20)
            ->connectTimeout(10);
    }

    /**
     * Build a readable exception from a failed WAHA response.
     *
     * @param  mixed  $body
     */
    private function error(string $label, int $status, $body): RuntimeException
    {
        $message = null;

        if (is_array($body)) {
            $message = $body['message'] ?? $body['error'] ?? null;
            if (is_array($message)) {
                $message = implode(', ', $message);
            }
        }

        // A bare "Forbidden" from WAHA means the server refused this write with a
        // valid key. WAHA itself is free (Plus was merged into Core in 2026.6),
        // so this is a server-side config issue — rewrite it into something
        // actionable rather than surfacing the raw word.
        if ($status === 403) {
            $message = 'Server WAHA menghalang operasi ini (403 Forbidden). WAHA kini percuma — '
                .'ini biasanya isu konfigurasi server (imej WAHA lama atau API key terhad). '
                .'Kemas kini imej WAHA / guna API key penuh, atau scan melalui dashboard WAHA buat sementara.';
        }

        $message = $message ?: match ($status) {
            401 => 'Unauthorized — check the WAHA API key.',
            404 => 'Not found on the WAHA server.',
            422 => 'WAHA rejected the request (the session may be in the wrong state).',
            default => "WAHA request failed with status {$status}.",
        };

        Log::warning("WAHA: {$label} failed", ['status' => $status, 'message' => $message]);

        return new RuntimeException($message);
    }
}
