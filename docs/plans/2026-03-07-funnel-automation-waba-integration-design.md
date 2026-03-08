# Funnel Automation WABA Integration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add per-automation WhatsApp provider choice (Onsend vs WABA templates) to the funnel automation "Send WhatsApp" action.

**Architecture:** The existing `send_whatsapp` action gets a `provider` field in `action_config` JSON. When `provider=waba`, the service resolves a `WhatsAppTemplate`, maps merge-tag variables, and calls `MetaCloudProvider::sendTemplate()` directly. The React config panel shows a provider toggle that conditionally renders either the current free-text editor (Onsend) or a template dropdown with variable mapping (WABA).

**Tech Stack:** Laravel 12, Livewire Volt, React (ReactFlow automation builder), MetaCloudProvider, WhatsAppTemplate model, Pest tests.

---

## Task 1: API Endpoint — Approved WhatsApp Templates

**Files:**
- Modify: `routes/api.php:100-103` (add route in the v1 auth group)

**Step 1: Add the API route**

Add this inside the `Route::middleware(['auth:sanctum'])->prefix('v1')` group in `routes/api.php`, after the automation merge tag variables routes (line 103):

```php
// WhatsApp Templates for Funnel Builder
Route::get('funnel-builder/whatsapp-templates', function () {
    return response()->json([
        'data' => \App\Models\WhatsAppTemplate::approved()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'components' => $t->components,
                'body_preview' => collect($t->components)
                    ->firstWhere('type', 'BODY')['text'] ?? '',
            ]),
    ]);
})->name('api.funnel-builder.whatsapp-templates');
```

**Step 2: Test manually with tinker or browser**

Run: `php artisan tinker` then `\App\Models\WhatsAppTemplate::approved()->count()` to verify approved templates exist.

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat: add API endpoint for approved WhatsApp templates in funnel builder"
```

---

## Task 2: Backend — WABA Branch in executeSendWhatsApp

**Files:**
- Modify: `app/Services/Funnel/FunnelAutomationService.php:156-189`

**Step 1: Add WhatsAppTemplate and WhatsAppManager imports**

Add at the top of the file with the other imports:

```php
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppManager;
```

**Step 2: Inject WhatsAppManager into constructor**

Update the constructor (line 21-24):

```php
public function __construct(
    protected MergeTagEngine $mergeTagEngine,
    protected WhatsAppService $whatsAppService,
    protected WhatsAppManager $whatsAppManager
) {}
```

**Step 3: Rewrite executeSendWhatsApp method**

Replace the entire `executeSendWhatsApp` method (lines 156-189) with:

```php
protected function executeSendWhatsApp(FunnelAutomationAction $action, array $context): array
{
    $config = $action->action_config ?? [];
    $provider = $config['provider'] ?? 'onsend';
    $phoneField = $config['phone_field'] ?? 'contact.phone';

    // Get phone number from context
    $phone = $this->getValueFromContext($phoneField, $context);

    if (empty($phone)) {
        return [
            'success' => false,
            'error' => 'No phone number found in context',
        ];
    }

    if ($provider === 'waba') {
        return $this->executeSendWhatsAppWaba($config, $phone, $context);
    }

    return $this->executeSendWhatsAppOnsend($config, $phone);
}

protected function executeSendWhatsAppOnsend(array $config, string $phone): array
{
    $message = $config['message'] ?? $config['template'] ?? '';
    $processedMessage = $this->mergeTagEngine->resolve($message);

    Log::info('FunnelAutomation: Sending WhatsApp via Onsend', [
        'phone' => $phone,
        'message_length' => strlen($processedMessage),
    ]);

    $result = $this->whatsAppService->send($phone, $processedMessage);

    return [
        'success' => $result['success'],
        'message_id' => $result['message_id'] ?? null,
        'error' => $result['error'] ?? null,
        'phone' => $phone,
        'provider' => 'onsend',
    ];
}

