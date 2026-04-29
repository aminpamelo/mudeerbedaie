# TikTok Multi-App Architecture Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable TikTok Shop accounts to authorize multiple TikTok Partner Center apps (Multi-Channel Management, Analytics & Reporting, etc.) so each sync service can use the credential issued by the app whose category grants the required scopes.

**Architecture:** Introduce a `platform_apps` table to register one row per TikTok app (key/secret/category). Add `platform_app_id` to `platform_api_credentials` so credentials know which app issued them. Refactor `TikTokClientFactory` to take a category, look up the right app + credential, and build the client. Sync services declare their required category via a class constant. OAuth flow extends state with `platform_app_id` and stores credentials per app.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest 4, EcomPHP\TiktokShop SDK, MySQL+SQLite migrations.

**Reference design doc:** [`docs/plans/2026-04-29-tiktok-multi-app-design.md`](2026-04-29-tiktok-multi-app-design.md)

---

## Phase 1 — Data model + PlatformApp model

### Task 1: Create `platform_apps` migration

**Files:**
- Create: `database/migrations/2026_04_29_100000_create_platform_apps_table.php`

**Step 1: Create migration via Artisan**

```bash
php artisan make:migration create_platform_apps_table --no-interaction
```

Then rename the generated file to `2026_04_29_100000_create_platform_apps_table.php` if needed.

**Step 2: Write the migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->string('slug');
            $table->string('name');
            $table->string('category');
            $table->string('app_key');
            $table->text('encrypted_app_secret');
            $table->string('redirect_uri')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'slug'], 'platform_apps_platform_slug_unique');
            $table->unique(['platform_id', 'category'], 'platform_apps_platform_category_unique');
            $table->index(['platform_id', 'is_active'], 'platform_apps_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_apps');
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `Running: 2026_04_29_100000_create_platform_apps_table` succeeds. Do NOT run `migrate:fresh` per project rules.

**Step 4: Commit**

```bash
git add database/migrations/2026_04_29_100000_create_platform_apps_table.php
git commit -m "feat: add platform_apps table for multi-app TikTok integration"
```

---

### Task 2: Add `platform_app_id` to `platform_api_credentials`

**Files:**
- Create: `database/migrations/2026_04_29_100100_add_platform_app_id_to_platform_api_credentials_table.php`

**Step 1: Generate migration**

```bash
php artisan make:migration add_platform_app_id_to_platform_api_credentials_table --no-interaction
```

**Step 2: Write the migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_api_credentials', function (Blueprint $table) {
            $table->foreignId('platform_app_id')
                ->nullable()
                ->after('platform_account_id')
                ->constrained('platform_apps')
                ->nullOnDelete();

            $table->index(
                ['platform_account_id', 'platform_app_id', 'is_active'],
                'platform_creds_account_app_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('platform_api_credentials', function (Blueprint $table) {
            $table->dropIndex('platform_creds_account_app_active_idx');
            $table->dropConstrainedForeignId('platform_app_id');
        });
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/2026_04_29_100100_add_platform_app_id_to_platform_api_credentials_table.php
git commit -m "feat: link platform_api_credentials to issuing platform_app"
```

---

### Task 3: Create `PlatformApp` model

**Files:**
- Create: `app/Models/PlatformApp.php`
- Create: `database/factories/PlatformAppFactory.php`

**Step 1: Generate model + factory**

```bash
php artisan make:model PlatformApp --factory --no-interaction
```

**Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PlatformApp extends Model
{
    use HasFactory;

    public const CATEGORY_MULTI_CHANNEL = 'multi_channel';

    public const CATEGORY_ANALYTICS_REPORTING = 'analytics_reporting';

    public const CATEGORY_AFFILIATE = 'affiliate';

    public const CATEGORY_CUSTOMER_SERVICE = 'customer_service';

    protected $fillable = [
        'platform_id',
        'slug',
        'name',
        'category',
        'app_key',
        'encrypted_app_secret',
        'redirect_uri',
        'scopes',
        'is_active',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_app_secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(PlatformApiCredential::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getAppSecret(): ?string
    {
        if (! $this->encrypted_app_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_app_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setAppSecret(string $secret): void
    {
        $this->encrypted_app_secret = Crypt::encryptString($secret);
    }
}
```

