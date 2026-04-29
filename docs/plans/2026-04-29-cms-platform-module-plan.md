# CMS Platform Module Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a "Platform" module that auto-creates per-platform cross-post tracking rows when content is marked, mirrors the Ads module architecture, and ships future-proofed for v2 API automation.

**Architecture:** Two new tables (`cms_platforms` reference + `cms_content_platform_posts` per-content rows). One model observer hooks the existing `is_marked_for_ads` false→true transition to auto-create rows. New REST endpoints under `/api/cms`. New top-level Inertia React page tree under `/cms/platform/*` mirroring Ads.

**Tech Stack:** Laravel 12, Eloquent, Pest 4 (feature tests), React 19 + Inertia (existing `/cms` SPA), Tailwind v4, Flux UI, TanStack Query.

**Design doc:** [docs/plans/2026-04-29-cms-platform-module-design.md](2026-04-29-cms-platform-module-design.md)

---

## Conventions to follow

- **Observers:** use the PHP 8 `#[ObservedBy(...)]` attribute on the model (matches `app/Models/Enrollment.php` and `app/Models/ClassAttendance.php`).
- **Form Requests:** match style of `app/Http/Requests/Cms/StoreAdCampaignRequest.php` (string-array rules, `authorize() => true`).
- **Controllers:** follow style of existing controllers in `app/Http/Controllers/Api/Cms/`.
- **Migrations:** plain adds — no enum mutation. Works on MySQL + SQLite without driver branching.
- **Tests:** Pest, `RefreshDatabase` trait, `--compact` runner.
- **Linting:** run `vendor/bin/pint --dirty` before each commit.
- **Frontend client:** all API calls go through `resources/js/cms/lib/api.js` (do NOT inline fetch in pages).

---

## Task 1: Migration — `cms_platforms` reference table

**Files:**
- Create: `database/migrations/2026_04_29_000001_create_cms_platforms_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_cms_platforms_table --no-interaction`

(Then rename the timestamp to `2026_04_29_000001_` so seed order is stable.)

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_platforms');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: `cms_platforms` table created.

**Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_04_29_000001_create_cms_platforms_table.php
git commit -m "feat(cms): add cms_platforms reference table migration"
```

---

## Task 2: Seeder — seed enabled platforms

**Files:**
- Create: `database/seeders/CmsPlatformSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (call new seeder)

**Step 1: Generate seeder**

Run: `php artisan make:seeder CmsPlatformSeeder --no-interaction`

