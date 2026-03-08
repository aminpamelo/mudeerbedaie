# Funnel Email Templates Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add reusable email templates (text + visual HTML) to the funnel automation Send Email action, with a global management page and template selection in the automation builder.

**Architecture:** New `FunnelEmailTemplate` model with its own table, API controller, Livewire management page, and visual builder page. The automation builder's Send Email panel gets a radio toggle for "Use Template" vs "Write Custom". The `FunnelAutomationService::executeSendEmail()` method is updated to load template content when `template_id` is present.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), Flux UI, React (automation builder JSX), Tailwind CSS v4

**Design Doc:** `docs/plans/2026-03-07-funnel-email-templates-design.md`

---

### Task 1: Create Migration

**Files:**
- Create: `database/migrations/2026_03_07_000001_create_funnel_email_templates_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_funnel_email_templates_table --no-interaction
```

**Step 2: Write migration schema**

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject')->nullable();
            $table->longText('content')->nullable();
            $table->json('design_json')->nullable();
            $table->longText('html_content')->nullable();
            $table->string('editor_type')->default('text');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_email_templates');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate --no-interaction`
Expected: Migration runs successfully, table created.

**Step 4: Commit**

```bash
git add database/migrations/*funnel_email_templates*
git commit -m "feat: add funnel_email_templates migration"
```

---

### Task 2: Create FunnelEmailTemplate Model

**Files:**
- Create: `app/Models/FunnelEmailTemplate.php`

**Step 1: Generate model with factory**

Run:
```bash
php artisan make:model FunnelEmailTemplate --factory --no-interaction
```

**Step 2: Write the model**

Replace `app/Models/FunnelEmailTemplate.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunnelEmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'content',
        'design_json',
        'html_content',
        'editor_type',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'design_json' => 'array',
        ];
    }

    public function isVisualEditor(): bool
    {
        return $this->editor_type === 'visual';
    }

    public function isTextEditor(): bool
    {
        return $this->editor_type === 'text';
    }

    public function getEffectiveContent(): string
    {
        if ($this->isVisualEditor() && $this->html_content) {
            return $this->html_content;
        }

        return $this->content ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public static function getCategories(): array
    {
        return [
            'purchase' => 'Purchase',
            'cart' => 'Cart',
            'welcome' => 'Welcome',
            'followup' => 'Follow-up',
            'upsell' => 'Upsell',
            'general' => 'General',
        ];
    }

    public static function getAvailablePlaceholders(): array
    {
        return [
            '{{contact.name}}' => 'Full contact name',
            '{{contact.first_name}}' => 'Contact first name',
            '{{contact.email}}' => 'Contact email',
            '{{contact.phone}}' => 'Contact phone',
            '{{order.number}}' => 'Order number',
            '{{order.total}}' => 'Order total',
            '{{order.date}}' => 'Order date',
            '{{order.items_list}}' => 'Order items list',
            '{{payment.method}}' => 'Payment method',
            '{{payment.status}}' => 'Payment status',
            '{{funnel.name}}' => 'Funnel name',
            '{{funnel.url}}' => 'Funnel URL',
            '{{current_date}}' => 'Current date',
            '{{current_time}}' => 'Current time',
            '{{company_name}}' => 'Company name',
            '{{company_email}}' => 'Company email',
        ];
    }
}
```

**Step 3: Write the factory**

Replace `database/factories/FunnelEmailTemplateFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Models\FunnelEmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FunnelEmailTemplateFactory extends Factory
{
    protected $model = FunnelEmailTemplate::class;

    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'subject' => 'Thank you for your order #{{order.number}}',
            'content' => "Hi {{contact.first_name}},\n\nThank you for your purchase!\n\nOrder: {{order.number}}\nTotal: {{order.total}}",
            'editor_type' => 'text',
            'category' => fake()->randomElement(['purchase', 'cart', 'welcome', 'followup', 'upsell', 'general']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function visual(): static
    {
        return $this->state(fn (array $attributes) => [
            'editor_type' => 'visual',
            'design_json' => ['blocks' => []],
            'html_content' => '<html><body><h1>Hello {{contact.first_name}}</h1></body></html>',
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Models/FunnelEmailTemplate.php database/factories/FunnelEmailTemplateFactory.php
git commit -m "feat: add FunnelEmailTemplate model and factory"
```

---

### Task 3: Create API Controller + Routes

**Files:**
- Create: `app/Http/Controllers/Api/V1/FunnelEmailTemplateController.php`
- Modify: `routes/api.php`

**Step 1: Generate controller**

Run:
```bash
php artisan make:controller Api/V1/FunnelEmailTemplateController --no-interaction
```

**Step 2: Write the controller**

