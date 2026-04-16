# Broadcast Email Builder Integration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let broadcast creators pick a Funnel Email Template or build one from scratch using the existing Unlayer visual email builder.

**Architecture:** Add `design_json`, `html_content`, `editor_type` to broadcasts table. Create a new broadcast-builder Volt component (mirrors funnel-email-template-builder). Enhance Step 3 of broadcast-create with a template picker and "Open Builder" button. Add a `broadcast` template type to the React email builder.

**Tech Stack:** Laravel 12, Livewire Volt, React (Unlayer email editor), Flux UI

**Design doc:** `docs/plans/2026-04-16-broadcast-email-template-integration.md`

---

### Task 1: Migration — Add email builder columns to broadcasts

**Files:**
- Create: `database/migrations/2026_04_16_000001_add_email_builder_fields_to_broadcasts.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration add_email_builder_fields_to_broadcasts --no-interaction
```

**Step 2: Write migration code**

Edit the generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->longText('design_json')->nullable()->after('content');
            $table->longText('html_content')->nullable()->after('design_json');
            $table->string('editor_type', 20)->default('text')->after('html_content');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['design_json', 'html_content', 'editor_type']);
        });
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully.

**Step 4: Commit**

```bash
git add database/migrations/*add_email_builder_fields_to_broadcasts*
git commit -m "feat(broadcast): add design_json, html_content, editor_type columns"
```

---

### Task 2: Update Broadcast model

**Files:**
- Modify: `app/Models/Broadcast.php` (lines 23-39 fillable, lines 14-21 casts)

**Step 1: Add new fields to fillable array**

In `app/Models/Broadcast.php`, add to the `$fillable` array (after `'content'`):
- `'design_json'`
- `'html_content'`
- `'editor_type'`

**Step 2: Add casts for design_json**

Add to the casts:
- `'design_json' => 'array'`

**Step 3: Add helper methods**

Add these methods to the Broadcast model (after the existing `getRecipientsAttribute()`):

```php
public function isVisualEditor(): bool
{
    return $this->editor_type === 'visual';
}

public function getEffectiveContent(): string
{
    return $this->isVisualEditor() ? ($this->html_content ?? '') : ($this->content ?? '');
}
```

**Step 4: Commit**

```bash
git add app/Models/Broadcast.php
git commit -m "feat(broadcast): add email builder fields to model"
```

---

### Task 3: Add broadcast placeholder set to React email builder

**Files:**
- Modify: `resources/js/react-email-builder.jsx` (lines 6-67, the `PLACEHOLDER_SETS` object)

**Step 1: Add broadcast entry to PLACEHOLDER_SETS**

In `resources/js/react-email-builder.jsx`, add a new entry to the `PLACEHOLDER_SETS` object (after `funnel_email_template` at line 66):

```javascript
broadcast: {
    'Recipient': {
        '{{name}}': 'Recipient name',
        '{{email}}': 'Recipient email',
        '{{student_id}}': 'Student ID',
    },
    'General': {
        '{{current_date}}': 'Current date',
        '{{current_time}}': 'Current time',
        '{{company_name}}': 'Company name',
        '{{company_email}}': 'Company email',
    },
},
```

**Step 2: Run build**

Run: `npm run build`
Expected: Build succeeds.

**Step 3: Commit**

```bash
git add resources/js/react-email-builder.jsx
git commit -m "feat(broadcast): add broadcast merge tags to email builder"
```

---

### Task 4: Create broadcast-builder Volt component

**Files:**
- Create: `resources/views/livewire/crm/broadcast-builder.blade.php`

This mirrors the existing `resources/views/livewire/admin/funnel-email-template-builder.blade.php` pattern but for the Broadcast model.

**Step 1: Create the component file**

Create `resources/views/livewire/crm/broadcast-builder.blade.php`:

