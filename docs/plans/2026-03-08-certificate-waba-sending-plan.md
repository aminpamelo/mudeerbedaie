# Certificate WABA Sending Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add WABA (Meta WhatsApp Business API) as a delivery sub-option for certificate sending, reusing the existing WhatsApp templates module for template management.

**Architecture:** Extend the certificate send modal with a WhatsApp provider sub-choice (Onsend/WABA). When WABA is selected, user picks an approved template from the existing WhatsApp templates. A new `SendCertificateWabaJob` sends via `MetaCloudProvider::sendTemplate()` with document header + body variables. Template creation/management stays on the existing `/admin/whatsapp/templates` page — we only add `variable_mappings` support to it.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest, Meta Graph API

**Key existing files:**
- WhatsApp templates page: `resources/views/livewire/admin/whatsapp-templates.blade.php`
- Template service: `app/Services/WhatsApp/TemplateService.php` (already has `submitToMeta()`, `updateOnMeta()`, `deleteFromMeta()`, `syncFromMeta()`)
- Template model: `app/Models/WhatsAppTemplate.php`
- Certificate send modal: `resources/views/livewire/admin/certificates/class-certificate-management.blade.php`
- Meta provider: `app/Services/WhatsApp/MetaCloudProvider.php` (already has `sendTemplate()`)
- Existing WhatsApp job: `app/Jobs/SendCertificateWhatsAppJob.php`
- Existing tests: `tests/Feature/CertificateSendTest.php`

---

### Task 1: Migration — Add `variable_mappings` to `whatsapp_templates`

**Files:**
- Create: `database/migrations/2026_03_08_XXXXXX_add_variable_mappings_to_whatsapp_templates_table.php`
- Modify: `app/Models/WhatsAppTemplate.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_variable_mappings_to_whatsapp_templates_table --no-interaction
```

**Step 2: Write migration content**

```php
public function up(): void
{
    Schema::table('whatsapp_templates', function (Blueprint $table) {
        $table->json('variable_mappings')->nullable()->after('components');
    });
}

public function down(): void
{
    Schema::table('whatsapp_templates', function (Blueprint $table) {
        $table->dropColumn('variable_mappings');
    });
}
```

**Step 3: Update WhatsAppTemplate model**

Add `'variable_mappings'` to `$fillable` array and add to `casts()`:

```php
protected $fillable = [
    'name',
    'language',
    'category',
    'status',
    'components',
    'meta_template_id',
    'last_synced_at',
    'variable_mappings',
];

protected function casts(): array
{
    return [
        'components' => 'array',
        'variable_mappings' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
```

**Step 4: Run migration**

```bash
php artisan migrate
```

**Step 5: Commit**

```bash
git add database/migrations/*add_variable_mappings* app/Models/WhatsAppTemplate.php
git commit -m "feat: add variable_mappings column to whatsapp_templates"
```

---

### Task 2: Add variable mappings UI to existing WhatsApp templates page

**Files:**
- Modify: `resources/views/livewire/admin/whatsapp-templates.blade.php`

**Step 1: Add `variable_mappings` property to the Volt component PHP section**

In the class properties (around line 36), add:

```php
public array $variableMappings = [];
```

In `openEditModal()` (around line 106), add loading the mappings:

```php
$this->variableMappings = $template->variable_mappings ?? [];
```

In `resetForm()` (around line 202), add:

```php
$this->variableMappings = [];
```

In `save()` method (around line 143), add `variable_mappings` to the `$data` array:

```php
$data = [
    'name' => $this->name,
    'language' => $this->language,
    'category' => $this->category,
    'status' => $this->status,
    'components' => $this->components,
    'variable_mappings' => $this->variableMappings,
];
```

**Step 2: Add variable mappings UI to the create/edit modal**

In the template modal (after the components section), add a variable mappings section that detects `{{N}}` variables from BODY components and lets the user map them:

