# WhatsApp Meta Cloud API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the unofficial Onsend WhatsApp integration with the official Meta Cloud API, add delivery tracking via webhooks, and build a 2-way admin chat inbox using React.

**Architecture:** Provider pattern wrapping both Onsend and Meta Cloud API behind a common interface. WhatsAppService delegates to the active provider. Webhook controller handles delivery receipts and incoming messages. React island for the chat inbox (same pattern as POS/funnel builder).

**Tech Stack:** Laravel 12, Livewire Volt, React 19, Tailwind CSS v4, Flux UI, Meta Cloud API v21.0, Pest PHP

---

## Phase 1: Provider Interface + Onsend Refactor

### Task 1.1: Create WhatsAppProviderInterface

**Files:**
- Create: `app/Contracts/WhatsAppProviderInterface.php`

**Step 1: Create the interface using artisan**

Run: `php artisan make:class Contracts/WhatsAppProviderInterface --no-interaction`

Then replace its content with:

```php
<?php

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    /**
     * Send a text message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function send(string $phoneNumber, string $message): array;

    /**
     * Send an image message.
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendImage(string $phoneNumber, string $imageUrl, ?string $caption = null): array;

    /**
     * Send a document (PDF, etc.).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendDocument(string $phoneNumber, string $documentUrl, string $mimeType, ?string $filename = null): array;

    /**
     * Send a template message (Meta Cloud API only — Onsend returns unsupported).
     *
     * @return array{success: bool, message_id: ?string, message: ?string, error: ?string}
     */
    public function sendTemplate(string $phoneNumber, string $templateName, string $language, array $components = []): array;

    /**
     * Check the provider's connection/device status.
     *
     * @return array{success: bool, status: string, message: string}
     */
    public function checkStatus(): array;

    /**
     * Whether this provider is properly configured (has credentials, etc.).
     */
    public function isConfigured(): bool;

    /**
     * Get the provider name identifier.
     */
    public function getName(): string;
}
```

**Step 2: Commit**

```bash
git add app/Contracts/WhatsAppProviderInterface.php
git commit -m "feat: add WhatsAppProviderInterface contract"
```

---

### Task 1.2: Create OnsendProvider

**Files:**
- Create: `app/Services/WhatsApp/OnsendProvider.php`
- Reference: `app/Services/WhatsAppService.php` (extract HTTP logic from here)

**Step 1: Write the failing test**

Run: `php artisan make:test OnsendProviderTest --pest --no-interaction`

```php
<?php

declare(strict_types=1);

use App\Services\WhatsApp\OnsendProvider;
use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;

it('implements WhatsAppProviderInterface', function () {
    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'test-token',
    );

    expect($provider)->toBeInstanceOf(WhatsAppProviderInterface::class);
});

it('returns not configured when token is empty', function () {
    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: '',
    );

    expect($provider->isConfigured())->toBeFalse();
});

it('sends a text message successfully', function () {
    Http::fake([
        'onsend.io/*' => Http::response([
            'status' => true,
            'message_id' => 'msg-123',
        ], 200),
    ]);

    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'test-token',
    );

    $result = $provider->send('60123456789', 'Hello test');

    expect($result['success'])->toBeTrue();
    expect($result['message_id'])->toBe('msg-123');
});

it('sends a document message successfully', function () {
    Http::fake([
        'onsend.io/*' => Http::response([
            'status' => true,
            'message_id' => 'doc-456',
        ], 200),
    ]);

    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'test-token',
    );

    $result = $provider->sendDocument('60123456789', 'https://example.com/cert.pdf', 'application/pdf', 'certificate.pdf');

    expect($result['success'])->toBeTrue();
});

it('returns unsupported for sendTemplate', function () {
    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'test-token',
    );

    $result = $provider->sendTemplate('60123456789', 'test_template', 'ms', []);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not supported');
});

it('handles API failure gracefully', function () {
    Http::fake([
        'onsend.io/*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'bad-token',
    );

    $result = $provider->send('60123456789', 'Hello');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->not->toBeEmpty();
});

it('returns onsend as provider name', function () {
    $provider = new OnsendProvider(
        apiUrl: 'https://onsend.io/api/v1',
        apiToken: 'test-token',
    );

    expect($provider->getName())->toBe('onsend');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/OnsendProviderTest.php`