```php
<?php

use App\Models\Broadcast;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.react-email-builder')]
class extends Component {
    public Broadcast $broadcast;
    public string $initialDesign = '';
    public bool $showPreview = false;
    public string $previewHtml = '';

    public function mount(Broadcast $broadcast): void
    {
        $this->broadcast = $broadcast;

        if ($broadcast->design_json) {
            $this->initialDesign = json_encode($broadcast->design_json);
        }
    }

    public function saveDesign(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->broadcast->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Broadcast email saved!',
        ]);
    }

    public function autoSave(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->broadcast->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);
    }

    public function previewEmailFromHtml(string $html): void
    {
        $sampleData = $this->getSampleData();
        $processedHtml = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $html
        );
        $this->previewHtml = $processedHtml;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewHtml = '';
    }

    public function sendTestEmail(string $email, string $html): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email address');
        }

        $sampleData = $this->getSampleData();
        $processedHtml = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $html
        );

        $finalHtml = $this->compileEmailHtml($processedHtml);

        \Illuminate\Support\Facades\Mail::html($finalHtml, function ($message) use ($email) {
            $message->to($email)
                ->subject('[TEST] Broadcast: ' . $this->broadcast->name);
        });
    }

    protected function compileEmailHtml(string $html): string
    {
        if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' . $html . '</body></html>';
        }

        return $html;
    }

    protected function getSampleData(): array
    {
        return [
            '{{name}}' => 'Ahmad Amin',
            '{{email}}' => 'ahmad@example.com',
            '{{student_id}}' => 'STU-001',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address', 'info@example.com'),
        ];
    }
}; ?>

<div wire:id="{{ $this->getId() }}" class="h-screen overflow-hidden">
    <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border-b dark:border-zinc-700">
        <div class="flex items-center gap-3">
            <a href="{{ route('crm.broadcasts.create') }}?resume={{ $broadcast->id }}" class="text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-lg font-semibold dark:text-zinc-100">{{ $broadcast->name ?: 'Untitled Broadcast' }}</h1>
                <p class="text-sm text-gray-500 dark:text-zinc-400">Visual Email Builder</p>
            </div>
        </div>
    </div>

    <div
        wire:ignore
        id="react-email-builder-root"
        class="h-full"
        data-template-id="{{ $broadcast->id }}"
        data-template-name="{{ $broadcast->name }}"
        data-template-type="broadcast"
        data-template-language="en"
        data-initial-design="{{ $initialDesign }}"
        data-back-url="{{ route('crm.broadcasts.create') }}?resume={{ $broadcast->id }}"
    ></div>

    @if($showPreview)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[300] flex items-center justify-center" wire:click.self="closePreview">
            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-2xl w-[90%] max-w-[700px] max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Email Preview</h2>
                    <button type="button" wire:click="closePreview" class="p-2 hover:bg-gray-100 dark:hover:bg-zinc-700 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-6 bg-gray-100 dark:bg-zinc-700">
                    <div class="flex justify-center">
                        <iframe
                            srcdoc="{{ $previewHtml }}"
                            class="w-full max-w-[600px] h-[500px] border-0 bg-white shadow-lg rounded-lg"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                </div>
                <div class="flex justify-end p-4 border-t border-gray-200 dark:border-zinc-700">
                    <button type="button" wire:click="closePreview" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-zinc-700 border border-gray-300 dark:border-zinc-600 rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
```

**Step 2: Add route**

In `routes/web.php`, add after the existing broadcast routes (around line 412):

```php
Volt::route('crm/broadcasts/{broadcast}/builder', 'crm.broadcast-builder')->name('crm.broadcasts.builder');
```

**Step 3: Run build**

Run: `npm run build`
Expected: Build succeeds.

**Step 4: Commit**

```bash
git add resources/views/livewire/crm/broadcast-builder.blade.php routes/web.php
git commit -m "feat(broadcast): add visual email builder page for broadcasts"
```

---

### Task 5: Enhance broadcast-create Step 3 — template picker + builder integration

**Files:**
- Modify: `resources/views/livewire/crm/broadcast-create.blade.php`

This is the largest task. We need to:
1. Add new properties for template selection and editor state
2. Add methods to load templates, select template, save draft for builder, and resume from builder
3. Replace the Step 3 content section with template picker + builder button

**Step 1: Add new properties and imports**

In `broadcast-create.blade.php`, add `FunnelEmailTemplate` import (after line 4):

```php
use App\Models\FunnelEmailTemplate;
```

Add new properties after line 29 (`content = ''`):

```php
    public string $design_json = '';
    public string $html_content = '';
    public string $editor_type = 'text';
    public ?int $selectedTemplateId = null;
    public string $templateSearch = '';
```

**Step 2: Add mount logic for resuming from builder**

In the `mount()` method (lines 35-41), add logic to resume a draft broadcast that was opened in the builder. After the existing mount code, add:

