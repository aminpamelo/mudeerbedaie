<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomDomain
{
    /**
     * Domains that should be skipped (main app domains).
     */
    private function isAppDomain(string $host): bool
    {
        $tunnelHosts = array_filter(array_map('trim', explode(',', (string) env('DEV_TUNNEL_HOSTS', ''))));

        $appDomains = array_merge([
            parse_url(config('app.url'), PHP_URL_HOST),
            'localhost',
            '127.0.0.1',
        ], $tunnelHosts);

        // Also skip if it matches the main app test domain pattern
        if (str_ends_with($host, '.test')) {
            return true;
        }

        return in_array($host, array_filter($appDomains));
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Skip main app domains
        if ($this->isAppDomain($host)) {
            return $next($request);
        }

        $subdomainBase = config('services.cloudflare.subdomain_base');

        // Check if it's a platform subdomain (e.g., mybrand.kelasify.com)
        if ($subdomainBase && str_ends_with($host, '.'.$subdomainBase)) {
            $subdomain = str_replace('.'.$subdomainBase, '', $host);

            // Skip common subdomains
            if (in_array($subdomain, ['www', 'api', 'cdn', 'admin', 'mail'])) {
                return $next($request);
            }

            $customDomain = $this->resolveSubdomain($subdomain);
        } else {
            $customDomain = $this->resolveCustomDomain($host);
        }

        if (! $customDomain) {
            abort(404, 'Domain not found');
        }

        // Bind the funnel to the request for downstream use
        $request->attributes->set('custom_domain', $customDomain);
        $request->attributes->set('custom_domain_funnel_id', $customDomain->funnel_id);

        return $next($request);
    }

    private function resolveSubdomain(string $subdomain): ?CustomDomain
    {
        return Cache::remember(
            "custom_domain:subdomain:{$subdomain}",
            now()->addMinutes(5),
            fn () => CustomDomain::where('domain', $subdomain)
                ->where('type', 'subdomain')
                ->where('verification_status', 'active')
                ->with('funnel')
                ->first()
        );
    }

    private function resolveCustomDomain(string $host): ?CustomDomain
    {
        return Cache::remember(
            "custom_domain:custom:{$host}",
            now()->addMinutes(5),
            fn () => CustomDomain::where('domain', $host)
                ->where('type', 'custom')
                ->where('verification_status', 'active')
                ->where('ssl_status', 'active')
                ->with('funnel')
                ->first()
        );
    }
}