**Step 3: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Platform;
use App\Models\PlatformApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class PlatformAppFactory extends Factory
{
    protected $model = PlatformApp::class;

    public function definition(): array
    {
        return [
            'platform_id' => Platform::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company().' App',
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
            'app_key' => fake()->uuid(),
            'encrypted_app_secret' => Crypt::encryptString(fake()->sha256()),
            'redirect_uri' => null,
            'scopes' => [],
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function analytics(): self
    {
        return $this->state(fn () => [
            'category' => PlatformApp::CATEGORY_ANALYTICS_REPORTING,
            'slug' => 'tiktok-analytics-reporting',
            'name' => 'TikTok Analytics & Reporting',
        ]);
    }

    public function multiChannel(): self
    {
        return $this->state(fn () => [
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
            'slug' => 'tiktok-multi-channel',
            'name' => 'TikTok Multi-Channel Management',
        ]);
    }
}
```

**Step 4: Add `platformApp()` relation to `PlatformApiCredential`**

Modify [`app/Models/PlatformApiCredential.php`](../../app/Models/PlatformApiCredential.php):

Add `'platform_app_id'` to `$fillable` (after `'platform_account_id'`) and add this method after `platformAccount()`:

```php
public function platformApp(): BelongsTo
{
    return $this->belongsTo(PlatformApp::class);
}
```

**Step 5: Write a unit test**

Create `tests/Unit/Models/PlatformAppTest.php`:

```php
<?php

use App\Models\PlatformApp;

it('encrypts and decrypts app_secret round-trip', function () {
    $app = PlatformApp::factory()->make();
    $app->setAppSecret('super-secret-value');

    expect($app->getAppSecret())->toBe('super-secret-value');
    expect($app->encrypted_app_secret)->not->toBe('super-secret-value');
});

it('returns null when getting unset secret', function () {
    $app = new PlatformApp();

    expect($app->getAppSecret())->toBeNull();
});
```

**Step 6: Run the test**

```bash
php artisan test --compact tests/Unit/Models/PlatformAppTest.php
```

Expected: 2 passed.

**Step 7: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 8: Commit**

```bash
git add app/Models/PlatformApp.php app/Models/PlatformApiCredential.php database/factories/PlatformAppFactory.php tests/Unit/Models/PlatformAppTest.php
git commit -m "feat: add PlatformApp model with encrypted secret"
```

---

### Task 4: Seeder for Multi-Channel app + backfill credentials

**Files:**
- Create: `database/seeders/TikTokMultiChannelAppSeeder.php`

**Step 1: Generate seeder**

```bash
php artisan make:seeder TikTokMultiChannelAppSeeder --no-interaction
```

**Step 2: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TikTokMultiChannelAppSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            $this->command->warn('TikTok Shop platform not found. Skipping.');
            return;
        }

        $appKey = config('services.tiktok.app_key');
        $appSecret = config('services.tiktok.app_secret');

        if (empty($appKey) || empty($appSecret)) {
            $this->command->warn('TIKTOK_APP_KEY/TIKTOK_APP_SECRET not set. Skipping seed.');
            return;
        }

        $app = PlatformApp::firstOrNew([
            'platform_id' => $platform->id,
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
        ]);

        if (! $app->exists) {
            $app->fill([
                'slug' => 'tiktok-multi-channel',
                'name' => 'TikTok Multi-Channel Management',
                'app_key' => $appKey,
                'encrypted_app_secret' => Crypt::encryptString($appSecret),
                'redirect_uri' => config('services.tiktok.redirect_uri'),
                'is_active' => true,
                'scopes' => [],
                'metadata' => ['seeded_from' => 'env'],
            ])->save();

            $this->command->info("Created Multi-Channel app row id={$app->id}");
        }

        $backfilled = DB::table('platform_api_credentials')
            ->where('platform_id', $platform->id)
            ->whereNull('platform_app_id')
            ->update(['platform_app_id' => $app->id]);

        $this->command->info("Backfilled {$backfilled} credentials with platform_app_id={$app->id}");
    }
}
```

**Step 3: Register in DatabaseSeeder if there's a production seeder list**

Check `database/seeders/DatabaseSeeder.php`. If it's an idempotent seeder list, append:

```php
$this->call(TikTokMultiChannelAppSeeder::class);
```

If not, skip — this seeder can be run manually with `php artisan db:seed --class=TikTokMultiChannelAppSeeder`.

**Step 4: Run the seeder**

```bash
php artisan db:seed --class=TikTokMultiChannelAppSeeder --no-interaction
```

Expected output: "Created Multi-Channel app row id=N" and "Backfilled M credentials...".

**Step 5: Verify in tinker**

```bash
php artisan tinker --execute="echo App\Models\PlatformApp::where('category', 'multi_channel')->count() . PHP_EOL;"
```

Expected: `1`.

**Step 6: Commit**

```bash
git add database/seeders/TikTokMultiChannelAppSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: seed multi-channel app + backfill existing credentials"
```

---

## Phase 2 — Client factory + auth service routing

### Task 5: Add `MissingPlatformAppConnectionException`

**Files:**
- Create: `app/Exceptions/MissingPlatformAppConnectionException.php`

**Step 1: Generate exception**

```bash
php artisan make:exception MissingPlatformAppConnectionException --no-interaction
```

**Step 2: Write the exception**

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PlatformAccount;
use RuntimeException;

class MissingPlatformAppConnectionException extends RuntimeException
{
    public function __construct(
        public readonly PlatformAccount $account,
        public readonly string $category,
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? "PlatformAccount #{$account->id} has no active credential for app category '{$category}'. Connect the corresponding TikTok app to enable this sync."
        );
    }
}
```

**Step 3: Commit**

```bash
git add app/Exceptions/MissingPlatformAppConnectionException.php
git commit -m "feat: add MissingPlatformAppConnectionException"
```

---

### Task 6: Refactor `TikTokClientFactory` to route by category (TDD)

**Files:**
- Modify: [`app/Services/TikTok/TikTokClientFactory.php`](../../app/Services/TikTok/TikTokClientFactory.php)
- Test: `tests/Feature/Services/TikTok/TikTokClientFactoryTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Services/TikTok/TikTokClientFactoryTest.php`:

```php
<?php

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use App\Models\User;
use App\Services\TikTok\TikTokClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $this->user = User::factory()->create();
});

it('builds client using app credentials for the requested category', function () {
    $multiChannelApp = PlatformApp::factory()->multiChannel()->create([
        'platform_id' => $this->platform->id,
        'app_key' => 'mc_key',
    ]);
    $multiChannelApp->setAppSecret('mc_secret');
    $multiChannelApp->save();

    $analyticsApp = PlatformApp::factory()->analytics()->create([
        'platform_id' => $this->platform->id,
        'app_key' => 'an_key',
    ]);
    $analyticsApp->setAppSecret('an_secret');
    $analyticsApp->save();

    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
        'metadata' => ['shop_cipher' => 'cipher123'],
    ]);

    $analyticsCredential = new PlatformApiCredential([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $analyticsApp->id,
        'credential_type' => 'oauth_token',
        'name' => 'Analytics Token',
        'is_active' => true,
        'expires_at' => now()->addHours(2),
    ]);
    $analyticsCredential->setValue('analytics_token_xyz');
    $analyticsCredential->save();

    $factory = app(TikTokClientFactory::class);
    $client = $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING);

    expect($client)->toBeInstanceOf(\EcomPHP\TiktokShop\Client::class);
});

