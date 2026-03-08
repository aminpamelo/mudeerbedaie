<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Services\SettingsService;
use InvalidArgumentException;

class WhatsAppManager
{
    private ?WhatsAppProviderInterface $resolvedProvider = null;

    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * Resolve and return the active WhatsApp provider instance.
     *
     * The provider is cached after the first resolution so subsequent
     * calls return the same instance.
     */
    public function provider(): WhatsAppProviderInterface
    {
        if ($this->resolvedProvider) {
            return $this->resolvedProvider;
        }

        $providerName = $this->settings->get('whatsapp_provider', 'onsend');

        $this->resolvedProvider = match ($providerName) {
            'onsend' => $this->createOnsendProvider(),
            'meta' => $this->createMetaCloudProvider(),
            default => throw new InvalidArgumentException("Unknown WhatsApp provider: {$providerName}"),
        };

        return $this->resolvedProvider;
    }

    /**
     * Get the name of the currently resolved provider.
     */
    public function getProviderName(): string
    {
        return $this->provider()->getName();
    }

    /**
     * Create an OnsendProvider instance using settings from the database.
     */
    private function createOnsendProvider(): OnsendProvider
    {
        $config = $this->settings->getWhatsAppConfig();

        return new OnsendProvider(
            apiUrl: $config['api_url'] ?? config('services.onsend.api_url', 'https://onsend.io/api/v1'),
            apiToken: $config['api_token'] ?? config('services.onsend.api_token', ''),
        );
    }

    /**
     * Get a MetaCloudProvider instance (always Meta, regardless of active provider setting).
     */
    public function metaProvider(): MetaCloudProvider
    {
        return $this->createMetaCloudProvider();
    }

    /**
     * Create a MetaCloudProvider instance using settings from the database.
     */
    private function createMetaCloudProvider(): MetaCloudProvider
    {
        return new MetaCloudProvider(
            phoneNumberId: $this->settings->get('meta_phone_number_id', ''),
            accessToken: $this->settings->get('meta_access_token', ''),
            apiVersion: $this->settings->get('meta_api_version', 'v21.0'),
        );
    }
}
