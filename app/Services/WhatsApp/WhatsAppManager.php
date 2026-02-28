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
     * Create a MetaCloudProvider instance.
     *
     * @throws \RuntimeException MetaCloudProvider is not yet implemented.
     */
    private function createMetaCloudProvider(): WhatsAppProviderInterface
    {
        throw new \RuntimeException('MetaCloudProvider not yet implemented. Complete Task 2.1 first.');
    }
}
