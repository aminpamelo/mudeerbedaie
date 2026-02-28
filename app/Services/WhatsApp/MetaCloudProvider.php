<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudProvider implements WhatsAppProviderInterface
{
    public function __construct(
        public string $phoneNumberId,
        public string $accessToken,
        public string $apiVersion = 'v21.0',
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
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => ['body' => $message],
            ];

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($this->baseUrl(), $payload);

            return $this->parseResponse($response, 'text', $phoneNumber);
        } catch (\Exception $e) {
            Log::error('Meta Cloud API: text message exception', [
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
            $image = ['link' => $imageUrl];

            if ($caption !== null) {
                $image['caption'] = $caption;
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'image',
                'image' => $image,
            ];

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($this->baseUrl(), $payload);

            return $this->parseResponse($response, 'image', $phoneNumber);
        } catch (\Exception $e) {
            Log::error('Meta Cloud API: image message exception', [
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
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'document',
                'document' => [
                    'link' => $documentUrl,
                    'filename' => $filename ?? 'document',
                ],
            ];

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($this->baseUrl(), $payload);

            return $this->parseResponse($response, 'document', $phoneNumber);
        } catch (\Exception $e) {
            Log::error('Meta Cloud API: document send exception', [
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
     * Send a template message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $language],
                    'components' => $components,
                ],
            ];

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post($this->baseUrl(), $payload);

            return $this->parseResponse($response, 'template', $phoneNumber);
        } catch (\Exception $e) {
            Log::error('Meta Cloud API: template message exception', [
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
                'message' => 'Meta Cloud API credentials not configured',
            ];
        }

        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Meta Cloud API connected',
                ];
            }

            $errorMessage = $response->json('error.message', 'Failed to connect to Meta Cloud API');

            return [
                'success' => false,
                'status' => 'error',
                'message' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Meta Cloud API: status check failed', ['error' => $e->getMessage()]);

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
        return ! empty($this->phoneNumberId) && ! empty($this->accessToken);
    }

    /**
     * Get the provider name identifier.
     */
    public function getName(): string
    {
        return 'meta';
    }

    /**
     * Get the base URL for sending messages.
     */
    private function baseUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
    }

    /**
     * Parse the Meta API response into a standard return shape.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    private function parseResponse(\Illuminate\Http\Client\Response $response, string $type, string $phoneNumber): array
    {
        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');

            Log::info("Meta Cloud API: {$type} message sent", [
                'phone' => $phoneNumber,
                'message_id' => $messageId,
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'message' => 'Message sent',
            ];
        }

        $errorMessage = $response->json('error.message', 'Unknown error');

        Log::warning("Meta Cloud API: {$type} message failed", [
            'phone' => $phoneNumber,
            'error' => $errorMessage,
            'status' => $response->status(),
        ]);

        return [
            'success' => false,
            'error' => $errorMessage,
        ];
    }
}