```php
    // Resume from builder if broadcast ID is passed
    $resumeId = request()->query('resume');
    if ($resumeId) {
        $broadcast = Broadcast::find($resumeId);
        if ($broadcast && $broadcast->status === 'draft') {
            $this->name = $broadcast->name ?: '';
            $this->type = $broadcast->type ?: 'standard';
            $this->from_name = $broadcast->from_name ?: $this->from_name;
            $this->from_email = $broadcast->from_email ?: $this->from_email;
            $this->reply_to_email = $broadcast->reply_to_email ?: '';
            $this->subject = $broadcast->subject ?: '';
            $this->preview_text = $broadcast->preview_text ?: '';
            $this->content = $broadcast->content ?: '';
            $this->design_json = $broadcast->design_json ? json_encode($broadcast->design_json) : '';
            $this->html_content = $broadcast->html_content ?: '';
            $this->editor_type = $broadcast->editor_type ?: 'text';
            $this->currentStep = 3;

            // Restore audiences and students
            $audienceIds = $broadcast->audiences()->pluck('audiences.id')->toArray();
            $this->selectedAudiences = $audienceIds;
            $this->selectedStudents = $broadcast->selected_students ?: [];
        }
    }
```

**Step 3: Add template methods**

Add these methods inside the class (before the `with()` method):

```php
    public function selectTemplate(int $templateId): void
    {
        $template = FunnelEmailTemplate::find($templateId);
        if (!$template) {
            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->subject = $template->subject ?: $this->subject;
        $this->html_content = $template->html_content ?: '';
        $this->design_json = $template->design_json ? json_encode($template->design_json) : '';
        $this->editor_type = 'visual';
        $this->content = $template->getEffectiveContent();
    }

    public function clearTemplate(): void
    {
        $this->selectedTemplateId = null;
        $this->html_content = '';
        $this->design_json = '';
        $this->editor_type = 'text';
        $this->content = '';
    }

    public function openBuilder(): string
    {
        // Save as draft first so builder has a broadcast to save to
        $broadcast = Broadcast::create([
            'name' => $this->name ?: 'Untitled Broadcast',
            'type' => $this->type,
            'status' => 'draft',
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to_email' => $this->reply_to_email,
            'subject' => $this->subject,
            'preview_text' => $this->preview_text,
            'content' => $this->content,
            'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
            'html_content' => $this->html_content,
            'editor_type' => $this->editor_type,
            'selected_students' => $this->selectedStudents,
        ]);

        if (!empty($this->selectedAudiences)) {
            $broadcast->audiences()->attach($this->selectedAudiences);
        }

        return $this->redirect(route('crm.broadcasts.builder', $broadcast));
    }
```

**Step 4: Update the with() method**

In the `with()` method, add funnel email templates to the returned data:

```php
'emailTemplates' => FunnelEmailTemplate::where('is_active', true)
    ->when($this->templateSearch, fn($q) => $q->where('name', 'like', "%{$this->templateSearch}%"))
    ->orderBy('name')
    ->get(),
```

**Step 5: Update saveDraft() and send() methods**

In `saveDraft()` (lines 153-173), add the new fields to `Broadcast::create()`:

```php
'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
'html_content' => $this->html_content,
'editor_type' => $this->editor_type,
```

In `send()` (lines 195-208), add the same fields to `Broadcast::create()`:

```php
'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
'html_content' => $this->html_content,
'editor_type' => $this->editor_type,
```

Also in `send()`, update the validation rule for content (line 186) to be conditional:

```php
'content' => $this->editor_type === 'visual' ? 'nullable' : 'required|string',
```

Add a custom validation check after the standard validate block:

```php
if ($this->editor_type === 'visual' && empty($this->html_content)) {
    $this->addError('content', 'Please create email content using the builder.');
    return;
}
```

**Step 6: Replace Step 3 template in the Blade**

Replace the Step 3 content block (lines 427-487) with the enhanced version. Keep the sender fields (from_name, from_email, reply_to_email, subject, preview_text) and replace the content textarea section (lines 477-484) with:

