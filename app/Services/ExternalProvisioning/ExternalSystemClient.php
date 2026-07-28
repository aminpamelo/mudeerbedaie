<?php

namespace App\Services\ExternalProvisioning;

use App\Models\ExternalSystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ExternalSystemClient
{
    /**
     * Provision an account on the external system.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> Parsed JSON response body.
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function provision(ExternalSystem $system, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $response = $this->request($system, $body)
            ->withBody($body, 'application/json')
            ->post($system->provisionUrl())
            ->throw();

        return $response->json() ?? [];
    }

    /**
     * Lightweight connectivity check for the admin "Test connection" button.
     *
     * @return array{success: bool, status?: int, error?: string}
     */
    public function testConnection(ExternalSystem $system): array
    {
        try {
            $body = json_encode(['test' => true], JSON_UNESCAPED_SLASHES) ?: '{}';

            $response = $this->request($system, $body)
                ->withBody($body, 'application/json')
                ->post($system->provisionUrl());

            // Any reachable, authenticated response (incl. a 422 validation reply
            // to the dummy payload) proves connectivity.
            return [
                'success' => $response->successful() || $response->status() === 422,
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the authenticated pending request. The HMAC signature is computed
     * over the exact serialized body so the receiver can verify integrity.
     */
    protected function request(ExternalSystem $system, string $body): PendingRequest
    {
        $request = Http::acceptJson()->timeout($system->timeout ?: 30);

        if ($system->usesBearer() && $system->api_key) {
            $request = $request->withToken($system->api_key);
        }

        if ($system->usesSignature() && $system->signing_secret) {
            $request = $request->withHeaders([
                'X-Signature' => hash_hmac('sha256', $body, $system->signing_secret),
            ]);
        }

        return $request;
    }
}
