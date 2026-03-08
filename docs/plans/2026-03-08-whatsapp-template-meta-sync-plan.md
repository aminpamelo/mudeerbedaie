# WhatsApp Template Meta Sync — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable submitting, updating, and deleting WhatsApp templates on Meta via the Graph API, so locally created templates can be verified by Meta for WABA usage.

**Architecture:** Extend existing `TemplateService` with 3 new methods (`submitToMeta`, `updateOnMeta`, `deleteFromMeta`) plus a private `mapComponentsForMeta` helper. Add corresponding Livewire actions and UI buttons in the Volt component. Add a `metaSynced` factory state.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest, Http facade (mocked in tests)

**Design doc:** `docs/plans/2026-03-08-whatsapp-template-meta-sync-design.md`

---

### Task 1: Add `metaSynced` factory state

**Files:**
- Modify: `database/factories/WhatsAppTemplateFactory.php:31` (after `rejected()` state)

**Step 1: Add the factory state**

Add after the `rejected()` method (line 46):

```php
public function metaSynced(): static
{
    return $this->state(fn (array $attributes) => [
        'meta_template_id' => (string) fake()->unique()->numberBetween(100000, 999999),
        'last_synced_at' => now(),
    ]);
}
```

**Step 2: Verify factory works**

Run: `php artisan tinker --execute="echo \App\Models\WhatsAppTemplate::factory()->metaSynced()->make()->toJson()"`
Expected: JSON with `meta_template_id` and `last_synced_at` populated.

**Step 3: Commit**

```bash
git add database/factories/WhatsAppTemplateFactory.php
git commit -m "feat: add metaSynced factory state for WhatsAppTemplate"
```

---

### Task 2: Add `mapComponentsForMeta` private helper to TemplateService

**Files:**
- Modify: `app/Services/WhatsApp/TemplateService.php:66` (before closing brace)

**Step 1: Write the failing test**

Create test file `tests/Feature/WhatsAppTemplateMetaSyncTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\SettingsService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    // Set up Meta credentials
    $settings = app(SettingsService::class);
    $settings->set('meta_waba_id', 'test-waba-123');
    $settings->set('meta_access_token', 'test-token-abc');
    $settings->set('meta_api_version', 'v21.0');
});

it('maps components for Meta with HEADER format field', function () {
    $service = app(TemplateService::class);

    $components = [
        ['type' => 'HEADER', 'text' => 'Hello {{1}}'],
        ['type' => 'BODY', 'text' => 'Your order is ready'],
        ['type' => 'FOOTER', 'text' => 'Thanks'],
    ];

    $reflection = new ReflectionMethod($service, 'mapComponentsForMeta');
    $mapped = $reflection->invoke($service, $components);

    expect($mapped[0])->toHaveKey('format', 'TEXT');
    expect($mapped[1])->not->toHaveKey('format');
    expect($mapped[2])->not->toHaveKey('format');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="maps components for Meta"`
Expected: FAIL — method `mapComponentsForMeta` does not exist.

**Step 3: Write the implementation**

Add to `app/Services/WhatsApp/TemplateService.php` before the closing `}`:

```php
/**
 * Get Meta API credentials or throw.
 *
 * @return array{wabaId: string, accessToken: string, apiVersion: string}
 *
 * @throws \RuntimeException
 */
private function getMetaCredentials(): array
{
    $wabaId = $this->settings->get('meta_waba_id');
    $accessToken = $this->settings->get('meta_access_token');
    $apiVersion = $this->settings->get('meta_api_version', 'v21.0');

    if (! $wabaId || ! $accessToken) {
        throw new \RuntimeException('Meta WABA ID and access token are required');
    }

    return compact('wabaId', 'accessToken', 'apiVersion');
}

/**
 * Map local components to Meta Graph API format.
 *
 * @param  array<int, array<string, mixed>>  $components
 * @return array<int, array<string, mixed>>
 */
private function mapComponentsForMeta(array $components): array
{
    return array_map(function (array $component) {
        if ($component['type'] === 'HEADER' && ! isset($component['format'])) {
            $component['format'] = 'TEXT';
        }

        return $component;
    }, $components);
}
```

Also refactor `syncFromMeta()` to use `getMetaCredentials()` — replace lines 23-29 with:

```php
['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="maps components for Meta"`
Expected: PASS

**Step 5: Run existing sync tests still pass**