```blade
<div class="border-t border-zinc-200 dark:border-zinc-700 pt-4"></div>

<!-- Content Mode Selector -->
<div class="space-y-4">
    <div class="flex items-center gap-2">
        <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Email Content</h3>
        @if($editor_type === 'visual')
            <flux:badge color="emerald" size="sm">Visual Builder</flux:badge>
        @endif
    </div>

    <!-- Template Picker -->
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex items-center justify-between mb-3">
            <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">Choose a Template</flux:text>
            <flux:input wire:model.live.debounce.300ms="templateSearch" placeholder="Search templates..." size="sm" class="w-48" />
        </div>

        @if($emailTemplates->isNotEmpty())
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-56 overflow-y-auto">
                @foreach($emailTemplates as $tmpl)
                    <button
                        wire:click="selectTemplate({{ $tmpl->id }})"
                        wire:key="tmpl-{{ $tmpl->id }}"
                        type="button"
                        class="text-left p-3 rounded-lg border transition-all {{ $selectedTemplateId === $tmpl->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500' : 'border-zinc-200 dark:border-zinc-600 hover:border-zinc-300 dark:hover:border-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-700/50' }}"
                    >
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $tmpl->name }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <flux:badge size="sm" color="zinc">{{ ucfirst($tmpl->category) }}</flux:badge>
                            @if($tmpl->editor_type === 'visual')
                                <flux:badge size="sm" color="blue">Visual</flux:badge>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @else
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">No email templates available.</flux:text>
        @endif

        @if($selectedTemplateId)
            <div class="mt-3 flex items-center gap-2">
                <flux:text size="sm" class="text-emerald-600 dark:text-emerald-400">
                    Template selected — content loaded
                </flux:text>
                <flux:button variant="ghost" size="sm" wire:click="clearTemplate">Clear</flux:button>
            </div>
        @endif
    </div>

    <!-- Builder Actions -->
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="primary" size="sm" wire:click="openBuilder">
            <div class="flex items-center justify-center">
                <flux:icon name="paint-brush" class="w-4 h-4 mr-1" />
                {{ $editor_type === 'visual' ? 'Edit in Builder' : 'Open Visual Builder' }}
            </div>
        </flux:button>

        @if($editor_type === 'visual' && $html_content)
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                Content created with visual builder
            </flux:text>
        @endif
    </div>

    <!-- Fallback: Manual HTML content (shown when not using visual builder) -->
    @if($editor_type !== 'visual')
        <div>
            <flux:field>
                <flux:label>Email Content (HTML)</flux:label>
                <flux:textarea wire:model="content" rows="10" placeholder="Enter your email content here..." />
                <p class="text-xs text-zinc-500 dark:text-zinc-400">You can use merge tags: @{{name}}, @{{email}}, @{{student_id}}</p>
                <flux:error name="content" />
            </flux:field>
        </div>
    @else
        <!-- Visual content preview -->
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-50 dark:bg-zinc-800 px-4 py-2 border-b border-zinc-200 dark:border-zinc-700">
                <flux:text size="sm" class="font-medium text-zinc-600 dark:text-zinc-400">Email Preview</flux:text>
            </div>
            <div class="p-4 bg-white dark:bg-zinc-900">
                <iframe
                    srcdoc="{{ $html_content }}"
                    class="w-full h-64 border-0 rounded"
                    sandbox="allow-same-origin"
                ></iframe>
            </div>
        </div>
        <flux:error name="content" />
    @endif
</div>
```

**Step 7: Run build**

Run: `npm run build`
Expected: Build succeeds.

**Step 8: Commit**

```bash
git add resources/views/livewire/crm/broadcast-create.blade.php
git commit -m "feat(broadcast): add template picker and visual builder integration to Step 3"
```

---

### Task 6: Update SendBroadcastEmail job to use html_content

**Files:**
- Modify: `app/Jobs/SendBroadcastEmail.php` (line 51)

**Step 1: Update content resolution**

In `SendBroadcastEmail.php`, change line 51 from:

```php
$content = $this->replaceMergeTags($this->broadcast->content, $student);
```

To:

```php
$content = $this->replaceMergeTags($this->broadcast->getEffectiveContent(), $student);
```

This uses the `getEffectiveContent()` method we added in Task 2, which returns `html_content` for visual editor and `content` for text editor.

**Step 2: Commit**

```bash
git add app/Jobs/SendBroadcastEmail.php
git commit -m "feat(broadcast): use html_content from visual builder when sending"
```

---

### Task 7: Write tests

**Files:**
- Create: `tests/Feature/BroadcastEmailBuilderTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test BroadcastEmailBuilderTest --pest --no-interaction
```

**Step 2: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\FunnelEmailTemplate;
use App\Models\User;
use Livewire\Volt\Volt;

test('broadcast model returns effective content for text editor', function () {
    $broadcast = Broadcast::factory()->create([
        'content' => '<p>Text content</p>',
        'html_content' => null,
        'editor_type' => 'text',
    ]);

    expect($broadcast->getEffectiveContent())->toBe('<p>Text content</p>');
    expect($broadcast->isVisualEditor())->toBeFalse();
});

test('broadcast model returns effective content for visual editor', function () {
    $broadcast = Broadcast::factory()->create([
        'content' => '<p>Text fallback</p>',
        'html_content' => '<html><body>Visual content</body></html>',
        'editor_type' => 'visual',
    ]);

    expect($broadcast->getEffectiveContent())->toBe('<html><body>Visual content</body></html>');
    expect($broadcast->isVisualEditor())->toBeTrue();
});