it('throws MissingPlatformAppConnectionException when no credential exists for category', function () {
    PlatformApp::factory()->multiChannel()->create(['platform_id' => $this->platform->id]);
    PlatformApp::factory()->analytics()->create(['platform_id' => $this->platform->id]);

    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
    ]);

    $factory = app(TikTokClientFactory::class);

    expect(fn () => $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING))
        ->toThrow(MissingPlatformAppConnectionException::class);
});

it('throws when no PlatformApp exists for the requested category', function () {
    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
    ]);

    $factory = app(TikTokClientFactory::class);

    expect(fn () => $factory->createClientForAccount($account, PlatformApp::CATEGORY_ANALYTICS_REPORTING))
        ->toThrow(MissingPlatformAppConnectionException::class);
});
```

**Step 2: Run test (expect failure)**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokClientFactoryTest.php
```

Expected: FAILS — `createClientForAccount` does not yet accept a category argument.

**Step 3: Refactor `TikTokClientFactory`**

Replace the contents of [`app/Services/TikTok/TikTokClientFactory.php`](../../app/Services/TikTok/TikTokClientFactory.php) with:

```php
<?php

declare(strict_types=1);

namespace App\Services\TikTok;

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TikTokClientFactory
{
    /**
     * Create an unauthenticated client using the app's keys for OAuth bootstrap.
     */
    public function createBaseClient(PlatformApp $app): Client
    {
        $appSecret = $app->getAppSecret();

        if (empty($app->app_key) || empty($appSecret)) {
            throw new RuntimeException(
                "PlatformApp #{$app->id} has missing app_key or app_secret."
            );
        }

        return new Client($app->app_key, $appSecret);
    }

    /**
     * Create an authenticated client for a specific account + app category.
     */
    public function createClientForAccount(PlatformAccount $account, string $category): Client
    {
        $app = $this->resolveApp($account, $category);
        $credential = $this->resolveCredential($account, $app);

        $accessToken = $credential->getValue();

        if (! $accessToken) {
            throw new RuntimeException(
                "Unable to decrypt access token for account: {$account->name} (app category: {$category})"
            );
        }

        $client = $this->createBaseClient($app);
        $client->setAccessToken($accessToken);

        $shopCipher = $account->metadata['shop_cipher'] ?? null;
        if ($shopCipher) {
            $client->setShopCipher($shopCipher);
        }

        $credential->markAsUsed();

        return $client;
    }

    public function resolveApp(PlatformAccount $account, string $category): PlatformApp
    {
        $app = PlatformApp::query()
            ->where('platform_id', $account->platform_id)
            ->where('category', $category)
            ->where('is_active', true)
            ->first();

        if (! $app) {
            throw new MissingPlatformAppConnectionException(
                $account,
                $category,
                "No active PlatformApp registered for platform_id={$account->platform_id}, category='{$category}'. Register the app under Platform Management → Apps."
            );
        }

        return $app;
    }

    public function resolveCredential(PlatformAccount $account, PlatformApp $app): PlatformApiCredential
    {
        $credential = $account->credentials()
            ->where('platform_app_id', $app->id)
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->first();

        if (! $credential) {
            throw new MissingPlatformAppConnectionException($account, $app->category);
        }

        return $credential;
    }

    public function getRedirectUri(?PlatformApp $app = null): string
    {
        if ($app && ! empty($app->redirect_uri)) {
            return $app->redirect_uri;
        }

        $configured = config('services.tiktok.redirect_uri');
        return $configured ?: url('/tiktok/callback');
    }

    public function getApiVersion(): string
    {
        return config('services.tiktok.api_version', '202309');
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.tiktok.sandbox', false);
    }

    public function logError(string $message, array $context = []): void
    {
        Log::channel('daily')->error("[TikTok API] {$message}", array_merge($context, [
            'sandbox' => $this->isSandbox(),
        ]));
    }
}
```

Note: `isConfigured()` is removed — configuration is now per-app via `PlatformApp` rows.

**Step 4: Run the test**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokClientFactoryTest.php
```

Expected: 3 passed.

**Step 5: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 6: Commit**

```bash
git add app/Services/TikTok/TikTokClientFactory.php tests/Feature/Services/TikTok/TikTokClientFactoryTest.php
git commit -m "refactor: route TikTok client by app category"
```

---

### Task 7: Refactor `TikTokAuthService` to accept a `PlatformApp`

**Files:**
- Modify: [`app/Services/TikTok/TikTokAuthService.php`](../../app/Services/TikTok/TikTokAuthService.php)

**Step 1: Update `getAuthorizationUrl`, `handleCallback`, and `refreshToken`**

In [`app/Services/TikTok/TikTokAuthService.php`](../../app/Services/TikTok/TikTokAuthService.php):

Replace the methods using `createBaseClient()` (no args) with versions that take `PlatformApp $app`:

```php
public function getAuthorizationUrl(PlatformApp $app, ?string $state = null): string
{
    $client = $this->clientFactory->createBaseClient($app);
    $auth = $client->Auth();

    $state = $state ?? Str::random(32);

    return $auth->createAuthRequest($state);
}

