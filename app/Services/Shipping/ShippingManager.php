<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProvider;
use App\DTOs\Shipping\ShippingRateRequest;
use InvalidArgumentException;

class ShippingManager
{
    /** @var array<string, ShippingProvider> */
    private array $providers = [];

    public function registerProvider(ShippingProvider $provider): void
    {
        $this->providers[$provider->getProviderSlug()] = $provider;
    }

    public function getProvider(string $slug): ShippingProvider
    {
        if (! isset($this->providers[$slug])) {
            throw new InvalidArgumentException("Shipping provider [{$slug}] not found.");
        }

        return $this->providers[$slug];
    }

    /**
     * @return array<string, ShippingProvider>
     */
    public function getEnabledProviders(): array
    {
        return array_filter(
            $this->providers,
            fn (ShippingProvider $provider) => $provider->isEnabled() && $provider->isConfigured()
        );
    }

    /**
     * @return array<string, ShippingProvider>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get shipping rates from all enabled providers.
     *
     * @return \App\DTOs\Shipping\ShippingRate[]
     */
    public function getRatesFromAllProviders(ShippingRateRequest $request): array
    {
        $rates = [];

        foreach ($this->getEnabledProviders() as $provider) {
            try {
                $providerRates = $provider->getRates($request);
                $rates = array_merge($rates, $providerRates);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to get rates from {$provider->getProviderName()}: {$e->getMessage()}");
            }
        }

        return $rates;
    }
}
