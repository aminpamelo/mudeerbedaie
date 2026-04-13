<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCustomHostnameService
{
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    private function client(): PendingRequest
    {
        return Http::withToken(config('services.cloudflare.api_token'))
            ->acceptJson()
            ->asJson();
    }

    /**
     * Ensure Cloudflare API credentials are configured before making any calls.
     */
    private function ensureConfigured(): void
    {
        if (empty(config('services.cloudflare.api_token')) || empty(config('services.cloudflare.zone_id'))) {
            throw new \RuntimeException(
                'Cloudflare for SaaS is not configured. Please set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID in your .env file.'
            );
        }
    }

    private function zoneId(): string
    {
        return config('services.cloudflare.zone_id') ?? '';
    }

    /**
     * Register a custom hostname with Cloudflare for SaaS.
     *
     * @return array{id: string, status: string, ssl_status: string, verification_errors: array}
     */
    public function createHostname(string $domain): array
    {
        $this->ensureConfigured();

        $response = $this->client()->post(
            "{$this->baseUrl}/zones/{$this->zoneId()}/custom_hostnames",
            [
                'hostname' => $domain,
                'ssl' => [
                    'method' => 'http',
                    'type' => 'dv',
                    'settings' => [
                        'min_tls_version' => '1.2',
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            Log::error('Cloudflare createHostname failed', [
                'domain' => $domain,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new \RuntimeException(
                'Failed to create custom hostname: '.($response->json('errors.0.message') ?? 'Unknown error')
            );
        }

        $result = $response->json('result');

        return [
            'id' => $result['id'],
            'status' => $result['status'],
            'ssl_status' => $result['ssl']['status'] ?? 'pending',
            'verification_errors' => $result['verification_errors'] ?? [],
        ];
    }

    /**
     * Get the current status of a custom hostname.
     *
     * @return array{status: string, ssl_status: string, verification_errors: array}
     */
    public function getHostnameStatus(string $hostnameId): array
    {
        $this->ensureConfigured();

        $response = $this->client()->get(
            "{$this->baseUrl}/zones/{$this->zoneId()}/custom_hostnames/{$hostnameId}"
        );

        if (! $response->successful()) {
            Log::error('Cloudflare getHostnameStatus failed', [
                'hostname_id' => $hostnameId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new \RuntimeException(
                'Failed to get hostname status: '.($response->json('errors.0.message') ?? 'Unknown error')
            );
        }

        $result = $response->json('result');

        return [
            'status' => $result['status'],
            'ssl_status' => $result['ssl']['status'] ?? 'pending',
            'verification_errors' => $result['verification_errors'] ?? [],
        ];
    }

    /**
     * Delete a custom hostname from Cloudflare.
     */
    public function deleteHostname(string $hostnameId): bool
    {
        $this->ensureConfigured();

        $response = $this->client()->delete(
            "{$this->baseUrl}/zones/{$this->zoneId()}/custom_hostnames/{$hostnameId}"
        );

        if (! $response->successful()) {
            Log::error('Cloudflare deleteHostname failed', [
                'hostname_id' => $hostnameId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }
}