public function handleCallback(PlatformApp $app, string $code): array
{
    $client = $this->clientFactory->createBaseClient($app);
    $auth = $client->Auth();

    try {
        $tokenResponse = $auth->getToken($code);

        if (! isset($tokenResponse['access_token'])) {
            throw new Exception('Invalid token response from TikTok');
        }

        return [
            'access_token' => $tokenResponse['access_token'],
            'refresh_token' => $tokenResponse['refresh_token'] ?? null,
            'expires_in' => $tokenResponse['access_token_expire_in'] ?? 86400,
            'refresh_expires_in' => $tokenResponse['refresh_token_expire_in'] ?? 31536000,
            'scopes' => $tokenResponse['granted_scopes'] ?? $tokenResponse['scopes'] ?? [],
        ];
    } catch (Exception $e) {
        $this->clientFactory->logError('OAuth callback failed', [
            'error' => $e->getMessage(),
            'platform_app_id' => $app->id,
        ]);
        throw $e;
    }
}
```

For `refreshToken(PlatformAccount $account)` — change it to find the credential's `platformApp` and pass it through:

```php
public function refreshToken(PlatformAccount $account, ?PlatformApp $app = null): bool
{
    $query = $account->credentials()
        ->where('credential_type', 'oauth_token')
        ->where('is_active', true);

    if ($app) {
        $query->where('platform_app_id', $app->id);
    }

    $credential = $query->orderByDesc('created_at')->first();

    if (! $credential) {
        $this->clientFactory->logError('No active credential found for refresh', [
            'account_id' => $account->id,
            'platform_app_id' => $app?->id,
        ]);
        return false;
    }

    if (! $credential->platformApp) {
        $this->clientFactory->logError('Credential has no associated PlatformApp', [
            'credential_id' => $credential->id,
        ]);
        return false;
    }

    $refreshToken = $credential->getRefreshToken();
    if (! $refreshToken) {
        $this->clientFactory->logError('No refresh token available', [
            'account_id' => $account->id,
        ]);
        return false;
    }

    try {
        $client = $this->clientFactory->createBaseClient($credential->platformApp);
        $auth = $client->Auth();

        $tokenResponse = $auth->refreshNewToken($refreshToken);

        if (! isset($tokenResponse['access_token'])) {
            throw new Exception('Invalid refresh token response');
        }

        $credential->setValue($tokenResponse['access_token']);
        $credential->setRefreshToken($tokenResponse['refresh_token'] ?? $refreshToken);
        $credential->expires_at = $this->calculateExpiryDate(
            $tokenResponse['access_token_expire_in'] ?? 86400
        );
        $credential->metadata = array_merge($credential->metadata ?? [], [
            'last_refresh' => now()->toIso8601String(),
            'refresh_count' => ($credential->metadata['refresh_count'] ?? 0) + 1,
        ]);
        $credential->save();

        Log::info('[TikTok] Token refreshed successfully', [
            'account_id' => $account->id,
            'platform_app_id' => $credential->platform_app_id,
        ]);

        return true;
    } catch (Exception $e) {
        $this->clientFactory->logError('Token refresh failed', [
            'account_id' => $account->id,
            'error' => $e->getMessage(),
            'credential_id' => $credential->id,
        ]);
        return false;
    }
}

public function needsTokenRefresh(PlatformAccount $account, PlatformApp $app): bool
{
    $credential = $account->credentials()
        ->where('platform_app_id', $app->id)
        ->where('credential_type', 'oauth_token')
        ->where('is_active', true)
        ->orderByDesc('created_at')
        ->first();

    if (! $credential || ! $credential->expires_at) {
        return true;
    }

    if ($credential->isExpired()) {
        return true;
    }

    $hoursBeforeExpiry = config('services.tiktok.token_refresh_hours_before_expiry', 1);

    return $credential->expires_at->isBefore(now()->addHours($hoursBeforeExpiry));
}
```

**Step 2: Update `getAuthorizedShops` and `createOrUpdateAccount` / `linkExistingAccount`**

`getAuthorizedShops(string $accessToken)` should also take `PlatformApp $app` so it builds the right client:

```php
public function getAuthorizedShops(PlatformApp $app, string $accessToken): array
{
    $client = $this->clientFactory->createBaseClient($app);
    $client->setAccessToken($accessToken);
    // ... rest unchanged
}
```

In `storeCredentials`, set `platform_app_id`:

```php
private function storeCredentials(
    PlatformAccount $account,
    PlatformApp $app,
    array $tokenData
): PlatformApiCredential {
    // Deactivate existing credentials FOR THIS APP only
    $account->credentials()
        ->where('platform_app_id', $app->id)
        ->where('credential_type', 'oauth_token')
        ->update(['is_active' => false]);

    $credential = new PlatformApiCredential([
        'platform_id' => $account->platform_id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $app->id,
        'credential_type' => 'oauth_token',
        'name' => $app->name . ' OAuth Token',
        'metadata' => [
            'created_via' => 'oauth_callback',
            'api_version' => $this->clientFactory->getApiVersion(),
        ],
        'scopes' => $tokenData['scopes'] ?? [],
        'expires_at' => $this->calculateExpiryDate($tokenData['expires_in'] ?? 86400),
        'is_active' => true,
        'auto_refresh' => true,
    ]);

    $credential->setValue($tokenData['access_token']);

    if (! empty($tokenData['refresh_token'])) {
        $credential->setRefreshToken($tokenData['refresh_token']);
    }

    $credential->save();

    return $credential;
}
```

`createOrUpdateAccount` and `linkExistingAccount` both take an extra `PlatformApp $app` parameter and pass it to `storeCredentials`. Update their signatures and callers.

**Step 3: Add unit-ish test for refresh wiring**

Append to `tests/Feature/Services/TikTok/TikTokClientFactoryTest.php`:

```php
it('looks up needsTokenRefresh per-app', function () {
    $multiChannelApp = PlatformApp::factory()->multiChannel()->create(['platform_id' => $this->platform->id]);
    $analyticsApp = PlatformApp::factory()->analytics()->create(['platform_id' => $this->platform->id]);

    $account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'user_id' => $this->user->id,
    ]);

    // Multi-channel credential is fresh
    $mcCred = new PlatformApiCredential([
        'platform_id' => $this->platform->id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $multiChannelApp->id,
        'credential_type' => 'oauth_token',
        'name' => 'MC',
        'is_active' => true,
        'expires_at' => now()->addHours(20),
    ]);
    $mcCred->setValue('mc_token');
    $mcCred->save();

    $authService = app(\App\Services\TikTok\TikTokAuthService::class);

    expect($authService->needsTokenRefresh($account, $multiChannelApp))->toBeFalse();
    expect($authService->needsTokenRefresh($account, $analyticsApp))->toBeTrue();
});
```

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokClientFactoryTest.php
```

