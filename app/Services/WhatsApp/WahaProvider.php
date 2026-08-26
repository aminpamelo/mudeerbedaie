<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaProvider implements WhatsAppProviderInterface
{
    public function __construct(
        public string $apiUrl,
        public string $apiKey,
        public string $session = 'default',
    ) {}

    /**
     * Send a text message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function send(string $phoneNumber, string $message): array
    {
        return $this->sendRequest('/api/sendText', [
            'session' => $this->session,
            'chatId' => $this->chatId($phoneNumber),
            'text' => $message,
        ], 'text message', $phoneNumber, 'Message sent');
    }

    /**
     * Send an image message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendImage(string $phoneNumber, string $imageUrl, ?string $caption = null): array
    {
        $payload = [
            'session' => $this->session,
            'chatId' => $this->chatId($phoneNumber),
            'file' => [
                'url' => $imageUrl,
                'mimetype' => $this->guessMimeType($imageUrl, 'image/jpeg'),
                'filename' => basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'image.jpg'),
            ],
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        return $this->sendRequest('/api/sendImage', $payload, 'image message', $phoneNumber, 'Image sent');
    }

    /**
     * Send a document (PDF, etc.).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendDocument(string $phoneNumber, string $documentUrl, string $mimeType, ?string $filename = null): array
    {
        $payload = [
            'session' => $this->session,
            'chatId' => $this->chatId($phoneNumber),
            'file' => [
                'url' => $documentUrl,
                'mimetype' => $mimeType,
                'filename' => $filename ?: basename(parse_url($documentUrl, PHP_URL_PATH) ?: 'document'),
            ],
        ];

        return $this->sendRequest('/api/sendFile', $payload, 'document', $phoneNumber, 'Document sent');
    }

    /**
     * Send a template message (not supported by WAHA — it is an unofficial API).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array
    {
        return [
            'success' => false,
            'error' => 'Template messages are not supported by WAHA provider',
        ];
    }

    /**
     * Check the provider's connection/device status.
     *
     * WAHA reports the session lifecycle state; only WORKING means the linked
     * phone number is authenticated and able to send.
     *
     * @return array{success: bool, status: string, message: string}
     */
    public function checkStatus(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'WAHA server URL or API key not configured',
            ];
        }

        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(10)
                ->get($this->url("/api/sessions/{$this->session}"));

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => "Session '{$this->session}' does not exist on the WAHA server",
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Failed to get session status: '.$response->status(),
                ];
            }

            $data = $response->json();
            $sessionStatus = $data['status'] ?? 'UNKNOWN';
            $connectedNumber = $data['me']['id'] ?? null;

            return match ($sessionStatus) {
                'WORKING' => [
                    'success' => true,
                    'status' => 'connected',
                    'message' => $connectedNumber
                        ? 'Connected as '.str_replace('@c.us', '', $connectedNumber)
                        : 'Session is working',
                ],
                'SCAN_QR_CODE' => [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Waiting for QR code scan — open the WAHA dashboard to link a phone number',
                ],
                'STARTING' => [
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Session is starting up, try again in a moment',
                ],
                default => [
                    'success' => false,
                    'status' => 'error',
                    'message' => "Session status: {$sessionStatus}",
                ],
            };
        } catch (\Exception $e) {
            Log::error('WAHA: status check failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Whether this provider is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiUrl) && ! empty($this->apiKey);
    }

    /**
     * Get the provider name identifier.
     */
    public function getName(): string
    {
        return 'waha';
    }

    /**
     * POST a payload to WAHA and normalise the response into the interface shape.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    private function sendRequest(string $path, array $payload, string $label, string $phoneNumber, string $successMessage): array
    {
        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(30)
                ->post($this->url($path), $payload);

            $data = $response->json();

            // WAHA returns the sent message object with an `id` field on success.
            if ($response->successful() && ! empty($data['id'])) {
                Log::info("WAHA: {$label} sent", [
                    'phone' => $phoneNumber,
                    'message_id' => $data['id'],
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['id'],
                    'message' => $successMessage,
                ];
            }

            $errorMessage = $this->extractError($data, $response->status());
            Log::warning("WAHA: {$label} failed", [
                'phone' => $phoneNumber,
                'error' => $errorMessage,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error("WAHA: {$label} exception", [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build an absolute WAHA URL, tolerating a trailing slash on the base URL.
     */
    private function url(string $path): string
    {
        return rtrim($this->apiUrl, '/').$path;
    }

    /**
     * Convert a recipient into a WAHA chat ID.
     *
     * Group IDs (`...@g.us`) and contact IDs (`...@c.us`) are already chat IDs
     * and pass through untouched; anything else is treated as a phone number.
     */
    private function chatId(string $recipient): string
    {
        if (str_contains($recipient, '@')) {
            return $recipient;
        }

        $digits = preg_replace('/[^0-9]/', '', $recipient);

        return $digits.'@c.us';
    }

    /**
     * Pull a human-readable error out of a WAHA error response.
     */
    private function extractError(?array $data, int $status): string
    {
        if (is_array($data)) {
            $message = $data['message'] ?? $data['error'] ?? null;

            if (is_array($message)) {
                $message = implode(', ', $message);
            }

            if (! empty($message)) {
                return (string) $message;
            }
        }

        return match ($status) {
            401 => 'Unauthorized — check the WAHA API key',
            404 => 'Not found — check the WAHA server URL and session name',
            422 => 'WAHA rejected the request (session may not be connected)',
            default => "Request failed with status {$status}",
        };
    }

    /**
     * Best-effort MIME type from a URL extension.
     */
    private function guessMimeType(string $url, string $fallback): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $fallback,
        };
    }
}
