# Password Vault Module — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a standalone Inertia React workspace at `/admin/vault` for admin-only management of external service credentials (TikTok Shop, social media, hosting, domains, API keys, etc.) with encryption, categories, tags, password generator, and audit logging.

**Architecture:** Follows the Blog & SEO module pattern — dedicated Inertia React app with its own layout, Blade root view, HandleVaultInertiaRequests middleware, and controllers. All passwords encrypted via Laravel Crypt. Dark-themed frosted-glass UI.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Tailwind CSS v4, Laravel Crypt (AES-256-CBC), Pest PHP 4.

**Design Doc:** `docs/plans/2026-08-09-password-vault-design.md`

---

## Task 1: Database — Migrations + Models

**Files:**
- Create: `database/migrations/2026_08_09_000001_create_vault_tables.php`
- Create: `app/Models/VaultCategory.php`
- Create: `app/Models/VaultTag.php`
- Create: `app/Models/VaultCredential.php`
- Create: `app/Models/VaultAuditLog.php`

### Step 1: Create migration

Run:
```bash
php artisan make:migration create_vault_tables --no-interaction
```

Write the migration with all 5 tables (`vault_categories`, `vault_tags`, `vault_credentials`, `vault_credential_tag`, `vault_audit_logs`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vault_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('vault_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->text('password');          // encrypted via Crypt
            $table->text('notes')->nullable(); // encrypted via Crypt
            $table->foreignId('category_id')->nullable()->constrained('vault_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category_id', 'vault_cred_category_index');
            $table->index('created_by', 'vault_cred_created_by_index');
        });

        Schema::create('vault_credential_tag', function (Blueprint $table) {
            $table->foreignId('vault_credential_id')->constrained('vault_credentials')->cascadeOnDelete();
            $table->foreignId('vault_tag_id')->constrained('vault_tags')->cascadeOnDelete();
            $table->primary(['vault_credential_id', 'vault_tag_id'], 'vault_cred_tag_pk');
        });

        Schema::create('vault_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->nullable()->constrained('vault_credentials')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20); // viewed, created, updated, deleted
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['credential_id', 'created_at'], 'vault_audit_cred_time_index');
            $table->index(['user_id', 'created_at'], 'vault_audit_user_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_audit_logs');
        Schema::dropIfExists('vault_credential_tag');
        Schema::dropIfExists('vault_credentials');
        Schema::dropIfExists('vault_tags');
        Schema::dropIfExists('vault_categories');
    }
};
```

### Step 2: Create models

Run:
```bash
php artisan make:model VaultCategory --no-interaction
php artisan make:model VaultTag --no-interaction
php artisan make:model VaultCredential --factory --no-interaction
php artisan make:model VaultAuditLog --no-interaction
```

**VaultCategory.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VaultCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'color', 'sort_order'];

    protected static function booted(): void
    {
        static::creating(function (self $cat) {
            $cat->slug ??= Str::slug($cat->name);
        });
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(VaultCredential::class, 'category_id');
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
```

**VaultTag.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class VaultTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (self $tag) {
            $tag->slug ??= Str::slug($tag->name);
        });
    }

    public function credentials(): BelongsToMany
    {
        return $this->belongsToMany(VaultCredential::class, 'vault_credential_tag');
    }
}
```

**VaultCredential.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class VaultCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'username', 'password', 'notes',
        'category_id', 'created_by', 'updated_by',
    ];

    protected $hidden = ['password', 'notes'];

    /* ── Encryption accessors ─────────────────── */

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getNotesAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /* ── Relationships ────────────────────────── */

    public function category(): BelongsTo
    {
        return $this->belongsTo(VaultCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(VaultTag::class, 'vault_credential_tag');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(VaultAuditLog::class, 'credential_id');
    }

    /* ── Scopes ───────────────────────────────── */

    public function scopeSearch($q, string $term): void
    {
        $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('username', 'like', "%{$term}%")
              ->orWhere('url', 'like', "%{$term}%");
        });
    }
}
```