protected function executeSendWhatsAppWaba(array $config, string $phone, array $context): array
{
    // Look up template
    $template = null;
    if (! empty($config['template_id'])) {
        $template = WhatsAppTemplate::find($config['template_id']);
    }

    $templateName = $template?->name ?? $config['template_name'] ?? '';
    $templateLanguage = $template?->language ?? $config['template_language'] ?? 'en';

    if (empty($templateName)) {
        return [
            'success' => false,
            'error' => 'No WhatsApp template configured',
        ];
    }

    // Check template is approved (if we found it locally)
    if ($template && $template->status !== 'APPROVED') {
        return [
            'success' => false,
            'error' => "Template '{$templateName}' is not approved (status: {$template->status})",
        ];
    }

    // Resolve merge tags in template variables
    $templateVariables = $config['template_variables'] ?? [];
    $components = $this->buildWabaComponents($templateVariables, $context);

    Log::info('FunnelAutomation: Sending WhatsApp via WABA template', [
        'phone' => $phone,
        'template' => $templateName,
        'language' => $templateLanguage,
    ]);

    // Send via Meta Cloud Provider directly
    $metaProvider = $this->whatsAppManager->provider();
    if (! ($metaProvider instanceof \App\Services\WhatsApp\MetaCloudProvider)) {
        // Force Meta provider for WABA sends
        $metaProvider = app(\App\Services\WhatsApp\MetaCloudProvider::class);
    }

    $result = $metaProvider->sendTemplate($phone, $templateName, $templateLanguage, $components);

    return [
        'success' => $result['success'],
        'message_id' => $result['message_id'] ?? null,
        'error' => $result['error'] ?? null,
        'phone' => $phone,
        'provider' => 'waba',
        'template_name' => $templateName,
    ];
}

/**
 * Build WABA components array from template variables config.
 *
 * Resolves merge tags like {{contact.name}} to actual values from context,
 * then formats them into the Meta API components structure.
 */
protected function buildWabaComponents(array $templateVariables, array $context): array
{
    $components = [];

    foreach ($templateVariables as $componentType => $variables) {
        if (empty($variables)) {
            continue;
        }

        $parameters = [];
        // Sort by key to ensure correct order (1, 2, 3...)
        ksort($variables);

        foreach ($variables as $index => $mergeTag) {
            $resolved = $this->resolveContextValue($mergeTag, $context);
            $parameters[] = [
                'type' => 'text',
                'text' => $resolved,
            ];
        }

        if (! empty($parameters)) {
            $components[] = [
                'type' => $componentType,
                'parameters' => $parameters,
            ];
        }
    }

    return $components;
}

/**
 * Resolve a merge tag or plain value from context.
 *
 * Handles {{contact.name}}, {{order.number}}, etc.
 * Falls back to the raw string if not a merge tag pattern.
 */
protected function resolveContextValue(string $value, array $context): string
{
    // Check if it's a merge tag pattern like {{contact.name}} or {{contact.name|default:"there"}}
    if (preg_match('/^\{\{(.+?)\}\}$/', trim($value), $matches)) {
        $key = $matches[1];

        // Handle default values: contact.name|default:"there"
        $default = '';
        if (str_contains($key, '|default:')) {
            [$key, $defaultPart] = explode('|default:', $key, 2);
            $default = trim($defaultPart, '"\'');
        }

        $resolved = $this->getValueFromContext(trim($key), $context);

        return ! empty($resolved) ? (string) $resolved : $default;
    }

    return $value;
}
```

**Step 4: Commit**

```bash
git add app/Services/Funnel/FunnelAutomationService.php
git commit -m "feat: add WABA template support to executeSendWhatsApp"
```

---

## Task 3: Tests — WABA Funnel Automation

**Files:**
- Create: `tests/Feature/FunnelAutomationWabaTest.php`

**Step 1: Create test file**

```php
<?php

declare(strict_types=1);

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\WhatsAppTemplate;
use App\Services\Funnel\FunnelAutomationService;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsApp\WhatsAppManager;

beforeEach(function () {
    // Mock the MetaCloudProvider so we don't hit real APIs
    $this->metaProvider = Mockery::mock(MetaCloudProvider::class);
    $this->metaProvider->shouldReceive('getName')->andReturn('meta');

    $manager = Mockery::mock(WhatsAppManager::class);
    $manager->shouldReceive('provider')->andReturn($this->metaProvider);
    app()->instance(WhatsAppManager::class, $manager);
});

