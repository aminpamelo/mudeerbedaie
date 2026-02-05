<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies configuration
        // - In local/development: trust all proxies for Expose/ngrok tunneling
        // - In production: trust specific proxies or none (configure TRUSTED_PROXIES env if needed)
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
        if (in_array($appEnv, ['local', 'development', 'testing'])) {
            $middleware->trustProxies(at: '*');
        } elseif ($trustedProxies = (getenv('TRUSTED_PROXIES') ?: ($_ENV['TRUSTED_PROXIES'] ?? null))) {
            // In production, only trust specific proxies (e.g., load balancer, CDN)
            // Set TRUSTED_PROXIES=* to trust all, or comma-separated IPs
            $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies));
        }

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'affiliate' => \App\Http\Middleware\AffiliateAuth::class,
        ]);

        // Add SetLocale middleware to web group
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Configure API middleware for Sanctum SPA authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Exclude routes from CSRF verification
        // - Stripe webhook (external)
        // - TikTok webhook (external)
        // - Internal API routes (protected by auth middleware)
        // - Funnel event tracking (public, no auth)
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'webhooks/tiktok',
            'api/workflows',
            'api/workflows/*',
            'api/crm/*',
            'api/v1/*',
            'api/funnel-events/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