**VaultAuditLog.php:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultAuditLog extends Model
{
    protected $fillable = [
        'credential_id', 'user_id', 'action', 'changes', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(VaultCredential::class, 'credential_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Step 3: Run migration

```bash
php artisan migrate
```

### Step 4: Write model tests

Create `tests/Feature/Vault/VaultCredentialTest.php`:

```php
<?php

use App\Models\User;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;

test('credential encrypts and decrypts password', function () {
    $user = User::factory()->create();
    $cred = VaultCredential::create([
        'name'       => 'Test Service',
        'username'   => 'admin@test.com',
        'password'   => 'super-secret-123',
        'created_by' => $user->id,
    ]);

    // Raw DB value should be encrypted (not plaintext)
    $raw = \DB::table('vault_credentials')->where('id', $cred->id)->value('password');
    expect($raw)->not->toBe('super-secret-123');

    // Accessor should decrypt
    $cred->refresh();
    expect($cred->password)->toBe('super-secret-123');
});

test('credential encrypts notes', function () {
    $user = User::factory()->create();
    $cred = VaultCredential::create([
        'name'       => 'Test',
        'password'   => 'pw',
        'notes'      => 'secret notes here',
        'created_by' => $user->id,
    ]);

    $raw = \DB::table('vault_credentials')->where('id', $cred->id)->value('notes');
    expect($raw)->not->toBe('secret notes here');

    $cred->refresh();
    expect($cred->notes)->toBe('secret notes here');
});

test('credential belongs to category', function () {
    $user = User::factory()->create();
    $cat  = VaultCategory::create(['name' => 'Social Media', 'slug' => 'social-media']);
    $cred = VaultCredential::create([
        'name'        => 'TikTok',
        'password'    => 'pw',
        'category_id' => $cat->id,
        'created_by'  => $user->id,
    ]);

    expect($cred->category->name)->toBe('Social Media');
});

test('credential has many tags', function () {
    $user = User::factory()->create();
    $cred = VaultCredential::create([
        'name'       => 'Test',
        'password'   => 'pw',
        'created_by' => $user->id,
    ]);
    $tag = VaultTag::create(['name' => 'important', 'slug' => 'important']);
    $cred->tags()->attach($tag);

    expect($cred->tags)->toHaveCount(1);
    expect($cred->tags->first()->name)->toBe('important');
});

test('credential search scope', function () {
    $user = User::factory()->create();
    VaultCredential::create(['name' => 'TikTok Shop MY', 'username' => 'admin', 'password' => 'pw', 'created_by' => $user->id]);
    VaultCredential::create(['name' => 'Hosting', 'username' => 'root', 'password' => 'pw', 'created_by' => $user->id]);

    $results = VaultCredential::query()->search('tiktok')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('TikTok Shop MY');
});
```

### Step 5: Run tests

```bash
php artisan test --compact tests/Feature/Vault/VaultCredentialTest.php
```

### Step 6: Commit

```bash
git add database/migrations/*vault* app/Models/Vault*.php tests/Feature/Vault/ database/factories/VaultCredentialFactory.php
git commit -m "feat(vault): add database tables and Eloquent models for password vault"
```

---

## Task 2: Backend — Service, Controllers, Form Requests, Routes

**Files:**
- Create: `app/Services/VaultAuditService.php`
- Create: `app/Http/Middleware/HandleVaultInertiaRequests.php`
- Create: `app/Http/Controllers/Vault/DashboardController.php`
- Create: `app/Http/Controllers/Vault/CredentialController.php`
- Create: `app/Http/Controllers/Vault/CategoryController.php`
- Create: `app/Http/Controllers/Vault/TagController.php`
- Create: `app/Http/Controllers/Vault/AuditLogController.php`
- Create: `app/Http/Requests/Vault/StoreCredentialRequest.php`
- Create: `app/Http/Requests/Vault/UpdateCredentialRequest.php`
- Create: `app/Http/Requests/Vault/StoreCategoryRequest.php`
- Create: `app/Http/Requests/Vault/StoreTagRequest.php`
- Modify: `routes/web.php` — add vault route group

### Step 1: Create VaultAuditService

```php
<?php

namespace App\Services;

use App\Models\VaultAuditLog;
use App\Models\VaultCredential;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class VaultAuditService
{
    public function log(string $action, ?VaultCredential $credential = null, ?array $changes = null): VaultAuditLog
    {
        // Strip password/notes values from changes (never log secrets)
        if ($changes) {
            foreach (['password', 'notes'] as $secret) {
                if (isset($changes['old'][$secret])) {
                    $changes['old'][$secret] = '***';
                }
                if (isset($changes['new'][$secret])) {
                    $changes['new'][$secret] = '***';
                }
            }
        }

        return VaultAuditLog::create([
            'credential_id' => $credential?->id,
            'user_id'       => Auth::id(),
            'action'        => $action,
            'changes'       => $changes,
            'ip_address'    => Request::ip(),
        ]);
    }
}
```

### Step 2: Create HandleVaultInertiaRequests middleware

```php
<?php

namespace App\Http\Middleware;

use App\Models\VaultCredential;
use Illuminate\Http\Request;

class HandleVaultInertiaRequests extends HandleInertiaRequests
{
    protected $rootView = 'vault.app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'totalCredentials' => fn () => VaultCredential::count(),
        ];
    }
}
```

### Step 3: Create controllers

**DashboardController.php:**
```php
<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\VaultAuditLog;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Overview', [
            'totalCredentials' => VaultCredential::count(),
            'byCategory'       => VaultCategory::query()
                ->withCount('credentials')
                ->ordered()
                ->get(['id', 'name', 'icon', 'color', 'credentials_count']),
            'recentActivity'   => VaultAuditLog::query()
                ->with('user:id,name', 'credential:id,name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($log) => [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'user'       => $log->user?->name,
                    'credential' => $log->credential?->name,
                    'ip'         => $log->ip_address,
                    'time'       => $log->created_at->diffForHumans(),
                    'date'       => $log->created_at->toDateTimeString(),
                ]),
        ]);
    }
}
```

**CredentialController.php:**
```php
<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreCredentialRequest;
use App\Http\Requests\Vault\UpdateCredentialRequest;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;
use App\Services\VaultAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CredentialController extends Controller
{
    public function __construct(private readonly VaultAuditService $audit) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search'   => $request->input('search', ''),
            'category' => $request->input('category', ''),
            'tag'      => $request->input('tag', ''),
            'sort'     => $request->input('sort', 'name'),
        ];

        $sortCol = match ($filters['sort']) {
            'oldest'   => ['created_at', 'asc'],
            'updated'  => ['updated_at', 'desc'],
            default    => ['name', 'asc'],  // 'name'
        };

        $credentials = VaultCredential::query()
            ->with(['category:id,name,color', 'tags:id,name', 'creator:id,name'])
            ->when($filters['search'], fn ($q, $v) => $q->search($v))
            ->when($filters['category'], fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['tag'], fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('vault_tags.id', $v)))
            ->orderBy($sortCol[0], $sortCol[1])
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'url'       => $c->url,
                'username'  => $c->username,
                'category'  => $c->category ? ['id' => $c->category->id, 'name' => $c->category->name, 'color' => $c->category->color] : null,
                'tags'      => $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                'creator'   => $c->creator?->name,
                'updated_at' => $c->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Credentials', [
            'credentials' => $credentials,
            'filters'     => $filters,
            'categories'  => VaultCategory::query()->ordered()->get(['id', 'name', 'color']),
            'tags'        => VaultTag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCredentialRequest $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $cred = VaultCredential::create($data);

        if (! empty($data['tags'])) {
            $this->syncTags($cred, $data['tags']);
        }

        $this->audit->log('created', $cred);

        return back()->with('success', 'Credential created.');
    }

    public function show(VaultCredential $credential): JsonResponse
    {
        $credential->load('category:id,name,color', 'tags:id,name');

        $this->audit->log('viewed', $credential);

        return response()->json([
            'id'       => $credential->id,
            'name'     => $credential->name,
            'url'      => $credential->url,
            'username' => $credential->username,
            'password' => $credential->password, // decrypted via accessor
            'notes'    => $credential->notes,     // decrypted via accessor
            'category' => $credential->category,
            'tags'     => $credential->tags,
        ]);
    }

    public function update(UpdateCredentialRequest $request, VaultCredential $credential): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $old = $credential->only(['name', 'url', 'username', 'category_id']);

        $credential->update($data);

        if (array_key_exists('tags', $data)) {
            $this->syncTags($credential, $data['tags']);
        }

        $this->audit->log('updated', $credential, [
            'old' => $old,
            'new' => $credential->only(['name', 'url', 'username', 'category_id']),
        ]);

        return back()->with('success', 'Credential updated.');
    }

    public function destroy(VaultCredential $credential): \Illuminate\Http\RedirectResponse
    {
        $this->audit->log('deleted', $credential, [
            'old' => ['name' => $credential->name, 'username' => $credential->username],
        ]);

        $credential->delete();

        return back()->with('success', 'Credential deleted.');
    }

    private function syncTags(VaultCredential $cred, array $tagNames): void
    {
        $ids = collect($tagNames)->map(function (string $name) {
            return VaultTag::firstOrCreate(
                ['slug' => \Str::slug($name)],
                ['name' => $name]
            )->id;
        });
        $cred->tags()->sync($ids);
    }
}
```

**CategoryController.php:**
```php
<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreCategoryRequest;
use App\Models\VaultCategory;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Categories', [
            'categories' => VaultCategory::query()
                ->withCount('credentials')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): \Illuminate\Http\RedirectResponse
    {
        VaultCategory::create($request->validated());
        return back()->with('success', 'Category created.');
    }

    public function update(StoreCategoryRequest $request, VaultCategory $category): \Illuminate\Http\RedirectResponse
    {
        $category->update($request->validated());
        return back()->with('success', 'Category updated.');
    }

    public function destroy(VaultCategory $category): \Illuminate\Http\RedirectResponse
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }
}
```

**TagController.php:**
```php
<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreTagRequest;
use App\Models\VaultTag;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tags', [
            'tags' => VaultTag::query()
                ->withCount('credentials')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreTagRequest $request): \Illuminate\Http\RedirectResponse
    {
        VaultTag::create($request->validated());
        return back()->with('success', 'Tag created.');
    }

    public function update(StoreTagRequest $request, VaultTag $tag): \Illuminate\Http\RedirectResponse
    {
        $tag->update($request->validated());
        return back()->with('success', 'Tag updated.');
    }

    public function destroy(VaultTag $tag): \Illuminate\Http\RedirectResponse
    {
        $tag->delete();
        return back()->with('success', 'Tag deleted.');
    }
}
```

**AuditLogController.php:**
```php
<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\VaultAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = VaultAuditLog::query()
            ->with('user:id,name', 'credential:id,name')
            ->when($request->input('action'), fn ($q, $v) => $q->where('action', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'user'       => $log->user?->name,
                'credential' => $log->credential?->name,
                'changes'    => $log->changes,
                'ip'         => $log->ip_address,
                'time'       => $log->created_at->diffForHumans(),
                'date'       => $log->created_at->toDateTimeString(),
            ]);

        return Inertia::render('AuditLog', [
            'logs'    => $logs,
            'filters' => ['action' => $request->input('action', '')],
        ]);
    }
}
```

### Step 4: Create Form Requests

**StoreCredentialRequest.php:**
```php
<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'url'         => ['nullable', 'url', 'max:500'],
            'username'    => ['nullable', 'string', 'max:255'],
            'password'    => ['required', 'string', 'max:1000'],
            'notes'       => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:vault_categories,id'],
            'tags'        => ['array'],
            'tags.*'      => ['string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url'         => trim((string) $this->input('url')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
        ]);
    }
}
```

**UpdateCredentialRequest.php:**
```php
<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'url'         => ['nullable', 'url', 'max:500'],
            'username'    => ['nullable', 'string', 'max:255'],
            'password'    => ['nullable', 'string', 'max:1000'],
            'notes'       => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:vault_categories,id'],
            'tags'        => ['array'],
            'tags.*'      => ['string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url'         => trim((string) $this->input('url')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
        ]);
    }
}
```

**StoreCategoryRequest.php:**
```php
<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:50'],
            'color'      => ['nullable', 'string', 'max:20'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
```

**StoreTagRequest.php:**
```php
<?php

namespace App\Http\Requests\Vault;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
        ];
    }
}
```

### Step 5: Add routes to routes/web.php

Add this route group (after the blog-seo group):

```php
use App\Http\Controllers\Vault\DashboardController as VaultDashboardController;
use App\Http\Controllers\Vault\CredentialController as VaultCredentialController;
use App\Http\Controllers\Vault\CategoryController as VaultCategoryController;
use App\Http\Controllers\Vault\TagController as VaultTagController;
use App\Http\Controllers\Vault\AuditLogController as VaultAuditLogController;
use App\Http\Middleware\HandleVaultInertiaRequests;

Route::middleware(['auth', 'role:admin', HandleVaultInertiaRequests::class])
    ->prefix('admin/vault')
    ->name('vault.')
    ->group(function () {
        Route::get('/', [VaultDashboardController::class, 'index'])->name('dashboard');

        Route::get('credentials', [VaultCredentialController::class, 'index'])->name('credentials.index');
        Route::post('credentials', [VaultCredentialController::class, 'store'])->name('credentials.store');
        Route::get('credentials/{credential}', [VaultCredentialController::class, 'show'])->name('credentials.show');
        Route::put('credentials/{credential}', [VaultCredentialController::class, 'update'])->name('credentials.update');
        Route::delete('credentials/{credential}', [VaultCredentialController::class, 'destroy'])->name('credentials.destroy');

        Route::get('categories', [VaultCategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [VaultCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [VaultCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [VaultCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('tags', [VaultTagController::class, 'index'])->name('tags.index');
        Route::post('tags', [VaultTagController::class, 'store'])->name('tags.store');
        Route::put('tags/{tag}', [VaultTagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [VaultTagController::class, 'destroy'])->name('tags.destroy');

        Route::get('audit-log', [VaultAuditLogController::class, 'index'])->name('audit.index');
    });
```

### Step 6: Write controller tests

Create `tests/Feature/Vault/VaultControllerTest.php`:

```php
<?php

use App\Models\User;
use App\Models\VaultCategory;
use App\Models\VaultCredential;
use App\Models\VaultTag;
use App\Models\VaultAuditLog;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('non-admin cannot access vault', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/vault')->assertForbidden();
});

test('admin can view vault dashboard', function () {
    $this->actingAs($this->admin)->get('/admin/vault')->assertSuccessful();
});

test('admin can list credentials', function () {
    $this->actingAs($this->admin)->get('/admin/vault/credentials')->assertSuccessful();
});

test('admin can create credential', function () {
    $cat = VaultCategory::create(['name' => 'Social Media', 'slug' => 'social-media']);

    $this->actingAs($this->admin)->post('/admin/vault/credentials', [
        'name'        => 'TikTok Shop MY',
        'url'         => 'https://seller-my.tiktok.com',
        'username'    => 'admin@company.com',
        'password'    => 'super-secret',
        'category_id' => $cat->id,
        'tags'        => ['ecommerce', 'live'],
    ])->assertRedirect();

    $cred = VaultCredential::where('name', 'TikTok Shop MY')->first();
    expect($cred)->not->toBeNull();
    expect($cred->password)->toBe('super-secret');
    expect($cred->tags)->toHaveCount(2);

    // Audit log created
    expect(VaultAuditLog::where('action', 'created')->count())->toBe(1);
});

test('admin can view credential password', function () {
    $cred = VaultCredential::create([
        'name' => 'Test', 'password' => 'my-password', 'created_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/admin/vault/credentials/{$cred->id}")
        ->assertSuccessful();

    expect($response->json('password'))->toBe('my-password');

    // Audit log: viewed
    expect(VaultAuditLog::where('action', 'viewed')->count())->toBe(1);
});

test('admin can update credential', function () {
    $cred = VaultCredential::create([
        'name' => 'Old Name', 'password' => 'pw', 'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->put("/admin/vault/credentials/{$cred->id}", [
        'name'     => 'New Name',
        'password' => 'new-pw',
    ])->assertRedirect();

    $cred->refresh();
    expect($cred->name)->toBe('New Name');
    expect($cred->password)->toBe('new-pw');
});

test('admin can delete credential', function () {
    $cred = VaultCredential::create([
        'name' => 'To Delete', 'password' => 'pw', 'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->delete("/admin/vault/credentials/{$cred->id}")->assertRedirect();

    expect(VaultCredential::find($cred->id))->toBeNull();
    expect(VaultAuditLog::where('action', 'deleted')->count())->toBe(1);
});

test('admin can CRUD categories', function () {
    $this->actingAs($this->admin)->post('/admin/vault/categories', [
        'name' => 'Hosting',
    ])->assertRedirect();

    $cat = VaultCategory::where('name', 'Hosting')->first();
    expect($cat)->not->toBeNull();
    expect($cat->slug)->toBe('hosting');
});

test('admin can CRUD tags', function () {
    $this->actingAs($this->admin)->post('/admin/vault/tags', [
        'name' => 'Critical',
    ])->assertRedirect();

    $tag = VaultTag::where('name', 'Critical')->first();
    expect($tag)->not->toBeNull();
});

test('admin can view audit log', function () {
    $this->actingAs($this->admin)->get('/admin/vault/audit-log')->assertSuccessful();
});

test('audit log never stores password values', function () {
    $cred = VaultCredential::create([
        'name' => 'Test', 'password' => 'secret-pw', 'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->put("/admin/vault/credentials/{$cred->id}", [
        'name'     => 'Test Updated',
        'password' => 'new-secret-pw',
    ]);

    $log = VaultAuditLog::where('action', 'updated')->first();
    $changes = $log->changes;

    // Password should be masked, never stored in plaintext
    if (isset($changes['old']['password'])) {
        expect($changes['old']['password'])->toBe('***');
    }
    if (isset($changes['new']['password'])) {
        expect($changes['new']['password'])->toBe('***');
    }
});
```

### Step 7: Run tests

```bash
php artisan test --compact tests/Feature/Vault/
```

### Step 8: Commit

```bash
git add app/Http/Controllers/Vault/ app/Http/Middleware/HandleVaultInertiaRequests.php app/Http/Requests/Vault/ app/Services/VaultAuditService.php routes/web.php tests/Feature/Vault/VaultControllerTest.php
git commit -m "feat(vault): add controllers, routes, form requests, and audit service"
```

---

## Task 3: Frontend — Inertia Root View, Entry Point, Layout, CSS

**Files:**
- Create: `resources/views/vault/app.blade.php`
- Create: `resources/js/vault/app.jsx`
- Create: `resources/js/vault/styles/vault.css`
- Create: `resources/js/vault/layouts/VaultLayout.jsx`
- Create: `resources/js/vault/components/Ui.jsx`
- Create: `resources/js/vault/lib/utils.js`
- Modify: `vite.config.js` — add vault entry points

### Step 1: Create Blade root view

`resources/views/vault/app.blade.php`:
```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#0B1120]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('app.name', 'Password Vault') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">
    @routes
    @viteReactRefresh
    @vite(['resources/js/vault/app.jsx', 'resources/js/vault/styles/vault.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
```

### Step 2: Create React entry point

`resources/js/vault/app.jsx`:
```jsx
import './styles/vault.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

router.on('invalid', (event) => {
  if (event.detail?.response?.status === 419) {
    event.preventDefault();
    if (window.confirm('Your session expired. Reload the page to continue?')) {
      window.location.reload();
    }
  }
});

createInertiaApp({
  title: (title) => (title ? `${title} · Password Vault` : 'Password Vault'),
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
    const page = pages[`./pages/${name}.jsx`];
    if (!page) throw new Error(`[vault] page not found: ${name}`);
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#F59E0B' },
});
```

### Step 3: Create CSS with Tailwind v4

`resources/js/vault/styles/vault.css`:
```css
@import "tailwindcss";

@theme {
  --color-brand: #F59E0B;
  --color-brand-2: #EAB308;
  --color-surface: rgba(255, 255, 255, 0.06);
  --color-border: rgba(255, 255, 255, 0.08);
}

body {
  background:
    radial-gradient(1200px 600px at 100% -10%, rgba(245, 158, 11, 0.10), transparent 60%),
    radial-gradient(900px 500px at -10% 10%, rgba(234, 179, 8, 0.07), transparent 55%),
    #0B1120;
  min-height: 100vh;
}

/* Frosted glass panel */
.panel {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(14px);
  border-radius: 0.75rem;
}
```

### Step 4: Create VaultLayout.jsx

`resources/js/vault/layouts/VaultLayout.jsx`:
```jsx
import { Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import toast, { Toaster } from 'react-hot-toast';
import {
  ShieldCheck, Key, FolderOpen, Tag, ClipboardList,
  LayoutDashboard, Menu, X, LogOut, ArrowLeft,
} from 'lucide-react';

/* ── Helpers ──────────────────────────────────── */

function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

function useFlashToasts() {
  const { flash } = usePage().props;
  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash?.success, flash?.error]);
}

/* ── Navigation ───────────────────────────────── */

const NAV = [
  { label: 'Overview',     href: '/admin/vault',             icon: LayoutDashboard },
  { label: 'Credentials',  href: '/admin/vault/credentials', icon: Key },
  { label: 'Categories',   href: '/admin/vault/categories',  icon: FolderOpen },
  { label: 'Tags',         href: '/admin/vault/tags',        icon: Tag },
  { label: 'Audit Log',    href: '/admin/vault/audit-log',   icon: ClipboardList },
];

function NavLinks() {
  const { url } = usePage();
  return (
    <nav className="flex flex-col gap-0.5">
      {NAV.map((item) => {
        const active = url === item.href || (item.href !== '/admin/vault' && url.startsWith(item.href));
        return (
          <Link
            key={item.href}
            href={item.href}
            className={cn(
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              active
                ? 'bg-amber-500/15 text-amber-400'
                : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'
            )}
          >
            <item.icon className="h-4 w-4 shrink-0" />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

function Brand() {
  return (
    <Link href="/admin/vault" className="flex items-center gap-3 px-3 py-4">
      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/20">
        <ShieldCheck className="h-5 w-5 text-amber-400" />
      </div>
      <div>
        <p className="text-sm font-semibold text-white">Password Vault</p>
        <p className="text-xs text-slate-500">Credential Manager</p>
      </div>
    </Link>
  );
}

function UserFooter() {
  const { auth } = usePage().props;
  return (
    <div className="border-t border-white/5 px-3 py-4">
      <div className="flex items-center justify-between">
        <div className="min-w-0">
          <p className="truncate text-sm font-medium text-white">{auth?.user?.name}</p>
          <p className="truncate text-xs text-slate-500">{auth?.user?.email}</p>
        </div>
        <a href="/admin" className="rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-slate-200" title="Back to Admin">
          <ArrowLeft className="h-4 w-4" />
        </a>
      </div>
    </div>
  );
}

/* ── Sidebar ──────────────────────────────────── */

function Sidebar() {
  return (
    <aside className="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 border-r border-white/5 bg-[#0B1120]/80 backdrop-blur-sm">
      <div className="flex flex-1 flex-col gap-6 overflow-y-auto px-4 py-2">
        <Brand />
        <NavLinks />
      </div>
      <UserFooter />
    </aside>
  );
}

/* ── Mobile ───────────────────────────────────── */

function MobileBar({ onOpen }) {
  return (
    <header className="sticky top-0 z-40 flex items-center gap-3 border-b border-white/5 bg-[#0B1120]/90 px-4 py-3 backdrop-blur-md lg:hidden">
      <button onClick={onOpen} className="rounded-lg p-1.5 text-slate-400 hover:text-white">
        <Menu className="h-5 w-5" />
      </button>
      <ShieldCheck className="h-5 w-5 text-amber-400" />
      <span className="text-sm font-semibold text-white">Password Vault</span>
    </header>
  );
}

function MobileDrawer({ open, onClose }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 lg:hidden">
      <div className="absolute inset-0 bg-black/60" onClick={onClose} />
      <aside className="absolute inset-y-0 left-0 w-72 bg-[#0B1120] border-r border-white/5 p-4 flex flex-col gap-6">
        <div className="flex items-center justify-between">
          <Brand />
          <button onClick={onClose} className="p-1.5 text-slate-400 hover:text-white">
            <X className="h-5 w-5" />
          </button>
        </div>
        <NavLinks />
        <div className="mt-auto"><UserFooter /></div>
      </aside>
    </div>
  );
}

/* ── Layout ───────────────────────────────────── */

export default function VaultLayout({ children, title, subtitle, actions, wide = false }) {
  const [drawer, setDrawer] = useState(false);
  useFlashToasts();

  return (
    <div className="min-h-screen text-slate-200">
      <Toaster position="top-right" toastOptions={{
        className: '!bg-slate-800 !text-slate-200 !border !border-white/10',
      }} />

      <Sidebar />
      <MobileBar onOpen={() => setDrawer(true)} />
      <MobileDrawer open={drawer} onClose={() => setDrawer(false)} />

      <main className={cn('lg:pl-64', wide ? 'mx-auto max-w-7xl' : 'mx-auto max-w-5xl')}>
        <div className="px-4 py-8 sm:px-6 lg:px-8">
          {(title || actions) && (
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                {title && <h1 className="text-2xl font-bold text-white">{title}</h1>}
                {subtitle && <p className="mt-1 text-sm text-slate-400">{subtitle}</p>}
              </div>
              {actions && <div className="flex items-center gap-2">{actions}</div>}
            </div>
          )}
          {children}
        </div>
      </main>
    </div>
  );
}
```

### Step 5: Create UI components

`resources/js/vault/components/Ui.jsx`:
```jsx
import { cn } from '../lib/utils';

export function Card({ children, className, ...props }) {
  return (
    <div className={cn('panel p-5', className)} {...props}>
      {children}
    </div>
  );
}

export function Badge({ children, color = 'slate', className }) {
  const colors = {
    slate:  'bg-slate-500/15 text-slate-400',
    amber:  'bg-amber-500/15 text-amber-400',
    green:  'bg-green-500/15 text-green-400',
    red:    'bg-red-500/15 text-red-400',
    blue:   'bg-blue-500/15 text-blue-400',
  };
  return (
    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', colors[color] || colors.slate, className)}>
      {children}
    </span>
  );
}

export function Button({ children, variant = 'primary', className, ...props }) {
  const base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50';
  const variants = {
    primary: 'bg-amber-500 text-white hover:bg-amber-600',
    secondary: 'bg-white/10 text-slate-200 hover:bg-white/15',
    ghost: 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
    danger: 'bg-red-500/15 text-red-400 hover:bg-red-500/25',
  };
  const Tag = props.href ? 'a' : 'button';
  return <Tag className={cn(base, variants[variant], className)} {...props}>{children}</Tag>;
}

export function Field({ label, error, children, className }) {
  return (
    <div className={cn('space-y-1.5', className)}>
      {label && <label className="block text-sm font-medium text-slate-300">{label}</label>}
      {children}
      {error && <p className="text-xs text-red-400">{error}</p>}
    </div>
  );
}

export function Input({ className, ...props }) {
  return (
    <input
      className={cn(
        'w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder-slate-500',
        'focus:border-amber-500/50 focus:outline-none focus:ring-1 focus:ring-amber-500/50',
        className
      )}
      {...props}
    />
  );
}

export function Textarea({ className, ...props }) {
  return (
    <textarea
      className={cn(
        'w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder-slate-500',
        'focus:border-amber-500/50 focus:outline-none focus:ring-1 focus:ring-amber-500/50',
        className
      )}
      {...props}
    />
  );
}

export function Select({ className, children, ...props }) {
  return (
    <select
      className={cn(
        'w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200',
        'focus:border-amber-500/50 focus:outline-none focus:ring-1 focus:ring-amber-500/50',
        className
      )}
      {...props}
    >
      {children}
    </select>
  );
}

export function EmptyState({ icon: Icon, title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-white/10 py-16 text-center">
      {Icon && <Icon className="mb-3 h-10 w-10 text-slate-600" />}
      <h3 className="text-sm font-medium text-slate-300">{title}</h3>
      {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function Pagination({ links }) {
  if (!links || links.length <= 3) return null;
  return (
    <nav className="mt-6 flex items-center justify-center gap-1">
      {links.map((link, i) => (
        <a
          key={i}
          href={link.url}
          className={cn(
            'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
            link.active ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-white/5',
            !link.url && 'pointer-events-none opacity-40',
          )}
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </nav>
  );
}

export function StatTile({ label, value, icon: Icon }) {
  return (
    <div className="panel flex items-center gap-4 p-4">
      {Icon && (
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/15">
          <Icon className="h-5 w-5 text-amber-400" />
        </div>
      )}
      <div>
        <p className="text-2xl font-bold text-white">{value}</p>
        <p className="text-xs text-slate-400">{label}</p>
      </div>
    </div>
  );
}
```

### Step 6: Create utils

`resources/js/vault/lib/utils.js`:
```js
export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

export function formatDate(iso) {
  if (!iso) return '—';
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric',
  }).format(new Date(iso));
}

export function timeAgo(iso) {
  if (!iso) return '—';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `${days}d ago`;
}

export function generatePassword(length = 16, options = {}) {
  const {
    uppercase = true,
    lowercase = true,
    numbers = true,
    symbols = true,
  } = options;

  let chars = '';
  if (lowercase) chars += 'abcdefghijklmnopqrstuvwxyz';
  if (uppercase) chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  if (numbers) chars += '0123456789';
  if (symbols) chars += '!@#$%^&*()_+-=[]{}|;:,.<>?';

  if (!chars) chars = 'abcdefghijklmnopqrstuvwxyz';

  const array = new Uint32Array(length);
  crypto.getRandomValues(array);

  return Array.from(array, (n) => chars[n % chars.length]).join('');
}

export async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}
```

### Step 7: Add Vite entry points

In `vite.config.js`, add these two entries to the `input` array:
```js
'resources/js/vault/app.jsx',
'resources/js/vault/styles/vault.css',
```

### Step 8: Commit

```bash
git add resources/views/vault/ resources/js/vault/ vite.config.js
git commit -m "feat(vault): add Inertia root view, React entry point, layout, CSS, and UI components"
```

---

## Task 4: Frontend — React Pages (Overview, Credentials, Categories, Tags, AuditLog)

**Files:**
- Create: `resources/js/vault/pages/Overview.jsx`
- Create: `resources/js/vault/pages/Credentials.jsx`
- Create: `resources/js/vault/pages/Categories.jsx`
- Create: `resources/js/vault/pages/Tags.jsx`
- Create: `resources/js/vault/pages/AuditLog.jsx`

### Step 1: Create Overview.jsx

```jsx
import { Head } from '@inertiajs/react';
import VaultLayout from '../layouts/VaultLayout';
import { Card, StatTile, Badge } from '../components/Ui';
import { Key, FolderOpen, ClipboardList, Shield } from 'lucide-react';

export default function Overview({ totalCredentials, byCategory, recentActivity }) {
  return (
    <VaultLayout
      title="Overview"
      subtitle="Password vault at a glance"
    >
      <Head title="Overview" />

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="Total Credentials" value={totalCredentials} icon={Key} />
        <StatTile label="Categories" value={byCategory.length} icon={FolderOpen} />
        <StatTile label="Recent Actions" value={recentActivity.length} icon={ClipboardList} />
        <StatTile label="Encrypted" value="AES-256" icon={Shield} />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        {/* By Category */}
        <Card>
          <h2 className="mb-4 text-sm font-semibold text-white">Credentials by Category</h2>
          {byCategory.length === 0 ? (
            <p className="text-sm text-slate-500">No categories yet.</p>
          ) : (
            <div className="space-y-2">
              {byCategory.map((cat) => (
                <div key={cat.id} className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2">
                  <span className="text-sm text-slate-300">{cat.name}</span>
                  <Badge color="amber">{cat.credentials_count}</Badge>
                </div>
              ))}
            </div>
          )}
        </Card>

        {/* Recent Activity */}
        <Card>
          <h2 className="mb-4 text-sm font-semibold text-white">Recent Activity</h2>
          {recentActivity.length === 0 ? (
            <p className="text-sm text-slate-500">No activity yet.</p>
          ) : (
            <div className="space-y-2">
              {recentActivity.map((log) => (
                <div key={log.id} className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2">
                  <div>
                    <span className="text-sm text-slate-300">{log.user}</span>
                    <span className="mx-1.5 text-slate-600">·</span>
                    <Badge color={log.action === 'deleted' ? 'red' : log.action === 'created' ? 'green' : 'slate'}>
                      {log.action}
                    </Badge>
                    {log.credential && (
                      <span className="ml-1.5 text-sm text-slate-400">{log.credential}</span>
                    )}
                  </div>
                  <span className="text-xs text-slate-500">{log.time}</span>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>
    </VaultLayout>
  );
}
```

### Step 2: Create Credentials.jsx

```jsx
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import VaultLayout from '../layouts/VaultLayout';
import { Card, Button, Field, Input, Textarea, Select, Badge, EmptyState, Pagination } from '../components/Ui';
import { generatePassword, copyToClipboard } from '../lib/utils';
import { Key, Plus, Eye, EyeOff, Copy, Pencil, Trash2, RefreshCw, Search, X, Check } from 'lucide-react';
import toast from 'react-hot-toast';

/* ── Password Generator ───────────────────────── */

function PasswordGenerator({ onGenerate }) {
  const [length, setLength] = useState(16);
  const [opts, setOpts] = useState({ uppercase: true, lowercase: true, numbers: true, symbols: true });

  const handleGenerate = () => {
    const pw = generatePassword(length, opts);
    onGenerate(pw);
  };

  return (
    <div className="rounded-lg border border-white/10 bg-white/5 p-3 space-y-3">
      <div className="flex items-center gap-3">
        <label className="text-xs text-slate-400 w-16">Length</label>
        <input type="range" min="8" max="64" value={length} onChange={(e) => setLength(+e.target.value)}
          className="flex-1 accent-amber-500" />
        <span className="text-xs text-slate-300 w-6 text-right">{length}</span>
      </div>
      <div className="flex flex-wrap gap-3">
        {[['uppercase', 'A-Z'], ['lowercase', 'a-z'], ['numbers', '0-9'], ['symbols', '!@#']].map(([key, label]) => (
          <label key={key} className="flex items-center gap-1.5 text-xs text-slate-400">
            <input type="checkbox" checked={opts[key]}
              onChange={(e) => setOpts({ ...opts, [key]: e.target.checked })}
              className="rounded border-white/20 bg-white/5 accent-amber-500" />
            {label}
          </label>
        ))}
      </div>
      <Button variant="secondary" onClick={handleGenerate} className="w-full">
        <RefreshCw className="h-3.5 w-3.5" /> Generate Password
      </Button>
    </div>
  );
}

/* ── Credential Modal ─────────────────────────── */

function CredentialModal({ credential, categories, onClose }) {
  const isEdit = !!credential;
  const form = useForm({
    name: credential?.name || '',
    url: credential?.url || '',
    username: credential?.username || '',
    password: credential?.password || '',
    notes: credential?.notes || '',
    category_id: credential?.category?.id || '',
    tags: credential?.tags?.map((t) => t.name) || [],
  });
  const [showPw, setShowPw] = useState(false);
  const [showGen, setShowGen] = useState(false);
  const [tagInput, setTagInput] = useState('');

  const submit = (e) => {
    e.preventDefault();
    if (isEdit) {
      form.put(`/admin/vault/credentials/${credential.id}`, { onSuccess: onClose });
    } else {
      form.post('/admin/vault/credentials', { onSuccess: onClose });
    }
  };

  const addTag = () => {
    const val = tagInput.trim();
    if (val && !form.data.tags.includes(val)) {
      form.setData('tags', [...form.data.tags, val]);
    }
    setTagInput('');
  };

  const removeTag = (tag) => {
    form.setData('tags', form.data.tags.filter((t) => t !== tag));
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60" onClick={onClose} />
      <div className="relative w-full max-w-lg panel p-6 max-h-[90vh] overflow-y-auto">
        <h2 className="mb-6 text-lg font-semibold text-white">
          {isEdit ? 'Edit Credential' : 'Add Credential'}
        </h2>

        <form onSubmit={submit} className="space-y-4">
          <Field label="Service Name" error={form.errors.name}>
            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. TikTok Shop MY" />
          </Field>

          <Field label="URL" error={form.errors.url}>
            <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://..." />
          </Field>

          <Field label="Username / Email" error={form.errors.username}>
            <Input value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
          </Field>

          <Field label="Password" error={form.errors.password}>
            <div className="relative">
              <Input
                type={showPw ? 'text' : 'password'}
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                className="pr-20"
              />
              <div className="absolute right-1 top-1 flex gap-0.5">
                <button type="button" onClick={() => setShowPw(!showPw)} className="rounded p-1.5 text-slate-400 hover:text-slate-200">
                  {showPw ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </button>
                <button type="button" onClick={() => { copyToClipboard(form.data.password); toast.success('Copied!'); }}
                  className="rounded p-1.5 text-slate-400 hover:text-slate-200">
                  <Copy className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
            <button type="button" onClick={() => setShowGen(!showGen)} className="mt-1 text-xs text-amber-400 hover:underline">
              {showGen ? 'Hide generator' : 'Generate password'}
            </button>
            {showGen && <PasswordGenerator onGenerate={(pw) => { form.setData('password', pw); setShowPw(true); }} />}
          </Field>

          <Field label="Category" error={form.errors.category_id}>
            <Select value={form.data.category_id} onChange={(e) => form.setData('category_id', e.target.value)}>
              <option value="">None</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </Select>
          </Field>

          <Field label="Tags">
            <div className="flex gap-2">
              <Input value={tagInput} onChange={(e) => setTagInput(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }}
                placeholder="Add tag..." className="flex-1" />
              <Button type="button" variant="secondary" onClick={addTag}><Plus className="h-3.5 w-3.5" /></Button>
            </div>
            {form.data.tags.length > 0 && (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {form.data.tags.map((tag) => (
                  <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-xs text-amber-400">
                    {tag}
                    <button type="button" onClick={() => removeTag(tag)}><X className="h-3 w-3" /></button>
                  </span>
                ))}
              </div>
            )}
          </Field>

          <Field label="Notes" error={form.errors.notes}>
            <Textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} rows={3} placeholder="Additional info..." />
          </Field>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Saving...' : (isEdit ? 'Update' : 'Create')}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ── Main Page ────────────────────────────────── */

export default function Credentials({ credentials, filters, categories, tags }) {
  const [search, setSearch] = useState(filters.search || '');
  const [modal, setModal] = useState(null); // null | 'create' | {credential}
  const [showPasswords, setShowPasswords] = useState({});
  const [loadingPw, setLoadingPw] = useState({});
  const [decryptedPw, setDecryptedPw] = useState({});

  const apply = (patch) => {
    router.get('/admin/vault/credentials', { ...filters, ...patch }, {
      preserveState: true, replace: true, preserveScroll: true,
    });
  };

  const revealPassword = async (id) => {
    if (decryptedPw[id]) {
      setShowPasswords({ ...showPasswords, [id]: !showPasswords[id] });
      return;
    }
    setLoadingPw({ ...loadingPw, [id]: true });
    try {
      const res = await fetch(`/admin/vault/credentials/${id}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      });
      const data = await res.json();
      setDecryptedPw({ ...decryptedPw, [id]: data.password });
      setShowPasswords({ ...showPasswords, [id]: true });
    } catch {
      toast.error('Failed to reveal password');
    } finally {
      setLoadingPw({ ...loadingPw, [id]: false });
    }
  };

  const copyPassword = async (id) => {
    let pw = decryptedPw[id];
    if (!pw) {
      const res = await fetch(`/admin/vault/credentials/${id}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      });
      const data = await res.json();
      pw = data.password;
      setDecryptedPw({ ...decryptedPw, [id]: pw });
    }
    const ok = await copyToClipboard(pw);
    toast.success(ok ? 'Password copied!' : 'Copy failed');
  };

  const openEdit = async (cred) => {
    const res = await fetch(`/admin/vault/credentials/${cred.id}`, {
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    const full = await res.json();
    setModal(full);
  };

  return (
    <VaultLayout
      title="Credentials"
      subtitle="Manage your service credentials"
      actions={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Credential</Button>}
    >
      <Head title="Credentials" />

      {/* Filters */}
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && apply({ search })}
            placeholder="Search credentials..."
            className="pl-9"
          />
        </div>
        <Select value={filters.category} onChange={(e) => apply({ category: e.target.value })} className="w-40">
          <option value="">All Categories</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>
        <Select value={filters.tag} onChange={(e) => apply({ tag: e.target.value })} className="w-32">
          <option value="">All Tags</option>
          {tags.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
        </Select>
      </div>

      {/* Credential List */}
      {credentials.data.length === 0 ? (
        <EmptyState
          icon={Key}
          title="No credentials yet"
          description="Add your first credential to get started."
          action={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Credential</Button>}
        />
      ) : (
        <div className="space-y-2">
          {credentials.data.map((cred) => (
            <div key={cred.id} className="panel flex items-center gap-4 p-4">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10">
                <Key className="h-5 w-5 text-amber-400" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate text-sm font-medium text-white">{cred.name}</p>
                  {cred.category && (
                    <Badge color="amber">{cred.category.name}</Badge>
                  )}
                </div>
                <p className="truncate text-xs text-slate-400">
                  {cred.username || '—'}
                  {cred.url && <> · <a href={cred.url} target="_blank" className="hover:text-amber-400">{new URL(cred.url).hostname}</a></>}
                </p>
                {cred.tags?.length > 0 && (
                  <div className="mt-1 flex flex-wrap gap-1">
                    {cred.tags.map((t) => <Badge key={t.id} color="slate">{t.name}</Badge>)}
                  </div>
                )}
              </div>

              {/* Password display */}
              <div className="flex items-center gap-1">
                <span className="text-sm font-mono text-slate-400 w-28 text-right truncate">
                  {showPasswords[cred.id] && decryptedPw[cred.id] ? decryptedPw[cred.id] : '••••••••'}
                </span>
                <button onClick={() => revealPassword(cred.id)}
                  className="rounded p-1.5 text-slate-400 hover:text-slate-200" title="Show/Hide">
                  {loadingPw[cred.id] ? <RefreshCw className="h-4 w-4 animate-spin" /> : showPasswords[cred.id] ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
                <button onClick={() => copyPassword(cred.id)}
                  className="rounded p-1.5 text-slate-400 hover:text-slate-200" title="Copy">
                  <Copy className="h-4 w-4" />
                </button>
              </div>

              {/* Actions */}
              <div className="flex items-center gap-1">
                <button onClick={() => openEdit(cred)}
                  className="rounded p-1.5 text-slate-400 hover:text-slate-200" title="Edit">
                  <Pencil className="h-4 w-4" />
                </button>
                <button onClick={() => { if (confirm('Delete this credential?')) router.delete(`/admin/vault/credentials/${cred.id}`); }}
                  className="rounded p-1.5 text-slate-400 hover:text-red-400" title="Delete">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination links={credentials.links} />

      {/* Modal */}
      {modal && (
        <CredentialModal
          credential={modal === 'create' ? null : modal}
          categories={categories}
          onClose={() => setModal(null)}
        />
      )}
    </VaultLayout>
  );
}
```

### Step 3: Create Categories.jsx

```jsx
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import VaultLayout from '../layouts/VaultLayout';
import { Card, Button, Field, Input, Badge, EmptyState } from '../components/Ui';
import { FolderOpen, Plus, Pencil, Trash2, X } from 'lucide-react';

function CategoryForm({ category, onClose }) {
  const isEdit = !!category;
  const form = useForm({
    name: category?.name || '',
    icon: category?.icon || '',
    color: category?.color || '',
    sort_order: category?.sort_order || 0,
  });

  const submit = (e) => {
    e.preventDefault();
    if (isEdit) {
      form.put(`/admin/vault/categories/${category.id}`, { onSuccess: onClose });
    } else {
      form.post('/admin/vault/categories', { onSuccess: onClose });
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60" onClick={onClose} />
      <div className="relative w-full max-w-sm panel p-6">
        <h2 className="mb-4 text-lg font-semibold text-white">{isEdit ? 'Edit Category' : 'Add Category'}</h2>
        <form onSubmit={submit} className="space-y-4">
          <Field label="Name" error={form.errors.name}>
            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Social Media" />
          </Field>
          <Field label="Icon (optional)" error={form.errors.icon}>
            <Input value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="e.g. globe" />
          </Field>
          <Field label="Color (optional)" error={form.errors.color}>
            <Input value={form.data.color} onChange={(e) => form.setData('color', e.target.value)} placeholder="e.g. amber" />
          </Field>
          <Field label="Sort Order" error={form.errors.sort_order}>
            <Input type="number" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', +e.target.value)} />
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
            <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save'}</Button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function Categories({ categories }) {
  const [modal, setModal] = useState(null);

  return (
    <VaultLayout
      title="Categories"
      subtitle="Organize credentials by category"
      actions={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Category</Button>}
    >
      <Head title="Categories" />

      {categories.length === 0 ? (
        <EmptyState
          icon={FolderOpen}
          title="No categories yet"
          description="Create categories to organize your credentials."
          action={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Category</Button>}
        />
      ) : (
        <div className="space-y-2">
          {categories.map((cat) => (
            <div key={cat.id} className="panel flex items-center justify-between p-4">
              <div className="flex items-center gap-3">
                <FolderOpen className="h-5 w-5 text-amber-400" />
                <div>
                  <p className="text-sm font-medium text-white">{cat.name}</p>
                  <p className="text-xs text-slate-500">
                    {cat.credentials_count} credential{cat.credentials_count !== 1 ? 's' : ''}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                <button onClick={() => setModal(cat)} className="rounded p-1.5 text-slate-400 hover:text-slate-200">
                  <Pencil className="h-4 w-4" />
                </button>
                <button onClick={() => { if (confirm('Delete this category?')) router.delete(`/admin/vault/categories/${cat.id}`); }}
                  className="rounded p-1.5 text-slate-400 hover:text-red-400">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {modal && <CategoryForm category={modal === 'create' ? null : modal} onClose={() => setModal(null)} />}
    </VaultLayout>
  );
}
```

### Step 4: Create Tags.jsx

```jsx
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import VaultLayout from '../layouts/VaultLayout';
import { Button, Field, Input, Badge, EmptyState } from '../components/Ui';
import { Tag, Plus, Pencil, Trash2 } from 'lucide-react';

function TagForm({ tag, onClose }) {
  const isEdit = !!tag;
  const form = useForm({ name: tag?.name || '' });
  const submit = (e) => {
    e.preventDefault();
    if (isEdit) {
      form.put(`/admin/vault/tags/${tag.id}`, { onSuccess: onClose });
    } else {
      form.post('/admin/vault/tags', { onSuccess: onClose });
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60" onClick={onClose} />
      <div className="relative w-full max-w-sm panel p-6">
        <h2 className="mb-4 text-lg font-semibold text-white">{isEdit ? 'Edit Tag' : 'Add Tag'}</h2>
        <form onSubmit={submit} className="space-y-4">
          <Field label="Name" error={form.errors.name}>
            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. critical" />
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
            <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving...' : 'Save'}</Button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function Tags({ tags }) {
  const [modal, setModal] = useState(null);

  return (
    <VaultLayout
      title="Tags"
      subtitle="Label and filter credentials with tags"
      actions={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Tag</Button>}
    >
      <Head title="Tags" />

      {tags.length === 0 ? (
        <EmptyState
          icon={Tag}
          title="No tags yet"
          description="Create tags to label your credentials."
          action={<Button onClick={() => setModal('create')}><Plus className="h-4 w-4" /> Add Tag</Button>}
        />
      ) : (
        <div className="space-y-2">
          {tags.map((t) => (
            <div key={t.id} className="panel flex items-center justify-between p-4">
              <div className="flex items-center gap-3">
                <Tag className="h-4 w-4 text-amber-400" />
                <div>
                  <p className="text-sm font-medium text-white">{t.name}</p>
                  <p className="text-xs text-slate-500">
                    {t.credentials_count} credential{t.credentials_count !== 1 ? 's' : ''}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                <button onClick={() => setModal(t)} className="rounded p-1.5 text-slate-400 hover:text-slate-200">
                  <Pencil className="h-4 w-4" />
                </button>
                <button onClick={() => { if (confirm('Delete this tag?')) router.delete(`/admin/vault/tags/${t.id}`); }}
                  className="rounded p-1.5 text-slate-400 hover:text-red-400">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {modal && <TagForm tag={modal === 'create' ? null : modal} onClose={() => setModal(null)} />}
    </VaultLayout>
  );
}
```

### Step 5: Create AuditLog.jsx

```jsx
import { Head, router } from '@inertiajs/react';
import VaultLayout from '../layouts/VaultLayout';
import { Badge, EmptyState, Pagination, Select } from '../components/Ui';
import { ClipboardList } from 'lucide-react';

const ACTION_COLORS = {
  created: 'green',
  viewed: 'blue',
  updated: 'amber',
  deleted: 'red',
};

export default function AuditLog({ logs, filters }) {
  return (
    <VaultLayout
      title="Audit Log"
      subtitle="Track who accessed or modified credentials"
    >
      <Head title="Audit Log" />

      <div className="mb-4">
        <Select
          value={filters.action}
          onChange={(e) => router.get('/admin/vault/audit-log', { action: e.target.value }, { preserveState: true, replace: true })}
          className="w-40"
        >
          <option value="">All Actions</option>
          <option value="created">Created</option>
          <option value="viewed">Viewed</option>
          <option value="updated">Updated</option>
          <option value="deleted">Deleted</option>
        </Select>
      </div>

      {logs.data.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title="No audit entries"
          description="Activity will appear here as credentials are accessed."
        />
      ) : (
        <div className="space-y-2">
          {logs.data.map((log) => (
            <div key={log.id} className="panel flex items-center justify-between p-4">
              <div className="flex items-center gap-3">
                <Badge color={ACTION_COLORS[log.action] || 'slate'}>{log.action}</Badge>
                <div>
                  <p className="text-sm text-slate-300">
                    <span className="font-medium text-white">{log.user}</span>
                    {log.credential && (
                      <> — <span className="text-slate-400">{log.credential}</span></>
                    )}
                  </p>
                  {log.changes && (
                    <p className="text-xs text-slate-500">
                      Fields changed: {Object.keys(log.changes.old || {}).join(', ')}
                    </p>
                  )}
                </div>
              </div>
              <div className="text-right">
                <p className="text-xs text-slate-400">{log.time}</p>
                <p className="text-xs text-slate-600">{log.ip}</p>
              </div>
            </div>
          ))}
        </div>
      )}

      <Pagination links={logs.links} />
    </VaultLayout>
  );
}
```

### Step 6: Commit

```bash
git add resources/js/vault/pages/
git commit -m "feat(vault): add React pages — Overview, Credentials, Categories, Tags, AuditLog"
```

---

## Task 5: Admin Sidebar Link + NPM Dependencies + Build

**Files:**
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` — add vault link
- Check/install: `react-hot-toast`, `lucide-react` npm packages

### Step 1: Add sidebar link

Add to admin sidebar (near the Blog & SEO link):
```blade
<flux:navlist.item
    icon="shield-check"
    href="/admin/vault"
>
    {{ __('Password Vault') }}
</flux:navlist.item>
```

### Step 2: Verify npm dependencies

Check if `react-hot-toast` and `lucide-react` are already installed:
```bash
grep -E '"react-hot-toast"|"lucide-react"' package.json
```

If missing, install:
```bash
npm install react-hot-toast lucide-react
```

### Step 3: Build and verify

```bash
npm run build
```

### Step 4: Run full test suite

```bash
php artisan test --compact tests/Feature/Vault/
```

### Step 5: Run Pint

```bash
vendor/bin/pint --dirty
```

### Step 6: Commit

```bash
git add resources/views/components/layouts/app/sidebar.blade.php package.json package-lock.json
git commit -m "feat(vault): add admin sidebar link and verify build"
```

---

## Task 6: Seed Default Categories + Final Smoke Test

**Files:**
- Create: `database/seeders/VaultCategorySeeder.php`

### Step 1: Create seeder

```bash
php artisan make:seeder VaultCategorySeeder --no-interaction
```

```php
<?php

namespace Database\Seeders;

use App\Models\VaultCategory;
use Illuminate\Database\Seeder;

class VaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Social Media',     'slug' => 'social-media',     'icon' => 'globe',       'color' => 'blue',   'sort_order' => 1],
            ['name' => 'E-commerce',        'slug' => 'ecommerce',        'icon' => 'shopping-bag', 'color' => 'green',  'sort_order' => 2],
            ['name' => 'Hosting & Domain',  'slug' => 'hosting-domain',   'icon' => 'server',      'color' => 'purple', 'sort_order' => 3],
            ['name' => 'Email Accounts',    'slug' => 'email-accounts',   'icon' => 'mail',        'color' => 'red',    'sort_order' => 4],
            ['name' => 'API Keys',          'slug' => 'api-keys',         'icon' => 'key',         'color' => 'amber',  'sort_order' => 5],
            ['name' => 'Banking & Payment', 'slug' => 'banking-payment',  'icon' => 'credit-card', 'color' => 'emerald','sort_order' => 6],
            ['name' => 'Others',            'slug' => 'others',           'icon' => 'folder',      'color' => 'slate',  'sort_order' => 99],
        ];

        foreach ($categories as $cat) {
            VaultCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
```

### Step 2: Run seeder

```bash
php artisan db:seed --class=VaultCategorySeeder
```

### Step 3: Smoke test — visit /admin/vault in browser

Login as admin@example.com, navigate to `/admin/vault`. Verify:
- Dashboard loads with stats
- Credentials page shows empty state
- Can create a credential with password generator
- Can view/copy password
- Categories page shows seeded categories
- Audit log records actions

### Step 4: Commit

```bash
git add database/seeders/VaultCategorySeeder.php
git commit -m "feat(vault): add default category seeder"
```

---

## Summary

| Task | Description | Est. Steps |
|------|-------------|-----------|
| 1 | Database migrations + models + model tests | 6 |
| 2 | Controllers, routes, form requests, service, controller tests | 8 |
| 3 | Blade root view, React entry, layout, CSS, UI components | 8 |
| 4 | React pages (5 pages) | 6 |
| 5 | Admin sidebar link, npm deps, build | 6 |
| 6 | Default category seeder + smoke test | 4 |
