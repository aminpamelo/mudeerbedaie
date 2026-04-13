<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\Funnel;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomDomainController extends Controller
{
    public function __construct(
        private CloudflareCustomHostnameService $cloudflare
    ) {}

    /**
     * Get the custom domain for a funnel.
     */
    public function show(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $domain = $funnel->customDomain;

        if (! $domain) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'uuid' => $domain->uuid,
                'domain' => $domain->domain,
                'full_domain' => $domain->full_domain,
                'type' => $domain->type,
                'verification_status' => $domain->verification_status,
                'ssl_status' => $domain->ssl_status,
                'verification_errors' => $domain->verification_errors,
                'is_active' => $domain->isActive(),
                'cname_target' => config('services.cloudflare.cname_target'),
                'verified_at' => $domain->verified_at?->toISOString(),
                'ssl_active_at' => $domain->ssl_active_at?->toISOString(),
                'created_at' => $domain->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Add a custom domain to a funnel.
     */
    public function store(Request $request, string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();

        if ($funnel->customDomain) {
            return response()->json([
                'message' => 'This funnel already has a custom domain. Remove it first.',
            ], 422);
        }

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:custom_domains,domain',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
            ],
            'type' => ['required', Rule::in(['custom', 'subdomain'])],
        ]);

        $domain = $validated['domain'];
        $type = $validated['type'];

        if ($type === 'subdomain') {
            $request->validate([
                'domain' => 'alpha_dash:ascii|max:63',
            ]);
        }

        $customDomain = new CustomDomain([
            'funnel_id' => $funnel->id,
            'user_id' => $request->user()->id,
            'domain' => $domain,
            'type' => $type,
        ]);

        if ($type === 'custom') {
            try {
                $result = $this->cloudflare->createHostname($domain);
                $customDomain->cloudflare_hostname_id = $result['id'];
                $customDomain->verification_status = 'pending';
                $customDomain->ssl_status = 'pending';
            } catch (\Exception $e) {
                Log::error('Failed to register custom hostname', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Failed to register domain with Cloudflare. Please try again.',
                ], 500);
            }
        } else {
            $customDomain->verification_status = 'active';
            $customDomain->ssl_status = 'active';
            $customDomain->verified_at = now();
            $customDomain->ssl_active_at = now();
        }

        $customDomain->save();

        return response()->json([
            'data' => [
                'uuid' => $customDomain->uuid,
                'domain' => $customDomain->domain,
                'full_domain' => $customDomain->full_domain,
                'type' => $customDomain->type,
                'verification_status' => $customDomain->verification_status,
                'ssl_status' => $customDomain->ssl_status,
                'is_active' => $customDomain->isActive(),
                'cname_target' => config('services.cloudflare.cname_target'),
            ],
        ], 201);
    }

    /**
     * Check the current verification status (manual refresh).
     */
    public function checkStatus(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $domain = $funnel->customDomain;

        if (! $domain) {
            return response()->json(['message' => 'No custom domain found'], 404);
        }

        if ($domain->type === 'subdomain') {
            return response()->json([
                'data' => [
                    'verification_status' => 'active',
                    'ssl_status' => 'active',
                    'is_active' => true,
                ],
            ]);
        }

        if (! $domain->cloudflare_hostname_id) {
            return response()->json(['message' => 'No Cloudflare hostname ID'], 422);
        }

        try {
            $status = $this->cloudflare->getHostnameStatus($domain->cloudflare_hostname_id);

            $domain->update([
                'verification_status' => $status['status'] === 'active' ? 'active' : $domain->verification_status,
                'ssl_status' => $status['ssl_status'] === 'active' ? 'active' : $domain->ssl_status,
                'verification_errors' => $status['verification_errors'] ?: null,
                'last_checked_at' => now(),
                'verified_at' => $status['status'] === 'active' ? now() : $domain->verified_at,
                'ssl_active_at' => $status['ssl_status'] === 'active' ? now() : $domain->ssl_active_at,
            ]);

            Cache::forget("custom_domain:custom:{$domain->domain}");

            return response()->json([
                'data' => [
                    'verification_status' => $domain->verification_status,
                    'ssl_status' => $domain->ssl_status,
                    'verification_errors' => $domain->verification_errors,
                    'is_active' => $domain->isActive(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check status. Try again later.',
            ], 500);
        }
    }

    /**
     * Remove a custom domain from a funnel.
     */
    public function destroy(string $funnelUuid): JsonResponse
    {
        $funnel = Funnel::where('uuid', $funnelUuid)->firstOrFail();
        $domain = $funnel->customDomain;

        if (! $domain) {
            return response()->json(['message' => 'No custom domain found'], 404);
        }

        if ($domain->type === 'custom' && $domain->cloudflare_hostname_id) {
            try {
                $this->cloudflare->deleteHostname($domain->cloudflare_hostname_id);
            } catch (\Exception $e) {
                Log::error('Failed to delete hostname from Cloudflare', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $cacheKey = $domain->type === 'subdomain'
            ? "custom_domain:subdomain:{$domain->domain}"
            : "custom_domain:custom:{$domain->domain}";
        Cache::forget($cacheKey);

        $domain->delete();

        return response()->json(['message' => 'Custom domain removed successfully']);
    }
}