```blade
{{-- Variable Mappings --}}
@php
    $bodyTexts = collect($components)->where('type', 'BODY')->pluck('text')->implode(' ');
    preg_match_all('/\{\{(\d+)\}\}/', $bodyTexts, $varMatches);
    $detectedVars = array_unique($varMatches[1] ?? []);
    sort($detectedVars);
@endphp
@if(!empty($detectedVars))
    <div class="space-y-2">
        <flux:label>Variable Mappings</flux:label>
        <flux:text class="text-xs text-zinc-500">Map template variables to data fields for auto-fill when sending.</flux:text>
        @foreach($detectedVars as $varNum)
            <div class="flex items-center gap-2">
                <flux:badge size="sm">{{ '{{' . $varNum . '}}' }}</flux:badge>
                <flux:select wire:model="variableMappings.body.{{ $varNum }}" class="flex-1">
                    <flux:select.option value="">-- Select field --</flux:select.option>
                    <flux:select.option value="student_name">Student Name</flux:select.option>
                    <flux:select.option value="certificate_name">Certificate Name</flux:select.option>
                    <flux:select.option value="certificate_number">Certificate Number</flux:select.option>
                    <flux:select.option value="class_name">Class Name</flux:select.option>
                    <flux:select.option value="course_name">Course Name</flux:select.option>
                    <flux:select.option value="issue_date">Issue Date</flux:select.option>
                    <flux:select.option value="custom">Custom Text</flux:select.option>
                </flux:select>
            </div>
        @endforeach
    </div>
@endif
```

**Step 3: Build assets and verify**

```bash
npm run build
```

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/whatsapp-templates.blade.php
git commit -m "feat: add variable mappings UI to WhatsApp templates page"
```

---

### Task 3: Create `SendCertificateWabaJob`

**Files:**
- Create: `app/Jobs/SendCertificateWabaJob.php`
- Test: `tests/Feature/CertificateWabaSendTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/CertificateWabaSendTest.php`:

```php
<?php

use App\Jobs\SendCertificateWabaJob;
use App\Models\CertificateIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\MetaCloudProvider;
use Illuminate\Support\Facades\Storage;

test('SendCertificateWabaJob sends template with document header and body variables', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'certificate_delivery',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [
            ['type' => 'HEADER', 'format' => 'DOCUMENT'],
            ['type' => 'BODY', 'text' => 'Assalamualaikum {{1}}, Sijil anda ({{2}}) telah dikeluarkan.'],
        ],
        'variable_mappings' => [
            'body' => ['1' => 'student_name', '2' => 'certificate_name'],
        ],
    ]);

    $mockProvider = Mockery::mock(MetaCloudProvider::class);
    $mockProvider->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function ($phone, $templateName, $language, $components) {
            return $phone === '60123456789'
                && $templateName === 'certificate_delivery'
                && $language === 'ms'
                && count($components) === 2; // header + body
        })
        ->andReturn(['success' => true, 'message_id' => 'wamid.test123']);

    $this->app->instance(MetaCloudProvider::class, $mockProvider);

    // Mock WhatsAppService::storeOutboundMessage
    $this->mock(\App\Services\WhatsAppService::class, function ($mock) {
        $mock->shouldReceive('storeOutboundMessage')->once();
    });

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $job->handle($mockProvider);

    expect($issue->fresh()->logs()->where('action', 'sent_waba')->exists())->toBeTrue();
});

test('SendCertificateWabaJob skips when template not approved', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'certificate_pending',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'PENDING',
        'components' => [],
    ]);

    $mockProvider = Mockery::mock(MetaCloudProvider::class);
    $mockProvider->shouldNotReceive('sendTemplate');

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $job->handle($mockProvider);

    expect($issue->fresh()->logs()->where('action', 'sent_waba')->exists())->toBeFalse();
});

