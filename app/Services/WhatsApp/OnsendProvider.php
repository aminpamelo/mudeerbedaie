<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnsendProvider implements WhatsAppProviderInterface
{
    public function __construct(
        public string $apiUrl,
        public string $apiToken,
    ) {}

    /**
     * Send a text message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function send(string $phoneNumber, string $message): array
    {
        try {
            $payload = [
                'phone_number' => $phoneNumber,
                'message' => $message,
                'type' => 'text',
            ];

            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("{$this->apiUrl}/send", $payload);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                Log::info('Onsend: text message sent', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['message_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['message_id'] ?? null,
                    'message' => $data['message'] ?? 'Message sent',
                ];
            }

            $errorMessage = $data['message'] ?? 'Unknown error';
            Log::warning('Onsend: text message failed', [
                'phone' => $phoneNumber,
                'error' => $errorMessage,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Onsend: text message exception', [
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
     * Send an image message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendImage(string $phoneNumber, string $imageUrl, ?string $caption = null): array
    {
        try {
            $payload = [
                'phone_number' => $phoneNumber,
                'type' => 'image',
                'url' => $imageUrl,
            ];

            if ($caption) {
                $payload['message'] = $caption;
            }

            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("{$this->apiUrl}/send", $payload);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                Log::info('Onsend: image message sent', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['message_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['message_id'] ?? null,
                    'message' => $data['message'] ?? 'Image sent',
                ];
            }

            $errorMessage = $data['message'] ?? 'Failed to send image';
            Log::warning('Onsend: image message failed', [
                'phone' => $phoneNumber,
                'error' => $errorMessage,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Onsend: image message exception', [
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
     * Send a document (PDF, etc.).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendDocument(string $phoneNumber, string $documentUrl, string $mimeType, ?string $filename = null): array
    {
        try {
            $payload = [
                'phone_number' => $phoneNumber,
                'type' => 'document',
                'url' => $documentUrl,
                'mimetype' => $mimeType,
            ];

            if ($filename) {
                $payload['filename'] = $filename;
            }

            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("{$this->apiUrl}/send", $payload);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                Log::info('Onsend: document sent', [
                    'phone' => $phoneNumber,
                    'message_id' => $data['message_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['message_id'] ?? null,
                    'message' => $data['message'] ?? 'Document sent',
                ];
            }

            $errorMessage = $data['message'] ?? 'Failed to send document';
            Log::warning('Onsend: document send failed', [
                'phone' => $phoneNumber,
                'error' => $errorMessage,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Onsend: document send exception', [
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
     * Send a template message (not supported by Onsend).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array
    {
        return [
            'success' => false,
            'error' => 'Template messages are not supported by Onsend provider',
        ];
    }

    /**
     * Check the provider's connection/device status.
     *
     * @return array{success: bool, status: string, message: string}
     */
    public function checkStatus(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'API token not configured',
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(10)
                ->get("{$this->apiUrl}/status");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'message' => $data['message'] ?? 'Status retrieved',
                ];
            }

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to get device status: '.$response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Onsend: status check failed', ['error' => $e->getMessage()]);

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
        return ! empty($this->apiToken);
    }

    /**
     * Get the provider name identifier.
     */
    public function getName(): string
    {
        return 'onsend';
    }
}