Expected: FAIL — class does not exist yet

**Step 3: Create the OnsendProvider class**

Create `app/Services/WhatsApp/OnsendProvider.php` — extract the HTTP calling logic from the existing `WhatsAppService::send()`, `sendImage()`, `sendDocument()` methods. The provider should:

- Accept `apiUrl` and `apiToken` via constructor promotion
- Implement all interface methods
- Use `Http::withToken($this->apiToken)->timeout(30)->post(...)` (same as current WhatsAppService)
- Match the existing OnSend payload format: `{ phone_number, message, type }` for text; `{ phone_number, type: "document", url, mimetype, filename }` for documents; `{ phone_number, type: "image", url, message }` for images
- `sendTemplate()` returns `['success' => false, 'error' => 'Template messages are not supported by Onsend provider']`
- `checkStatus()` does `GET {apiUrl}/status` (same as current `WhatsAppService::checkDeviceStatus()`)
- `isConfigured()` checks `!empty($this->apiToken)`
- `getName()` returns `'onsend'`

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/OnsendProviderTest.php`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/OnsendProvider.php tests/Feature/OnsendProviderTest.php
git commit -m "feat: create OnsendProvider implementing WhatsAppProviderInterface"
```

---

### Task 1.3: Create WhatsAppManager

**Files:**
- Create: `app/Services/WhatsApp/WhatsAppManager.php`
- Reference: `app/Services/SettingsService.php` (for reading provider config)

**Step 1: Write the failing test**

Run: `php artisan make:test WhatsAppManagerTest --pest --no-interaction`

```php
<?php

declare(strict_types=1);

use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\OnsendProvider;
use App\Contracts\WhatsAppProviderInterface;

it('resolves onsend provider by default', function () {
    config(['services.whatsapp.provider' => 'onsend']);
    config(['services.onsend.api_url' => 'https://onsend.io/api/v1']);
    config(['services.onsend.api_token' => 'test']);

    $manager = app(WhatsAppManager::class);
    $provider = $manager->provider();

    expect($provider)->toBeInstanceOf(OnsendProvider::class);
    expect($provider->getName())->toBe('onsend');
});

it('throws exception for unknown provider', function () {
    config(['services.whatsapp.provider' => 'unknown']);

    $manager = app(WhatsAppManager::class);
    $manager->provider();
})->throws(InvalidArgumentException::class);
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/WhatsAppManagerTest.php`
Expected: FAIL

**Step 3: Create WhatsAppManager**

```php
<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use App\Services\SettingsService;
use InvalidArgumentException;

class WhatsAppManager
{
    private ?WhatsAppProviderInterface $resolvedProvider = null;

    public function __construct(
        private SettingsService $settings,
    ) {}

    public function provider(): WhatsAppProviderInterface
    {
        if ($this->resolvedProvider) {
            return $this->resolvedProvider;
        }

        $providerName = $this->settings->get('whatsapp_provider', 'whatsapp', 'onsend');

        $this->resolvedProvider = match ($providerName) {
            'onsend' => $this->createOnsendProvider(),
            'meta' => $this->createMetaCloudProvider(),
            default => throw new InvalidArgumentException("Unknown WhatsApp provider: {$providerName}"),
        };

        return $this->resolvedProvider;
    }

    public function getProviderName(): string
    {
        return $this->provider()->getName();
    }

    private function createOnsendProvider(): OnsendProvider
    {
        $config = $this->settings->getWhatsAppConfig();

        return new OnsendProvider(
            apiUrl: $config['api_url'] ?? config('services.onsend.api_url', 'https://onsend.io/api/v1'),
            apiToken: $config['api_token'] ?? config('services.onsend.api_token', ''),
        );
    }

    private function createMetaCloudProvider(): MetaCloudProvider
    {
        return new MetaCloudProvider(
            phoneNumberId: $this->settings->get('meta_phone_number_id', 'whatsapp', ''),
            accessToken: $this->settings->get('meta_access_token', 'whatsapp', ''),
            apiVersion: $this->settings->get('meta_api_version', 'whatsapp', 'v21.0'),
        );
    }
}
```

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/WhatsAppManagerTest.php`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/WhatsAppManager.php tests/Feature/WhatsAppManagerTest.php
git commit -m "feat: create WhatsAppManager for provider resolution"
```