Expected: all pass.

**Step 5: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 6: Commit**

```bash
git add app/Services/TikTok/TikTokAuthService.php tests/Feature/Services/TikTok/TikTokClientFactoryTest.php
git commit -m "refactor: TikTokAuthService takes PlatformApp parameter"
```

---

## Phase 3 — Sync service routing

### Task 8: Add `REQUIRED_CATEGORY` constants and route `getClient`

**Files:**
- Modify: [`app/Services/TikTok/TikTokAnalyticsSyncService.php`](../../app/Services/TikTok/TikTokAnalyticsSyncService.php)
- Modify: [`app/Services/TikTok/TikTokOrderSyncService.php`](../../app/Services/TikTok/TikTokOrderSyncService.php)
- Modify: [`app/Services/TikTok/TikTokProductSyncService.php`](../../app/Services/TikTok/TikTokProductSyncService.php)
- Modify: [`app/Services/TikTok/TikTokFinanceSyncService.php`](../../app/Services/TikTok/TikTokFinanceSyncService.php)
- Modify: [`app/Services/TikTok/TikTokAffiliateSyncService.php`](../../app/Services/TikTok/TikTokAffiliateSyncService.php)

**Step 1: For each sync service, add the constant**

Example for `TikTokAnalyticsSyncService`:

```php
class TikTokAnalyticsSyncService
{
    protected const REQUIRED_CATEGORY = PlatformApp::CATEGORY_ANALYTICS_REPORTING;
    // ...
}
```

For Order/Product/Finance/Affiliate use `PlatformApp::CATEGORY_MULTI_CHANNEL`.

Add `use App\Models\PlatformApp;` at the top of each file.

**Step 2: Update each `getClient(PlatformAccount $account)` method**

Replace the body of `getClient` in each sync service with the new routing:

```php
protected function getClient(PlatformAccount $account): Client
{
    $app = $this->clientFactory->resolveApp($account, static::REQUIRED_CATEGORY);

    if ($this->authService->needsTokenRefresh($account, $app)) {
        Log::info('[TikTok Sync] Refreshing token before sync', [
            'account_id' => $account->id,
            'platform_app_id' => $app->id,
            'category' => static::REQUIRED_CATEGORY,
        ]);
        $this->authService->refreshToken($account, $app);
    }

    $client = $this->clientFactory->createClientForAccount($account, static::REQUIRED_CATEGORY);
    $client->useVersion($this->clientFactory->getApiVersion());

    return $client;
}
```

Add `use EcomPHP\TiktokShop\Client;` if not already imported.

**Step 3: Add a feature test ensuring missing-category short-circuits**

Create `tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php`:

```php
<?php

use App\Exceptions\MissingPlatformAppConnectionException;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformApiCredential;
use App\Models\PlatformApp;
use App\Models\User;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses to sync analytics when only multi-channel credential exists', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $user = User::factory()->create();

    $multiChannelApp = PlatformApp::factory()->multiChannel()->create(['platform_id' => $platform->id]);

    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $user->id,
    ]);

    $cred = new PlatformApiCredential([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_app_id' => $multiChannelApp->id,
        'credential_type' => 'oauth_token',
        'name' => 'MC',
        'is_active' => true,
        'expires_at' => now()->addHours(20),
    ]);
    $cred->setValue('token');
    $cred->save();

    $service = app(TikTokAnalyticsSyncService::class);

    expect(fn () => $service->syncShopPerformance($account))
        ->toThrow(MissingPlatformAppConnectionException::class);
});
```

**Step 4: Run the test**

```bash
php artisan test --compact tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php
```

Expected: PASS.

**Step 5: Run pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/TikTok tests/Feature/Services/TikTok/TikTokAnalyticsSyncServiceTest.php
git commit -m "feat: route sync services to required app category"
```

---

## Phase 4 — OAuth flow with platform_app_id

### Task 9: Update `TikTokAuthController::redirect` to accept `app` query param

**Files:**
- Modify: [`app/Http/Controllers/TikTok/TikTokAuthController.php`](../../app/Http/Controllers/TikTok/TikTokAuthController.php)
- Modify: [`routes/web.php`](../../routes/web.php) (no route change needed — already uses query params)

**Step 1: Resolve `PlatformApp` from request**

In `redirect()`, accept an `app` query param (the `PlatformApp` slug or ID), default to the Multi-Channel app for the platform:

```php
public function redirect(Request $request): RedirectResponse
{
    $platform = \App\Models\Platform::where('slug', 'tiktok-shop')->firstOrFail();

    $appIdentifier = $request->get('app', 'tiktok-multi-channel');
    $app = \App\Models\PlatformApp::where('platform_id', $platform->id)
        ->where(function ($q) use ($appIdentifier) {
            $q->where('slug', $appIdentifier)->orWhere('id', $appIdentifier);
        })
        ->where('is_active', true)
        ->first();

    if (! $app) {
        return redirect()
            ->route('platforms.index')
            ->with('error', "TikTok app '{$appIdentifier}' is not registered. Register it under Platform Management → Apps first.");
    }

    $csrfToken = Str::random(40);
    $userId = auth()->id();
    $linkAccountId = null;

    if ($request->has('link_account')) {
        $linkAccountId = (int) $request->get('link_account');
        $existingAccount = \App\Models\PlatformAccount::find($linkAccountId);

        if (! ($existingAccount && $existingAccount->platform->slug === 'tiktok-shop')) {
            $linkAccountId = null;
        }
    }

    $payload = json_encode([
        'user_id' => $userId,
        'link_account_id' => $linkAccountId,
        'platform_app_id' => $app->id,
    ]);
    $encryptedPayload = Crypt::encryptString($payload);
    $state = $csrfToken . '.' . base64_encode($encryptedPayload);

    session([
        'tiktok_oauth_state' => $csrfToken,
        'tiktok_oauth_user_id' => $userId,
        'tiktok_link_account_id' => $linkAccountId,
        'tiktok_oauth_app_id' => $app->id,
    ]);

    try {
        $authUrl = $this->authService->getAuthorizationUrl($app, $state);

        return redirect()->away($authUrl);
    } catch (Exception $e) {
        Log::error('[TikTok OAuth] Failed to generate authorization URL', [
            'error' => $e->getMessage(),
            'app_id' => $app->id,
        ]);

        return redirect()
            ->route('platforms.index')
            ->with('error', 'Failed to connect to TikTok: ' . $e->getMessage());
    }
}
```

**Step 2: Update `callback()` to extract and use `platform_app_id`**

In the state-decoding block, extract `platform_app_id`:

```php
$platformAppId = $payload['platform_app_id'] ?? null;
```

After resolving `$user`, resolve `$app`:

```php
if (! $platformAppId) {
    $platformAppId = session('tiktok_oauth_app_id');
}