Replace `app/Http/Controllers/Api/V1/FunnelEmailTemplateController.php` with:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FunnelEmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelEmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FunnelEmailTemplate::query()
            ->orderBy('name');

        if ($request->boolean('active', false)) {
            $query->active();
        }

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:funnel_email_templates,slug',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        }

        $template = FunnelEmailTemplate::create($validated);

        return response()->json([
            'data' => $template,
            'message' => 'Template created successfully.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:funnel_email_templates,slug,' . $template->id,
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'design_json' => 'nullable|array',
            'html_content' => 'nullable|string',
            'editor_type' => 'sometimes|in:text,visual',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'data' => $template->fresh(),
            'message' => 'Template updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully.',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $template = FunnelEmailTemplate::findOrFail($id);

        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->slug = Str::slug($newTemplate->name) . '-' . Str::random(4);
        $newTemplate->save();

        return response()->json([
            'data' => $newTemplate,
            'message' => 'Template duplicated successfully.',
        ], 201);
    }
}
```

**Step 3: Add API routes**

Add to `routes/api.php` inside the `auth:sanctum` + `v1` middleware group (after line 103, before line 105):

```php
    // Funnel Email Templates
    Route::get('funnel-email-templates', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'index'])->name('api.funnel-email-templates.index');
    Route::get('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'show'])->name('api.funnel-email-templates.show');
    Route::post('funnel-email-templates', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'store'])->name('api.funnel-email-templates.store');
    Route::put('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'update'])->name('api.funnel-email-templates.update');
    Route::delete('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'destroy'])->name('api.funnel-email-templates.destroy');
    Route::post('funnel-email-templates/{id}/duplicate', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'duplicate'])->name('api.funnel-email-templates.duplicate');
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/FunnelEmailTemplateController.php routes/api.php
git commit -m "feat: add FunnelEmailTemplate API controller and routes"
```

---

### Task 4: Write API Feature Tests

**Files:**
- Create: `tests/Feature/FunnelEmailTemplateTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test FunnelEmailTemplateTest --pest --no-interaction
```

**Step 2: Write the tests**

Replace `tests/Feature/FunnelEmailTemplateTest.php` with:

```php
<?php

declare(strict_types=1);

use App\Models\FunnelEmailTemplate;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('can list funnel email templates', function () {
    FunnelEmailTemplate::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/funnel-email-templates');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can filter templates by active status', function () {
    FunnelEmailTemplate::factory()->count(2)->create();
    FunnelEmailTemplate::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/funnel-email-templates?active=true');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can filter templates by category', function () {
    FunnelEmailTemplate::factory()->category('purchase')->count(2)->create();
    FunnelEmailTemplate::factory()->category('cart')->create();

    $response = $this->getJson('/api/v1/funnel-email-templates?category=purchase');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('can search templates by name', function () {
    FunnelEmailTemplate::factory()->create(['name' => 'Purchase Confirmation']);
    FunnelEmailTemplate::factory()->create(['name' => 'Cart Abandonment']);

    $response = $this->getJson('/api/v1/funnel-email-templates?search=Purchase');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('can show a single template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->getJson("/api/v1/funnel-email-templates/{$template->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $template->id)
        ->assertJsonPath('data.name', $template->name);
});

test('can create a template', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'Purchase Confirmation',
        'subject' => 'Order #{{order.number}} confirmed',
        'content' => 'Hi {{contact.first_name}}, thank you!',
        'category' => 'purchase',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Purchase Confirmation');

    expect(FunnelEmailTemplate::where('name', 'Purchase Confirmation')->exists())->toBeTrue();
});

test('can create a template with custom slug', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'My Template',
        'slug' => 'my-custom-slug',
        'content' => 'Hello',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'my-custom-slug');
});

test('auto-generates slug when not provided', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'Purchase Confirmation',
        'content' => 'Hello',
    ]);

    $response->assertCreated();
    expect($response->json('data.slug'))->toStartWith('purchase-confirmation');
});

test('can update a template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->putJson("/api/v1/funnel-email-templates/{$template->id}", [
        'name' => 'Updated Name',
        'subject' => 'New Subject',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.subject', 'New Subject');
});

test('can delete a template', function () {
    $template = FunnelEmailTemplate::factory()->create();

    $response = $this->deleteJson("/api/v1/funnel-email-templates/{$template->id}");

    $response->assertSuccessful();
    expect(FunnelEmailTemplate::find($template->id))->toBeNull();
    expect(FunnelEmailTemplate::withTrashed()->find($template->id))->not->toBeNull();
});

test('can duplicate a template', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'name' => 'Original Template',
        'subject' => 'Original Subject',
        'content' => 'Original Content',
    ]);

    $response = $this->postJson("/api/v1/funnel-email-templates/{$template->id}/duplicate");

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Original Template (Copy)')
        ->assertJsonPath('data.subject', 'Original Subject')
        ->assertJsonPath('data.content', 'Original Content');

    expect(FunnelEmailTemplate::count())->toBe(2);
});