---

### Task 1.4: Refactor WhatsAppService to use WhatsAppManager

**Files:**
- Modify: `app/Services/WhatsAppService.php`

**Step 1: Write the test**

Run: `php artisan make:test WhatsAppServiceProviderDelegationTest --pest --no-interaction`

```php
<?php

declare(strict_types=1);

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;

it('delegates send to the active provider', function () {
    Http::fake([
        'onsend.io/*' => Http::response(['status' => true, 'message_id' => 'test-123'], 200),
    ]);

    // Ensure onsend is active provider
    config(['services.whatsapp.provider' => 'onsend']);

    $service = app(WhatsAppService::class);
    $result = $service->send('60123456789', 'Test message');

    expect($result['success'])->toBeTrue();
});
```

**Step 2: Modify WhatsAppService**

Key changes to `WhatsAppService`:
- Inject `WhatsAppManager` via constructor
- In `send()`, `sendImage()`, `sendDocument()`: delegate to `$this->manager->provider()->send(...)` instead of direct HTTP calls
- Keep `isEnabled()`, `canSendNow()`, `shouldPauseBatch()`, `getRandomDelay()`, `formatPhoneNumber()`, `logSendAttempt()` — these are service-level concerns, not provider concerns
- Make anti-ban logic conditional: skip delays/batching when provider is `meta`
- Add a new `sendTemplate()` method that delegates to provider
- Keep all existing return shapes identical

**Step 3: Run all existing WhatsApp-related tests**

Run: `php artisan test --compact --filter=WhatsApp`
Expected: All PASS

**Step 4: Commit**

```bash
git add app/Services/WhatsAppService.php tests/Feature/WhatsAppServiceProviderDelegationTest.php
git commit -m "refactor: WhatsAppService delegates to WhatsAppManager provider"
```

---

## Phase 2: MetaCloudProvider + Config + Admin Settings

### Task 2.1: Create MetaCloudProvider

**Files:**
- Create: `app/Services/WhatsApp/MetaCloudProvider.php`
- Test: `tests/Feature/MetaCloudProviderTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\WhatsApp\MetaCloudProvider;
use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;

it('implements WhatsAppProviderInterface', function () {
    $provider = new MetaCloudProvider(
        phoneNumberId: '123456789',
        accessToken: 'test-token',
    );
    expect($provider)->toBeInstanceOf(WhatsAppProviderInterface::class);
});

it('is not configured without access token', function () {
    $provider = new MetaCloudProvider(phoneNumberId: '123', accessToken: '');
    expect($provider->isConfigured())->toBeFalse();
});

it('is not configured without phone number id', function () {
    $provider = new MetaCloudProvider(phoneNumberId: '', accessToken: 'token');
    expect($provider->isConfigured())->toBeFalse();
});

it('sends a text message via Meta Cloud API', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['wa_id' => '60123456789']],
            'messages' => [['id' => 'wamid.abc123']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider(phoneNumberId: '111222333', accessToken: 'valid-token');
    $result = $provider->send('60123456789', 'Hello from Meta');

    expect($result['success'])->toBeTrue();
    expect($result['message_id'])->toBe('wamid.abc123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/111222333/messages'
            && $request['messaging_product'] === 'whatsapp'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Hello from Meta';
    });
});

it('sends a document message via Meta Cloud API', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.doc456']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider(phoneNumberId: '111222333', accessToken: 'valid-token');
    $result = $provider->sendDocument('60123456789', 'https://example.com/cert.pdf', 'application/pdf', 'certificate.pdf');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request['type'] === 'document'
            && $request['document']['link'] === 'https://example.com/cert.pdf'
            && $request['document']['filename'] === 'certificate.pdf';
    });
});

it('sends a template message via Meta Cloud API', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.tpl789']],
        ], 200),
    ]);

    $provider = new MetaCloudProvider(phoneNumberId: '111222333', accessToken: 'valid-token');
    $result = $provider->sendTemplate('60123456789', 'session_reminder', 'ms', [
        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Ahmad']]],
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request['type'] === 'template'
            && $request['template']['name'] === 'session_reminder'
            && $request['template']['language']['code'] === 'ms';
    });
});

it('handles Meta API error response', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Invalid OAuth access token',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 401),
    ]);

    $provider = new MetaCloudProvider(phoneNumberId: '111222333', accessToken: 'bad-token');
    $result = $provider->send('60123456789', 'Hello');

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Invalid OAuth access token');
});

it('returns meta as provider name', function () {
    $provider = new MetaCloudProvider(phoneNumberId: '123', accessToken: 'token');
    expect($provider->getName())->toBe('meta');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/MetaCloudProviderTest.php`