test('executeSendWhatsApp sends via onsend when provider is onsend', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'onsend',
            'message' => 'Hello {{contact.name}}!',
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');

    // Mock WhatsAppService send
    $whatsAppService = Mockery::mock(\App\Services\WhatsAppService::class);
    $whatsAppService->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true, 'message_id' => 'msg_123']);
    app()->instance(\App\Services\WhatsAppService::class, $whatsAppService);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad', 'phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('onsend');
});

test('executeSendWhatsApp defaults to onsend when no provider set (backward compatible)', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'message' => 'Hello!',
            'phone_field' => 'contact.phone',
        ],
    ]);

    $whatsAppService = Mockery::mock(\App\Services\WhatsAppService::class);
    $whatsAppService->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true, 'message_id' => 'msg_456']);
    app()->instance(\App\Services\WhatsAppService::class, $whatsAppService);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('onsend');
});

test('executeSendWhatsApp sends WABA template when provider is waba', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'order_confirmation',
        'language' => 'ms',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'BODY', 'text' => 'Terima kasih {{1}}! Pesanan {{2}} berjumlah {{3}}.'],
        ],
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'template_name' => 'order_confirmation',
            'template_language' => 'ms',
            'template_variables' => [
                'body' => [
                    '1' => '{{contact.name}}',
                    '2' => '{{order.number}}',
                    '3' => '{{order.total}}',
                ],
            ],
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function ($phone, $name, $lang, $components) {
            return $phone === '60123456789'
                && $name === 'order_confirmation'
                && $lang === 'ms'
                && $components[0]['type'] === 'body'
                && $components[0]['parameters'][0]['text'] === 'Ahmad'
                && $components[0]['parameters'][1]['text'] === 'PO-001'
                && $components[0]['parameters'][2]['text'] === 'RM 99.00';
        })
        ->andReturn(['success' => true, 'message_id' => 'wamid_789']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad', 'phone' => '60123456789'],
        'order' => ['number' => 'PO-001', 'total' => 'RM 99.00'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['provider'])->toBe('waba');
    expect($result['template_name'])->toBe('order_confirmation');
});

test('executeSendWhatsApp fails when waba template is not approved', function () {
    $template = WhatsAppTemplate::factory()->create([
        'name' => 'pending_template',
        'language' => 'ms',
        'status' => 'PENDING',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => $template->id,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not approved');
});

test('executeSendWhatsApp fails when no template configured for waba', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('No WhatsApp template configured');
});

test('executeSendWhatsApp fails when no phone number in context', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => 1,
            'phone_field' => 'contact.phone',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['name' => 'Ahmad'],
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('No phone number');
});

test('WABA template falls back to template_name when template_id not found', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_whatsapp',
        'action_config' => [
            'provider' => 'waba',
            'template_id' => 99999, // non-existent
            'template_name' => 'fallback_template',
            'template_language' => 'en',
            'template_variables' => [],
            'phone_field' => 'contact.phone',
        ],
    ]);

    $this->metaProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn ($phone, $name, $lang) => $name === 'fallback_template' && $lang === 'en')
        ->andReturn(['success' => true, 'message_id' => 'wamid_fallback']);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendWhatsApp');
    $result = $method->invoke($service, $action, [
        'contact' => ['phone' => '60123456789'],
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['template_name'])->toBe('fallback_template');
});