Run: `php artisan test --compact tests/Feature/WhatsAppTemplateManagementTest.php`
Expected: All PASS

**Step 6: Commit**

```bash
git add app/Services/WhatsApp/TemplateService.php tests/Feature/WhatsAppTemplateMetaSyncTest.php
git commit -m "feat: add mapComponentsForMeta helper and getMetaCredentials to TemplateService"
```

---

### Task 3: Implement `submitToMeta()` in TemplateService

**Files:**
- Modify: `app/Services/WhatsApp/TemplateService.php`
- Modify: `tests/Feature/WhatsAppTemplateMetaSyncTest.php`

**Step 1: Write the failing tests**

Append to `tests/Feature/WhatsAppTemplateMetaSyncTest.php`:

```php
it('submits a local template to Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => 'meta-tpl-456',
            'status' => 'PENDING',
            'category' => 'UTILITY',
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'name' => 'test_submit',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'PENDING',
        'components' => [
            ['type' => 'HEADER', 'text' => 'Hello'],
            ['type' => 'BODY', 'text' => 'Welcome {{1}}'],
        ],
    ]);

    app(TemplateService::class)->submitToMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'test-waba-123/message_templates')
            && $request['name'] === 'test_submit'
            && $request['language'] === 'ms'
            && $request['category'] === 'UTILITY'
            && $request['components'][0]['format'] === 'TEXT';
    });

    $template->refresh();
    expect($template->meta_template_id)->toBe('meta-tpl-456');
    expect($template->status)->toBe('PENDING');
    expect($template->last_synced_at)->not->toBeNull();
});

it('throws exception when submitting to Meta fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template name already exists'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->create();

    app(TemplateService::class)->submitToMeta($template);
})->throws(RuntimeException::class, 'Failed to submit template to Meta: Template name already exists');

it('throws exception when Meta credentials are missing for submit', function () {
    $settings = app(SettingsService::class);
    $settings->set('meta_waba_id', '');
    $settings->set('meta_access_token', '');

    $template = WhatsAppTemplate::factory()->create();

    app(TemplateService::class)->submitToMeta($template);
})->throws(RuntimeException::class, 'Meta WABA ID and access token are required');
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="submits a local template to Meta"`
Expected: FAIL — method `submitToMeta` does not exist.

**Step 3: Write the implementation**

Add to `app/Services/WhatsApp/TemplateService.php` after `syncFromMeta()`:

```php
/**
 * Submit a local template to Meta for approval.
 *
 * @throws \RuntimeException
 */
public function submitToMeta(WhatsAppTemplate $template): void
{
    ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

    $response = Http::withToken($accessToken)
        ->post("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
            'name' => $template->name,
            'language' => $template->language,
            'category' => strtoupper($template->category),
            'components' => $this->mapComponentsForMeta($template->components ?? []),
        ]);

    if (! $response->successful()) {
        throw new \RuntimeException('Failed to submit template to Meta: ' . ($response->json('error.message') ?? 'Unknown error'));
    }

    $template->update([
        'meta_template_id' => $response->json('id'),
        'status' => $response->json('status', 'PENDING'),
        'last_synced_at' => now(),
    ]);
}
```

**Step 4: Run all 3 new tests to verify they pass**

Run: `php artisan test --compact --filter="submits a local template|throws exception when submitting|throws exception when Meta credentials are missing for submit"`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/TemplateService.php tests/Feature/WhatsAppTemplateMetaSyncTest.php
git commit -m "feat: add submitToMeta method to TemplateService"
```

---

### Task 4: Implement `updateOnMeta()` in TemplateService

**Files:**
- Modify: `app/Services/WhatsApp/TemplateService.php`
- Modify: `tests/Feature/WhatsAppTemplateMetaSyncTest.php`

**Step 1: Write the failing tests**

Append to `tests/Feature/WhatsAppTemplateMetaSyncTest.php`:

```php
it('updates a template on Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'name' => 'test_update',
        'category' => 'utility',
        'meta_template_id' => 'meta-789',
        'components' => [
            ['type' => 'BODY', 'text' => 'Updated body {{1}}'],
        ],
    ]);

    app(TemplateService::class)->updateOnMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'meta-789')
            && $request['category'] === 'UTILITY';
    });

    expect($template->fresh()->last_synced_at)->not->toBeNull();
});