**Step 3: Create MetaCloudProvider**

Create `app/Services/WhatsApp/MetaCloudProvider.php`:
- Constructor: `(string $phoneNumberId, string $accessToken, string $apiVersion = 'v21.0')`
- Base URL: `https://graph.facebook.com/{apiVersion}/{phoneNumberId}/messages`
- Auth: `Authorization: Bearer {accessToken}`
- `send()`: payload `{ messaging_product: 'whatsapp', to, type: 'text', text: { body } }`
- `sendImage()`: payload `{ messaging_product: 'whatsapp', to, type: 'image', image: { link, caption? } }`
- `sendDocument()`: payload `{ messaging_product: 'whatsapp', to, type: 'document', document: { link, filename? } }`
- `sendTemplate()`: payload `{ messaging_product: 'whatsapp', to, type: 'template', template: { name, language: { code }, components } }`
- `checkStatus()`: Call `GET /v21.0/{phoneNumberId}` to verify the phone number is registered. Return `['success' => true, 'status' => 'connected', 'message' => 'Meta Cloud API connected']` on 200.
- Parse Meta error responses: `$response['error']['message']` on failure
- Extract message ID from `$response['messages'][0]['id']`

**Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/MetaCloudProviderTest.php`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/MetaCloudProvider.php tests/Feature/MetaCloudProviderTest.php
git commit -m "feat: create MetaCloudProvider for official WhatsApp Cloud API"
```

---

### Task 2.2: Add Meta config to services.php and .env

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`

**Step 1: Add to config/services.php**

Add a new `whatsapp` block alongside the existing `onsend` block:

```php
'whatsapp' => [
    'provider' => env('WHATSAPP_PROVIDER', 'onsend'),
    'meta' => [
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', ''),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', ''),
        'waba_id' => env('META_WHATSAPP_WABA_ID', ''),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', ''),
        'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN', ''),
        'api_version' => env('META_WHATSAPP_API_VERSION', 'v21.0'),
    ],
],
```

**Step 2: Add to .env.example**

```
# WhatsApp Provider (onsend or meta)
WHATSAPP_PROVIDER=onsend

# Meta WhatsApp Cloud API
META_WHATSAPP_PHONE_NUMBER_ID=
META_WHATSAPP_ACCESS_TOKEN=
META_WHATSAPP_WABA_ID=
META_WHATSAPP_APP_SECRET=
META_WHATSAPP_VERIFY_TOKEN=
META_WHATSAPP_API_VERSION=v21.0
```

**Step 3: Commit**

```bash
git add config/services.php .env.example
git commit -m "feat: add Meta WhatsApp Cloud API config"
```

---

### Task 2.3: Update admin settings page to support provider switching

**Files:**
- Modify: `resources/views/livewire/admin/settings-whatsapp.blade.php`

**Step 1: Add provider-related Livewire properties**

Add to the Volt class:
```php
public string $provider = 'onsend';
public string $metaPhoneNumberId = '';
public string $metaAccessToken = '';
public string $metaWabaId = '';
public string $metaAppSecret = '';
public string $metaVerifyToken = '';
public string $metaApiVersion = 'v21.0';
```

Load them in `mount()` from `SettingsService`. Save them in `save()`.

**Step 2: Add provider selector and Meta config section to the Blade template**

At the top of the settings form, add:
- `flux:radio.group` for provider selection (`onsend` / `meta`)
- Conditional section: when `$provider === 'meta'`, show Meta config fields (phone number ID, access token, WABA ID, app secret, verify token, API version)
- Keep existing Onsend fields shown when `$provider === 'onsend'`
- The device status check should call the active provider's `checkStatus()` method

**Step 3: Test manually in browser**

Visit `/admin/settings/whatsapp`, verify provider toggle works, Meta fields appear/hide.

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/settings-whatsapp.blade.php
git commit -m "feat: add Meta provider settings to WhatsApp admin page"
```

---