test('validates required name on create', function () {
    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'subject' => 'Some subject',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('validates unique slug on create', function () {
    FunnelEmailTemplate::factory()->create(['slug' => 'existing-slug']);

    $response = $this->postJson('/api/v1/funnel-email-templates', [
        'name' => 'New Template',
        'slug' => 'existing-slug',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});
```

**Step 3: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/FunnelEmailTemplateTest.php`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/FunnelEmailTemplateTest.php
git commit -m "test: add FunnelEmailTemplate API feature tests"
```

---

### Task 5: Update FunnelAutomationService for Template Support

**Files:**
- Modify: `app/Services/Funnel/FunnelAutomationService.php` (lines 194-232, the `executeSendEmail` method)

**Step 1: Write test for template-based email sending**

Create `tests/Feature/FunnelAutomationEmailTemplateTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\FunnelEmailTemplate;
use App\Models\User;
use App\Services\Funnel\FunnelAutomationService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('executeSendEmail uses template content when template_id is set', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'subject' => 'Template Subject: {{order.number}}',
        'content' => 'Template body for {{contact.first_name}}',
        'editor_type' => 'text',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);

    // Use reflection to call protected method
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com', 'first_name' => 'John'],
        'order' => ['number' => 'PO-001'],
    ]);

    expect($result['success'])->toBeTrue();
    Mail::assertSent(function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('executeSendEmail uses subject override when provided with template', function () {
    $template = FunnelEmailTemplate::factory()->create([
        'subject' => 'Template Default Subject',
        'content' => 'Template body',
        'editor_type' => 'text',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'subject' => 'Override Subject',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail falls back to inline content when email_source is custom', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'custom',
            'subject' => 'Inline Subject',
            'content' => 'Inline body',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail sends HTML email for visual templates', function () {
    $template = FunnelEmailTemplate::factory()->visual()->create([
        'subject' => 'HTML Template',
    ]);

    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'email_source' => 'template',
            'template_id' => $template->id,
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com', 'first_name' => 'John'],
    ]);

    expect($result['success'])->toBeTrue();
});

test('executeSendEmail backward compatible with no email_source field', function () {
    $automation = FunnelAutomation::factory()->create();
    $action = FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'send_email',
        'action_config' => [
            'subject' => 'Old Style Subject',
            'content' => 'Old style body',
            'email_field' => 'contact.email',
        ],
    ]);

    $service = app(FunnelAutomationService::class);
    $method = new ReflectionMethod($service, 'executeSendEmail');
    $result = $method->invoke($service, $action, [
        'contact' => ['email' => 'test@example.com'],
    ]);

    expect($result['success'])->toBeTrue();
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/FunnelAutomationEmailTemplateTest.php`
Expected: Tests that use template_id should fail (template loading not implemented yet).

**Step 3: Update executeSendEmail method**

In `app/Services/Funnel/FunnelAutomationService.php`, replace the `executeSendEmail` method (lines ~194-232) with:

```php
    /**
     * Execute send_email action.
     */
    protected function executeSendEmail(FunnelAutomationAction $action, array $context): array
    {
        $config = $action->action_config ?? [];
        $emailSource = $config['email_source'] ?? 'custom';
        $emailField = $config['email_field'] ?? 'contact.email';

        // Get email from context
        $email = $this->getValueFromContext($emailField, $context);

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'No email address found in context',
            ];
        }

        // Resolve subject and body based on source
        if ($emailSource === 'template' && ! empty($config['template_id'])) {
            $template = \App\Models\FunnelEmailTemplate::find($config['template_id']);

            if (! $template) {
                return [
                    'success' => false,
                    'error' => 'Email template not found (ID: ' . $config['template_id'] . ')',
                    'email' => $email,
                ];
            }

            // Subject: use override if provided, else template subject
            $subject = ! empty($config['subject']) ? $config['subject'] : ($template->subject ?? 'Notification');
            $body = $template->getEffectiveContent();
            $isHtml = $template->isVisualEditor() && $template->html_content;
        } else {
            // Inline/custom content (backward compatible)
            $subject = $config['subject'] ?? 'Notification';
            $body = $config['body'] ?? $config['content'] ?? $config['template'] ?? '';
            $isHtml = false;
        }

        // Process merge tags
        $processedSubject = $this->mergeTagEngine->resolve($subject);
        $processedBody = $this->mergeTagEngine->resolve($body);

        try {
            if ($isHtml) {
                Mail::html($processedBody, function ($message) use ($email, $processedSubject) {
                    $message->to($email)
                        ->subject($processedSubject);
                });
            } else {
                Mail::raw($processedBody, function ($message) use ($email, $processedSubject) {
                    $message->to($email)
                        ->subject($processedSubject);
                });
            }

            return [
                'success' => true,
                'email' => $email,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'email' => $email,
            ];
        }
    }
```

**Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/FunnelAutomationEmailTemplateTest.php`
Expected: All tests pass.

**Step 5: Also run existing tests to check backward compatibility**

Run: `php artisan test --compact tests/Feature/FunnelEmailTemplateTest.php`
Expected: Still passing.

**Step 6: Commit**

```bash
git add app/Services/Funnel/FunnelAutomationService.php tests/Feature/FunnelAutomationEmailTemplateTest.php
git commit -m "feat: update executeSendEmail to support template-based emails"
```

---

### Task 6: Update Automation Builder Frontend - Template Selection UI

**Files:**
- Modify: `resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx` (lines ~1030-1075)
- Modify: `resources/js/funnel-builder/types/funnel-automation-types.js` (line 210)

**Step 1: Update action type defaults**

In `resources/js/funnel-builder/types/funnel-automation-types.js`, update the SEND_EMAIL config (line 210):

```javascript
    [FUNNEL_ACTION_TYPES.SEND_EMAIL]: {
        label: 'Send Email',
        description: 'Send an email to the visitor',
        icon: '📧',
        color: '#3B82F6',
        category: 'communication',
        config: { email_source: 'custom', subject: '', content: '', template_id: null },
    },
```

**Step 2: Update NodeConfigPanel in FunnelAutomationBuilder.jsx**

Replace the Send Email config section (lines ~1030-1075) with:

```jsx
                    {/* Email Action Config */}
                    {nodeType === 'action' && data.actionType === FUNNEL_ACTION_TYPES.SEND_EMAIL && (
                        <EmailActionConfig
                            data={data}
                            onUpdate={onUpdate}
                            triggerType={triggerType}
                        />
                    )}
```

**Step 3: Add EmailActionConfig component**

Add this component definition above the `NodeConfigPanel` component in `FunnelAutomationBuilder.jsx`:

```jsx
// Email Action Config - handles template selection and custom email
function EmailActionConfig({ data, onUpdate, triggerType }) {
    const [templates, setTemplates] = React.useState([]);
    const [loadingTemplates, setLoadingTemplates] = React.useState(false);
    const [selectedTemplate, setSelectedTemplate] = React.useState(null);
    const emailSource = data.config?.email_source || 'custom';

    // Fetch templates when switching to template mode
    React.useEffect(() => {
        if (emailSource === 'template' && templates.length === 0) {
            fetchTemplates();
        }
    }, [emailSource]);

    // Load selected template details
    React.useEffect(() => {
        if (emailSource === 'template' && data.config?.template_id) {
            const found = templates.find(t => t.id === data.config.template_id);
            setSelectedTemplate(found || null);
        } else {
            setSelectedTemplate(null);
        }
    }, [data.config?.template_id, templates]);

    const fetchTemplates = async () => {
        setLoadingTemplates(true);
        try {
            const response = await fetch('/api/v1/funnel-email-templates?active=true', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const result = await response.json();
            setTemplates(result.data || []);
        } catch (error) {
            console.error('Failed to fetch email templates:', error);
        } finally {
            setLoadingTemplates(false);
        }
    };

    const handleSourceChange = (source) => {
        if (source === 'template') {
            onUpdate({
                config: {
                    ...data.config,
                    email_source: 'template',
                    template_id: data.config?.template_id || null,
                    subject: data.config?.subject || '',
                }
            });
            if (templates.length === 0) fetchTemplates();
        } else {
            onUpdate({
                config: {
                    ...data.config,
                    email_source: 'custom',
                    template_id: null,
                    subject: data.config?.subject || '',
                    content: data.config?.content || '',
                }
            });
        }
    };

    const handleTemplateSelect = (templateId) => {
        const template = templates.find(t => t.id === parseInt(templateId));
        onUpdate({
            config: {
                ...data.config,
                template_id: template ? template.id : null,
                subject: template?.subject || data.config?.subject || '',
            }
        });
        setSelectedTemplate(template || null);
    };

    return (
        <>
            {/* Email Source Toggle */}
            <div>
                <label className="block text-xs font-medium text-gray-700 mb-2">
                    Email Source
                </label>
                <div className="flex gap-1 p-1 bg-gray-100 rounded-lg">
                    <button
                        type="button"
                        onClick={() => handleSourceChange('custom')}
                        className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                            emailSource === 'custom'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Write Custom
                    </button>
                    <button
                        type="button"
                        onClick={() => handleSourceChange('template')}
                        className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
                            emailSource === 'template'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        Use Template
                    </button>
                </div>
            </div>

            {emailSource === 'template' ? (
                <>
                    {/* Template Selector */}
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">
                            Template
                        </label>
                        {loadingTemplates ? (
                            <div className="text-xs text-gray-500 py-2">Loading templates...</div>
                        ) : (
                            <select
                                value={data.config?.template_id || ''}
                                onChange={(e) => handleTemplateSelect(e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select a template...</option>
                                {templates.map((template) => (
                                    <option key={template.id} value={template.id}>
                                        {template.name}
                                        {template.category ? ` (${template.category})` : ''}
                                    </option>
                                ))}
                            </select>
                        )}
                        {templates.length === 0 && !loadingTemplates && (
                            <p className="text-xs text-amber-600 mt-1">
                                No templates found. Create templates in the Funnel Email Templates page.
                            </p>
                        )}
                    </div>

                    {/* Subject Override */}
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="block text-xs font-medium text-gray-700">
                                Subject {selectedTemplate && <span className="text-gray-400 font-normal">(override)</span>}
                            </label>
                            <VariablePicker
                                triggerType={triggerType}
                                buttonText="Insert"
                                buttonClassName="text-xs py-0.5 px-2"
                                onSelect={(tag) => {
                                    const newSubject = (data.config?.subject || '') + tag;
                                    onUpdate({ config: { ...data.config, subject: newSubject } });
                                }}
                            />
                        </div>
                        <input
                            type="text"
                            value={data.config?.subject || ''}
                            onChange={(e) => onUpdate({ config: { ...data.config, subject: e.target.value } })}
                            className="w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder={selectedTemplate?.subject || "Email subject with {{contact.name}}"}
                        />
                    </div>

                    {/* Content Preview */}
                    {selectedTemplate && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Content Preview
                                <span className="ml-1 text-gray-400 font-normal">
                                    ({selectedTemplate.editor_type === 'visual' ? 'HTML' : 'Text'})
                                </span>
                            </label>
                            <div className="p-3 bg-gray-50 rounded-lg border border-gray-200 max-h-48 overflow-y-auto">
                                {selectedTemplate.editor_type === 'visual' && selectedTemplate.html_content ? (
                                    <div className="text-xs text-gray-500 italic">
                                        Visual HTML template - preview available on templates page
                                    </div>
                                ) : (
                                    <pre className="text-xs text-gray-700 whitespace-pre-wrap font-mono">
                                        {selectedTemplate.content || 'No content'}
                                    </pre>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Link to manage templates */}
                    <div className="pt-1">
                        <a
                            href="/admin/funnel-email-templates"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1"
                        >
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Manage Email Templates
                        </a>
                    </div>
                </>
            ) : (
                <>
                    {/* Custom Subject */}
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="block text-xs font-medium text-gray-700">
                                Subject
                            </label>
                            <VariablePicker
                                triggerType={triggerType}
                                buttonText="Insert"
                                buttonClassName="text-xs py-0.5 px-2"
                                onSelect={(tag) => {
                                    const newSubject = (data.config?.subject || '') + tag;
                                    onUpdate({ config: { ...data.config, subject: newSubject } });
                                }}
                            />
                        </div>
                        <input
                            type="text"
                            value={data.config?.subject || ''}
                            onChange={(e) => onUpdate({ config: { ...data.config, subject: e.target.value } })}
                            className="w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Email subject with {{contact.name}}"
                        />
                    </div>
                    {/* Custom Content */}
                    <div>
                        <TextareaWithVariables
                            label="Content"
                            value={data.config?.content || ''}
                            onChange={(value) => onUpdate({ config: { ...data.config, content: value } })}
                            triggerType={triggerType}
                            rows={6}
                            placeholder={"Hi {{contact.first_name}},\n\nThank you for your order #{{order.number}}!\n\nTotal: {{order.total}}"}
                            helpText="Use merge tags to personalize your email"
                        />
                    </div>
                    {/* Preview */}
                    {data.config?.content && (
                        <VariablePreview
                            text={data.config.content}
                            className="mt-2"
                        />
                    )}
                </>
            )}
        </>
    );
}
```

**Step 4: Build assets**

Run: `npm run build`
Expected: Build completes without errors.

**Step 5: Commit**

```bash
git add resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx resources/js/funnel-builder/types/funnel-automation-types.js
git commit -m "feat: add template selection UI in Send Email automation config"
```

---

### Task 7: Create Livewire Management Page

**Files:**
- Create: `resources/views/livewire/admin/funnel-email-templates.blade.php`
- Modify: `routes/web.php` (add route near line 432)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (add nav item near line 183)

**Step 1: Create the Volt component**

Run:
```bash
php artisan make:volt admin/funnel-email-templates --class --no-interaction
```

**Step 2: Write the management page**

Replace `resources/views/livewire/admin/funnel-email-templates.blade.php` with the full Volt component. This is a class-based Volt component following the pattern from `settings-notifications.blade.php`:

```php
<?php

use App\Models\FunnelEmailTemplate;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public bool $showEditModal = false;
    public bool $showPreviewModal = false;
    public ?FunnelEmailTemplate $editingTemplate = null;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $subject = '';
    public string $content = '';
    public string $category = '';
    public bool $is_active = true;

    // Preview
    public string $previewSubject = '';
    public string $previewContent = '';
    public bool $previewIsVisual = false;

    // Search
    public string $search = '';

    public function getTemplatesProperty()
    {
        $query = FunnelEmailTemplate::query()
            ->orderBy('category')
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            });
        }

        return $query->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createTemplate(): void
    {
        $this->resetForm();
        $this->showEditModal = true;
    }

    public function editTemplate(FunnelEmailTemplate $template): void
    {
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->slug = $template->slug;
        $this->subject = $template->subject ?? '';
        $this->content = $template->content ?? '';
        $this->category = $template->category ?? '';
        $this->is_active = $template->is_active;
        $this->showEditModal = true;
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:funnel_email_templates,slug,' . ($this->editingTemplate?->id ?? ''),
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:50',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject ?: null,
            'content' => $this->content ?: null,
            'category' => $this->category ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingTemplate) {
            $this->editingTemplate->update($data);
            $message = 'Template updated successfully';
        } else {
            FunnelEmailTemplate::create($data);
            $message = 'Template created successfully';
        }

        $this->showEditModal = false;
        $this->resetForm();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function toggleActive(FunnelEmailTemplate $template): void
    {
        $template->update(['is_active' => !$template->is_active]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $template->is_active ? 'Template activated' : 'Template deactivated',
        ]);
    }

    public function duplicateTemplate(FunnelEmailTemplate $template): void
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->slug = Str::slug($newTemplate->name) . '-' . Str::random(4);
        $newTemplate->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template duplicated successfully',
        ]);
    }

    public function deleteTemplate(FunnelEmailTemplate $template): void
    {
        $template->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template deleted successfully',
        ]);
    }

    public function previewTemplate(FunnelEmailTemplate $template): void
    {
        $sampleData = [
            '{{contact.name}}' => 'Ahmad Amin',
            '{{contact.first_name}}' => 'Ahmad',
            '{{contact.email}}' => 'ahmad@example.com',
            '{{contact.phone}}' => '+60123456789',
            '{{order.number}}' => 'PO-20260307-ABC',
            '{{order.total}}' => 'RM 299.00',
            '{{order.date}}' => now()->format('d M Y'),
            '{{order.items_list}}' => '1x Product Name - RM 299.00',
            '{{payment.method}}' => 'Credit Card',
            '{{payment.status}}' => 'Paid',
            '{{funnel.name}}' => 'My Sales Funnel',
            '{{funnel.url}}' => 'https://example.com/funnel',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address'),
        ];

        $this->previewSubject = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $template->subject ?? ''
        );

        $content = $template->getEffectiveContent();
        $this->previewContent = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $content
        );
        $this->previewIsVisual = $template->isVisualEditor() && $template->html_content;
        $this->showPreviewModal = true;
    }

    public function updatedName(): void
    {
        if (!$this->editingTemplate) {
            $this->slug = Str::slug($this->name);
        }
    }

    protected function resetForm(): void
    {
        $this->editingTemplate = null;
        $this->name = '';
        $this->slug = '';
        $this->subject = '';
        $this->content = '';
        $this->category = '';
        $this->is_active = true;
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Funnel Email Templates</flux:heading>
            <flux:text class="mt-2">Create and manage reusable email templates for funnel automations</flux:text>
        </div>
        <flux:button variant="primary" wire:click="createTemplate">
            <div class="flex items-center justify-center">
                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                Create Template
            </div>
        </flux:button>
    </div>

    <!-- Placeholders Reference -->
    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <div class="flex items-center justify-between mb-2">
            <flux:text class="font-medium text-sm">Available Placeholders</flux:text>
            <flux:text class="text-xs text-gray-400">Click to copy</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(App\Models\FunnelEmailTemplate::getAvailablePlaceholders() as $tag => $description)
                <span
                    x-data
                    x-on:click="navigator.clipboard.writeText('{{ $tag }}'); $dispatch('notify', { type: 'success', message: 'Copied!' })"
                    class="inline-flex items-center px-2 py-1 bg-white border border-gray-200 rounded text-xs font-mono cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition-colors"
                    title="{{ $description }}"
                >
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search templates..." icon="magnifying-glass" />
    </div>

    <!-- Templates Table -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Editor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->templates as $template)
                    <tr wire:key="template-{{ $template->id }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $template->name }}</div>
                            <div class="text-xs text-gray-500 font-mono">{{ $template->slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($template->category)
                                <flux:badge size="sm">{{ ucfirst($template->category) }}</flux:badge>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($template->editor_type === 'visual')
                                <flux:badge color="purple" size="sm">Visual</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Text</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($template->is_active)
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="previewTemplate({{ $template->id }})" title="Preview">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                </flux:button>
                                @if($template->editor_type === 'visual' || !$template->content)
                                    <a href="{{ route('admin.funnel-email-templates.builder', $template) }}" title="Visual Builder">
                                        <flux:button size="sm" variant="ghost">
                                            <flux:icon name="paint-brush" class="w-4 h-4" />
                                        </flux:button>
                                    </a>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="editTemplate({{ $template->id }})" title="Edit">
                                    <flux:icon name="pencil" class="w-4 h-4" />
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="duplicateTemplate({{ $template->id }})" title="Duplicate">
                                    <flux:icon name="document-duplicate" class="w-4 h-4" />
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $template->id }})" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                    <flux:icon name="{{ $template->is_active ? 'eye-slash' : 'eye' }}" class="w-4 h-4" />
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <flux:icon name="envelope" class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                            <flux:text class="text-gray-500">No email templates yet</flux:text>
                            <flux:text class="text-sm text-gray-400 mt-1">Create your first template to get started</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->templates->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $this->templates->links() }}
            </div>
        @endif
    </div>

    <!-- Edit/Create Modal -->
    <flux:modal wire:model="showEditModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingTemplate ? 'Edit Template' : 'Create Template' }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.live="name" placeholder="e.g. Purchase Confirmation" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Slug</flux:label>
                    <flux:input wire:model="slug" placeholder="auto-generated-slug" />
                    <flux:error name="slug" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="category">
                        <option value="">No category</option>
                        @foreach(App\Models\FunnelEmailTemplate::getCategories() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:switch wire:model="is_active" label="Active" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Subject</flux:label>
                <flux:input wire:model="subject" placeholder="Order #{{order.number}} confirmed" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>Content (Text)</flux:label>
                <flux:textarea wire:model="content" rows="8" placeholder="Hi {{contact.first_name}},&#10;&#10;Thank you for your order #{{order.number}}!&#10;&#10;Total: {{order.total}}" />
                <flux:error name="content" />
                <flux:description>Use placeholders above to personalize. For visual HTML templates, save first then use the Visual Builder.</flux:description>
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveTemplate">
                    {{ $editingTemplate ? 'Update Template' : 'Create Template' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Preview Modal -->
    <flux:modal wire:model="showPreviewModal" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">Template Preview</flux:heading>

            @if($previewSubject)
                <div>
                    <flux:label>Subject</flux:label>
                    <div class="mt-1 p-3 bg-gray-50 rounded-lg border text-sm">{{ $previewSubject }}</div>
                </div>
            @endif

            <div>
                <flux:label>Content</flux:label>
                @if($previewIsVisual)
                    <div class="mt-1 border rounded-lg overflow-hidden">
                        <iframe
                            srcdoc="{{ $previewContent }}"
                            class="w-full h-96 border-0"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                @else
                    <div class="mt-1 p-4 bg-gray-50 rounded-lg border">
                        <pre class="text-sm whitespace-pre-wrap font-mono text-gray-700">{{ $previewContent }}</pre>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showPreviewModal', false)">Close</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
```

**Step 3: Add web route**

In `routes/web.php`, add after line 433 (after the funnel show route):

```php
    // Funnel Email Templates
    Volt::route('funnel-email-templates', 'admin.funnel-email-templates')->name('admin.funnel-email-templates');
```

**Step 4: Add sidebar navigation item**

In `resources/views/components/layouts/app/sidebar.blade.php`, add after line 183 (after the Workflows nav item inside the Sales Funnels group):

```blade
                    <flux:navlist.item icon="envelope" :href="route('admin.funnel-email-templates')" :current="request()->routeIs('admin.funnel-email-templates*')" wire:navigate>{{ __('Email Templates') }}</flux:navlist.item>
```

**Step 5: Build assets and verify**

Run: `npm run build`
Expected: Builds successfully.

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/funnel-email-templates.blade.php routes/web.php resources/views/components/layouts/app/sidebar.blade.php
git commit -m "feat: add funnel email templates management page with sidebar nav"
```

---

### Task 8: Create Visual Builder Page for Funnel Email Templates

**Files:**
- Create: `resources/views/livewire/admin/funnel-email-template-builder.blade.php`
- Modify: `routes/web.php` (add builder route)

**Step 1: Create the Volt component**

Run:
```bash
php artisan make:volt admin/funnel-email-template-builder --class --no-interaction
```

**Step 2: Write the builder page**

This follows the exact same pattern as `class-notification-builder.blade.php` and `react-template-builder.blade.php`, reusing the existing `react-email-builder` layout:

```php
<?php

use App\Models\FunnelEmailTemplate;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.react-email-builder')]
class extends Component {
    public FunnelEmailTemplate $template;
    public string $initialDesign = '';
    public bool $showPreview = false;
    public string $previewHtml = '';

    public function mount(FunnelEmailTemplate $template): void
    {
        $this->template = $template;

        // Get design JSON for React editor
        if ($template->design_json) {
            $this->initialDesign = json_encode($template->design_json);
        }
    }

    public function saveDesign(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->template->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template saved successfully!',
        ]);
    }

    public function autoSave(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->template->update([
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

    protected function compileEmailHtml(string $html): string
    {
        // Wrap in email-safe HTML if not already wrapped
        if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' . $html . '</body></html>';
        }

        return $html;
    }

    protected function getSampleData(): array
    {
        return [
            '{{contact.name}}' => 'Ahmad Amin',
            '{{contact.first_name}}' => 'Ahmad',
            '{{contact.email}}' => 'ahmad@example.com',
            '{{contact.phone}}' => '+60123456789',
            '{{order.number}}' => 'PO-20260307-ABC',
            '{{order.total}}' => 'RM 299.00',
            '{{order.date}}' => now()->format('d M Y'),
            '{{order.items_list}}' => '1x Product Name - RM 299.00',
            '{{payment.method}}' => 'Credit Card',
            '{{payment.status}}' => 'Paid',
            '{{funnel.name}}' => 'My Sales Funnel',
            '{{funnel.url}}' => 'https://example.com/funnel',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address', 'info@example.com'),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between p-4 bg-white border-b">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.funnel-email-templates') }}" class="text-gray-500 hover:text-gray-700">
                <flux:icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-lg font-semibold">{{ $template->name }}</h1>
                <p class="text-sm text-gray-500">Visual Email Builder</p>
            </div>
        </div>
    </div>

    <div
        id="react-email-builder"
        data-design='{{ $initialDesign }}'
        data-save-url="{{ url()->current() }}"
        data-livewire-id="{{ $this->getId() }}"
    ></div>

    @if($showPreview)
        <flux:modal wire:model="showPreview" class="max-w-4xl">
            <div class="space-y-4">
                <flux:heading size="lg">Email Preview</flux:heading>
                <div class="border rounded-lg overflow-hidden">
                    <iframe
                        srcdoc="{{ $previewHtml }}"
                        class="w-full h-[600px] border-0"
                        sandbox="allow-same-origin"
                    ></iframe>
                </div>
                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="$set('showPreview', false)">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
```

**Step 3: Add web route**

In `routes/web.php`, add right after the funnel-email-templates route:

```php
    Volt::route('funnel-email-templates/{template}/builder', 'admin.funnel-email-template-builder')->name('admin.funnel-email-templates.builder');
```

**Step 4: Build assets**

Run: `npm run build`
Expected: Builds successfully.

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/funnel-email-template-builder.blade.php routes/web.php
git commit -m "feat: add visual builder page for funnel email templates"
```

---

### Task 9: Run Full Test Suite and Format Code

**Step 1: Run Laravel Pint**

Run: `vendor/bin/pint --dirty`
Expected: Formats any newly added/modified PHP files.

**Step 2: Run all related tests**

Run: `php artisan test --compact tests/Feature/FunnelEmailTemplateTest.php tests/Feature/FunnelAutomationEmailTemplateTest.php`
Expected: All tests pass.

**Step 3: Ask user if they want to run full test suite**

After the related tests pass, ask the user if they want to run the entire suite: `php artisan test --compact`

**Step 4: Build frontend assets**

Run: `npm run build`
Expected: No errors.

**Step 5: Final commit**

```bash
git add -A
git commit -m "chore: format code with Pint and finalize funnel email templates feature"
```

---

## Summary of All Files

### New Files (7)
1. `database/migrations/*_create_funnel_email_templates_table.php` - Migration
2. `app/Models/FunnelEmailTemplate.php` - Model
3. `database/factories/FunnelEmailTemplateFactory.php` - Factory
4. `app/Http/Controllers/Api/V1/FunnelEmailTemplateController.php` - API Controller
5. `resources/views/livewire/admin/funnel-email-templates.blade.php` - Management page
6. `resources/views/livewire/admin/funnel-email-template-builder.blade.php` - Visual builder
7. `tests/Feature/FunnelEmailTemplateTest.php` - API tests
8. `tests/Feature/FunnelAutomationEmailTemplateTest.php` - Service tests

### Modified Files (5)
1. `routes/api.php` - API routes
2. `routes/web.php` - Admin page routes
3. `resources/views/components/layouts/app/sidebar.blade.php` - Nav item
4. `resources/js/funnel-builder/components/FunnelAutomationBuilder.jsx` - Template UI
5. `resources/js/funnel-builder/types/funnel-automation-types.js` - Config defaults
6. `app/Services/Funnel/FunnelAutomationService.php` - Template email sending