test('whatsapp templates API returns only approved templates', function () {
    WhatsAppTemplate::factory()->create(['status' => 'APPROVED', 'name' => 'approved_one']);
    WhatsAppTemplate::factory()->create(['status' => 'PENDING', 'name' => 'pending_one']);
    WhatsAppTemplate::factory()->create(['status' => 'REJECTED', 'name' => 'rejected_one']);

    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/funnel-builder/whatsapp-templates');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('approved_one');
});
```

**Step 2: Run tests**

Run: `php artisan test --compact tests/Feature/FunnelAutomationWabaTest.php`

**Step 3: Fix any failures, then commit**

```bash
git add tests/Feature/FunnelAutomationWabaTest.php
git commit -m "test: add WABA funnel automation integration tests"
```

---

## Task 4: Frontend — Update Default Config and Types

**Files:**
- Modify: `resources/js/funnel-builder/types/funnel-automation-types.js:212-219`

**Step 1: Update SEND_WHATSAPP default config**

Change the config in `FUNNEL_ACTION_CONFIGS[SEND_WHATSAPP]` (line 218) from:

```js
config: { message: '', template_id: null },
```

to:

```js
config: { provider: 'onsend', message: '', template_id: null, template_name: '', template_language: '', template_variables: {} },
```

**Step 2: Commit**

```bash
git add resources/js/funnel-builder/types/funnel-automation-types.js
git commit -m "feat: update SEND_WHATSAPP default config with provider field"
```

---

## Task 5: Frontend — Provider Toggle and WABA Template UI

**Files:**
- Modify: `resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx:1296-1328`

**Step 1: Add WhatsApp template state and fetch logic**

Near the top of the `FunnelAutomationBuilderInner` component (or wherever React state is managed), add state for templates:

```jsx
const [wabaTemplates, setWabaTemplates] = useState([]);
const [wabaTemplatesLoading, setWabaTemplatesLoading] = useState(false);

