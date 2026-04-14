<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerAvatarDomainService
{
    private string $baseUrl = 'https://api.serveravatar.com';

    private function client(): PendingRequest
    {
        return Http::withToken(config('services.serveravatar.api_token'))
            ->acceptJson()
            ->asJson();
    }

    private function isConfigured(): bool
    {
        return ! empty(config('services.serveravatar.api_token'))
            && ! empty(config('services.serveravatar.organization_id'))
            && ! empty(config('services.serveravatar.server_id'))
            && ! empty(config('services.serveravatar.application_id'));
    }

    private function applicationDomainsUrl(): string
    {
        $orgId = config('services.serveravatar.organization_id');
        $serverId = config('services.serveravatar.server_id');
        $appId = config('services.serveravatar.application_id');

        return "{$this->baseUrl}/organizations/{$orgId}/servers/{$serverId}/applications/{$appId}/application-domains";
    }

    /**
     * Register a domain with ServerAvatar so the web server accepts requests for it.
     */
    public function addDomain(string $domain): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('ServerAvatar not configured, skipping domain registration', ['domain' => $domain]);

            return false;
        }

        try {
            $response = $this->client()->post($this->applicationDomainsUrl(), [
                'domain' => $domain,
            ]);

            if ($response->successful()) {
                Log::info('Domain registered with ServerAvatar', [
                    'domain' => $domain,
                    'id' => $response->json('application_domain.id'),
                ]);

                return true;
            }

            Log::error('ServerAvatar addDomain failed', [
                'domain' => $domain,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('ServerAvatar addDomain exception', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove a domain from ServerAvatar.
     */
    public function removeDomain(string $domain): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $domainId = $this->findDomainId($domain);

            if (! $domainId) {
                Log::info('Domain not found in ServerAvatar, nothing to remove', ['domain' => $domain]);

                return true;
            }

            $response = $this->client()->delete("{$this->applicationDomainsUrl()}/{$domainId}");

            if ($response->successful()) {
                Log::info('Domain removed from ServerAvatar', ['domain' => $domain]);

                return true;
            }

            Log::error('ServerAvatar removeDomain failed', [
                'domain' => $domain,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('ServerAvatar removeDomain exception', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Find the ServerAvatar domain ID by domain name.
     */
    private function findDomainId(string $domain): ?int
    {
        $page = 1;

        do {
            $response = $this->client()->get($this->applicationDomainsUrl(), ['page' => $page]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json('applicationDomains');

            foreach ($data['data'] as $appDomain) {
                if ($appDomain['domain'] === $domain) {
                    return $appDomain['id'];
                }
            }

            $page++;
        } while ($page <= $data['last_page']);

        return null;
    }
}