test('SendCertificateWabaJob skips when issue has no PDF', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $issue = CertificateIssue::factory()->issued()->create([
        'file_path' => null,
    ]);

    $template = WhatsAppTemplate::create([
        'name' => 'cert_no_pdf',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
    ]);

    $mockProvider = Mockery::mock(MetaCloudProvider::class);
    $mockProvider->shouldNotReceive('sendTemplate');

    $job = new SendCertificateWabaJob(
        certificateIssueId: $issue->id,
        phoneNumber: '60123456789',
        templateId: $template->id,
        sentByUserId: $admin->id,
    );

    $job->handle($mockProvider);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="SendCertificateWabaJob"
```

Expected: FAIL — class doesn't exist.

**Step 3: Create the job**

```bash
php artisan make:job SendCertificateWabaJob --no-interaction
```

Then write `app/Jobs/SendCertificateWabaJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\MetaCloudProvider;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendCertificateWabaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $certificateIssueId,
        public string $phoneNumber,
        public int $templateId,
        public int $sentByUserId,
    ) {}

    public function handle(MetaCloudProvider $metaProvider): void
    {
        $issue = CertificateIssue::with(['certificate', 'student.user'])->find($this->certificateIssueId);

        if (! $issue || ! $issue->hasFile()) {
            Log::warning('SendCertificateWabaJob: Issue not found or no PDF', [
                'issue_id' => $this->certificateIssueId,
            ]);

            return;
        }

        $template = WhatsAppTemplate::find($this->templateId);

        if (! $template || $template->status !== 'APPROVED') {
            Log::warning('SendCertificateWabaJob: Template not found or not approved', [
                'template_id' => $this->templateId,
                'status' => $template?->status,
            ]);

            return;
        }

        $components = $this->buildComponents($issue, $template);

        $result = $metaProvider->sendTemplate(
            $this->phoneNumber,
            $template->name,
            $template->language,
            $components,
        );

        // Store outbound message in WhatsApp inbox
        app(WhatsAppService::class)->storeOutboundMessage(
            phoneNumber: $this->phoneNumber,
            type: 'template',
            body: "Template: {$template->name}",
            sendResult: $result,
            sentByUserId: $this->sentByUserId,
        );

        if ($result['success']) {
            $sentBy = User::find($this->sentByUserId);
            $issue->logAction('sent_waba', $sentBy);

            Log::info('Certificate WABA sent', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
                'template' => $template->name,
                'message_id' => $result['message_id'] ?? null,
            ]);
        } else {
            Log::warning('Certificate WABA send failed', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Build Meta template components with document header and resolved body variables.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildComponents(CertificateIssue $issue, WhatsAppTemplate $template): array
    {
        $components = [];
        $mappings = $template->variable_mappings ?? [];

        // Document header — attach the certificate PDF
        $hasDocumentHeader = collect($template->components ?? [])
            ->contains(fn ($c) => ($c['type'] ?? '') === 'HEADER' && ($c['format'] ?? '') === 'DOCUMENT');

        if ($hasDocumentHeader) {
            $documentUrl = Storage::disk('public')->url($issue->file_path);
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'document',
                        'document' => [
                            'link' => $documentUrl,
                            'filename' => $issue->getDownloadFilename(),
                        ],
                    ],
                ],
            ];
        }

        // Body variables
        if (! empty($mappings['body'])) {
            $contextMap = $this->getContextMap($issue);
            $parameters = [];

            ksort($mappings['body']);
            foreach ($mappings['body'] as $index => $fieldName) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $contextMap[$fieldName] ?? '',
                ];
            }

            if (! empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }
        }

        return $components;
    }

    /**
     * Get the context map of available certificate fields.
     *
     * @return array<string, string>
     */
    protected function getContextMap(CertificateIssue $issue): array
    {
        return [
            'student_name' => $issue->student?->user?->name ?? '',
            'certificate_name' => $issue->getCertificateName(),
            'certificate_number' => $issue->certificate_number ?? '',
            'class_name' => $issue->classModel?->title ?? '',
            'course_name' => $issue->classModel?->course?->name ?? '',
            'issue_date' => $issue->issued_at?->format('d/m/Y') ?? '',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCertificateWabaJob failed', [
            'issue_id' => $this->certificateIssueId,
            'phone' => $this->phoneNumber,
            'template_id' => $this->templateId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --compact --filter="SendCertificateWabaJob"
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Jobs/SendCertificateWabaJob.php tests/Feature/CertificateWabaSendTest.php
git commit -m "feat: add SendCertificateWabaJob for WABA certificate delivery"
```

---

### Task 4: Update Volt component PHP logic — add WABA properties and update `sendCertificates()`

**Files:**
- Modify: `resources/views/livewire/admin/certificates/class-certificate-management.blade.php` (PHP section lines 1-918)
- Test: `tests/Feature/CertificateWabaSendTest.php` (append)

**Step 1: Write the failing tests**

Append to `tests/Feature/CertificateWabaSendTest.php`:

```php
use App\Jobs\SendCertificateEmailJob;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;

test('send certificates via waba dispatches waba jobs', function () {
    Bus::fake([SendCertificateWabaJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'cert_waba_test',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
        'variable_mappings' => ['body' => ['1' => 'student_name']],
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'whatsapp')
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', $template->id)
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false)
        ->assertDispatched('notify');

    Bus::assertDispatched(SendCertificateWabaJob::class, function ($job) use ($issue, $template) {
        return $job->certificateIssueId === $issue->id
            && $job->templateId === $template->id
            && $job->phoneNumber === '60123456789';
    });
});

test('send via both channel with waba dispatches email and waba jobs', function () {
    Bus::fake([SendCertificateEmailJob::class, SendCertificateWabaJob::class]);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();
    $student = Student::factory()->create(['phone' => '60123456789']);
    $issue = CertificateIssue::factory()->issued()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'file_path' => 'certificates/test.pdf',
    ]);

    Storage::disk('public')->put('certificates/test.pdf', 'fake-pdf');

    $template = WhatsAppTemplate::create([
        'name' => 'cert_both_test',
        'language' => 'ms',
        'category' => 'utility',
        'status' => 'APPROVED',
        'components' => [],
    ]);

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('sendIssueIds', [$issue->id])
        ->set('sendChannel', 'both')
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', $template->id)
        ->set('sendMessage', 'Here is your certificate. Congratulations!')
        ->set('isBulkSend', false)
        ->call('sendCertificates')
        ->assertSet('showSendModal', false);

    Bus::assertDispatched(SendCertificateEmailJob::class);
    Bus::assertDispatched(SendCertificateWabaJob::class);
});