$app = $platformAppId
    ? \App\Models\PlatformApp::find($platformAppId)
    : null;

if (! $app) {
    Log::error('[TikTok OAuth] No PlatformApp resolved for callback', [
        'platform_app_id' => $platformAppId,
    ]);

    return redirect()
        ->route('platforms.index')
        ->with('error', 'OAuth callback could not identify which TikTok app this connection is for. Please retry from the Platform page.');
}
```

Then pass `$app` everywhere it's needed:

```php
$tokenData = $this->authService->handleCallback($app, $code);
// ...
$shops = $this->authService->getAuthorizedShops($app, $tokenData['access_token']);
// ...
session(['tiktok_oauth_app_id' => $app->id]); // keep around for selectShop/connectShop
// ...
return $this->connectShop($shops[0], $user, $linkAccountId, $app);
```

Add `?PlatformApp $app = null` to `connectShop()` signature and use it for `createOrUpdateAccount` / `linkExistingAccount`. Same for `confirmShop`.

`disconnect($accountId)` — add a `?app` query param to allow disconnecting one app's credentials only:

```php
public function disconnect(Request $request, int $accountId): RedirectResponse
{
    $account = \App\Models\PlatformAccount::findOrFail($accountId);

    if ($account->platform->slug !== 'tiktok-shop') {
        return redirect()->back()->with('error', 'Invalid account.');
    }

    $appSlug = $request->get('app');
    $app = $appSlug
        ? \App\Models\PlatformApp::where('platform_id', $account->platform_id)
            ->where('slug', $appSlug)->first()
        : null;

    if ($app) {
        $account->credentials()
            ->where('platform_app_id', $app->id)
            ->update(['is_active' => false]);
        $msg = "Disconnected '{$app->name}' from {$account->name}.";
    } else {
        $this->authService->disconnectAccount($account);
        $msg = "TikTok Shop '{$account->name}' has been disconnected.";
    }

    return redirect()
        ->route('platforms.accounts.show', ['platform' => 'tiktok-shop', 'account' => $account->id])
        ->with('success', $msg);
}
```

**Step 3: Write a feature test for the OAuth redirect**

Create `tests/Feature/Http/TikTokAuthControllerTest.php`:

```php
<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $this->user = User::factory()->create();
});

it('redirects to TikTok with the analytics app key when app=tiktok-analytics-reporting', function () {
    $multiChannelApp = PlatformApp::factory()->multiChannel()->create(['platform_id' => $this->platform->id, 'app_key' => 'mc_key']);
    $multiChannelApp->setAppSecret('mc_secret');
    $multiChannelApp->save();

    $analyticsApp = PlatformApp::factory()->analytics()->create(['platform_id' => $this->platform->id, 'app_key' => 'an_key']);
    $analyticsApp->setAppSecret('an_secret');
    $analyticsApp->save();

    $response = $this->actingAs($this->user)
        ->get('/tiktok/connect?app=tiktok-analytics-reporting');

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    // The redirect URL should contain the analytics app_key, NOT the multi-channel one
    expect($location)->toContain('an_key');
    expect($location)->not->toContain('mc_key');
});

it('returns helpful error when requested app is not registered', function () {
    $response = $this->actingAs($this->user)
        ->get('/tiktok/connect?app=tiktok-unregistered-app')
        ->assertRedirect(route('platforms.index'));

    expect(session('error'))->toContain('not registered');
});
```

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Http/TikTokAuthControllerTest.php
```

Expected: 2 passed.

**Step 5: Run pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/TikTok/TikTokAuthController.php tests/Feature/Http/TikTokAuthControllerTest.php
git commit -m "feat: OAuth flow carries platform_app_id end-to-end"
```

---

## Phase 5 — Admin UI for managing platform apps

### Task 10: Volt page to list & register `platform_apps`

**Files:**
- Create: `resources/views/livewire/admin/platform-apps/index.blade.php`
- Create: `resources/views/livewire/admin/platform-apps/edit.blade.php`
- Modify: [`routes/web.php`](../../routes/web.php) (add Volt routes)

**Step 1: Add routes**

In [`routes/web.php`](../../routes/web.php), after the `platforms.accounts.*` block (around line 700), add:

```php
Volt::route('platforms/{platform}/apps', 'admin.platform-apps.index')->name('platforms.apps.index');
Volt::route('platforms/{platform}/apps/create', 'admin.platform-apps.edit')->name('platforms.apps.create');
Volt::route('platforms/{platform}/apps/{app}/edit', 'admin.platform-apps.edit')->name('platforms.apps.edit');
```

**Step 2: Build the index page**

Create `resources/views/livewire/admin/platform-apps/index.blade.php`. Reference structure of [`resources/views/livewire/admin/platforms/accounts/index.blade.php`](../../resources/views/livewire/admin/platforms/accounts/index.blade.php) for the layout pattern. Class-based Volt component listing all `PlatformApp` rows for the given `Platform`, with a button to register a new app.

```php
<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use Livewire\Volt\Component;