## Phase 3: Webhook Controller + Delivery Tracking

### Task 3.1: Create webhook routes and controller

**Files:**
- Create: `app/Http/Controllers/WhatsAppWebhookController.php`
- Modify: `routes/api.php`
- Create: `app/Http/Middleware/VerifyWhatsAppWebhook.php`

**Step 1: Write the test**

Run: `php artisan make:test WhatsAppWebhookTest --pest --no-interaction`

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

it('verifies webhook with correct token', function () {
    config(['services.whatsapp.meta.verify_token' => 'my-verify-token']);

    $this->get('/api/whatsapp/webhook?' . http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'my-verify-token',
        'hub_challenge' => 'challenge-string-123',
    ]))->assertOk()->assertSee('challenge-string-123');
});

it('rejects webhook with wrong token', function () {
    config(['services.whatsapp.meta.verify_token' => 'my-verify-token']);

    $this->get('/api/whatsapp/webhook?' . http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'challenge-string-123',
    ]))->assertForbidden();
});

it('accepts valid webhook POST and dispatches job', function () {
    Queue::fake();

    config(['services.whatsapp.meta.app_secret' => 'test-secret']);

    $payload = json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => '123', 'changes' => []]],
    ]);

    $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-secret');

    $this->postJson('/api/whatsapp/webhook', json_decode($payload, true), [
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();

    Queue::assertPushed(\App\Jobs\ProcessWhatsAppWebhookJob::class);
});