it('throws exception when updating template without meta_template_id', function () {
    $template = WhatsAppTemplate::factory()->create(['meta_template_id' => null]);

    app(TemplateService::class)->updateOnMeta($template);
})->throws(RuntimeException::class, 'Template has not been submitted to Meta yet');

it('throws exception when Meta update fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid parameter'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-fail-123',
    ]);

    app(TemplateService::class)->updateOnMeta($template);
})->throws(RuntimeException::class, 'Failed to update template on Meta: Invalid parameter');
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="updates a template on Meta"`
Expected: FAIL — method `updateOnMeta` does not exist.

**Step 3: Write the implementation**

Add to `app/Services/WhatsApp/TemplateService.php` after `submitToMeta()`:

```php
/**
 * Update an existing template on Meta.
 *
 * @throws \RuntimeException
 */
public function updateOnMeta(WhatsAppTemplate $template): void
{
    if (! $template->meta_template_id) {
        throw new \RuntimeException('Template has not been submitted to Meta yet');
    }

    ['accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

    $response = Http::withToken($accessToken)
        ->post("https://graph.facebook.com/{$apiVersion}/{$template->meta_template_id}", [
            'components' => $this->mapComponentsForMeta($template->components ?? []),
            'category' => strtoupper($template->category),
        ]);

    if (! $response->successful()) {
        throw new \RuntimeException('Failed to update template on Meta: ' . ($response->json('error.message') ?? 'Unknown error'));
    }

    $template->update(['last_synced_at' => now()]);
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="updates a template on Meta|throws exception when updating template|throws exception when Meta update"`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/TemplateService.php tests/Feature/WhatsAppTemplateMetaSyncTest.php
git commit -m "feat: add updateOnMeta method to TemplateService"
```

---

### Task 5: Implement `deleteFromMeta()` in TemplateService

**Files:**
- Modify: `app/Services/WhatsApp/TemplateService.php`
- Modify: `tests/Feature/WhatsAppTemplateMetaSyncTest.php`

**Step 1: Write the failing tests**

Append to `tests/Feature/WhatsAppTemplateMetaSyncTest.php`:

```php
it('deletes a template from Meta successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'name' => 'test_delete',
        'meta_template_id' => 'meta-del-123',
    ]);
    $templateId = $template->id;

    app(TemplateService::class)->deleteFromMeta($template);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'message_templates')
            && str_contains($request->url(), 'name=test_delete');
    });

    expect(WhatsAppTemplate::find($templateId))->toBeNull();
});

it('throws exception when deleting template without meta_template_id', function () {
    $template = WhatsAppTemplate::factory()->create(['meta_template_id' => null]);

    app(TemplateService::class)->deleteFromMeta($template);
})->throws(RuntimeException::class, 'Template has not been submitted to Meta yet');

it('throws exception when Meta delete fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template not found'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-notfound',
    ]);

    app(TemplateService::class)->deleteFromMeta($template);
})->throws(RuntimeException::class, 'Failed to delete template from Meta: Template not found');
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="deletes a template from Meta"`
Expected: FAIL — method `deleteFromMeta` does not exist.

**Step 3: Write the implementation**

Add to `app/Services/WhatsApp/TemplateService.php` after `updateOnMeta()`:

```php
/**
 * Delete a template from Meta and locally.
 *
 * @throws \RuntimeException
 */
public function deleteFromMeta(WhatsAppTemplate $template): void
{
    if (! $template->meta_template_id) {
        throw new \RuntimeException('Template has not been submitted to Meta yet');
    }

    ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

    $response = Http::withToken($accessToken)
        ->delete("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
            'name' => $template->name,
        ]);

    if (! $response->successful()) {
        throw new \RuntimeException('Failed to delete template from Meta: ' . ($response->json('error.message') ?? 'Unknown error'));
    }

    $template->delete();
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="deletes a template from Meta|throws exception when deleting|throws exception when Meta delete"`
Expected: All PASS

**Step 5: Commit**

```bash
git add app/Services/WhatsApp/TemplateService.php tests/Feature/WhatsAppTemplateMetaSyncTest.php
git commit -m "feat: add deleteFromMeta method to TemplateService"
```

---

### Task 6: Add Volt component methods for Meta actions

**Files:**
- Modify: `resources/views/livewire/admin/whatsapp-templates.blade.php:9-223` (PHP section)
- Modify: `tests/Feature/WhatsAppTemplateMetaSyncTest.php`

**Step 1: Write the failing tests**

Append to `tests/Feature/WhatsAppTemplateMetaSyncTest.php`:

```php
it('submits template to Meta from Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'id' => 'meta-volt-123',
            'status' => 'PENDING',
            'category' => 'UTILITY',
        ], 200),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'meta_template_id' => null,
        'components' => [['type' => 'BODY', 'text' => 'Hello']],
    ]);

    Volt::test('admin.whatsapp-templates')
        ->call('submitTemplateToMeta', $template->id)
        ->assertSee('Template submitted to Meta for approval.');

    expect($template->fresh()->meta_template_id)->toBe('meta-volt-123');
});

it('updates template on Meta from Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-update-volt',
    ]);

    Volt::test('admin.whatsapp-templates')
        ->call('updateTemplateOnMeta', $template->id)
        ->assertSee('Template updated on Meta.');
});

it('deletes template from Meta via Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['success' => true], 200),
    ]);

    $template = WhatsAppTemplate::factory()->metaSynced()->create([
        'meta_template_id' => 'meta-del-volt',
    ]);
    $templateId = $template->id;

    Volt::test('admin.whatsapp-templates')
        ->call('deleteFromMeta', $templateId);

    expect(WhatsAppTemplate::find($templateId))->toBeNull();
});

it('handles Meta submit error in Volt component', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Name conflict'],
        ], 400),
    ]);

    $template = WhatsAppTemplate::factory()->create([
        'meta_template_id' => null,
        'components' => [['type' => 'BODY', 'text' => 'Test']],
    ]);

    Volt::test('admin.whatsapp-templates')
        ->call('submitTemplateToMeta', $template->id)
        ->assertSee('Name conflict');
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="submits template to Meta from Volt|updates template on Meta from Volt|deletes template from Meta via Volt|handles Meta submit error"`
Expected: FAIL — methods don't exist on component.

**Step 3: Add Volt component methods**

In `resources/views/livewire/admin/whatsapp-templates.blade.php`, add these 3 new properties after line 25 (`public ?int $previewTemplateId = null;`):

```php
public bool $showDeleteFromMetaModal = false;
public ?int $deletingFromMetaTemplateId = null;
public string $deletingFromMetaTemplateName = '';
```

Add these 3 methods after the `clearFilters()` method (after line 222):

```php
public function submitTemplateToMeta(int $templateId): void
{
    try {
        $template = WhatsAppTemplate::findOrFail($templateId);
        app(TemplateService::class)->submitToMeta($template);
        session()->flash('success', 'Template submitted to Meta for approval.');
    } catch (\RuntimeException $e) {
        session()->flash('error', $e->getMessage());
    }
}

public function updateTemplateOnMeta(int $templateId): void
{
    try {
        $template = WhatsAppTemplate::findOrFail($templateId);
        app(TemplateService::class)->updateOnMeta($template);
        session()->flash('success', 'Template updated on Meta.');
    } catch (\RuntimeException $e) {
        session()->flash('error', $e->getMessage());
    }
}

public function confirmDeleteFromMeta(WhatsAppTemplate $template): void
{
    $this->deletingFromMetaTemplateId = $template->id;
    $this->deletingFromMetaTemplateName = $template->name;
    $this->showDeleteFromMetaModal = true;
}

public function deleteFromMeta(?int $templateId = null): void
{
    $id = $templateId ?? $this->deletingFromMetaTemplateId;

    try {
        $template = WhatsAppTemplate::findOrFail($id);
        app(TemplateService::class)->deleteFromMeta($template);
        session()->flash('success', 'Template deleted from Meta.');
    } catch (\RuntimeException $e) {
        session()->flash('error', $e->getMessage());
    }

    $this->showDeleteFromMetaModal = false;
    $this->deletingFromMetaTemplateId = null;
    $this->deletingFromMetaTemplateName = '';
}
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="submits template to Meta from Volt|updates template on Meta from Volt|deletes template from Meta via Volt|handles Meta submit error"`
Expected: All PASS

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/whatsapp-templates.blade.php tests/Feature/WhatsAppTemplateMetaSyncTest.php
git commit -m "feat: add Meta sync Livewire actions to WhatsApp templates component"
```

---

### Task 7: Add UI buttons and delete-from-Meta modal

**Files:**
- Modify: `resources/views/livewire/admin/whatsapp-templates.blade.php:414-443` (actions column)

**Step 1: Replace the actions column content**

Replace the actions `<td>` content (lines 416-442) with:

```blade
<div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
    {{-- Meta sync actions --}}
    @if(! $template->meta_template_id)
        <flux:button
            size="sm"
            variant="ghost"
            icon="cloud-arrow-up"
            wire:click="submitTemplateToMeta({{ $template->id }})"
            wire:loading.attr="disabled"
            wire:target="submitTemplateToMeta({{ $template->id }})"
            class="text-blue-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
        >
            <flux:tooltip content="Submit to Meta" />
        </flux:button>
    @else
        <flux:button
            size="sm"
            variant="ghost"
            icon="arrow-path"
            wire:click="updateTemplateOnMeta({{ $template->id }})"
            wire:loading.attr="disabled"
            wire:target="updateTemplateOnMeta({{ $template->id }})"
            class="text-green-500 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
        >
            <flux:tooltip content="Update on Meta" />
        </flux:button>
    @endif

    <flux:button
        size="sm"
        variant="ghost"
        icon="eye"
        wire:click="openPreviewModal({{ $template->id }})"
    >
        <flux:tooltip content="Preview" />
    </flux:button>
    <flux:button
        size="sm"
        variant="ghost"
        icon="pencil-square"
        wire:click="openEditModal({{ $template->id }})"
    >
        <flux:tooltip content="Edit" />
    </flux:button>

    @if($template->meta_template_id)
        <flux:button
            size="sm"
            variant="ghost"
            icon="cloud-arrow-down"
            class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
            wire:click="confirmDeleteFromMeta({{ $template->id }})"
        >
            <flux:tooltip content="Delete from Meta" />
        </flux:button>
    @endif

    <flux:button
        size="sm"
        variant="ghost"
        icon="trash"
        class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
        wire:click="confirmDelete({{ $template->id }})"
    >
        <flux:tooltip content="Delete locally" />
    </flux:button>
</div>
```

**Step 2: Add the Delete from Meta confirmation modal**

Add before the closing `</div>` of the root element (before line 738):

```blade
{{-- Delete from Meta Confirmation Modal --}}
<flux:modal wire:model="showDeleteFromMetaModal" class="max-w-sm">
    <div class="p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
            <flux:icon name="cloud-arrow-down" class="w-6 h-6 text-red-600 dark:text-red-400" />
        </div>
        <flux:heading size="lg" class="mb-2">Delete from Meta</flux:heading>
        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
            Are you sure you want to delete <strong class="text-zinc-700 dark:text-zinc-300">{{ $deletingFromMetaTemplateName }}</strong> from Meta? This will remove the template from Meta and delete it locally. All language versions will be deleted on Meta.
        </flux:text>
        <div class="flex items-center justify-center gap-2 mt-5">
            <flux:button variant="ghost" wire:click="$set('showDeleteFromMetaModal', false)">
                Cancel
            </flux:button>
            <flux:button variant="danger" wire:click="deleteFromMeta">
                <span wire:loading.remove wire:target="deleteFromMeta">Delete from Meta</span>
                <span wire:loading wire:target="deleteFromMeta">Deleting...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
```

**Step 3: Verify visually**

Run: `npm run build` (or ensure `npm run dev` is running)
Navigate to: `https://mudeerbedaie.test/admin/whatsapp/templates`
Verify:
- Local templates show "Submit to Meta" (cloud-arrow-up icon) button on hover
- Meta-synced templates show "Update on Meta" (arrow-path icon) and "Delete from Meta" (cloud-arrow-down icon) buttons on hover
- All existing buttons (Preview, Edit, Delete locally) still present

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/whatsapp-templates.blade.php
git commit -m "feat: add Meta sync UI buttons and delete-from-Meta modal"
```

---

### Task 8: Run full test suite and format code

**Step 1: Run Pint formatter**

Run: `vendor/bin/pint --dirty`

**Step 2: Run the full new test file**

Run: `php artisan test --compact tests/Feature/WhatsAppTemplateMetaSyncTest.php`
Expected: All tests PASS

**Step 3: Run existing WhatsApp template tests**

Run: `php artisan test --compact tests/Feature/WhatsAppTemplateManagementTest.php`
Expected: All tests PASS

**Step 4: Commit any formatting changes**

```bash
git add -A
git commit -m "style: apply Pint formatting"
```

**Step 5: Ask user if they want to run the entire test suite**