// Fetch WABA templates when needed
const fetchWabaTemplates = useCallback(async () => {
    if (wabaTemplates.length > 0) return; // already fetched
    setWabaTemplatesLoading(true);
    try {
        const response = await fetch('/api/v1/funnel-builder/whatsapp-templates', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        setWabaTemplates(data.data || []);
    } catch (err) {
        console.error('Failed to fetch WABA templates:', err);
    } finally {
        setWabaTemplatesLoading(false);
    }
}, [wabaTemplates.length]);
```

**Step 2: Replace WhatsApp config panel**

Replace the WhatsApp Action Config block (lines 1296-1328) with:

```jsx
{/* WhatsApp Action Config */}
{nodeType === 'action' && data.actionType === FUNNEL_ACTION_TYPES.SEND_WHATSAPP && (
    <div className="space-y-3">
        {/* Provider Toggle */}
        <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Provider</label>
            <div className="flex rounded-lg border border-gray-300 overflow-hidden">
                <button
                    type="button"
                    className={`flex-1 px-3 py-2 text-xs font-medium transition-colors ${
                        (data.config?.provider || 'onsend') === 'onsend'
                            ? 'bg-green-500 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                    onClick={() => onUpdate({ config: { ...data.config, provider: 'onsend' } })}
                >
                    Onsend
                </button>
                <button
                    type="button"
                    className={`flex-1 px-3 py-2 text-xs font-medium transition-colors ${
                        data.config?.provider === 'waba'
                            ? 'bg-green-500 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                    onClick={() => {
                        onUpdate({ config: { ...data.config, provider: 'waba' } });
                        fetchWabaTemplates();
                    }}
                >
                    WABA (Official)
                </button>
            </div>
        </div>

        {/* Onsend Mode: Free-text message */}
        {(data.config?.provider || 'onsend') === 'onsend' && (
            <>
                <TextareaWithVariables
                    label="Message"
                    value={data.config?.message || ''}
                    onChange={(value) => onUpdate({ config: { ...data.config, message: value } })}
                    triggerType={triggerType}
                    rows={6}
                    placeholder="Hi {{contact.name|default:&quot;there&quot;}}!&#10;&#10;Thank you for your purchase!&#10;&#10;Order #: {{order.number}}&#10;Total: {{order.total}}&#10;&#10;{{order.items_list}}"
                    helpText="Use merge tags to personalize your message"
                />
                {data.config?.message && (
                    <VariablePreview text={data.config.message} className="mt-2" />
                )}
            </>
        )}

        {/* WABA Mode: Template selection + variable mapping */}
        {data.config?.provider === 'waba' && (
            <>
                {/* Template Dropdown */}
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Template</label>
                    {wabaTemplatesLoading ? (
                        <div className="text-xs text-gray-500 py-2">Loading templates...</div>
                    ) : (
                        <select
                            value={data.config?.template_id || ''}
                            onChange={(e) => {
                                const tpl = wabaTemplates.find(t => t.id === Number(e.target.value));
                                if (tpl) {
                                    onUpdate({
                                        config: {
                                            ...data.config,
                                            template_id: tpl.id,
                                            template_name: tpl.name,
                                            template_language: tpl.language,
                                            template_variables: data.config?.template_variables || {},
                                        },
                                    });
                                }
                            }}
                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Select template...</option>
                            {wabaTemplates.map((tpl) => (
                                <option key={tpl.id} value={tpl.id}>
                                    {tpl.name} ({tpl.language})
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {/* Template Preview */}
                {data.config?.template_id && (() => {
                    const selectedTpl = wabaTemplates.find(t => t.id === Number(data.config.template_id));
                    if (!selectedTpl) return null;

                    const bodyComponent = selectedTpl.components?.find(c => c.type === 'BODY');
                    const bodyText = bodyComponent?.text || '';
                    // Extract {{1}}, {{2}}, etc.
                    const variableMatches = bodyText.match(/\{\{\d+\}\}/g) || [];
                    const variableNumbers = variableMatches.map(m => m.replace(/[{}]/g, ''));

                    return (
                        <>
                            {/* Preview */}
                            <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <label className="block text-xs font-medium text-gray-500 mb-1">Template Preview</label>
                                <p className="text-sm text-gray-800 whitespace-pre-wrap">{bodyText}</p>
                            </div>

                            {/* Variable Mapping */}
                            {variableNumbers.length > 0 && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-2">Variable Mapping</label>
                                    <div className="space-y-2">
                                        {variableNumbers.map((num) => (
                                            <div key={num} className="flex items-center gap-2">
                                                <span className="text-xs font-mono text-gray-500 w-10 shrink-0">{`{{${num}}}`}</span>
                                                <TextareaWithVariables
                                                    value={data.config?.template_variables?.body?.[num] || ''}
                                                    onChange={(value) => {
                                                        const vars = { ...(data.config?.template_variables || {}) };
                                                        vars.body = { ...(vars.body || {}), [num]: value };
                                                        onUpdate({ config: { ...data.config, template_variables: vars } });
                                                    }}
                                                    triggerType={triggerType}
                                                    rows={1}
                                                    placeholder={`Value for {{${num}}}`}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    );
                })()}

                {/* Fetch templates hint if empty */}
                {!wabaTemplatesLoading && wabaTemplates.length === 0 && (
                    <div className="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                        <p className="text-xs text-yellow-700">
                            No approved templates found. Sync templates from Meta in{' '}
                            <a href="/admin/whatsapp/templates" target="_blank" className="underline font-medium">
                                WhatsApp Templates
                            </a>.
                        </p>
                    </div>
                )}
            </>
        )}

        {/* Phone Number Field Info (shown for both providers) */}
        <div className="p-3 bg-blue-50 rounded-lg border border-blue-100">
            <div className="flex items-start gap-2">
                <svg className="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div className="text-xs text-blue-700">
                    <p className="font-medium mb-1">Phone Number</p>
                    <p>The message will be sent to <code className="bg-blue-100 px-1 rounded">{'{{contact.phone}}'}</code> automatically from the order/contact data.</p>
                </div>
            </div>
        </div>
    </div>
)}
```

**Step 3: Build and verify**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx
git commit -m "feat: add provider toggle and WABA template UI to automation builder"
```

---

## Task 6: Run All Tests and Final Verification

**Step 1: Run the WABA-specific tests**

Run: `php artisan test --compact tests/Feature/FunnelAutomationWabaTest.php`

**Step 2: Run the existing automation email template tests (regression)**

Run: `php artisan test --compact tests/Feature/FunnelAutomationEmailTemplateTest.php`

**Step 3: Build frontend**

Run: `npm run build`

**Step 4: Manual verification in browser**

1. Go to a funnel's automation builder
2. Add a "Send WhatsApp" action
3. Verify the provider toggle shows Onsend / WABA
4. Onsend mode: verify the current free-text editor works as before
5. WABA mode: verify template dropdown loads, selecting a template shows preview and variable mapping
6. Save and reload: verify config persists correctly

**Step 5: Format code**

Run: `vendor/bin/pint --dirty`

**Step 6: Final commit**

```bash
git add -A
git commit -m "style: format code with Pint"
```