**Step 2: Write seeder**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CmsPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['key' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'sort_order' => 10],
            ['key' => 'facebook',  'name' => 'Facebook',  'icon' => 'facebook',  'sort_order' => 20],
            ['key' => 'youtube',   'name' => 'YouTube',   'icon' => 'youtube',   'sort_order' => 30],
            ['key' => 'threads',   'name' => 'Threads',   'icon' => 'at-sign',   'sort_order' => 40],
            ['key' => 'x',         'name' => 'X',         'icon' => 'twitter',   'sort_order' => 50],
        ];

        foreach ($platforms as $platform) {
            DB::table('cms_platforms')->updateOrInsert(
                ['key' => $platform['key']],
                array_merge($platform, [
                    'is_enabled' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
```

**Step 3: Wire into DatabaseSeeder**

Open `database/seeders/DatabaseSeeder.php` and add `$this->call(CmsPlatformSeeder::class);` to the `run()` method (alongside other seeder calls).

**Step 4: Run seeder**

Run: `php artisan db:seed --class=CmsPlatformSeeder --no-interaction`
Expected: 5 rows in `cms_platforms`.

Verify: `php artisan tinker --execute="echo DB::table('cms_platforms')->count();"`
Expected output: `5`

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/seeders/CmsPlatformSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(cms): seed cms platforms (ig, fb, yt, threads, x)"
```

---

## Task 3: Migration — `cms_content_platform_posts` table

**Files:**
- Create: `database/migrations/2026_04_29_000002_create_cms_content_platform_posts_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_cms_content_platform_posts_table --no-interaction`

(Rename timestamp to `2026_04_29_000002_`.)

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content_platform_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('cms_platforms')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|posted|skipped
            $table->string('post_url')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('caption_variant')->nullable();           // reserved for v2
            $table->string('external_post_id')->nullable();        // reserved for v2 API
            $table->string('sync_status')->nullable();             // reserved for v2 API
            $table->json('stats')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'platform_id']);
            $table->index(['status', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content_platform_posts');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: table created.

**Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_04_29_000002_create_cms_content_platform_posts_table.php
git commit -m "feat(cms): add cms_content_platform_posts table migration"
```

---

## Task 4: Model — `CmsPlatform`

**Files:**
- Create: `app/Models/CmsPlatform.php`
- Create: `database/factories/CmsPlatformFactory.php`

**Step 1: Generate model + factory**

Run: `php artisan make:model CmsPlatform --factory --no-interaction`

**Step 2: Write model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPlatform extends Model
{
    /** @use HasFactory<\Database\Factories\CmsPlatformFactory> */
    use HasFactory;

    protected $table = 'cms_platforms';

    protected $fillable = [
        'key',
        'name',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CmsContentPlatformPost::class, 'platform_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->orderBy('sort_order');
    }
}
```

**Step 3: Write factory**

Edit `database/factories/CmsPlatformFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CmsPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsPlatform>
 */
class CmsPlatformFactory extends Factory
{
    protected $model = CmsPlatform::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'icon' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_enabled' => true,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
```

**Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/CmsPlatform.php database/factories/CmsPlatformFactory.php
git commit -m "feat(cms): add CmsPlatform model + factory"
```

---

## Task 5: Model — `CmsContentPlatformPost`

**Files:**
- Create: `app/Models/CmsContentPlatformPost.php`
- Create: `database/factories/CmsContentPlatformPostFactory.php`
- Modify: `app/Models/Content.php` (add `platformPosts` relation)

**Step 1: Generate model + factory**

Run: `php artisan make:model CmsContentPlatformPost --factory --no-interaction`

**Step 2: Write model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContentPlatformPost extends Model
{
    /** @use HasFactory<\Database\Factories\CmsContentPlatformPostFactory> */
    use HasFactory;

    protected $table = 'cms_content_platform_posts';

    protected $fillable = [
        'content_id',
        'platform_id',
        'status',
        'post_url',
        'posted_at',
        'assignee_id',
        'caption_variant',
        'external_post_id',
        'sync_status',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'stats' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(CmsPlatform::class, 'platform_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }
}
```

**Step 3: Write factory**

Edit `database/factories/CmsContentPlatformPostFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsContentPlatformPost>
 */
class CmsContentPlatformPostFactory extends Factory
{
    protected $model = CmsContentPlatformPost::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'platform_id' => CmsPlatform::factory(),
            'status' => 'pending',
            'post_url' => null,
            'posted_at' => null,
            'assignee_id' => null,
            'stats' => null,
        ];
    }

    public function posted(): self
    {
        return $this->state(fn () => [
            'status' => 'posted',
            'post_url' => $this->faker->url(),
            'posted_at' => now(),
        ]);
    }

    public function skipped(): self
    {
        return $this->state(fn () => ['status' => 'skipped']);
    }
}
```

**Step 4: Add relation to Content model**

Modify [app/Models/Content.php](../../app/Models/Content.php) — after the existing `markedByEmployee()` method, add:

```php
public function platformPosts(): HasMany
{
    return $this->hasMany(CmsContentPlatformPost::class);
}
```

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/CmsContentPlatformPost.php app/Models/Content.php database/factories/CmsContentPlatformPostFactory.php
git commit -m "feat(cms): add CmsContentPlatformPost model + Content relation"
```

---

## Task 6: Service — `CreatePlatformPostsForContent`

**Files:**
- Create: `app/Services/Cms/CreatePlatformPostsForContent.php`
- Create: `tests/Feature/Cms/CreatePlatformPostsForContentTest.php`

**Step 1: Generate test**

Run: `php artisan make:test Cms/CreatePlatformPostsForContentTest --pest --no-interaction`

**Step 2: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use App\Services\Cms\CreatePlatformPostsForContent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
});

it('creates one row per enabled platform', function () {
    $content = Content::factory()->create();

    app(CreatePlatformPostsForContent::class)->handle($content);

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('is idempotent — running twice does not duplicate rows', function () {
    $content = Content::factory()->create();
    $service = app(CreatePlatformPostsForContent::class);

    $service->handle($content);
    $service->handle($content);

    expect(CmsContentPlatformPost::where('content_id', $content->id)->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('ignores disabled platforms', function () {
    CmsPlatform::query()->where('key', 'threads')->update(['is_enabled' => false]);
    $content = Content::factory()->create();

    app(CreatePlatformPostsForContent::class)->handle($content);

    $platformIds = CmsContentPlatformPost::where('content_id', $content->id)
        ->pluck('platform_id')->toArray();

    expect($platformIds)
        ->not->toContain(CmsPlatform::where('key', 'threads')->value('id'));
});
```

**Step 3: Run test, verify it fails**

Run: `php artisan test --compact --filter=CreatePlatformPostsForContentTest`
Expected: FAIL — `Class "App\Services\Cms\CreatePlatformPostsForContent" not found`.

**Step 4: Write service**

```php
<?php

namespace App\Services\Cms;

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;

class CreatePlatformPostsForContent
{
    public function handle(Content $content): void
    {
        CmsPlatform::enabled()->each(function (CmsPlatform $platform) use ($content): void {
            CmsContentPlatformPost::firstOrCreate(
                [
                    'content_id' => $content->id,
                    'platform_id' => $platform->id,
                ],
                [
                    'status' => 'pending',
                ]
            );
        });
    }
}
```

**Step 5: Run test, verify pass**

Run: `php artisan test --compact --filter=CreatePlatformPostsForContentTest`
Expected: PASS, 3 tests.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services/Cms/CreatePlatformPostsForContent.php tests/Feature/Cms/CreatePlatformPostsForContentTest.php
git commit -m "feat(cms): service to auto-create platform posts for marked content"
```

---

## Task 7: Observer — hook into `is_marked_for_ads` transition

**Files:**
- Create: `app/Observers/ContentObserver.php`
- Modify: `app/Models/Content.php` (add `#[ObservedBy(...)]` attribute)
- Create: `tests/Feature/Cms/ContentMarkAutoCreatesPlatformPostsTest.php`

**Step 1: Write test first**

Create `tests/Feature/Cms/ContentMarkAutoCreatesPlatformPostsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\CmsPlatform;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
});

it('auto-creates platform posts when content is marked', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    expect($content->platformPosts()->count())->toBe(0);

    $content->update(['is_marked_for_ads' => true, 'marked_at' => now()]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('does not create platform posts on unrelated updates', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);

    $content->update(['title' => 'New title']);

    expect($content->fresh()->platformPosts()->count())->toBe(0);
});

it('does not delete platform posts on unmark', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    $content->update(['is_marked_for_ads' => false]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});

it('is idempotent — re-marking does not duplicate', function () {
    $content = Content::factory()->create(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);
    $content->update(['is_marked_for_ads' => false]);
    $content->update(['is_marked_for_ads' => true]);

    expect($content->fresh()->platformPosts()->count())
        ->toBe(CmsPlatform::enabled()->count());
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=ContentMarkAutoCreatesPlatformPostsTest`
Expected: FAIL — first test, count is 0 instead of 5.

**Step 3: Write observer**

```php
<?php

namespace App\Observers;

use App\Models\Content;
use App\Services\Cms\CreatePlatformPostsForContent;

class ContentObserver
{
    public function __construct(
        protected CreatePlatformPostsForContent $createPlatformPosts,
    ) {}

    public function updated(Content $content): void
    {
        if (
            $content->wasChanged('is_marked_for_ads')
            && $content->is_marked_for_ads === true
        ) {
            $this->createPlatformPosts->handle($content);
        }
    }
}
```

**Step 4: Attach observer to model**

Modify [app/Models/Content.php](../../app/Models/Content.php):

- Add use statement: `use Illuminate\Database\Eloquent\Attributes\ObservedBy;`
- Add use statement: `use App\Observers\ContentObserver;`
- Add attribute above the class: `#[ObservedBy(ContentObserver::class)]`

**Step 5: Run test, verify pass**

Run: `php artisan test --compact --filter=ContentMarkAutoCreatesPlatformPostsTest`
Expected: PASS, 4 tests.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Observers/ContentObserver.php app/Models/Content.php tests/Feature/Cms/ContentMarkAutoCreatesPlatformPostsTest.php
git commit -m "feat(cms): observer auto-creates platform posts on mark transition"
```

---

## Task 8: API — `GET /api/cms/platforms`

**Files:**
- Create: `app/Http/Controllers/Api/Cms/CmsPlatformController.php`
- Modify: `routes/api.php` (add route inside the `cms` prefix group)
- Create: `tests/Feature/Cms/CmsPlatformControllerTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('returns enabled platforms ordered by sort_order', function () {
    $response = $this->getJson('/api/cms/platforms');

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.key', 'instagram');
});
```

> NOTE: If the project uses a different auth/role helper (check existing `tests/Feature/Cms/*` tests), match that exactly.

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsPlatformControllerTest`
Expected: FAIL — 404 (route not registered).

**Step 3: Write controller**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsPlatform;
use Illuminate\Http\JsonResponse;

class CmsPlatformController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CmsPlatform::enabled()->get(),
        ]);
    }
}
```

**Step 4: Register route**

In `routes/api.php`, inside the existing `Route::middleware(['auth:sanctum', 'role:admin,employee'])->prefix('cms')->group(function () { ... });` block (around line 1137), add:

```php
// CMS Platform routes
Route::get('platforms', [CmsPlatformController::class, 'index']);
```

Add the import at the top of the file: `use App\Http\Controllers\Api\Cms\CmsPlatformController;`

**Step 5: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsPlatformControllerTest`
Expected: PASS.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Api/Cms/CmsPlatformController.php routes/api.php tests/Feature/Cms/CmsPlatformControllerTest.php
git commit -m "feat(cms): GET /api/cms/platforms endpoint"
```

---

## Task 9: API — `GET /api/cms/platform-posts` (index with filters)

**Files:**
- Create: `app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Cms/CmsContentPlatformPostIndexTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\CmsPlatform;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('lists platform posts with default order', function () {
    CmsContentPlatformPost::factory()->count(3)->create();

    $response = $this->getJson('/api/cms/platform-posts');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters by status', function () {
    CmsContentPlatformPost::factory()->posted()->create();
    CmsContentPlatformPost::factory()->create(); // pending

    $response = $this->getJson('/api/cms/platform-posts?status=posted');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('filters by platform_id', function () {
    $instagram = CmsPlatform::where('key', 'instagram')->first();
    $youtube = CmsPlatform::where('key', 'youtube')->first();
    CmsContentPlatformPost::factory()->create(['platform_id' => $instagram->id]);
    CmsContentPlatformPost::factory()->create(['platform_id' => $youtube->id]);

    $response = $this->getJson("/api/cms/platform-posts?platform_id={$instagram->id}");

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('searches by content title', function () {
    $matchingContent = Content::factory()->create(['title' => 'Buku Solat Hook']);
    $otherContent = Content::factory()->create(['title' => 'Random Title']);
    CmsContentPlatformPost::factory()->create(['content_id' => $matchingContent->id]);
    CmsContentPlatformPost::factory()->create(['content_id' => $otherContent->id]);

    $response = $this->getJson('/api/cms/platform-posts?search=Solat');

    $response->assertOk()->assertJsonCount(1, 'data');
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsContentPlatformPostIndexTest`
Expected: FAIL.

**Step 3: Write controller**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsContentPlatformPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsContentPlatformPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CmsContentPlatformPost::query()
            ->with(['content:id,title,tiktok_url', 'platform', 'assignee:id,name'])
            ->latest('updated_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($platformId = $request->query('platform_id')) {
            $query->where('platform_id', $platformId);
        }

        if ($assigneeId = $request->query('assignee_id')) {
            $query->where('assignee_id', $assigneeId);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('content', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 25))->items(),
        ]);
    }
}
```

**Step 4: Register route**

In `routes/api.php` cms group, add: `Route::get('platform-posts', [CmsContentPlatformPostController::class, 'index']);`

Add import: `use App\Http\Controllers\Api\Cms\CmsContentPlatformPostController;`

**Step 5: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsContentPlatformPostIndexTest`
Expected: PASS.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php routes/api.php tests/Feature/Cms/CmsContentPlatformPostIndexTest.php
git commit -m "feat(cms): GET /api/cms/platform-posts with filters"
```

---

## Task 10: API — `GET /api/cms/platform-posts/{post}` (show)

**Files:**
- Modify: `app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Cms/CmsContentPlatformPostShowTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('returns a single platform post with relations', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->getJson("/api/cms/platform-posts/{$post->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonStructure(['data' => ['id', 'status', 'content', 'platform']]);
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsContentPlatformPostShowTest`
Expected: FAIL — 404.

**Step 3: Add `show` method to controller**

Append:

```php
public function show(CmsContentPlatformPost $platformPost): JsonResponse
{
    return response()->json([
        'data' => $platformPost->load(['content:id,title,tiktok_url', 'platform', 'assignee:id,name']),
    ]);
}
```

**Step 4: Register route**

```php
Route::get('platform-posts/{platformPost}', [CmsContentPlatformPostController::class, 'show']);
```

**Step 5: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsContentPlatformPostShowTest`
Expected: PASS.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php routes/api.php tests/Feature/Cms/CmsContentPlatformPostShowTest.php
git commit -m "feat(cms): GET /api/cms/platform-posts/{post} show endpoint"
```

---

## Task 11: API — `PATCH /api/cms/platform-posts/{post}` (update)

**Files:**
- Create: `app/Http/Requests/Cms/UpdatePlatformPostRequest.php`
- Modify: `app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Cms/CmsContentPlatformPostUpdateTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('updates status, post_url, posted_at, assignee_id', function () {
    $post = CmsContentPlatformPost::factory()->create();
    $employee = Employee::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'status' => 'posted',
        'post_url' => 'https://instagram.com/p/abc123',
        'posted_at' => '2026-04-30 12:00:00',
        'assignee_id' => $employee->id,
    ]);

    $response->assertOk();
    expect($post->fresh())
        ->status->toBe('posted')
        ->post_url->toBe('https://instagram.com/p/abc123')
        ->assignee_id->toBe($employee->id);
});

it('rejects invalid status', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'status' => 'banana',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

it('rejects malformed post_url', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}", [
        'post_url' => 'not a url',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['post_url']);
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsContentPlatformPostUpdateTest`
Expected: FAIL.

**Step 3: Write Form Request**

Create `app/Http/Requests/Cms/UpdatePlatformPostRequest.php`:

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:pending,posted,skipped'],
            'post_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'posted_at' => ['sometimes', 'nullable', 'date'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:employees,id'],
        ];
    }
}
```

**Step 4: Add `update` method to controller**

Add `use App\Http\Requests\Cms\UpdatePlatformPostRequest;` import, and:

```php
public function update(UpdatePlatformPostRequest $request, CmsContentPlatformPost $platformPost): JsonResponse
{
    $platformPost->update($request->validated());

    return response()->json([
        'data' => $platformPost->fresh()->load(['content:id,title', 'platform', 'assignee:id,name']),
    ]);
}
```

**Step 5: Register route**

```php
Route::patch('platform-posts/{platformPost}', [CmsContentPlatformPostController::class, 'update']);
```

**Step 6: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsContentPlatformPostUpdateTest`
Expected: PASS.

**Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Cms/UpdatePlatformPostRequest.php app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php routes/api.php tests/Feature/Cms/CmsContentPlatformPostUpdateTest.php
git commit -m "feat(cms): PATCH /api/cms/platform-posts/{post} update endpoint"
```

---

## Task 12: API — `PATCH /api/cms/platform-posts/{post}/stats`

**Files:**
- Create: `app/Http/Requests/Cms/UpdatePlatformPostStatsRequest.php`
- Modify: `app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Cms/CmsContentPlatformPostStatsTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('merges stats keys without overwriting other keys', function () {
    $post = CmsContentPlatformPost::factory()->create([
        'stats' => ['views' => 100, 'likes' => 10],
    ]);

    $this->patchJson("/api/cms/platform-posts/{$post->id}/stats", [
        'comments' => 5,
    ])->assertOk();

    expect($post->fresh()->stats)
        ->toMatchArray(['views' => 100, 'likes' => 10, 'comments' => 5]);
});

it('rejects negative numbers', function () {
    $post = CmsContentPlatformPost::factory()->create();

    $response = $this->patchJson("/api/cms/platform-posts/{$post->id}/stats", [
        'views' => -1,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['views']);
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsContentPlatformPostStatsTest`
Expected: FAIL.

**Step 3: Write Form Request**

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformPostStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'views' => ['sometimes', 'integer', 'min:0'],
            'likes' => ['sometimes', 'integer', 'min:0'],
            'comments' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
```

**Step 4: Add `updateStats` method**

```php
public function updateStats(UpdatePlatformPostStatsRequest $request, CmsContentPlatformPost $platformPost): JsonResponse
{
    $existing = $platformPost->stats ?? [];
    $merged = array_merge($existing, $request->validated(), ['last_synced_at' => now()->toIso8601String()]);

    $platformPost->update(['stats' => $merged]);

    return response()->json([
        'data' => $platformPost->fresh(),
    ]);
}
```

Add import: `use App\Http\Requests\Cms\UpdatePlatformPostStatsRequest;`

**Step 5: Register route**

```php
Route::patch('platform-posts/{platformPost}/stats', [CmsContentPlatformPostController::class, 'updateStats']);
```

**Step 6: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsContentPlatformPostStatsTest`
Expected: PASS.

**Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Cms/UpdatePlatformPostStatsRequest.php app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php routes/api.php tests/Feature/Cms/CmsContentPlatformPostStatsTest.php
git commit -m "feat(cms): PATCH /api/cms/platform-posts/{post}/stats endpoint"
```

---

## Task 13: API — `POST /api/cms/platform-posts/bulk-assign`

**Files:**
- Create: `app/Http/Requests/Cms/BulkAssignPlatformPostsRequest.php`
- Modify: `app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Cms/CmsContentPlatformPostBulkAssignTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

use App\Models\CmsContentPlatformPost;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\CmsPlatformSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    Sanctum::actingAs($this->user);
});

it('reassigns multiple posts to a single employee', function () {
    $employee = Employee::factory()->create();
    $posts = CmsContentPlatformPost::factory()->count(3)->create();

    $response = $this->postJson('/api/cms/platform-posts/bulk-assign', [
        'post_ids' => $posts->pluck('id')->toArray(),
        'assignee_id' => $employee->id,
    ]);

    $response->assertOk();
    expect(CmsContentPlatformPost::whereIn('id', $posts->pluck('id'))
        ->where('assignee_id', $employee->id)->count())->toBe(3);
});

it('allows clearing assignee with null', function () {
    $employee = Employee::factory()->create();
    $posts = CmsContentPlatformPost::factory()->count(2)
        ->create(['assignee_id' => $employee->id]);

    $this->postJson('/api/cms/platform-posts/bulk-assign', [
        'post_ids' => $posts->pluck('id')->toArray(),
        'assignee_id' => null,
    ])->assertOk();

    expect(CmsContentPlatformPost::whereIn('id', $posts->pluck('id'))
        ->whereNull('assignee_id')->count())->toBe(2);
});
```

**Step 2: Run test, verify it fails**

Run: `php artisan test --compact --filter=CmsContentPlatformPostBulkAssignTest`
Expected: FAIL.

**Step 3: Write Form Request**

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignPlatformPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_ids' => ['required', 'array', 'min:1'],
            'post_ids.*' => ['integer', 'exists:cms_content_platform_posts,id'],
            'assignee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
```

**Step 4: Add `bulkAssign` method**

```php
public function bulkAssign(BulkAssignPlatformPostsRequest $request): JsonResponse
{
    $validated = $request->validated();

    CmsContentPlatformPost::whereIn('id', $validated['post_ids'])
        ->update(['assignee_id' => $validated['assignee_id'] ?? null]);

    return response()->json(['updated' => count($validated['post_ids'])]);
}
```

Add import: `use App\Http\Requests\Cms\BulkAssignPlatformPostsRequest;`

**Step 5: Register route**

```php
Route::post('platform-posts/bulk-assign', [CmsContentPlatformPostController::class, 'bulkAssign']);
```

> ORDER MATTERS: Register `bulk-assign` BEFORE the `{platformPost}` show/update routes if the show route uses a wildcard, otherwise Laravel will try to resolve "bulk-assign" as a model. Use route ordering or constrain with `->whereNumber('platformPost')`.

Apply this constraint to existing show/update/stats routes:
```php
Route::get('platform-posts/{platformPost}', [...])->whereNumber('platformPost');
Route::patch('platform-posts/{platformPost}', [...])->whereNumber('platformPost');
Route::patch('platform-posts/{platformPost}/stats', [...])->whereNumber('platformPost');
```

**Step 6: Run test, verify pass**

Run: `php artisan test --compact --filter=CmsContentPlatformPostBulkAssignTest`
Expected: PASS.

Then run all CMS feature tests to verify route ordering didn't break anything:
Run: `php artisan test --compact tests/Feature/Cms/`
Expected: ALL PASS.

**Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Cms/BulkAssignPlatformPostsRequest.php app/Http/Controllers/Api/Cms/CmsContentPlatformPostController.php routes/api.php tests/Feature/Cms/CmsContentPlatformPostBulkAssignTest.php
git commit -m "feat(cms): POST /api/cms/platform-posts/bulk-assign endpoint"
```

---

## Task 14: Frontend — API client methods

**Files:**
- Modify: `resources/js/cms/lib/api.js`

**Step 1: Add API methods**

Open [resources/js/cms/lib/api.js](../../resources/js/cms/lib/api.js). Find an existing exported function such as `markContentForAds` to understand the axios instance pattern used. Then append (matching the same style):

```js
export async function fetchPlatforms() {
    const { data } = await api.get('/platforms');
    return data.data;
}

export async function fetchPlatformPosts(params = {}) {
    const { data } = await api.get('/platform-posts', { params });
    return data.data;
}

export async function fetchPlatformPost(id) {
    const { data } = await api.get(`/platform-posts/${id}`);
    return data.data;
}

export async function updatePlatformPost(id, payload) {
    const { data } = await api.patch(`/platform-posts/${id}`, payload);
    return data.data;
}

export async function updatePlatformPostStats(id, payload) {
    const { data } = await api.patch(`/platform-posts/${id}/stats`, payload);
    return data.data;
}

export async function bulkAssignPlatformPosts(postIds, assigneeId) {
    const { data } = await api.post('/platform-posts/bulk-assign', {
        post_ids: postIds,
        assignee_id: assigneeId,
    });
    return data;
}
```

**Step 2: Verify build**

Run: `npm run build`
Expected: build succeeds, no syntax errors.

**Step 3: Commit**

```bash
git add resources/js/cms/lib/api.js
git commit -m "feat(cms): add platform-posts API client methods"
```

---

## Task 15: Frontend — Sidebar nav + routing

**Files:**
- Modify: `resources/js/cms/App.jsx` (sidebar + routes)
- Modify: `resources/js/cms/layouts/*` (if sidebar lives in a layout component — check first)

**Step 1: Locate sidebar code**

Run: `grep -rn "Marked Posts\|Campaigns" resources/js/cms/`
Find the file that renders the existing **Ads** sub-nav. The new **Platform** group should be added immediately AFTER Ads.

**Step 2: Add new sidebar group**

Mirror the existing Ads pattern:

```jsx
{
    label: 'Platform',
    icon: Share2,            // import from lucide-react
    children: [
        { to: '/platform/queue',   label: 'Cross-Post Queue',   icon: ListChecks },
        { to: '/platform/history', label: 'Posted History',     icon: CheckCheck },
    ],
},
```

(Adapt object shape exactly to match the existing Ads sub-nav definition.)

**Step 3: Register routes**

In `App.jsx`, alongside existing routes:

```jsx
import PlatformQueue from './pages/PlatformQueue';
import PlatformHistory from './pages/PlatformHistory';

// inside <Routes>:
<Route path="/platform/queue" element={<PlatformQueue />} />
<Route path="/platform/history" element={<PlatformHistory />} />
```

**Step 4: Stub the page components**

Create both files with a placeholder so the build passes:

```jsx
// resources/js/cms/pages/PlatformQueue.jsx
export default function PlatformQueue() {
    return <div className="p-6">Cross-Post Queue (coming next)</div>;
}
```

```jsx
// resources/js/cms/pages/PlatformHistory.jsx
export default function PlatformHistory() {
    return <div className="p-6">Posted History (coming next)</div>;
}
```

**Step 5: Verify build + visit pages in browser**

Run: `npm run build`
Visit `/cms/platform/queue` and `/cms/platform/history` — confirm new sidebar entry shows and clicking each page renders the placeholder.

**Step 6: Commit**

```bash
git add resources/js/cms/App.jsx resources/js/cms/pages/PlatformQueue.jsx resources/js/cms/pages/PlatformHistory.jsx
git commit -m "feat(cms): add Platform sidebar group + page stubs"
```

---

## Task 16: Frontend — `PlatformQueue.jsx`

**Files:**
- Modify: `resources/js/cms/pages/PlatformQueue.jsx`

**Step 1: Build the queue page**

Mirror the structure of [resources/js/cms/pages/MarkedPosts.jsx](../../resources/js/cms/pages/MarkedPosts.jsx). Key elements:

- Page header with title "Cross-Post Queue"
- Filter row: platform select, status select, assignee select, search input
- Table columns: Content (link to ContentDetail), Platform (badge), Status, Assignee, Posted At, URL, Actions (edit modal)
- Edit modal that lets the user update status, post_url, posted_at, assignee_id via `updatePlatformPost`
- Use TanStack Query (`useQuery`, `useMutation`) — match how `MarkedPosts.jsx` does it
- Use Flux UI components consistently with sibling pages (badges, buttons, modals)

> Reference [resources/js/cms/pages/MarkedPosts.jsx](../../resources/js/cms/pages/MarkedPosts.jsx) verbatim for table + dialog patterns. The shape should be near-identical.

**Step 2: Verify in browser**

- Run: `composer run dev` (or ensure `npm run dev` is running)
- Mark a content (use existing UI on a TikTok-posted content)
- Visit `/cms/platform/queue` — verify 5 pending rows appear (one per seeded platform)
- Click a row → modal opens → edit URL + status → save → row updates
- Apply filters → rows narrow down

**Step 3: Commit**

```bash
git add resources/js/cms/pages/PlatformQueue.jsx
git commit -m "feat(cms): build Cross-Post Queue page with edit modal"
```

---

## Task 17: Frontend — `PlatformHistory.jsx`

**Files:**
- Modify: `resources/js/cms/pages/PlatformHistory.jsx`

**Step 1: Build history page**

Same shape as Queue but call `fetchPlatformPosts({ status: 'posted' })` and sort by `posted_at desc`. No edit modal (read-only history). Each row shows the post URL as an external link.

**Step 2: Verify**

Visit `/cms/platform/history` — confirm only Posted rows show, sorted newest first.

**Step 3: Commit**

```bash
git add resources/js/cms/pages/PlatformHistory.jsx
git commit -m "feat(cms): build Posted History page (read-only)"
```

---

## Task 18: Frontend — Cross-Platform Posts card on ContentDetail

**Files:**
- Modify: `resources/js/cms/pages/ContentDetail.jsx`

**Step 1: Locate insertion point**

Open [resources/js/cms/pages/ContentDetail.jsx](../../resources/js/cms/pages/ContentDetail.jsx). Find the `TikTok Stats` card (search "TikTok Stats" or similar). The new card goes immediately after it.

**Step 2: Build the card**

Render `content.platform_posts` (eager-load this on the existing content show endpoint — you may need to add `with('platformPosts.platform','platformPosts.assignee')` in `CmsContentController::show`).

For each row:
- Platform name + icon
- Status badge (Pending / Posted / Skipped)
- Inline-editable URL field
- Inline-editable status select
- Stats summary (views • likes • comments) with "Edit Stats" → opens stats modal calling `updatePlatformPostStats`

Match the visual language of the existing **Stage Details** card in the same file.

**Step 3: If `CmsContentController::show` doesn't already eager-load platformPosts**

Add a small backend tweak: eager-load `platformPosts.platform`, `platformPosts.assignee` and write a quick test in `tests/Feature/Cms/CmsContentShowTest.php` (or extend an existing show test) confirming `platform_posts` is in the JSON.

**Step 4: Verify in browser**

Open a marked content's detail page → confirm the new card renders with one row per platform → editing URL/status persists → stats modal merges into stats JSON.

**Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add resources/js/cms/pages/ContentDetail.jsx app/Http/Controllers/Api/Cms/CmsContentController.php tests/Feature/Cms/
git commit -m "feat(cms): Cross-Platform Posts card on Content detail page"
```

---

## Task 19: Final verification

**Step 1: Run full CMS test suite**

Run: `php artisan test --compact tests/Feature/Cms/`
Expected: all green.

**Step 2: Run pint on the whole working tree**

Run: `vendor/bin/pint --dirty`
Expected: no violations.

**Step 3: Build frontend for production**

Run: `npm run build`
Expected: clean build.

**Step 4: Manual smoke test (browser)**

Walk the full flow:
1. Mark an unmarked content → confirm it appears in `/cms/platform/queue`.
2. Edit one row → set Posted, paste URL, set posted_at → save.
3. Open content detail → confirm Cross-Platform Posts card reflects the change.
4. Add stats to one row → confirm they persist.
5. Visit `/cms/platform/history` → confirm the posted row appears there.
6. Unmark the content → confirm queue rows are NOT deleted.

**Step 5: Ask user if they want to run the entire test suite**

Per CLAUDE.md convention: ask "Run full `php artisan test --compact`?" before doing it.

**Step 6: Commit any final fixes, push branch**

If on a feature branch, push and offer PR. If on main (per the user's preference for small UI fixes), the commits are already in place.

---

## Risk register

| Risk | Mitigation |
| ---- | ---------- |
| Route ordering — `bulk-assign` confused with `{platformPost}` show | Apply `->whereNumber('platformPost')` constraint (Task 13) |
| Sanctum/role helper differs from this plan | Match exact pattern from existing `tests/Feature/Cms/*` tests |
| Existing Ads sidebar shape differs from assumed | Match exact prop shape (Task 15 step 2) |
| `markedByEmployee` race when observer fires | Observer runs in `updated()` after the save commits — stable |
| Auto-creation triggers in seeders / factory tests | `CreatePlatformPostsForContent` is idempotent; observer only fires on `is_marked_for_ads` true→false transition (false→true) |

---

**End of plan.**