new class extends Component {
    public Platform $platform;

    public function mount(Platform $platform): void
    {
        $this->platform = $platform;
    }

    public function with(): array
    {
        return [
            'apps' => PlatformApp::where('platform_id', $this->platform->id)
                ->orderBy('category')
                ->get(),
        ];
    }
};
?>

<div class="space-y-6 p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $platform->name }} Apps</flux:heading>
            <flux:text class="mt-2">Register Partner Center apps and their credentials. Each category (Multi-Channel, Analytics, etc.) typically requires a separate app.</flux:text>
        </div>
        <flux:button :href="route('platforms.apps.create', $platform)" variant="primary">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Register App
            </div>
        </flux:button>
    </div>

    <div class="bg-white rounded-lg border border-gray-200">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Name</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Category</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">App Key</th>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-700">Status</th>
                    <th class="text-right px-4 py-3 text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($apps as $app)
                    <tr wire:key="app-{{ $app->id }}">
                        <td class="px-4 py-3">{{ $app->name }}</td>
                        <td class="px-4 py-3"><flux:badge>{{ $app->category }}</flux:badge></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ Str::limit($app->app_key, 16) }}</td>
                        <td class="px-4 py-3">
                            @if ($app->is_active)
                                <flux:badge color="green">Active</flux:badge>
                            @else
                                <flux:badge color="zinc">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button :href="route('platforms.apps.edit', [$platform, $app])" size="sm" variant="outline">Edit</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">No apps registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

**Step 3: Build the edit page**

Create `resources/views/livewire/admin/platform-apps/edit.blade.php` — class-based component with form for slug, name, category dropdown, app_key, app_secret, redirect_uri, is_active. On save, encrypt app_secret via `setAppSecret()` and persist.

```php
<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public Platform $platform;
    public ?PlatformApp $app = null;

    #[Validate('required|string|max:255')]
    public string $slug = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $category = PlatformApp::CATEGORY_MULTI_CHANNEL;

    #[Validate('required|string|max:255')]
    public string $app_key = '';

    public string $app_secret = '';

    public ?string $redirect_uri = null;

    public bool $is_active = true;

    public function mount(Platform $platform, ?PlatformApp $app = null): void
    {
        $this->platform = $platform;
        $this->app = $app?->exists ? $app : null;

        if ($this->app) {
            $this->slug = $this->app->slug;
            $this->name = $this->app->name;
            $this->category = $this->app->category;
            $this->app_key = $this->app->app_key;
            $this->redirect_uri = $this->app->redirect_uri;
            $this->is_active = $this->app->is_active;
        }
    }

    public function save(): void
    {
        $this->validate();

        if ($this->app) {
            $this->app->fill([
                'slug' => $this->slug,
                'name' => $this->name,
                'category' => $this->category,
                'app_key' => $this->app_key,
                'redirect_uri' => $this->redirect_uri ?: null,
                'is_active' => $this->is_active,
            ]);
            if (filled($this->app_secret)) {
                $this->app->setAppSecret($this->app_secret);
            }
            $this->app->save();
        } else {
            $this->validate(['app_secret' => 'required|string']);
            $newApp = new PlatformApp([
                'platform_id' => $this->platform->id,
                'slug' => $this->slug,
                'name' => $this->name,
                'category' => $this->category,
                'app_key' => $this->app_key,
                'redirect_uri' => $this->redirect_uri ?: null,
                'is_active' => $this->is_active,
            ]);
            $newApp->setAppSecret($this->app_secret);
            $newApp->save();
        }

        session()->flash('success', 'App saved.');
        $this->redirectRoute('platforms.apps.index', $this->platform);
    }
};
?>

<div class="p-6 max-w-2xl">
    <flux:heading size="xl">{{ $app ? 'Edit App' : 'Register App' }}</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-4">
        <flux:input wire:model="slug" label="Slug" placeholder="tiktok-analytics-reporting" />
        <flux:input wire:model="name" label="Name" />

        <flux:select wire:model="category" label="Category">
            <option value="multi_channel">Multi-Channel Management</option>
            <option value="analytics_reporting">Analytics & Reporting</option>
            <option value="affiliate">Affiliate</option>
            <option value="customer_service">Customer Service</option>
        </flux:select>

        <flux:input wire:model="app_key" label="App Key" />
        <flux:input
            wire:model="app_secret"
            label="App Secret"
            type="password"
            :placeholder="$app ? '(leave empty to keep current)' : 'Required'"
        />
        <flux:input wire:model="redirect_uri" label="Redirect URI (optional override)" />

        <flux:switch wire:model="is_active" label="Active" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Save</flux:button>
            <flux:button :href="route('platforms.apps.index', $platform)" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
```

**Step 4: Add a test**

Create `tests/Feature/Livewire/Admin/PlatformAppsManagementTest.php`:

```php
<?php

use App\Models\Platform;
use App\Models\PlatformApp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('registers a new platform app with encrypted secret', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $admin = User::factory()->create();

    Volt::test('admin.platform-apps.edit', ['platform' => $platform])
        ->actingAs($admin)
        ->set('slug', 'tiktok-analytics-reporting')
        ->set('name', 'TikTok Analytics & Reporting')
        ->set('category', 'analytics_reporting')
        ->set('app_key', 'an_key_xyz')
        ->set('app_secret', 'an_secret_abc')
        ->call('save');

    $app = PlatformApp::where('slug', 'tiktok-analytics-reporting')->firstOrFail();

    expect($app->getAppSecret())->toBe('an_secret_abc');
    expect($app->encrypted_app_secret)->not->toBe('an_secret_abc');
});
```

**Step 5: Run test**

```bash
php artisan test --compact tests/Feature/Livewire/Admin/PlatformAppsManagementTest.php
```

Expected: PASS.

**Step 6: Run pint + commit**