it('rejects webhook POST with invalid signature', function () {
    config(['services.whatsapp.meta.app_secret' => 'test-secret']);

    $this->postJson('/api/whatsapp/webhook', ['object' => 'test'], [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertForbidden();
});
```

**Step 2: Create the controller**

Run: `php artisan make:controller WhatsAppWebhookController --no-interaction`

Implement:
- `verify(Request $request)` — GET handler for Meta's verification challenge
- `handle(Request $request)` — POST handler; validate signature, dispatch `ProcessWhatsAppWebhookJob`, return 200

**Step 3: Create the middleware**

Run: `php artisan make:middleware VerifyWhatsAppWebhook --no-interaction`

Validate `X-Hub-Signature-256` against `hash_hmac('sha256', $request->getContent(), config('services.whatsapp.meta.app_secret'))`.

**Step 4: Add routes to `routes/api.php`**

```php
Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->middleware(VerifyWhatsAppWebhook::class);
```

**Step 5: Run tests**

Run: `php artisan test --compact tests/Feature/WhatsAppWebhookTest.php`

**Step 6: Commit**

```bash
git add app/Http/Controllers/WhatsAppWebhookController.php app/Http/Middleware/VerifyWhatsAppWebhook.php routes/api.php tests/Feature/WhatsAppWebhookTest.php
git commit -m "feat: add WhatsApp webhook controller with signature verification"
```

---

### Task 3.2: Create ProcessWhatsAppWebhookJob

**Files:**
- Create: `app/Jobs/ProcessWhatsAppWebhookJob.php`

**Step 1: Write test**

```php
it('processes delivery status update', function () {
    $log = NotificationLog::factory()->create([
        'channel' => 'whatsapp',
        'message_id' => 'wamid.abc123',
        'status' => 'sent',
    ]);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [[
                        'id' => 'wamid.abc123',
                        'status' => 'delivered',
                        'timestamp' => now()->timestamp,
                    ]],
                ],
            ]],
        ]],
    ];

    ProcessWhatsAppWebhookJob::dispatch($payload);

    $log->refresh();
    expect($log->status)->toBe('delivered');
    expect($log->delivered_at)->not->toBeNull();
});
```

**Step 2: Create the job**

Run: `php artisan make:job ProcessWhatsAppWebhookJob --no-interaction`

Implement:
- Parse `statuses[]` → update `NotificationLog` by `message_id` (wamid) and `WhatsAppMessage` by `wamid`
- Parse `messages[]` → create/update `WhatsAppConversation` + create `WhatsAppMessage` (inbound)
- Parse `errors[]` → update `NotificationLog` with error details
- Handle each webhook entry change type

**Step 3: Run tests and commit**

---

## Phase 4: Conversation + Message Models

### Task 4.1: Create database migrations

**Files:**
- Create: migration for `whatsapp_conversations`
- Create: migration for `whatsapp_messages`
- Create: migration for `whatsapp_templates`

**Step 1: Create migrations**

Run:
```bash
php artisan make:migration create_whatsapp_conversations_table --no-interaction
php artisan make:migration create_whatsapp_messages_table --no-interaction
php artisan make:migration create_whatsapp_templates_table --no-interaction
```

Schema as defined in the design document.

**Step 2: Run migrations**

Run: `php artisan migrate`

**Step 3: Commit**

---

### Task 4.2: Create Eloquent models

**Files:**
- Create: `app/Models/WhatsAppConversation.php`
- Create: `app/Models/WhatsAppMessage.php`
- Create: `app/Models/WhatsAppTemplate.php`

**Step 1: Create models with factories**

Run:
```bash
php artisan make:model WhatsAppConversation --factory --no-interaction
php artisan make:model WhatsAppMessage --factory --no-interaction
php artisan make:model WhatsAppTemplate --factory --no-interaction
```

**Step 2: Define relationships**

- `WhatsAppConversation`: `hasMany(WhatsAppMessage::class)`, `belongsTo(Student::class)`, scopes for `active()`, `archived()`, `withUnread()`
- `WhatsAppMessage`: `belongsTo(WhatsAppConversation::class)`, `belongsTo(User::class, 'sent_by_user_id')`, scopes for `inbound()`, `outbound()`
- `WhatsAppTemplate`: scopes for `approved()`, `byCategory()`

**Step 3: Write tests for relationships and scopes**

**Step 4: Commit**

---

### Task 4.3: Update ProcessWhatsAppWebhookJob to handle incoming messages

**Files:**
- Modify: `app/Jobs/ProcessWhatsAppWebhookJob.php`

**Step 1: Add incoming message handling**

When webhook contains `messages[]`:
1. Find or create `WhatsAppConversation` by phone number
2. Try to match phone number to a student in the DB
3. Create `WhatsAppMessage` (direction: `inbound`)
4. Update conversation: `last_message_at`, `last_message_preview`, increment `unread_count`
5. Set `is_service_window_open = true`, `service_window_expires_at = now + 24h`

**Step 2: Test and commit**

---

## Phase 5: Admin Chat Inbox (React)

### Task 5.1: Create API routes for chat inbox

**Files:**
- Create: `app/Http/Controllers/Admin/WhatsAppInboxController.php`
- Modify: `routes/api.php`

**Step 1: Create controller with REST methods**

Run: `php artisan make:controller Admin/WhatsAppInboxController --no-interaction`

Endpoints:
- `GET /api/admin/whatsapp/conversations` — list with search, filter (active/archived), paginated
- `GET /api/admin/whatsapp/conversations/{id}` — messages for a conversation, paginated, mark as read
- `POST /api/admin/whatsapp/conversations/{id}/reply` — send free-form reply (within 24h window)
- `POST /api/admin/whatsapp/conversations/{id}/template` — send template message
- `POST /api/admin/whatsapp/conversations/{id}/media` — upload and send media
- `POST /api/admin/whatsapp/conversations/{id}/archive` — archive conversation
- `GET /api/admin/whatsapp/templates` — list synced templates

**Step 2: Add routes under admin auth middleware**

```php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/whatsapp')->group(function () {
    Route::get('conversations', [WhatsAppInboxController::class, 'index']);
    Route::get('conversations/{conversation}', [WhatsAppInboxController::class, 'show']);
    Route::post('conversations/{conversation}/reply', [WhatsAppInboxController::class, 'reply']);
    Route::post('conversations/{conversation}/template', [WhatsAppInboxController::class, 'sendTemplate']);
    Route::post('conversations/{conversation}/media', [WhatsAppInboxController::class, 'sendMedia']);
    Route::post('conversations/{conversation}/archive', [WhatsAppInboxController::class, 'archive']);
    Route::get('templates', [WhatsAppInboxController::class, 'templates']);
    Route::post('templates/sync', [WhatsAppInboxController::class, 'syncTemplates']);
});
```

**Step 3: Write tests and commit**

---

### Task 5.2: Create Blade wrapper page

**Files:**
- Create: `resources/views/livewire/admin/whatsapp-inbox.blade.php`
- Modify: `routes/web.php` (add Volt route)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add nav item)

**Step 1: Create the Blade wrapper**

Minimal Volt component that renders the React mount point:

```php
<?php
use Livewire\Volt\Component;

