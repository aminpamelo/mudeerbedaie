# Custom Domain for Funnels — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow funnel owners to connect custom domains or platform subdomains to individual funnels, with Cloudflare for SaaS handling SSL and DNS verification.

**Architecture:** A `custom_domains` table tracks domain-to-funnel mappings. A `CloudflareCustomHostnameService` manages the Cloudflare API. A `ResolveCustomDomain` middleware intercepts requests and resolves domains to funnels. Scheduled jobs poll Cloudflare for DNS/SSL verification status. Admin UI in Volt manages domains, and the React funnel settings tab gets a custom domain section.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Cloudflare for SaaS API, React (funnel builder settings)

---

## Task 1: Configuration & Environment Setup

**Files:**
- Modify: `config/services.php:120` (add cloudflare section before closing `];`)
- Modify: `.env.example` (add Cloudflare env vars at end)

**Step 1: Add Cloudflare config to `config/services.php`**

Insert before the closing `];` on line 122:

```php
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'fallback_origin' => env('CLOUDFLARE_FALLBACK_ORIGIN'),
        'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET'),
        'subdomain_base' => env('CUSTOM_DOMAIN_SUBDOMAIN_BASE', 'kelasify.com'),
    ],
```

**Step 2: Add env vars to `.env.example`**

Append to the end of `.env.example`:

```
# Cloudflare for SaaS - Custom Domains
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ZONE_ID=
CLOUDFLARE_FALLBACK_ORIGIN=cdn.kelasify.com
CUSTOM_DOMAIN_CNAME_TARGET=cdn.kelasify.com
CUSTOM_DOMAIN_SUBDOMAIN_BASE=kelasify.com
```

**Step 3: Commit**

```bash
git add config/services.php .env.example
git commit -m "feat(custom-domain): add Cloudflare for SaaS configuration"
```

---

## Task 2: Database Migration

**Files:**
- Create: `database/migrations/xxxx_create_custom_domains_table.php`

**Step 1: Create the migration**

```bash
php artisan make:migration create_custom_domains_table --no-interaction
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->enum('type', ['custom', 'subdomain'])->default('custom');
            $table->string('cloudflare_hostname_id')->nullable();
            $table->enum('verification_status', ['pending', 'active', 'failed', 'deleting'])->default('pending');
            $table->enum('ssl_status', ['pending', 'active', 'failed'])->default('pending');
            $table->json('verification_errors')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_active_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('funnel_id');
            $table->index('user_id');
            $table->index('verification_status');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*_create_custom_domains_table.php
git commit -m "feat(custom-domain): create custom_domains table migration"
```

---

## Task 3: CustomDomain Model

**Files:**
- Create: `app/Models/CustomDomain.php`
- Modify: `app/Models/Funnel.php:128` (add relationship)
- Modify: `app/Models/User.php:459` (add relationship)

**Step 1: Create the model**

```bash
php artisan make:class App/Models/CustomDomain --no-interaction
```

**Step 2: Write model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomDomain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'user_id',
        'domain',
        'type',
        'cloudflare_hostname_id',
        'verification_status',
        'ssl_status',
        'verification_errors',
        'verified_at',
        'ssl_active_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_errors' => 'array',
            'verified_at' => 'datetime',
            'ssl_active_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomDomain $domain) {
            $domain->uuid = $domain->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Status helpers

    public function isActive(): bool
    {
        return $this->verification_status === 'active' && $this->ssl_status === 'active';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->verification_status === 'failed' || $this->ssl_status === 'failed';
    }

    public function isSubdomain(): bool
    {
        return $this->type === 'subdomain';
    }

    public function getFullDomainAttribute(): string
    {
        if ($this->isSubdomain()) {
            return $this->domain . '.' . config('services.cloudflare.subdomain_base');
        }

        return $this->domain;
    }
}
```

**Step 3: Add `customDomain()` relationship to Funnel model**

In `app/Models/Funnel.php`, after the `affiliateCommissionRules()` method (line 128), add:

```php
    public function customDomain(): HasOne
    {
        return $this->hasOne(CustomDomain::class);
    }
```

Also add `use Illuminate\Database\Eloquent\Relations\HasOne;` to the imports if not already present.

**Step 4: Add `customDomains()` relationship to User model**

In `app/Models/User.php`, after the `employee()` method (around line 459), add:

```php
    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }
```

**Step 5: Commit**

```bash
git add app/Models/CustomDomain.php app/Models/Funnel.php app/Models/User.php
git commit -m "feat(custom-domain): add CustomDomain model with relationships"
```

---

## Task 4: Cloudflare Custom Hostname Service

**Files:**
- Create: `app/Services/CloudflareCustomHostnameService.php`

**Step 1: Create the service**

```bash
php artisan make:class App/Services/CloudflareCustomHostnameService --no-interaction
```

**Step 2: Write service content**

```php
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

    private function zoneId(): string
    {
        return config('services.cloudflare.zone_id');
    }

    /**
     * Register a custom hostname with Cloudflare for SaaS.
     *
     * @return array{id: string, status: string, ssl_status: string}
     */
    public function createHostname(string $domain): array
    {
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
                'Failed to create custom hostname: ' . ($response->json('errors.0.message') ?? 'Unknown error')
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
                'Failed to get hostname status: ' . ($response->json('errors.0.message') ?? 'Unknown error')
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
```

**Step 3: Commit**

```bash
git add app/Services/CloudflareCustomHostnameService.php
git commit -m "feat(custom-domain): add CloudflareCustomHostnameService for API integration"
```

---

## Task 5: ResolveCustomDomain Middleware

**Files:**
- Create: `app/Http/Middleware/ResolveCustomDomain.php`
- Modify: `bootstrap/app.php:33-35` (register middleware in web group)

**Step 1: Create the middleware**

```bash
php artisan make:middleware ResolveCustomDomain --no-interaction
```

**Step 2: Write middleware content**

```php
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
        $appDomains = [
            parse_url(config('app.url'), PHP_URL_HOST),
            'localhost',
            '127.0.0.1',
        ];

        // Also skip if it matches the main app test domain pattern
        if (str_ends_with($host, '.test')) {
            return true;
        }

        return in_array($host, array_filter($appDomains));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Skip main app domains
        if ($this->isAppDomain($host)) {
            return $next($request);
        }

        $subdomainBase = config('services.cloudflare.subdomain_base');

        // Check if it's a platform subdomain (e.g., mybrand.kelasify.com)
        if ($subdomainBase && str_ends_with($host, '.' . $subdomainBase)) {
            $subdomain = str_replace('.' . $subdomainBase, '', $host);

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
```

**Step 3: Register middleware in `bootstrap/app.php`**

In the `->withMiddleware()` callback, add to the web group (around line 33-35):

```php
    $middleware->web(prepend: [
        \App\Http\Middleware\ResolveCustomDomain::class,
    ]);

    $middleware->web(append: [
        \App\Http\Middleware\SetLocale::class,
    ]);
```

Replace the existing `$middleware->web(append: [...])` block. The custom domain middleware should run first (prepend) so it's available to all routes.

**Step 4: Commit**

```bash
git add app/Http/Middleware/ResolveCustomDomain.php bootstrap/app.php
git commit -m "feat(custom-domain): add ResolveCustomDomain middleware"
```

---

## Task 6: Custom Domain Routes & PublicFunnelController Updates

**Files:**
- Modify: `routes/web.php` (add custom domain funnel routes)
- Modify: `app/Http/Controllers/PublicFunnelController.php` (add custom domain resolution)

**Step 1: Add custom domain routes in `routes/web.php`**

Add BEFORE the existing `/f/{slug}` routes (around line 533). These routes should only activate when a custom domain is detected by the middleware:

```php
// Custom domain funnel routes (served at root when custom domain is active)
Route::middleware('web')->group(function () {
    Route::get('/', [PublicFunnelController::class, 'showFromCustomDomain'])
        ->name('funnel.custom-domain.show');
    Route::get('{stepSlug}', [PublicFunnelController::class, 'showStepFromCustomDomain'])
        ->name('funnel.custom-domain.step')
        ->where('stepSlug', '^(?!f|api|admin|livewire|embed|stripe|webhooks|login|register|dashboard|settings|teacher|funnel-builder|hr|pos|cms|affiliate).*');
    Route::post('optin', [PublicFunnelController::class, 'submitOptinFromCustomDomain'])
        ->name('funnel.custom-domain.optin');
});
```

**Step 2: Add methods to PublicFunnelController**

Add these methods to `app/Http/Controllers/PublicFunnelController.php`:

```php
    /**
     * Serve funnel landing page via custom domain.
     */
    public function showFromCustomDomain(Request $request): View|\Illuminate\Http\Response
    {
        $customDomain = $request->attributes->get('custom_domain');

        if (! $customDomain) {
            abort(404);
        }

        $funnel = $customDomain->funnel;

        if (! $funnel || ! $funnel->isPublished()) {
            abort(404, 'Funnel not found or not published');
        }

        $funnel->load(['steps' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);

        $step = $funnel->steps->first();
        if (! $step) {
            abort(404, 'Funnel has no active steps');
        }

        return $this->renderStep($request, $funnel, $step);
    }

    /**
     * Serve a specific funnel step via custom domain.
     */
    public function showStepFromCustomDomain(Request $request, string $stepSlug): View|\Illuminate\Http\Response
    {
        $customDomain = $request->attributes->get('custom_domain');

        if (! $customDomain) {
            abort(404);
        }

        $funnel = $customDomain->funnel;

        if (! $funnel || ! $funnel->isPublished()) {
            abort(404, 'Funnel not found or not published');
        }

        $step = $funnel->steps()
            ->where('slug', $stepSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->renderStep($request, $funnel, $step);
    }

    /**
     * Handle opt-in submission via custom domain.
     */
    public function submitOptinFromCustomDomain(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $customDomain = $request->attributes->get('custom_domain');

        if (! $customDomain) {
            abort(404);
        }

        $funnel = $customDomain->funnel;

        if (! $funnel || ! $funnel->isPublished()) {
            abort(404);
        }

        // Reuse the existing optin logic
        return $this->processOptin($request, $funnel);
    }
```

**Step 3: Commit**

```bash
git add routes/web.php app/Http/Controllers/PublicFunnelController.php
git commit -m "feat(custom-domain): add custom domain routing and controller methods"
```

---

## Task 7: Scheduled Jobs for Domain Verification

**Files:**
- Create: `app/Jobs/Funnel/VerifyCustomDomains.php`
- Create: `app/Jobs/Funnel/CleanupFailedDomains.php`
- Modify: `routes/console.php` (register schedules)

**Step 1: Create VerifyCustomDomains job**

```bash
php artisan make:job Funnel/VerifyCustomDomains --no-interaction
```

Write the job:

```php
<?php

namespace App\Jobs\Funnel;

use App\Models\CustomDomain;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyCustomDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CloudflareCustomHostnameService $cloudflare): void
    {
        $domains = CustomDomain::where('type', 'custom')
            ->whereIn('verification_status', ['pending'])
            ->whereNotNull('cloudflare_hostname_id')
            ->get();

        foreach ($domains as $domain) {
            try {
                $status = $cloudflare->getHostnameStatus($domain->cloudflare_hostname_id);

                $domain->update([
                    'verification_status' => $this->mapVerificationStatus($status['status']),
                    'ssl_status' => $this->mapSslStatus($status['ssl_status']),
                    'verification_errors' => $status['verification_errors'] ?: null,
                    'last_checked_at' => now(),
                    'verified_at' => $status['status'] === 'active' ? now() : $domain->verified_at,
                    'ssl_active_at' => $status['ssl_status'] === 'active' ? now() : $domain->ssl_active_at,
                ]);

                // Clear cache when status changes
                Cache::forget("custom_domain:custom:{$domain->domain}");

                Log::info('Custom domain verification check', [
                    'domain' => $domain->domain,
                    'verification_status' => $status['status'],
                    'ssl_status' => $status['ssl_status'],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to verify custom domain', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function mapVerificationStatus(string $cfStatus): string
    {
        return match ($cfStatus) {
            'active' => 'active',
            'pending' => 'pending',
            'moved', 'deleted' => 'deleting',
            default => 'failed',
        };
    }

    private function mapSslStatus(string $cfStatus): string
    {
        return match ($cfStatus) {
            'active' => 'active',
            'pending_validation', 'pending_issuance', 'pending_deployment', 'initializing' => 'pending',
            default => 'failed',
        };
    }
}
```

**Step 2: Create CleanupFailedDomains job**

```bash
php artisan make:job Funnel/CleanupFailedDomains --no-interaction
```

Write the job:

```php
<?php

namespace App\Jobs\Funnel;

use App\Models\CustomDomain;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupFailedDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CloudflareCustomHostnameService $cloudflare): void
    {
        // Find domains stuck in failed state for more than 7 days
        $failedDomains = CustomDomain::where('verification_status', 'failed')
            ->where('updated_at', '<', now()->subDays(7))
            ->get();

        foreach ($failedDomains as $domain) {
            try {
                // Clean up from Cloudflare if hostname exists
                if ($domain->cloudflare_hostname_id) {
                    $cloudflare->deleteHostname($domain->cloudflare_hostname_id);
                }

                $domain->delete(); // Soft delete

                Log::info('Cleaned up failed custom domain', [
                    'domain' => $domain->domain,
                    'funnel_id' => $domain->funnel_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to cleanup custom domain', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

**Step 3: Register schedules in `routes/console.php`**

Add after the existing funnel jobs (around line 21):

```php
// Custom domain verification
Schedule::job(new \App\Jobs\Funnel\VerifyCustomDomains)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new \App\Jobs\Funnel\CleanupFailedDomains)->daily()->at('03:30');
```

**Step 4: Commit**

```bash
git add app/Jobs/Funnel/VerifyCustomDomains.php app/Jobs/Funnel/CleanupFailedDomains.php routes/console.php
git commit -m "feat(custom-domain): add verification and cleanup scheduled jobs"
```

---

## Task 8: Custom Domain API Endpoints

**Files:**
- Create: `app/Http/Controllers/Api/V1/CustomDomainController.php`
- Modify: `routes/api.php` (add custom domain API routes)

**Step 1: Create the API controller**

```bash
php artisan make:controller Api/V1/CustomDomainController --no-interaction
```

**Step 2: Write controller content**

```php
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

        // Check if funnel already has a domain
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

        // For subdomains, just the subdomain part (no dots)
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
            // Register with Cloudflare
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
            // Subdomain — instant activation (wildcard DNS handles it)
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

        // Remove from Cloudflare if it's a custom domain
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

        // Clear cache
        $cacheKey = $domain->type === 'subdomain'
            ? "custom_domain:subdomain:{$domain->domain}"
            : "custom_domain:custom:{$domain->domain}";
        Cache::forget($cacheKey);

        $domain->delete();

        return response()->json(['message' => 'Custom domain removed successfully']);
    }
}
```

**Step 3: Add API routes in `routes/api.php`**

Add inside the authenticated API v1 group:

```php
// Custom Domain management
Route::get('funnels/{uuid}/custom-domain', [CustomDomainController::class, 'show']);
Route::post('funnels/{uuid}/custom-domain', [CustomDomainController::class, 'store']);
Route::post('funnels/{uuid}/custom-domain/check-status', [CustomDomainController::class, 'checkStatus']);
Route::delete('funnels/{uuid}/custom-domain', [CustomDomainController::class, 'destroy']);
```

Don't forget to add the import: `use App\Http\Controllers\Api\V1\CustomDomainController;`

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/CustomDomainController.php routes/api.php
git commit -m "feat(custom-domain): add API endpoints for domain management"
```

---

## Task 9: React Settings Tab — Custom Domain UI

**Files:**
- Modify: `resources/js/funnel-builder/components/SettingsTab.jsx` (add Custom Domain section)

**Step 1: Add custom domain state and API calls**

Add to the component's state (alongside existing form state):

```jsx
const [customDomain, setCustomDomain] = useState(null);
const [domainInput, setDomainInput] = useState('');
const [domainType, setDomainType] = useState('custom'); // 'custom' or 'subdomain'
const [domainLoading, setDomainLoading] = useState(false);
const [domainError, setDomainError] = useState('');
```

Add fetch on mount (inside useEffect):

```jsx
// Fetch custom domain
const fetchCustomDomain = async () => {
    try {
        const response = await funnelApi.getCustomDomain(funnelUuid);
        setCustomDomain(response.data?.data || null);
    } catch (err) {
        console.error('Failed to fetch custom domain', err);
    }
};
fetchCustomDomain();
```

Add handler functions:

```jsx
const handleAddDomain = async () => {
    setDomainLoading(true);
    setDomainError('');
    try {
        const response = await funnelApi.addCustomDomain(funnelUuid, {
            domain: domainInput,
            type: domainType,
        });
        setCustomDomain(response.data?.data);
        setDomainInput('');
        showToast('Custom domain added successfully', 'success');
    } catch (err) {
        setDomainError(err.response?.data?.message || 'Failed to add domain');
    } finally {
        setDomainLoading(false);
    }
};

const handleCheckStatus = async () => {
    setDomainLoading(true);
    try {
        const response = await funnelApi.checkCustomDomainStatus(funnelUuid);
        setCustomDomain(prev => ({ ...prev, ...response.data?.data }));
        showToast('Status updated', 'success');
    } catch (err) {
        showToast('Failed to check status', 'error');
    } finally {
        setDomainLoading(false);
    }
};

const handleRemoveDomain = async () => {
    if (!confirm('Are you sure you want to remove this custom domain?')) return;
    setDomainLoading(true);
    try {
        await funnelApi.removeCustomDomain(funnelUuid);
        setCustomDomain(null);
        showToast('Custom domain removed', 'success');
    } catch (err) {
        showToast('Failed to remove domain', 'error');
    } finally {
        setDomainLoading(false);
    }
};
```

**Step 2: Add Custom Domain JSX section**

Add after the existing settings sections (e.g., after Shipping Cost Settings):

```jsx
{/* Custom Domain Settings */}
<div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 className="text-lg font-semibold text-gray-900 mb-4">Custom Domain</h3>
    <p className="text-sm text-gray-500 mb-4">
        Connect your own domain or use a platform subdomain for this funnel.
    </p>

    {customDomain ? (
        <div className="space-y-4">
            {/* Domain Info */}
            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <p className="font-medium text-gray-900">
                        {customDomain.full_domain}
                    </p>
                    <p className="text-sm text-gray-500 capitalize">
                        {customDomain.type} domain
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {/* Status Badge */}
                    {customDomain.is_active ? (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Active
                        </span>
                    ) : customDomain.verification_status === 'pending' ? (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Pending Verification
                        </span>
                    ) : (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Failed
                        </span>
                    )}
                </div>
            </div>

            {/* CNAME Instructions (for pending custom domains) */}
            {customDomain.type === 'custom' && customDomain.verification_status === 'pending' && (
                <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 className="text-sm font-medium text-blue-800 mb-2">DNS Setup Required</h4>
                    <p className="text-sm text-blue-700 mb-3">
                        Add this CNAME record at your domain's DNS provider:
                    </p>
                    <div className="bg-white p-3 rounded border border-blue-200 font-mono text-sm">
                        <div className="grid grid-cols-3 gap-2">
                            <div>
                                <span className="text-gray-500">Type:</span>
                                <span className="ml-1 font-semibold">CNAME</span>
                            </div>
                            <div>
                                <span className="text-gray-500">Name:</span>
                                <span className="ml-1 font-semibold">{customDomain.domain}</span>
                            </div>
                            <div>
                                <span className="text-gray-500">Target:</span>
                                <span className="ml-1 font-semibold">{customDomain.cname_target}</span>
                            </div>
                        </div>
                    </div>
                    <p className="text-xs text-blue-600 mt-2">
                        DNS changes can take up to 24 hours to propagate. We check automatically every 5 minutes.
                    </p>
                </div>
            )}

            {/* Verification Errors */}
            {customDomain.verification_errors?.length > 0 && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h4 className="text-sm font-medium text-red-800 mb-1">Verification Errors</h4>
                    <ul className="text-sm text-red-700 list-disc list-inside">
                        {customDomain.verification_errors.map((error, i) => (
                            <li key={i}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Action Buttons */}
            <div className="flex gap-2">
                {!customDomain.is_active && customDomain.type === 'custom' && (
                    <button
                        onClick={handleCheckStatus}
                        disabled={domainLoading}
                        className="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 disabled:opacity-50"
                    >
                        {domainLoading ? 'Checking...' : 'Check Status'}
                    </button>
                )}
                <button
                    onClick={handleRemoveDomain}
                    disabled={domainLoading}
                    className="px-4 py-2 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 disabled:opacity-50"
                >
                    Remove Domain
                </button>
            </div>
        </div>
    ) : (
        <div className="space-y-4">
            {/* Domain Type Selector */}
            <div className="flex gap-4">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="domainType"
                        value="custom"
                        checked={domainType === 'custom'}
                        onChange={() => setDomainType('custom')}
                        className="text-blue-600"
                    />
                    <span className="text-sm font-medium text-gray-700">Custom Domain</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="domainType"
                        value="subdomain"
                        checked={domainType === 'subdomain'}
                        onChange={() => setDomainType('subdomain')}
                        className="text-blue-600"
                    />
                    <span className="text-sm font-medium text-gray-700">Platform Subdomain</span>
                </label>
            </div>

            {/* Domain Input */}
            <div>
                {domainType === 'custom' ? (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Your Domain
                        </label>
                        <input
                            type="text"
                            value={domainInput}
                            onChange={(e) => setDomainInput(e.target.value)}
                            placeholder="checkout.yourdomain.com"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p className="text-xs text-gray-500 mt-1">
                            Enter the full domain (e.g., checkout.yourdomain.com)
                        </p>
                    </div>
                ) : (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Subdomain
                        </label>
                        <div className="flex items-center">
                            <input
                                type="text"
                                value={domainInput}
                                onChange={(e) => setDomainInput(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                                placeholder="mybrand"
                                className="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <span className="px-3 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">
                                .kelasify.com
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {/* Error */}
            {domainError && (
                <p className="text-sm text-red-600">{domainError}</p>
            )}

            {/* Add Button */}
            <button
                onClick={handleAddDomain}
                disabled={domainLoading || !domainInput}
                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
                {domainLoading ? 'Adding...' : 'Add Domain'}
            </button>
        </div>
    )}
</div>
```

**Step 3: Add API methods to the funnel API service**

Find the funnel API service file (likely `resources/js/funnel-builder/api/funnelApi.js` or similar) and add:

```javascript
getCustomDomain: (funnelUuid) => axios.get(`/api/v1/funnels/${funnelUuid}/custom-domain`),
addCustomDomain: (funnelUuid, data) => axios.post(`/api/v1/funnels/${funnelUuid}/custom-domain`, data),
checkCustomDomainStatus: (funnelUuid) => axios.post(`/api/v1/funnels/${funnelUuid}/custom-domain/check-status`),
removeCustomDomain: (funnelUuid) => axios.delete(`/api/v1/funnels/${funnelUuid}/custom-domain`),
```

**Step 4: Commit**

```bash
git add resources/js/
git commit -m "feat(custom-domain): add custom domain UI to funnel settings"
```

---

## Task 10: Admin Dashboard — Custom Domains List

**Files:**
- Create: `resources/views/livewire/admin/custom-domains-index.blade.php`
- Modify: `routes/web.php` (add admin route)

**Step 1: Create the Volt component**

```bash
php artisan make:volt admin/custom-domains-index --class --no-interaction
```

**Step 2: Write the component**

Follow the exact pattern from `funnel-list.blade.php`. Key features:
- Table listing all custom domains across users
- Columns: domain, funnel name, user name, type, verification status, SSL status, created date
- Filters: by verification_status, by type
- Actions: delete domain (with Cloudflare cleanup)
- Search by domain name

**Step 3: Add admin route in `routes/web.php`**

Add alongside other admin routes:

```php
Volt::route('custom-domains', 'admin.custom-domains-index')->name('admin.custom-domains');
```

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/custom-domains-index.blade.php routes/web.php
git commit -m "feat(custom-domain): add admin dashboard for custom domains"
```

---

## Task 11: Tests

**Files:**
- Create: `tests/Feature/CustomDomainTest.php`

**Step 1: Create the test file**

```bash
php artisan make:test CustomDomainTest --pest --no-interaction
```

**Step 2: Write tests covering:**

1. User can add a subdomain to a funnel (instant activation)
2. User can add a custom domain to a funnel (pending state)
3. User cannot add duplicate domains
4. User cannot add domain to funnel that already has one
5. User can remove a custom domain
6. User can check domain status
7. Middleware resolves subdomain correctly
8. Middleware resolves custom domain correctly
9. Middleware returns 404 for unknown domains
10. Middleware skips main app domain

**Step 3: Run tests**

```bash
php artisan test --compact --filter=CustomDomain
```

**Step 4: Commit**

```bash
git add tests/Feature/CustomDomainTest.php
git commit -m "test(custom-domain): add feature tests for custom domain management"
```

---

## Task 12: Run Pint & Full Test Suite

**Step 1: Run Pint**

```bash
./vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

**Step 3: Build frontend assets**

```bash
npm run build
```

**Step 4: Final commit if needed**

```bash
git add -A
git commit -m "chore(custom-domain): code formatting and build"
```