```bash
vendor/bin/pint --dirty
git add app/ resources/views/livewire/admin/platform-apps routes/web.php tests/Feature/Livewire/Admin/PlatformAppsManagementTest.php
git commit -m "feat: admin UI for managing TikTok platform apps"
```

---

## Phase 6 — App Connections panel on account detail

### Task 11: Add "Connections" tab to account `show.blade.php`

**Files:**
- Modify: [`resources/views/livewire/admin/platforms/accounts/show.blade.php`](../../resources/views/livewire/admin/platforms/accounts/show.blade.php)

**Step 1: Add `connections` to the activeTab options**

In the existing tab navigation (search for `activeTab === 'overview'`), add a new tab button "Connections" next to Overview:

```blade
<button wire:click="$set('activeTab', 'connections')"
    class="px-3 py-2 text-sm font-medium {{ $activeTab === 'connections' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600' }}">
    Connections
</button>
```

**Step 2: Add the tab content section**

In the same blade file, after the Overview section, add:

```blade
@if ($activeTab === 'connections')
    <div class="space-y-4">
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b">
                <flux:heading size="lg">App Connections</flux:heading>
                <flux:text class="mt-1">TikTok Shop categorizes apps. Each category needs its own OAuth connection to grant the relevant scopes.</flux:text>
            </div>

            <div class="divide-y">
                @foreach ($apps as $app)
                    @php
                        $cred = $credentialsByAppId[$app->id] ?? null;
                        $isConnected = $cred && $cred->is_active && (! $cred->expires_at || ! $cred->expires_at->isPast());
                    @endphp
                    <div wire:key="app-{{ $app->id }}" class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $app->name }}</div>
                            <div class="text-sm text-gray-500">Category: {{ $app->category }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($isConnected)
                                <flux:badge color="green">Connected</flux:badge>
                                <flux:button size="sm" variant="outline"
                                    href="{{ route('tiktok.connect', ['app' => $app->slug, 'link_account' => $account->id]) }}">
                                    Reconnect
                                </flux:button>
                                <form method="POST" action="{{ route('tiktok.disconnect', $account->id) }}?app={{ $app->slug }}">
                                    @csrf
                                    <flux:button size="sm" variant="ghost" type="submit">Disconnect</flux:button>
                                </form>
                            @else
                                <flux:badge color="amber">Not connected</flux:badge>
                                <flux:button size="sm" variant="primary"
                                    href="{{ route('tiktok.connect', ['app' => $app->slug, 'link_account' => $account->id]) }}">
                                    Connect
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
```

**Step 3: Wire up `apps` and `credentialsByAppId` in the component**

In the Volt class (top of the file), add:

```php
public function with(): array
{
    return [
        'apps' => \App\Models\PlatformApp::where('platform_id', $this->platform->id)
            ->where('is_active', true)
            ->orderBy('category')
            ->get(),
        'credentialsByAppId' => $this->account->credentials()
            ->whereNotNull('platform_app_id')
            ->where('credential_type', 'oauth_token')
            ->where('is_active', true)
            ->get()
            ->keyBy('platform_app_id'),
    ];
}
```

**Step 4: Update Analytics tab empty state**

Search for `No analytics data yet` in the same file. Replace the block under it with:

```blade
@php
    $analyticsCred = ($credentialsByAppId ?? collect())
        ->first(fn ($c) => optional($c->platformApp)->category === 'analytics_reporting');
@endphp
@if (! $analyticsCred)
    <div class="text-center py-8">
        <p class="text-gray-700">Analytics requires a separate TikTok app connection.</p>
        <flux:button class="mt-4" variant="primary" wire:click="$set('activeTab', 'connections')">
            Connect Analytics & Reporting
        </flux:button>
    </div>
@else
    Run <code>php artisan tiktok:sync-analytics</code> to pull shop performance data.
@endif
```

**Step 5: Manually verify in the browser**

```bash
composer run dev
```

Visit `/admin/platforms/tiktok-shop/accounts/{id}?tab=connections`. Confirm:
- Multi-Channel app shows as Connected (existing credential backfilled)
- Analytics app shows as Not connected with a Connect button
- Click Connect → OAuth flow goes to TikTok with the analytics app key in the URL

**Step 6: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 7: Commit**

```bash
git add resources/views/livewire/admin/platforms/accounts/show.blade.php
git commit -m "feat: app connections tab + analytics empty-state CTA"
```

---

## Phase 7 — Final regression sweep

### Task 12: Full TikTok test suite + manual end-to-end

**Step 1: Run full TikTok-related test suite**

```bash
php artisan test --compact --filter=TikTok
```

Expected: all pass.

**Step 2: Run full suite to catch unrelated regressions**

```bash
php artisan test --compact
```

If anything fails, investigate and fix before continuing.

**Step 3: Manual end-to-end test**

User-driven (with the user's own credentials):

1. Visit Platform Management → Platforms → TikTok Shop → Apps tab
2. Confirm Multi-Channel app shows up (seeded)
3. Click "Register App", paste Analytics app credentials from Partner Center, save
4. Open the TikTok shop account → Connections tab
5. Click "Connect" on Analytics & Reporting → OAuth round-trip → returns connected
6. Click Sync Now on the Analytics tab → confirm shop performance data populates

**Step 4: Run pint one more time across the whole repo**

```bash
vendor/bin/pint --dirty
```

**Step 5: Final commit (if pint touched anything)**

```bash
git add -A
git commit -m "chore: final pint pass"
```

---

## Deferred / out of scope

These are NOT part of this plan; raise separate tickets if needed:

- Migrating Finance/Affiliate to their own app categories
- Other platforms (Shopee, Lazada) using the same architecture
- Granular scope subset selection within an app category

---

## Skill references

- `superpowers:executing-plans` — execute this plan task-by-task
- `superpowers:test-driven-development` — TDD discipline for each task
- `superpowers:verification-before-completion` — verify before claiming done
