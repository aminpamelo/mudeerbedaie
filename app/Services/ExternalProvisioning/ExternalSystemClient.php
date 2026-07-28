<?php

namespace App\Services\ExternalProvisioning;

use App\Models\ExternalSystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        return $this->send($system, 'POST', $system->provision_path, $payload);
    }

    /**
     * List the plans/packages the external system offers (for the plan picker).
     *
     * @return array<int, mixed>
     */
    public function packages(ExternalSystem $system): array
    {
        $response = $this->send($system, 'GET', $this->apiBase($system).'/packages');

        return $response['packages'] ?? $response['data'] ?? $response;
    }

    /**
     * Read the live state of a buyer's account (plan, active/inactive, expiry).
     *
     * @return array<string, mixed>
     */
    public function accountStatus(ExternalSystem $system, string $email): array
    {
        return $this->send($system, 'POST', $this->apiBase($system).'/accounts/status', ['email' => $email]);
    }

    /**
     * Move a buyer onto another plan on the external system.
     *
     * @return array<string, mixed>
     */
    public function changePlan(ExternalSystem $system, string $email, string $plan, ?string $orderRef = null): array
    {
        return $this->send($system, 'POST', $this->apiBase($system).'/accounts/change-plan', array_filter([
            'email' => $email,
            'plan' => $plan,
            'order_ref' => $orderRef,
        ], fn ($value): bool => $value !== null));
    }

    /**
     * Withdraw a buyer's access on the external system (e.g. on refund).
     *
     * @return array<string, mixed>
     */
    public function revoke(ExternalSystem $system, string $email, ?string $reason = null): array
    {
        return $this->send($system, 'POST', $this->apiBase($system).'/accounts/revoke', array_filter([
            'email' => $email,
            'reason' => $reason,
        ], fn ($value): bool => $value !== null));
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
     * Send a signed request to the external system and return the parsed body.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    protected function send(ExternalSystem $system, string $method, string $path, ?array $payload = null): array
    {
        $body = $payload !== null
            ? (json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}')
            : '';

        $request = $this->request($system, $body);
        $url = $system->url($path);

        $response = $method === 'GET'
            ? $request->get($url)->throw()
            : $request->withBody($body, 'application/json')->post($url)->throw();

        return $response->json() ?? [];
    }

    /**
     * The external system's API base, derived from the provision path.
     * e.g. "/api/v1/provision" -> "/api/v1".
     */
    protected function apiBase(ExternalSystem $system): string
    {
        return '/'.trim(Str::beforeLast($system->provision_path, '/'), '/');
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