new class extends Component {
    public function layout(): string
    {
        return 'components.layouts.app.sidebar';
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">WhatsApp Inbox</flux:heading>
        <flux:text class="mt-2">Manage WhatsApp conversations with students and parents</flux:text>
    </div>

    <div id="whatsapp-inbox-app"
         data-csrf-token="{{ csrf_token() }}"
         data-api-base="{{ url('/api/admin/whatsapp') }}"
         class="min-h-[calc(100vh-200px)]">
        <div class="flex items-center justify-center h-64">
            <flux:icon name="arrow-path" class="w-6 h-6 animate-spin text-zinc-400" />
        </div>
    </div>

    @viteReactRefresh
    @vite('resources/js/whatsapp-inbox/index.jsx')
</div>
```

**Step 2: Add route and nav item**

In `routes/web.php` admin group:
```php
Volt::route('whatsapp-inbox', 'admin.whatsapp-inbox')->name('admin.whatsapp-inbox');
```

In `sidebar.blade.php`, under the Settings navlist group:
```html
<flux:navlist.item icon="chat-bubble-left-right" :href="route('admin.whatsapp-inbox')" :current="request()->routeIs('admin.whatsapp-inbox')" wire:navigate>
    WhatsApp Inbox
</flux:navlist.item>
```

**Step 3: Commit**

---

### Task 5.3: Build React chat inbox app

**Files:**
- Create: `resources/js/whatsapp-inbox/index.jsx`
- Create: `resources/js/whatsapp-inbox/App.jsx`
- Create: `resources/js/whatsapp-inbox/components/ConversationList.jsx`
- Create: `resources/js/whatsapp-inbox/components/ChatPanel.jsx`
- Create: `resources/js/whatsapp-inbox/components/MessageBubble.jsx`
- Create: `resources/js/whatsapp-inbox/components/ReplyInput.jsx`
- Create: `resources/js/whatsapp-inbox/components/TemplatePicker.jsx`
- Create: `resources/js/whatsapp-inbox/components/ConversationHeader.jsx`
- Create: `resources/js/whatsapp-inbox/components/ServiceWindowBadge.jsx`
- Create: `resources/js/whatsapp-inbox/styles/whatsapp-inbox.css`
- Modify: `vite.config.js` (add entry point)

**Step 1: Create index.jsx (mount point)**

Same pattern as POS:
```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/whatsapp-inbox.css';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('whatsapp-inbox-app');
    if (container) {
        const props = {
            csrfToken: container.dataset.csrfToken,
            apiBase: container.dataset.apiBase,
        };
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App {...props} />
            </React.StrictMode>
        );
    }
});
```

**Step 2: Build App.jsx**

Two-panel layout:
- State: `selectedConversation`, `conversations`, `messages`, `searchQuery`, `activeTab`
- Polling: `useEffect` with `setInterval(fetchConversations, 5000)`
- Left panel: `<ConversationList>` with search and tabs
- Right panel: `<ChatPanel>` with messages and reply input

**Step 3: Build each component**

Follow the design from Section 4. Use Tailwind CSS classes. Clean, utilitarian aesthetic matching admin panel.

Key UX details:
- Message bubbles: `bg-zinc-100 rounded-2xl` for inbound (left), `bg-blue-50 rounded-2xl` for outbound (right)
- Unread badge on conversation list items
- 24h service window countdown in conversation header
- Template picker modal when window expired
- File attachment button with drag-and-drop
- Auto-scroll to bottom on new messages

**Step 4: Add Vite entry point**

In `vite.config.js`, add `'resources/js/whatsapp-inbox/index.jsx'` to the input array.

**Step 5: Test in browser**

Run `npm run build` and visit `/admin/whatsapp-inbox`.

**Step 6: Commit**

```bash
git add resources/js/whatsapp-inbox/ resources/views/livewire/admin/whatsapp-inbox.blade.php vite.config.js
git commit -m "feat: build WhatsApp admin chat inbox with React"
```

---

## Phase 6: Template Management

### Task 6.1: Template sync from Meta API

**Files:**
- Create: `app/Services/WhatsApp/TemplateService.php`
- Modify: `app/Http/Controllers/Admin/WhatsAppInboxController.php` (syncTemplates method)

**Step 1: Create TemplateService**

```php
class TemplateService
{
    public function syncFromMeta(): int
    {
        // GET /v21.0/{waba_id}/message_templates
        // Upsert each template into whatsapp_templates table
        // Return count of synced templates
    }