test('close send modal resets waba state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $class = ClassModel::factory()->create();

    Volt::actingAs($admin)
        ->test('admin.certificates.class-certificate-management', ['class' => $class])
        ->set('whatsappProvider', 'waba')
        ->set('selectedWabaTemplateId', 99)
        ->call('closeSendModal')
        ->assertSet('whatsappProvider', 'onsend')
        ->assertSet('selectedWabaTemplateId', null);
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="send certificates via waba|send via both channel with waba|close send modal resets waba"
```

Expected: FAIL — `whatsappProvider` property doesn't exist.

**Step 3: Add properties and update methods**

In the PHP section of `class-certificate-management.blade.php`:

Add after `public string $modalStudentSearch = '';` (line 60):

```php
// WABA state
public string $whatsappProvider = 'onsend';
public ?int $selectedWabaTemplateId = null;
```

Update `closeSendModal()` (line 748) to reset WABA state:

```php
public function closeSendModal(): void
{
    $this->showSendModal = false;
    $this->sendIssueIds = [];
    $this->sendChannel = 'email';
    $this->sendMessage = '';
    $this->isBulkSend = false;
    $this->whatsappProvider = 'onsend';
    $this->selectedWabaTemplateId = null;
}
```

Add computed property for approved WABA templates:

```php
public function getWabaTemplatesProperty(): array
{
    return \App\Models\WhatsAppTemplate::approved()
        ->orderBy('name')
        ->get()
        ->toArray();
}
```

Update `sendCertificates()` validation (line 802):

```php
public function sendCertificates(): void
{
    $isWaba = in_array($this->sendChannel, ['whatsapp', 'both']) && $this->whatsappProvider === 'waba';

    $rules = [
        'sendChannel' => 'required|in:email,whatsapp,both',
    ];

    // Message required for email, or onsend whatsapp, or email part of both
    if ($this->sendChannel === 'email' || ($this->sendChannel === 'both') || ($this->sendChannel === 'whatsapp' && $this->whatsappProvider === 'onsend')) {
        // For waba-only whatsapp, no message needed
        if (!($this->sendChannel === 'whatsapp' && $this->whatsappProvider === 'waba')) {
            $rules['sendMessage'] = 'required|string|min:10';
        }
    }

    if ($isWaba) {
        $rules['selectedWabaTemplateId'] = 'required|exists:whatsapp_templates,id';
    }

    $this->validate($rules);
```

Replace the WhatsApp channel block (lines 856-873) with:

```php
// WhatsApp channel
if (in_array($this->sendChannel, ['whatsapp', 'both'])) {
    $phone = $student?->phone_number;

    if ($phone && $this->whatsappProvider === 'waba' && $this->selectedWabaTemplateId) {
        \App\Jobs\SendCertificateWabaJob::dispatch(
            $issue->id,
            $phone,
            $this->selectedWabaTemplateId,
            auth()->id()
        )->onQueue('whatsapp');
        $whatsappCount++;
    } elseif ($phone && $this->whatsappProvider === 'onsend' && $whatsApp->isEnabled()) {
        $randomDelay = $whatsApp->getRandomDelay();
        \App\Jobs\SendCertificateWhatsAppJob::dispatch(
            $issue->id,
            $phone,
            $message,
            auth()->id()
        )->delay(now()->addSeconds($delay))
            ->onQueue('whatsapp');
        $delay += $randomDelay;
        $whatsappCount++;
    } else {
        $skippedWhatsapp++;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter="send certificates via waba|send via both channel with waba|close send modal resets waba"
```

Expected: PASS

**Step 5: Run existing tests to ensure no regressions**

```bash
php artisan test --compact tests/Feature/CertificateSendTest.php
```

Expected: PASS (existing tests use default `whatsappProvider = 'onsend'`)

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/certificates/class-certificate-management.blade.php tests/Feature/CertificateWabaSendTest.php
git commit -m "feat: add WABA provider selection and dispatch logic to certificate component"
```

---

### Task 5: Update Send Modal UI — provider sub-option and template picker

**Files:**
- Modify: `resources/views/livewire/admin/certificates/class-certificate-management.blade.php` (Blade section lines 1664-1713)

**Step 1: Update channel selection to include WhatsApp provider sub-option**

Replace the channel selection block (lines 1664-1673) with:

```blade
{{-- Channel Selection --}}
<flux:field>
    <flux:label>Delivery Channel</flux:label>
    <flux:radio.group wire:model.live="sendChannel">
        <flux:radio value="email" label="Email (PDF attachment)" />
        <flux:radio value="whatsapp" label="WhatsApp (PDF document)" />
        <flux:radio value="both" label="Both Email & WhatsApp" />
    </flux:radio.group>
    <flux:error name="sendChannel" />
</flux:field>

{{-- WhatsApp Provider Sub-option --}}
@if(in_array($sendChannel, ['whatsapp', 'both']))
    <flux:field>
        <flux:label>WhatsApp Provider</flux:label>
        <flux:radio.group wire:model.live="whatsappProvider">
            <flux:radio value="onsend" label="Onsend (Free-form message)" />
            <flux:radio value="waba" label="WABA Official (Template message)" />
        </flux:radio.group>
    </flux:field>

    @if($whatsappProvider === 'waba')
        {{-- WABA Template Picker --}}
        <flux:field>
            <flux:label>WhatsApp Template</flux:label>
            <flux:select wire:model.live="selectedWabaTemplateId" placeholder="Select a template...">
                @foreach($this->wabaTemplates as $tpl)
                    <flux:select.option value="{{ $tpl['id'] }}">
                        {{ $tpl['name'] }} ({{ $tpl['language'] }})
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="selectedWabaTemplateId" />

            @if(empty($this->wabaTemplates))
                <flux:text class="text-xs text-amber-600 mt-1">
                    No approved templates found. Create one in <a href="{{ route('whatsapp-templates') }}" class="underline" target="_blank">WhatsApp Templates</a>.
                </flux:text>
            @endif
        </flux:field>

        {{-- Template Preview --}}
        @if($selectedWabaTemplateId)
            @php
                $selectedTemplate = collect($this->wabaTemplates)->firstWhere('id', (int) $selectedWabaTemplateId);
                $bodyComponent = collect($selectedTemplate['components'] ?? [])->firstWhere('type', 'BODY');
            @endphp
            @if($bodyComponent)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:text class="text-xs font-medium text-zinc-500 mb-1">Template Preview</flux:text>
                    <flux:text class="text-sm">{{ $bodyComponent['text'] ?? '' }}</flux:text>
                    @if(!empty($selectedTemplate['variable_mappings']['body'] ?? []))
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach($selectedTemplate['variable_mappings']['body'] as $num => $field)
                                <flux:badge size="sm" color="blue">
                                    {{ '{{' . $num . '}}' }} → {{ str_replace('_', ' ', $field) }}
                                </flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif
    @endif
@endif
```

**Step 2: Conditionally show/hide message textarea**

Replace the message field (lines 1705-1713) with:

```blade
{{-- Message (hidden when WABA-only WhatsApp, shown for email or onsend) --}}
@if(!($whatsappProvider === 'waba' && $sendChannel === 'whatsapp'))
    <flux:field>
        <flux:label>Message</flux:label>
        <flux:textarea wire:model="sendMessage" rows="6" placeholder="Enter the message to send with the certificate..." />
        <flux:error name="sendMessage" />
        <flux:text class="text-xs text-zinc-400 mt-1">
            @if($sendChannel === 'both' && $whatsappProvider === 'waba')
                This message will be used for the email body. WhatsApp will use the selected template.
            @else
                This message will be included in the email body and/or WhatsApp text message. The certificate PDF will be attached automatically.
            @endif
        </flux:text>
    </flux:field>
@endif
```

**Step 3: Build assets**

```bash
npm run build
```

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/certificates/class-certificate-management.blade.php
git commit -m "feat: add WABA provider sub-option and template picker UI to send modal"
```

---

### Task 6: Update existing tests and run full suite

**Files:**
- Modify: `tests/Feature/CertificateSendTest.php`

**Step 1: Update close modal test to verify WABA state reset**

In `tests/Feature/CertificateSendTest.php`, update the `'close send modal resets state'` test to also assert:

```php
->assertSet('whatsappProvider', 'onsend')
->assertSet('selectedWabaTemplateId', null)
```

**Step 2: Run all certificate tests**

```bash
php artisan test --compact tests/Feature/CertificateSendTest.php tests/Feature/CertificateWabaSendTest.php
```

Expected: ALL PASS

**Step 3: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 4: Run full test suite**

```bash
php artisan test --compact
```

**Step 5: Commit**

```bash
git add tests/Feature/CertificateSendTest.php
git commit -m "test: update certificate send tests for WABA integration"
```

If pint made changes:

```bash
git add -A
git commit -m "style: run pint formatting"
```
