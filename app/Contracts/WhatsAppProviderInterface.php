<?php

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    /**
     * Send a text message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function send(string $phoneNumber, string $message): array;

    /**
     * Send an image message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendImage(string $phoneNumber, string $imageUrl, ?string $caption = null): array;

    /**
     * Send a document (PDF, etc.).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendDocument(string $phoneNumber, string $documentUrl, string $mimeType, ?string $filename = null): array;

    /**
     * Send a template message (Meta Cloud API only — Onsend returns unsupported).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array;

    /**
     * Check the provider's connection/device status.
     *
     * @return array{success: bool, status: string, message: string}
     */
    public function checkStatus(): array;

    /**
     * Whether this provider is properly configured (has credentials, etc.).
     */
    public function isConfigured(): bool;

    /**
     * Get the provider name identifier.
     */
    public function getName(): string;
}