    public function getApproved(): Collection
    {
        return WhatsAppTemplate::approved()->get();
    }
}
```

**Step 2: Test and commit**

---

### Task 6.2: Template picker in React inbox

**Files:**
- Modify: `resources/js/whatsapp-inbox/components/TemplatePicker.jsx`

Modal that:
- Fetches approved templates from `/api/admin/whatsapp/templates`
- Shows template name, category, language, preview
- Allows filling in template parameters
- Sends via `/api/admin/whatsapp/conversations/{id}/template`

---

## Phase 7: CRM Workflow Handler Migration

### Task 7.1: Update SendWhatsAppHandler to use WhatsAppManager

**Files:**
- Modify: `app/Services/Workflow/Actions/SendWhatsAppHandler.php`

**Step 1: Inject WhatsAppManager instead of direct HTTP calls**

Replace:
```php
Http::withToken($token)->post($apiUrl . '/send', [...])
```

With:
```php
$provider = app(WhatsAppManager::class)->provider();
$provider->send($phone, $message);
```

**Step 2: Unify phone formatting** — use `WhatsAppService::formatPhoneNumber()` instead of the handler's own implementation.

**Step 3: Test and commit**

---

## Phase 8: Testing + Production Cutover

### Task 8.1: Integration testing

- Test full flow: send message via Meta → receive webhook → verify delivery status updated
- Test incoming message → conversation created → appears in inbox
- Test 24h window logic: reply within window (free-form allowed), reply outside window (template required)
- Test provider switching: toggle between onsend and meta, verify correct provider is used

### Task 8.2: Production setup

1. Add Meta env vars to production `.env`
2. Run migrations
3. Configure webhook URL in Meta Developer Console
4. Switch `WHATSAPP_PROVIDER=meta` (or via admin panel)
5. Test with a real phone number
6. Monitor webhook logs for delivery confirmations

### Task 8.3: Cleanup (after stable on Meta)

- Remove anti-ban logic from WhatsAppService (no longer needed)
- Remove Onsend-specific settings from admin panel (or keep as fallback)
- Update documentation

---

## Files Summary

### New files to create:
- `app/Contracts/WhatsAppProviderInterface.php`
- `app/Services/WhatsApp/OnsendProvider.php`
- `app/Services/WhatsApp/MetaCloudProvider.php`
- `app/Services/WhatsApp/WhatsAppManager.php`
- `app/Services/WhatsApp/TemplateService.php`
- `app/Http/Controllers/WhatsAppWebhookController.php`
- `app/Http/Controllers/Admin/WhatsAppInboxController.php`
- `app/Http/Middleware/VerifyWhatsAppWebhook.php`
- `app/Jobs/ProcessWhatsAppWebhookJob.php`
- `app/Models/WhatsAppConversation.php` + factory + migration
- `app/Models/WhatsAppMessage.php` + factory + migration
- `app/Models/WhatsAppTemplate.php` + factory + migration
- `resources/views/livewire/admin/whatsapp-inbox.blade.php`
- `resources/js/whatsapp-inbox/` (full React app, ~8 components)
- `tests/Feature/OnsendProviderTest.php`
- `tests/Feature/MetaCloudProviderTest.php`
- `tests/Feature/WhatsAppManagerTest.php`
- `tests/Feature/WhatsAppWebhookTest.php`
- `tests/Feature/WhatsAppServiceProviderDelegationTest.php`

### Files to modify:
- `app/Services/WhatsAppService.php` (delegate to manager)
- `app/Services/Workflow/Actions/SendWhatsAppHandler.php` (use manager)
- `config/services.php` (add whatsapp.meta config)
- `.env.example` (add META_WHATSAPP_* vars)
- `routes/api.php` (webhook + inbox API routes)
- `routes/web.php` (inbox page route)
- `resources/views/livewire/admin/settings-whatsapp.blade.php` (provider toggle + Meta fields)
- `resources/views/components/layouts/app/sidebar.blade.php` (nav item)
- `vite.config.js` (React entry point)