test('broadcast create step 3 shows template picker', function () {
    $user = User::where('email', 'admin@example.com')->first();

    FunnelEmailTemplate::factory()->create([
        'name' => 'Welcome Template',
        'category' => 'welcome',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->actingAs($user)
        ->set('currentStep', 3)
        ->assertSee('Choose a Template')
        ->assertSee('Welcome Template');
});

test('broadcast create can select a funnel email template', function () {
    $user = User::where('email', 'admin@example.com')->first();

    $template = FunnelEmailTemplate::factory()->create([
        'name' => 'Test Template',
        'subject' => 'Template Subject',
        'html_content' => '<html><body>Template HTML</body></html>',
        'design_json' => ['body' => ['rows' => []]],
        'editor_type' => 'visual',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->actingAs($user)
        ->set('currentStep', 3)
        ->call('selectTemplate', $template->id)
        ->assertSet('selectedTemplateId', $template->id)
        ->assertSet('editor_type', 'visual')
        ->assertSet('subject', 'Template Subject');
});

test('broadcast create can clear selected template', function () {
    $user = User::where('email', 'admin@example.com')->first();

    $template = FunnelEmailTemplate::factory()->create([
        'name' => 'Test Template',
        'subject' => 'Template Subject',
        'html_content' => '<html><body>HTML</body></html>',
        'design_json' => ['body' => []],
        'editor_type' => 'visual',
        'is_active' => true,
    ]);

    Volt::test('crm.broadcast-create')
        ->actingAs($user)
        ->set('currentStep', 3)
        ->call('selectTemplate', $template->id)
        ->call('clearTemplate')
        ->assertSet('selectedTemplateId', null)
        ->assertSet('editor_type', 'text')
        ->assertSet('html_content', '');
});

test('broadcast builder page loads for draft broadcast', function () {
    $user = User::where('email', 'admin@example.com')->first();

    $broadcast = Broadcast::factory()->create([
        'status' => 'draft',
        'name' => 'Test Broadcast',
    ]);

    $this->actingAs($user)
        ->get(route('crm.broadcasts.builder', $broadcast))
        ->assertSuccessful();
});
```

Note: If `Broadcast::factory()` or `FunnelEmailTemplate::factory()` don't exist yet, create them first following existing factory patterns in the project.

**Step 3: Run tests**

Run:
```bash
php artisan test --compact --filter=BroadcastEmailBuilder
```

Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/BroadcastEmailBuilderTest.php
git commit -m "test(broadcast): add email builder integration tests"
```

---

### Task 8: Ensure factories exist for testing

**Files:**
- Check/Create: `database/factories/BroadcastFactory.php`
- Check/Create: `database/factories/FunnelEmailTemplateFactory.php`

**Step 1: Check if factories exist**

Look for existing factories. If they don't exist:

Run:
```bash
php artisan make:factory BroadcastFactory --no-interaction
php artisan make:factory FunnelEmailTemplateFactory --no-interaction
```

**Step 2: Define factory fields**

`BroadcastFactory`:
```php
public function definition(): array
{
    return [
        'name' => fake()->sentence(3),
        'type' => 'standard',
        'status' => 'draft',
        'from_name' => fake()->name(),
        'from_email' => fake()->safeEmail(),
        'subject' => fake()->sentence(),
        'content' => '<p>' . fake()->paragraph() . '</p>',
        'editor_type' => 'text',
    ];
}
```

`FunnelEmailTemplateFactory`:
```php
public function definition(): array
{
    return [
        'name' => fake()->words(3, true),
        'slug' => fake()->slug(),
        'subject' => fake()->sentence(),
        'content' => '<p>' . fake()->paragraph() . '</p>',
        'editor_type' => 'text',
        'category' => fake()->randomElement(['purchase', 'cart', 'welcome', 'followup', 'upsell', 'general']),
        'is_active' => true,
    ];
}
```

**Step 3: Commit**

```bash
git add database/factories/
git commit -m "feat: add factories for Broadcast and FunnelEmailTemplate"
```

---

### Task 9: Final build, format, and full test run

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run build**

Run: `npm run build`
Expected: Build succeeds.

**Step 3: Run related tests**

Run:
```bash
php artisan test --compact --filter=BroadcastEmailBuilder
```

Expected: All tests pass.

**Step 4: Ask user if they want to run full test suite**

**Step 5: Final commit**

```bash
git add -A
git commit -m "feat(broadcast): integrate visual email builder and template picker into broadcasts"
```
