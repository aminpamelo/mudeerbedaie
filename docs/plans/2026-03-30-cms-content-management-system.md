# CMS Content Management System — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a React SPA Content Management System with content pipeline (Idea → Shooting → Editing → Posting) and ads pipeline, following the same architecture as the HR module.

**Architecture:** React SPA with Laravel API backend. Two pipelines — Content Pipeline (workflow stages with multi-assignee support) and Ads Pipeline (marked posts → campaigns). Reuses HR module patterns: Radix UI components, TanStack React Query, Axios API layer, Zustand store.

**Tech Stack:** Laravel 12, React 19, React Router 7, TanStack React Query 5, Zustand, Radix UI, Tailwind CSS v4, Recharts, Lucide React icons

**Design Doc:** `docs/plans/2026-03-30-cms-content-management-system-design.md`

---

## Phase 1: Foundation (Backend Models, Migrations, SPA Shell)

### Task 1: Create Database Migrations

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_contents_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_content_stages_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_content_stage_assignees_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_content_stats_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_ad_campaigns_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_ad_stats_table.php`

**Step 1: Generate migrations**

```bash
php artisan make:migration create_contents_table --no-interaction
php artisan make:migration create_content_stages_table --no-interaction
php artisan make:migration create_content_stage_assignees_table --no-interaction
php artisan make:migration create_content_stats_table --no-interaction
php artisan make:migration create_ad_campaigns_table --no-interaction
php artisan make:migration create_ad_stats_table --no-interaction
```

**Step 2: Write `contents` migration**

```php
Schema::create('contents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('stage', ['idea', 'shooting', 'editing', 'posting', 'posted'])->default('idea');
    $table->date('due_date')->nullable();
    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
    $table->string('tiktok_url')->nullable();
    $table->string('tiktok_post_id')->nullable();
    $table->boolean('is_flagged_for_ads')->default(false);
    $table->boolean('is_marked_for_ads')->default(false);
    $table->foreignId('marked_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->timestamp('marked_at')->nullable();
    $table->foreignId('created_by')->constrained('employees')->cascadeOnDelete();
    $table->timestamp('posted_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index('stage');
    $table->index('priority');
    $table->index('is_marked_for_ads');
});
```

**Step 3: Write `content_stages` migration**

```php
Schema::create('content_stages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained()->cascadeOnDelete();
    $table->enum('stage', ['idea', 'shooting', 'editing', 'posting']);
    $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
    $table->date('due_date')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['content_id', 'stage']);
});
```

**Step 4: Write `content_stage_assignees` migration**

```php
Schema::create('content_stage_assignees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_stage_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->string('role')->nullable();
    $table->timestamps();

    $table->unique(['content_stage_id', 'employee_id']);
});
```

**Step 5: Write `content_stats` migration**

```php
Schema::create('content_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained()->cascadeOnDelete();
    $table->bigInteger('views')->default(0);
    $table->bigInteger('likes')->default(0);
    $table->bigInteger('comments')->default(0);
    $table->bigInteger('shares')->default(0);
    $table->timestamp('fetched_at');
    $table->timestamps();

    $table->index(['content_id', 'fetched_at']);
});
```

**Step 6: Write `ad_campaigns` migration**

```php
Schema::create('ad_campaigns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained()->cascadeOnDelete();
    $table->enum('platform', ['facebook', 'tiktok'])->default('facebook');
    $table->string('ad_id')->nullable();
    $table->enum('status', ['pending', 'running', 'paused', 'completed'])->default('pending');
    $table->decimal('budget', 10, 2)->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('assigned_by')->constrained('employees')->cascadeOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

**Step 7: Write `ad_stats` migration**

```php
Schema::create('ad_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
    $table->bigInteger('impressions')->default(0);
    $table->bigInteger('clicks')->default(0);
    $table->decimal('spend', 10, 2)->default(0);
    $table->bigInteger('conversions')->default(0);
    $table->timestamp('fetched_at');
    $table->timestamps();
});
```

**Step 8: Run migrations**

```bash
php artisan migrate
```
Expected: All 6 tables created successfully.

**Step 9: Commit**

```bash
git add database/migrations/
git commit -m "feat(cms): add database migrations for content and ads pipeline"
```

---

### Task 2: Create Eloquent Models

**Files:**
- Create: `app/Models/Content.php`
- Create: `app/Models/ContentStage.php`
- Create: `app/Models/ContentStageAssignee.php`
- Create: `app/Models/ContentStat.php`
- Create: `app/Models/AdCampaign.php`
- Create: `app/Models/AdStat.php`

**Step 1: Generate models with factories**

```bash
php artisan make:model Content --factory --no-interaction
php artisan make:model ContentStage --factory --no-interaction
php artisan make:model ContentStageAssignee --no-interaction
php artisan make:model ContentStat --no-interaction
php artisan make:model AdCampaign --factory --no-interaction
php artisan make:model AdStat --no-interaction
```

**Step 2: Write `Content` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'stage',
        'due_date',
        'priority',
        'tiktok_url',
        'tiktok_post_id',
        'is_flagged_for_ads',
        'is_marked_for_ads',
        'marked_by',
        'marked_at',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'marked_at' => 'datetime',
            'is_flagged_for_ads' => 'boolean',
            'is_marked_for_ads' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function markedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'marked_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ContentStage::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(ContentStat::class);
    }

    public function latestStats(): HasMany
    {
        return $this->hasMany(ContentStat::class)->latest('fetched_at')->limit(1);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function getLatestStatsAttribute(): ?ContentStat
    {
        return $this->stats()->latest('fetched_at')->first();
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }

    public function getStageOrderAttribute(): int
    {
        return match ($this->stage) {
            'idea' => 1,
            'shooting' => 2,
            'editing' => 3,
            'posting' => 4,
            'posted' => 5,
            default => 0,
        };
    }
}
```

**Step 3: Write `ContentStage` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'stage',
        'status',
        'due_date',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ContentStageAssignee::class);
    }
}
```

**Step 4: Write `ContentStageAssignee` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentStageAssignee extends Model
{
    protected $fillable = [
        'content_stage_id',
        'employee_id',
        'role',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ContentStage::class, 'content_stage_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

**Step 5: Write `ContentStat` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentStat extends Model
{
    protected $fillable = [
        'content_id',
        'views',
        'likes',
        'comments',
        'shares',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function getEngagementRateAttribute(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->likes + $this->comments + $this->shares) / $this->views * 100, 2);
    }
}
```

**Step 6: Write `AdCampaign` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdCampaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'content_id',
        'platform',
        'ad_id',
        'status',
        'budget',
        'start_date',
        'end_date',
        'notes',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function assignedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdStat::class);
    }
}
```

**Step 7: Write `AdStat` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdStat extends Model
{
    protected $fillable = [
        'ad_campaign_id',
        'impressions',
        'clicks',
        'spend',
        'conversions',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'spend' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }

    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function getCtrAttribute(): float
    {
        if ($this->impressions === 0) {
            return 0;
        }

        return round($this->clicks / $this->impressions * 100, 2);
    }
}
```

**Step 8: Write factories for Content and AdCampaign**

Update `database/factories/ContentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'stage' => fake()->randomElement(['idea', 'shooting', 'editing', 'posting', 'posted']),
            'due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'created_by' => Employee::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'stage' => 'posted',
            'tiktok_url' => 'https://www.tiktok.com/@user/video/' . fake()->numerify('############'),
            'posted_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function markedForAds(): static
    {
        return $this->state(fn () => [
            'is_marked_for_ads' => true,
            'marked_at' => now(),
        ]);
    }
}
```

Update `database/factories/AdCampaignFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content_id' => Content::factory()->posted()->markedForAds(),
            'platform' => fake()->randomElement(['facebook', 'tiktok']),
            'status' => fake()->randomElement(['pending', 'running', 'paused', 'completed']),
            'budget' => fake()->randomFloat(2, 50, 5000),
            'start_date' => fake()->dateTimeBetween('now', '+7 days'),
            'end_date' => fake()->dateTimeBetween('+7 days', '+30 days'),
            'assigned_by' => Employee::factory(),
        ];
    }
}
```

**Step 9: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat(cms): add Eloquent models and factories for content and ads"
```

---

### Task 3: Create Laravel API Controllers & Routes

**Files:**
- Create: `app/Http/Controllers/Api/Cms/CmsDashboardController.php`
- Create: `app/Http/Controllers/Api/Cms/CmsContentController.php`
- Create: `app/Http/Controllers/Api/Cms/CmsContentStageController.php`
- Create: `app/Http/Controllers/Api/Cms/CmsAdCampaignController.php`
- Create: `app/Http/Requests/Cms/StoreContentRequest.php`
- Create: `app/Http/Requests/Cms/UpdateContentRequest.php`
- Create: `app/Http/Requests/Cms/StoreAdCampaignRequest.php`
- Modify: `routes/api.php` — add CMS route group

**Step 1: Create controllers**

```bash
php artisan make:controller Api/Cms/CmsDashboardController --no-interaction
php artisan make:controller Api/Cms/CmsContentController --api --no-interaction
php artisan make:controller Api/Cms/CmsContentStageController --no-interaction
php artisan make:controller Api/Cms/CmsAdCampaignController --api --no-interaction
```

**Step 2: Create form requests**

```bash
php artisan make:request Cms/StoreContentRequest --no-interaction
php artisan make:request Cms/UpdateContentRequest --no-interaction
php artisan make:request Cms/StoreAdCampaignRequest --no-interaction
```

**Step 3: Write `StoreContentRequest`**

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'stages' => ['nullable', 'array'],
            'stages.*.stage' => ['required', 'in:idea,shooting,editing,posting'],
            'stages.*.due_date' => ['nullable', 'date'],
            'stages.*.assignees' => ['nullable', 'array'],
            'stages.*.assignees.*.employee_id' => ['required', 'exists:employees,id'],
            'stages.*.assignees.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

**Step 4: Write `UpdateContentRequest`**

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'tiktok_url' => ['nullable', 'string', 'url', 'max:500'],
            'stages' => ['nullable', 'array'],
            'stages.*.stage' => ['required', 'in:idea,shooting,editing,posting'],
            'stages.*.due_date' => ['nullable', 'date'],
            'stages.*.assignees' => ['nullable', 'array'],
            'stages.*.assignees.*.employee_id' => ['required', 'exists:employees,id'],
            'stages.*.assignees.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

**Step 5: Write `StoreAdCampaignRequest`**

```php
<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_id' => ['required', 'exists:contents,id'],
            'platform' => ['required', 'in:facebook,tiktok'],
            'ad_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,running,paused,completed'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

**Step 6: Write `CmsDashboardController`**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentStat;
use Illuminate\Http\JsonResponse;

class CmsDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_contents' => Content::count(),
            'in_progress' => Content::whereNotIn('stage', ['posted'])->count(),
            'posted_this_month' => Content::where('stage', 'posted')
                ->whereMonth('posted_at', now()->month)
                ->whereYear('posted_at', now()->year)
                ->count(),
            'flagged_for_ads' => Content::where('is_flagged_for_ads', true)
                ->where('is_marked_for_ads', false)
                ->count(),
            'marked_for_ads' => Content::where('is_marked_for_ads', true)->count(),
            'by_stage' => [
                'idea' => Content::where('stage', 'idea')->count(),
                'shooting' => Content::where('stage', 'shooting')->count(),
                'editing' => Content::where('stage', 'editing')->count(),
                'posting' => Content::where('stage', 'posting')->count(),
                'posted' => Content::where('stage', 'posted')->count(),
            ],
        ]);
    }

    public function topPosts(): JsonResponse
    {
        $topPosts = Content::where('stage', 'posted')
            ->whereHas('stats')
            ->with(['creator:id,full_name', 'stats' => fn ($q) => $q->latest('fetched_at')->limit(1)])
            ->get()
            ->sortByDesc(fn ($content) => $content->stats->first()?->views ?? 0)
            ->take(10)
            ->values();

        return response()->json(['data' => $topPosts]);
    }
}
```

**Step 7: Write `CmsContentController`**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreContentRequest;
use App\Http\Requests\Cms\UpdateContentRequest;
use App\Models\Content;
use App\Models\ContentStage;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Content::query()
            ->with([
                'creator:id,full_name,profile_photo',
                'stages.assignees.employee:id,full_name,profile_photo',
            ]);

        if ($stage = $request->get('stage')) {
            $query->where('stage', $stage);
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($assignee = $request->get('assignee_id')) {
            $query->whereHas('stages.assignees', fn ($q) => $q->where('employee_id', $assignee));
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($from = $request->get('from_date')) {
            $query->where('due_date', '>=', $from);
        }

        if ($to = $request->get('to_date')) {
            $query->where('due_date', '<=', $to);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        $contents = $query->paginate($request->get('per_page', 15));

        return response()->json($contents);
    }

    public function store(StoreContentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

            $content = Content::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'priority' => $validated['priority'],
                'stage' => 'idea',
                'created_by' => $employee->id,
            ]);

            // Create stages with assignees
            $stageOrder = ['idea', 'shooting', 'editing', 'posting'];
            foreach ($stageOrder as $stageName) {
                $stageData = collect($validated['stages'] ?? [])
                    ->firstWhere('stage', $stageName);

                $stage = ContentStage::create([
                    'content_id' => $content->id,
                    'stage' => $stageName,
                    'status' => $stageName === 'idea' ? 'in_progress' : 'pending',
                    'due_date' => $stageData['due_date'] ?? null,
                    'started_at' => $stageName === 'idea' ? now() : null,
                ]);

                if (isset($stageData['assignees'])) {
                    foreach ($stageData['assignees'] as $assignee) {
                        $stage->assignees()->create([
                            'employee_id' => $assignee['employee_id'],
                            'role' => $assignee['role'] ?? null,
                        ]);
                    }
                }
            }

            $content->load([
                'creator:id,full_name',
                'stages.assignees.employee:id,full_name',
            ]);

            return response()->json(['data' => $content, 'message' => 'Content created successfully.'], 201);
        });
    }

    public function show(Content $content): JsonResponse
    {
        $content->load([
            'creator:id,full_name,profile_photo',
            'markedByEmployee:id,full_name',
            'stages.assignees.employee:id,full_name,profile_photo',
            'stats' => fn ($q) => $q->latest('fetched_at')->limit(10),
            'adCampaigns.assignedByEmployee:id,full_name',
        ]);

        return response()->json(['data' => $content]);
    }

    public function update(UpdateContentRequest $request, Content $content): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $content) {
            $content->update(collect($validated)->only([
                'title', 'description', 'due_date', 'priority', 'tiktok_url',
            ])->toArray());

            // Update stages and assignees if provided
            if (isset($validated['stages'])) {
                foreach ($validated['stages'] as $stageData) {
                    $stage = $content->stages()->where('stage', $stageData['stage'])->first();
                    if ($stage) {
                        $stage->update(['due_date' => $stageData['due_date'] ?? $stage->due_date]);

                        if (isset($stageData['assignees'])) {
                            $stage->assignees()->delete();
                            foreach ($stageData['assignees'] as $assignee) {
                                $stage->assignees()->create([
                                    'employee_id' => $assignee['employee_id'],
                                    'role' => $assignee['role'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $content->load([
                'creator:id,full_name',
                'stages.assignees.employee:id,full_name',
            ]);

            return response()->json(['data' => $content, 'message' => 'Content updated successfully.']);
        });
    }

    public function destroy(Content $content): JsonResponse
    {
        $content->delete();

        return response()->json(['message' => 'Content deleted successfully.']);
    }

    public function updateStage(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:idea,shooting,editing,posting,posted'],
        ]);

        $newStage = $validated['stage'];

        // Complete current stage
        $currentStage = $content->stages()->where('stage', $content->stage)->first();
        if ($currentStage && $currentStage->status !== 'completed') {
            $currentStage->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        // Start new stage
        $nextStage = $content->stages()->where('stage', $newStage)->first();
        if ($nextStage && $nextStage->status === 'pending') {
            $nextStage->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }

        $content->update([
            'stage' => $newStage,
            'posted_at' => $newStage === 'posted' ? now() : $content->posted_at,
        ]);

        $content->load(['stages.assignees.employee:id,full_name']);

        return response()->json(['data' => $content, 'message' => 'Stage updated successfully.']);
    }

    public function addStats(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'views' => ['required', 'integer', 'min:0'],
            'likes' => ['required', 'integer', 'min:0'],
            'comments' => ['required', 'integer', 'min:0'],
            'shares' => ['required', 'integer', 'min:0'],
        ]);

        $stat = $content->stats()->create([
            ...$validated,
            'fetched_at' => now(),
        ]);

        // Check auto-flag thresholds
        if (! $content->is_flagged_for_ads) {
            $shouldFlag = $validated['views'] > 10000
                || $validated['likes'] > 1000
                || ($validated['views'] > 0 && (($validated['likes'] + $validated['comments'] + $validated['shares']) / $validated['views'] * 100) > 5);

            if ($shouldFlag) {
                $content->update(['is_flagged_for_ads' => true]);
            }
        }

        return response()->json(['data' => $stat, 'message' => 'Stats recorded successfully.']);
    }

    public function markForAds(Request $request, Content $content): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $content->update([
            'is_marked_for_ads' => ! $content->is_marked_for_ads,
            'marked_by' => ! $content->is_marked_for_ads ? $employee->id : null,
            'marked_at' => ! $content->is_marked_for_ads ? now() : null,
        ]);

        return response()->json(['data' => $content, 'message' => 'Content marked for ads.']);
    }

    public function kanban(): JsonResponse
    {
        $stages = ['idea', 'shooting', 'editing', 'posting', 'posted'];
        $kanban = [];

        foreach ($stages as $stage) {
            $kanban[$stage] = Content::where('stage', $stage)
                ->with([
                    'creator:id,full_name,profile_photo',
                    'stages' => fn ($q) => $q->where('stage', $stage),
                    'stages.assignees.employee:id,full_name,profile_photo',
                ])
                ->orderBy('priority', 'desc')
                ->orderBy('due_date')
                ->get();
        }

        return response()->json($kanban);
    }

    public function calendar(Request $request): JsonResponse
    {
        $query = Content::query()
            ->with(['creator:id,full_name', 'stages.assignees.employee:id,full_name'])
            ->whereNotNull('due_date');

        if ($month = $request->get('month')) {
            $query->whereMonth('due_date', $month);
        }

        if ($year = $request->get('year')) {
            $query->whereYear('due_date', $year);
        }

        return response()->json(['data' => $query->orderBy('due_date')->get()]);
    }
}
```

**Step 8: Write `CmsContentStageController`**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentStageAssignee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsContentStageController extends Controller
{
    public function addAssignee(Request $request, Content $content, string $stage): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $contentStage = $content->stages()->where('stage', $stage)->firstOrFail();

        $assignee = $contentStage->assignees()->firstOrCreate(
            ['employee_id' => $validated['employee_id']],
            ['role' => $validated['role'] ?? null]
        );

        $assignee->load('employee:id,full_name,profile_photo');

        return response()->json(['data' => $assignee, 'message' => 'Assignee added.']);
    }

    public function removeAssignee(Content $content, string $stage, int $employeeId): JsonResponse
    {
        $contentStage = $content->stages()->where('stage', $stage)->firstOrFail();

        $contentStage->assignees()->where('employee_id', $employeeId)->delete();

        return response()->json(['message' => 'Assignee removed.']);
    }
}
```

**Step 9: Write `CmsAdCampaignController`**

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreAdCampaignRequest;
use App\Models\AdCampaign;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAdCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AdCampaign::query()
            ->with([
                'content:id,title,stage,tiktok_url',
                'assignedByEmployee:id,full_name',
                'stats' => fn ($q) => $q->latest('fetched_at')->limit(1),
            ]);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($platform = $request->get('platform')) {
            $query->where('platform', $platform);
        }

        $campaigns = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($campaigns);
    }

    public function store(StoreAdCampaignRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $campaign = AdCampaign::create([
            ...$validated,
            'assigned_by' => $employee->id,
        ]);

        $campaign->load(['content:id,title', 'assignedByEmployee:id,full_name']);

        return response()->json(['data' => $campaign, 'message' => 'Ad campaign created.'], 201);
    }

    public function show(AdCampaign $adCampaign): JsonResponse
    {
        $adCampaign->load([
            'content.creator:id,full_name',
            'content.stats' => fn ($q) => $q->latest('fetched_at')->limit(5),
            'assignedByEmployee:id,full_name',
            'stats' => fn ($q) => $q->latest('fetched_at'),
        ]);

        return response()->json(['data' => $adCampaign]);
    }

    public function update(Request $request, AdCampaign $adCampaign): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['sometimes', 'in:facebook,tiktok'],
            'ad_id' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,running,paused,completed'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $adCampaign->update($validated);

        return response()->json(['data' => $adCampaign, 'message' => 'Campaign updated.']);
    }

    public function addStats(Request $request, AdCampaign $adCampaign): JsonResponse
    {
        $validated = $request->validate([
            'impressions' => ['required', 'integer', 'min:0'],
            'clicks' => ['required', 'integer', 'min:0'],
            'spend' => ['required', 'numeric', 'min:0'],
            'conversions' => ['required', 'integer', 'min:0'],
        ]);

        $stat = $adCampaign->stats()->create([
            ...$validated,
            'fetched_at' => now(),
        ]);

        return response()->json(['data' => $stat, 'message' => 'Ad stats recorded.']);
    }
}
```

**Step 10: Add CMS routes to `routes/api.php`**

Add after the HR routes block:

```php
/*
|--------------------------------------------------------------------------
| CMS Module API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin,employee'])->prefix('cms')->group(function () {
    // Dashboard
    Route::get('dashboard/stats', [CmsDashboardController::class, 'stats'])->name('api.cms.dashboard.stats');
    Route::get('dashboard/top-posts', [CmsDashboardController::class, 'topPosts'])->name('api.cms.dashboard.top-posts');

    // Contents
    Route::get('contents/kanban', [CmsContentController::class, 'kanban'])->name('api.cms.contents.kanban');
    Route::get('contents/calendar', [CmsContentController::class, 'calendar'])->name('api.cms.contents.calendar');
    Route::apiResource('contents', CmsContentController::class)->names('api.cms.contents');
    Route::patch('contents/{content}/stage', [CmsContentController::class, 'updateStage'])->name('api.cms.contents.update-stage');
    Route::post('contents/{content}/stats', [CmsContentController::class, 'addStats'])->name('api.cms.contents.add-stats');
    Route::patch('contents/{content}/mark-for-ads', [CmsContentController::class, 'markForAds'])->name('api.cms.contents.mark-for-ads');

    // Content Stage Assignees
    Route::post('contents/{content}/stages/{stage}/assignees', [CmsContentStageController::class, 'addAssignee'])->name('api.cms.stages.add-assignee');
    Route::delete('contents/{content}/stages/{stage}/assignees/{employee}', [CmsContentStageController::class, 'removeAssignee'])->name('api.cms.stages.remove-assignee');

    // Ad Campaigns
    Route::apiResource('ads', CmsAdCampaignController::class)->names('api.cms.ads')->parameters(['ads' => 'adCampaign']);
    Route::post('ads/{adCampaign}/stats', [CmsAdCampaignController::class, 'addStats'])->name('api.cms.ads.add-stats');
});
```

Add the use statements at the top of `routes/api.php`:

```php
use App\Http\Controllers\Api\Cms\CmsDashboardController;
use App\Http\Controllers\Api\Cms\CmsContentController;
use App\Http\Controllers\Api\Cms\CmsContentStageController;
use App\Http\Controllers\Api\Cms\CmsAdCampaignController;
```

**Step 11: Verify routes**

```bash
php artisan route:list --path=api/cms
```

Expected: All CMS API routes listed.

**Step 12: Commit**

```bash
git add app/Http/Controllers/Api/Cms/ app/Http/Requests/Cms/ routes/api.php
git commit -m "feat(cms): add API controllers, form requests, and routes"
```

---

### Task 4: Create React SPA Shell

**Files:**
- Create: `resources/js/cms/main.jsx`
- Create: `resources/js/cms/App.jsx`
- Create: `resources/js/cms/styles/cms.css`
- Create: `resources/js/cms/lib/api.js`
- Create: `resources/js/cms/lib/utils.js`
- Create: `resources/js/cms/stores/useCmsStore.js`
- Create: `resources/js/cms/layouts/CmsLayout.jsx`
- Create: `resources/views/cms/index.blade.php`
- Modify: `vite.config.js` — add CMS entry points
- Modify: `routes/web.php` — add CMS shell routes

**Step 1: Create `resources/views/cms/index.blade.php`**

Follow the exact pattern from `resources/views/hr/index.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Content Management</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/cms/styles/cms.css', 'resources/js/cms/main.jsx'])
</head>
<body class="h-full bg-zinc-50 antialiased">
    <div id="cms-app" class="h-full"></div>

    @php
        $cmsUser = auth()->user()?->only(['id', 'name', 'email', 'role']);
    @endphp
    <script>
        window.cmsConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/cms') }}',
            appUrl: '{{ url('/') }}',
            dashboardUrl: '{{ url('/dashboard') }}',
            user: @json($cmsUser),
        };
    </script>
</body>
</html>
```

**Step 2: Add CMS routes to `routes/web.php`**

Add alongside the HR routes:

```php
Route::get('cms', fn () => view('cms.index'))->name('cms.dashboard');
Route::get('cms/{any}', fn () => view('cms.index'))->where('any', '.*')->name('cms.catchall');
```

**Step 3: Add CMS entry points to `vite.config.js`**

Add to the `input` array:

```javascript
'resources/js/cms/main.jsx',
'resources/js/cms/styles/cms.css',
```

**Step 4: Create `resources/js/cms/styles/cms.css`**

```css
@import "tailwindcss";
```

**Step 5: Create `resources/js/cms/lib/utils.js`**

```javascript
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
```

**Step 6: Create `resources/js/cms/stores/useCmsStore.js`**

```javascript
import { create } from 'zustand';

const useCmsStore = create((set) => ({
    sidebarOpen: false,
    toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
}));

export default useCmsStore;
```

**Step 7: Create `resources/js/cms/lib/api.js`**

```javascript
import axios from 'axios';

const config = window.cmsConfig || {};

const api = axios.create({
    baseURL: config.apiBaseUrl || '/api/cms',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
        if (error.response?.status === 419) {
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

// ========== Dashboard ==========
export const fetchDashboardStats = () => api.get('/dashboard/stats').then(r => r.data);
export const fetchTopPosts = () => api.get('/dashboard/top-posts').then(r => r.data);

// ========== Contents ==========
export const fetchContents = (params) => api.get('/contents', { params }).then(r => r.data);
export const fetchContent = (id) => api.get(`/contents/${id}`).then(r => r.data);
export const createContent = (data) => api.post('/contents', data).then(r => r.data);
export const updateContent = (id, data) => api.put(`/contents/${id}`, data).then(r => r.data);
export const deleteContent = (id) => api.delete(`/contents/${id}`).then(r => r.data);
export const updateContentStage = (id, data) => api.patch(`/contents/${id}/stage`, data).then(r => r.data);
export const addContentStats = (id, data) => api.post(`/contents/${id}/stats`, data).then(r => r.data);
export const markContentForAds = (id) => api.patch(`/contents/${id}/mark-for-ads`).then(r => r.data);
export const fetchKanban = () => api.get('/contents/kanban').then(r => r.data);
export const fetchCalendar = (params) => api.get('/contents/calendar', { params }).then(r => r.data);

// ========== Stage Assignees ==========
export const addStageAssignee = (contentId, stage, data) => api.post(`/contents/${contentId}/stages/${stage}/assignees`, data).then(r => r.data);
export const removeStageAssignee = (contentId, stage, employeeId) => api.delete(`/contents/${contentId}/stages/${stage}/assignees/${employeeId}`).then(r => r.data);

// ========== Ad Campaigns ==========
export const fetchAdCampaigns = (params) => api.get('/ads', { params }).then(r => r.data);
export const fetchAdCampaign = (id) => api.get(`/ads/${id}`).then(r => r.data);
export const createAdCampaign = (data) => api.post('/ads', data).then(r => r.data);
export const updateAdCampaign = (id, data) => api.put(`/ads/${id}`, data).then(r => r.data);
export const addAdStats = (id, data) => api.post(`/ads/${id}/stats`, data).then(r => r.data);

// ========== Employees (for assignee picker) ==========
export const fetchEmployees = (params) => api.get('/employees', { params }).then(r => r.data);
```

Note: The `fetchEmployees` endpoint will use the HR module's employee API. Update the baseURL for this one call, or add a CMS endpoint that proxies to it. Simpler approach — use a full URL:

Replace the last line with:

```javascript
export const fetchEmployees = (params) => axios.get('/api/hr/employees', {
    params,
    headers: {
        'X-CSRF-TOKEN': config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
}).then(r => r.data);
```

**Step 8: Create `resources/js/cms/layouts/CmsLayout.jsx`**

```jsx
import { useState, useEffect } from 'react';
import { Outlet, NavLink, Link, useLocation } from 'react-router-dom';
import {
    LayoutDashboard,
    FileText,
    Columns3,
    Calendar,
    Megaphone,
    Flag,
    Settings2,
    Menu,
    X,
    ArrowLeft,
    ChevronRight,
    ChevronDown,
} from 'lucide-react';
import useCmsStore from '../stores/useCmsStore';

const NAV_ITEMS = [
    {
        label: 'Dashboard',
        to: '/',
        icon: LayoutDashboard,
    },
    {
        label: 'Contents',
        icon: FileText,
        children: [
            { label: 'All Contents', to: '/contents' },
            { label: 'Kanban Board', to: '/kanban' },
            { label: 'Calendar', to: '/calendar' },
        ],
    },
    {
        label: 'Ads',
        icon: Megaphone,
        children: [
            { label: 'Marked Posts', to: '/ads/marked' },
            { label: 'Campaigns', to: '/ads' },
        ],
    },
];

function NavItem({ item, collapsed }) {
    const location = useLocation();
    const [expanded, setExpanded] = useState(false);

    const isActive = item.to
        ? location.pathname === item.to || location.pathname === item.to + '/'
        : item.children?.some((child) => location.pathname.startsWith(child.to));

    useEffect(() => {
        if (isActive && item.children) {
            setExpanded(true);
        }
    }, [isActive]);

    if (item.children) {
        return (
            <div>
                <button
                    onClick={() => setExpanded(!expanded)}
                    className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                        isActive
                            ? 'bg-zinc-100 text-zinc-900'
                            : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900'
                    }`}
                >
                    <item.icon className="h-5 w-5 shrink-0" />
                    {!collapsed && (
                        <>
                            <span className="flex-1 text-left">{item.label}</span>
                            {expanded ? (
                                <ChevronDown className="h-4 w-4" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )}
                        </>
                    )}
                </button>
                {expanded && !collapsed && (
                    <div className="ml-8 mt-1 space-y-1">
                        {item.children.map((child) => (
                            <NavLink
                                key={child.to}
                                to={child.to}
                                className={({ isActive }) =>
                                    `block rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                        isActive
                                            ? 'bg-zinc-100 font-medium text-zinc-900'
                                            : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900'
                                    }`
                                }
                            >
                                {child.label}
                            </NavLink>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <NavLink
            to={item.to}
            end
            className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                        ? 'bg-zinc-100 text-zinc-900'
                        : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900'
                }`
            }
        >
            <item.icon className="h-5 w-5 shrink-0" />
            {!collapsed && <span>{item.label}</span>}
        </NavLink>
    );
}

export default function CmsLayout() {
    const { sidebarOpen, toggleSidebar } = useCmsStore();
    const user = window.cmsConfig?.user;

    return (
        <div className="flex h-full">
            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-zinc-200 bg-white transition-transform lg:static lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Logo */}
                <div className="flex h-16 items-center justify-between border-b border-zinc-200 px-4">
                    <Link to="/" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white">
                            CMS
                        </div>
                        <span className="text-lg font-semibold text-zinc-900">CMS Module</span>
                    </Link>
                    <button onClick={toggleSidebar} className="lg:hidden">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 space-y-1 overflow-y-auto p-4">
                    {NAV_ITEMS.map((item) => (
                        <NavItem key={item.label} item={item} />
                    ))}
                </nav>

                {/* Back to Main App */}
                <div className="border-t border-zinc-200 p-4">
                    <a
                        href={window.cmsConfig?.dashboardUrl || '/dashboard'}
                        className="flex items-center gap-2 text-sm text-zinc-500 hover:text-zinc-900"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Main App
                    </a>
                    {user && (
                        <div className="mt-3 flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-600">
                                {user.name?.charAt(0)?.toUpperCase() || '?'}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-zinc-900">{user.name}</p>
                                <p className="truncate text-xs text-zinc-500">{user.role}</p>
                            </div>
                        </div>
                    )}
                </div>
            </aside>

            {/* Overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-black/50 lg:hidden" onClick={toggleSidebar} />
            )}

            {/* Main */}
            <main className="flex-1 overflow-y-auto">
                {/* Mobile header */}
                <div className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-zinc-200 bg-white px-4 lg:hidden">
                    <button onClick={toggleSidebar}>
                        <Menu className="h-5 w-5" />
                    </button>
                    <span className="font-semibold text-zinc-900">CMS Module</span>
                </div>

                <div className="p-6">
                    <Outlet />
                </div>
            </main>
        </div>
    );
}
```

**Step 9: Create `resources/js/cms/main.jsx`**

```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('cms-app');
    if (container) {
        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <App />
            </React.StrictMode>
        );
    }
});
```

**Step 10: Create `resources/js/cms/App.jsx`**

```jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import CmsLayout from './layouts/CmsLayout';
import Dashboard from './pages/Dashboard';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            retry: 1,
        },
    },
});

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <BrowserRouter basename="/cms">
                <Routes>
                    <Route element={<CmsLayout />}>
                        <Route index element={<Dashboard />} />
                        {/* Content pages - added in Phase 2 */}
                        {/* Ad pages - added in Phase 3 */}
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Route>
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}
```

**Step 11: Create placeholder `resources/js/cms/pages/Dashboard.jsx`**

```jsx
export default function Dashboard() {
    return (
        <div>
            <h1 className="text-2xl font-bold text-zinc-900">CMS Dashboard</h1>
            <p className="mt-2 text-zinc-500">Content Management System — coming soon.</p>
        </div>
    );
}
```

**Step 12: Copy shared UI components**

Copy the `resources/js/hr/components/ui/` directory to `resources/js/cms/components/ui/`. These are Radix UI + Tailwind components (button, card, input, dialog, badge, select, table, tabs, label, separator, tooltip, avatar, dropdown-menu, radio-group, checkbox, textarea).

```bash
cp -r resources/js/hr/components/ui resources/js/cms/components/ui
```

**Step 13: Build and verify**

```bash
npm run build
```

Expected: Build succeeds with CMS bundle included.

**Step 14: Verify the SPA loads**

Visit `https://mudeerbedaie.test/cms` in browser. Should show the CMS layout with sidebar and "CMS Dashboard" heading.

**Step 15: Commit**

```bash
git add resources/js/cms/ resources/views/cms/ routes/web.php vite.config.js
git commit -m "feat(cms): add React SPA shell with layout, routing, and API layer"
```

---

## Phase 2: Content Pipeline Pages

### Task 5: Dashboard Page

**Files:**
- Modify: `resources/js/cms/pages/Dashboard.jsx`

Build the full dashboard with:
- 4 stats cards (Total, In Progress, Posted This Month, Flagged for Ads) using `fetchDashboardStats`
- Mini kanban preview showing stage counts
- Top performing posts table using `fetchTopPosts`
- Use `useQuery` from TanStack React Query
- Reuse `Card`, `Badge` components from `components/ui/`

**Step 1: Implement Dashboard with stats cards and top posts table**

**Step 2: Build and verify visually**

**Step 3: Commit**

```bash
git add resources/js/cms/pages/Dashboard.jsx
git commit -m "feat(cms): implement dashboard with stats and top posts"
```

---

### Task 6: Content List Page

**Files:**
- Create: `resources/js/cms/pages/ContentList.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build a table view with:
- Search bar (title/description)
- Filters: stage, priority, assignee, date range
- Sortable columns: title, stage, priority, due_date, created_at
- Pagination
- Each row shows: title, stage badge, priority badge, assignee avatars, due date, actions (view/edit/delete)
- Use `fetchContents` with query params

**Step 1: Create ContentList component with filters and table**

**Step 2: Add route `<Route path="contents" element={<ContentList />} />` in App.jsx**

**Step 3: Build and verify**

**Step 4: Commit**

```bash
git add resources/js/cms/pages/ContentList.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add content list page with filters and pagination"
```

---

### Task 7: Content Create Page

**Files:**
- Create: `resources/js/cms/pages/ContentCreate.jsx`
- Create: `resources/js/cms/components/AssigneePicker.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build a form with:
- Title, description, priority, due date fields
- 4 stage sections (Idea, Shooting, Editing, Posting), each with:
  - Stage due date
  - Assignee picker (multi-select employees with optional role label)
- `AssigneePicker` component: search employees, add multiple, show avatars, remove button
- Submit creates content via `createContent`

**Step 1: Create AssigneePicker component**

**Step 2: Create ContentCreate page with form and stage assignment sections**

**Step 3: Add route and verify**

**Step 4: Commit**

```bash
git add resources/js/cms/pages/ContentCreate.jsx resources/js/cms/components/AssigneePicker.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add content create page with stage assignments"
```

---

### Task 8: Content Detail Page

**Files:**
- Create: `resources/js/cms/pages/ContentDetail.jsx`
- Create: `resources/js/cms/components/StageTimeline.jsx`
- Create: `resources/js/cms/components/StatsCard.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build the detail page with:
- Header: title, priority badge, creator, due date
- `StageTimeline`: horizontal progress showing stages with checkmarks/active/pending states
- Stage details: current stage's status, assignees with avatars, due date, notes
- TikTok stats section (if posted): views, likes, comments, shares + history chart using Recharts
- Actions: "Move to Next Stage", "Mark for Ads", "Add Stats" (manual)
- Description section
- Dialogs for: add stats, confirm stage move

**Step 1: Create StageTimeline component**

**Step 2: Create StatsCard component**

**Step 3: Create ContentDetail page**

**Step 4: Add route `<Route path="contents/:id" element={<ContentDetail />} />` and verify**

**Step 5: Commit**

```bash
git add resources/js/cms/pages/ContentDetail.jsx resources/js/cms/components/StageTimeline.jsx resources/js/cms/components/StatsCard.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add content detail page with stage timeline and stats"
```

---

### Task 9: Content Edit Page

**Files:**
- Create: `resources/js/cms/pages/ContentEdit.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Similar to ContentCreate but pre-populated with existing data. Uses `updateContent`.

**Step 1: Create ContentEdit with pre-filled form**

**Step 2: Add route and verify**

**Step 3: Commit**

```bash
git add resources/js/cms/pages/ContentEdit.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add content edit page"
```

---

### Task 10: Kanban Board Page

**Files:**
- Create: `resources/js/cms/pages/KanbanBoard.jsx`
- Create: `resources/js/cms/components/KanbanColumn.jsx`
- Create: `resources/js/cms/components/KanbanCard.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build a kanban board with:
- 5 columns: Idea, Shooting, Editing, Posting, Posted
- Each column shows count badge
- Cards show: title, priority color stripe, assignee avatars, due date
- Click card navigates to detail page
- Use `fetchKanban` API

**Step 1: Create KanbanCard component**

**Step 2: Create KanbanColumn component**

**Step 3: Create KanbanBoard page**

**Step 4: Add route `<Route path="kanban" element={<KanbanBoard />} />` and verify**

**Step 5: Commit**

```bash
git add resources/js/cms/pages/KanbanBoard.jsx resources/js/cms/components/KanbanColumn.jsx resources/js/cms/components/KanbanCard.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add kanban board view"
```

---

### Task 11: Content Calendar Page

**Files:**
- Create: `resources/js/cms/pages/ContentCalendar.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build a monthly calendar view:
- Month/year navigation (prev/next)
- Grid of days showing content items as colored dots/badges by priority
- Click a day to see contents due that day
- Click a content to navigate to detail
- Use `fetchCalendar` with month/year params

**Step 1: Create ContentCalendar page**

**Step 2: Add route and verify**

**Step 3: Commit**

```bash
git add resources/js/cms/pages/ContentCalendar.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add content calendar view"
```

---

## Phase 3: Ads Pipeline Pages

### Task 12: Ads List / Marked Posts Page

**Files:**
- Create: `resources/js/cms/pages/AdsList.jsx`
- Create: `resources/js/cms/pages/MarkedPosts.jsx`
- Modify: `resources/js/cms/App.jsx` — add routes

**MarkedPosts**: List of contents where `is_flagged_for_ads` or `is_marked_for_ads` is true. Shows TikTok stats, flag/mark status, button to create ad campaign.

**AdsList**: List of ad campaigns with filters (status, platform). Shows linked content, budget, status, latest stats.

**Step 1: Create MarkedPosts page**

**Step 2: Create AdsList page**

**Step 3: Add routes and verify**

**Step 4: Commit**

```bash
git add resources/js/cms/pages/AdsList.jsx resources/js/cms/pages/MarkedPosts.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add marked posts and ads list pages"
```

---

### Task 13: Ad Campaign Detail Page

**Files:**
- Create: `resources/js/cms/pages/AdCampaignDetail.jsx`
- Modify: `resources/js/cms/App.jsx` — add route

Build detail page with:
- Campaign info: platform, status, budget, dates
- Linked content card with TikTok stats
- Ad performance stats table/chart (impressions, clicks, CTR, spend, conversions)
- Add stats dialog (manual entry)
- Edit campaign dialog

**Step 1: Create AdCampaignDetail page**

**Step 2: Add route and verify**

**Step 3: Commit**

```bash
git add resources/js/cms/pages/AdCampaignDetail.jsx resources/js/cms/App.jsx
git commit -m "feat(cms): add ad campaign detail page"
```

---

## Phase 4: Testing & Polish

### Task 14: Write Feature Tests

**Files:**
- Create: `tests/Feature/Api/Cms/CmsContentTest.php`
- Create: `tests/Feature/Api/Cms/CmsAdCampaignTest.php`
- Create: `tests/Feature/Api/Cms/CmsDashboardTest.php`

**Step 1: Generate test files**

```bash
php artisan make:test Api/Cms/CmsContentTest --pest --no-interaction
php artisan make:test Api/Cms/CmsAdCampaignTest --pest --no-interaction
php artisan make:test Api/Cms/CmsDashboardTest --pest --no-interaction
```

**Step 2: Write content tests** — CRUD operations, stage transitions, stats, mark for ads, kanban, calendar

**Step 3: Write ad campaign tests** — CRUD, add stats

**Step 4: Write dashboard tests** — stats endpoint, top posts

**Step 5: Run tests**

```bash
php artisan test --compact --filter=Cms
```

**Step 6: Commit**

```bash
git add tests/Feature/Api/Cms/
git commit -m "test(cms): add feature tests for content and ads pipelines"
```

---

### Task 15: Add CMS Link to Main App Navigation

**Files:**
- Modify: Appropriate sidebar/navigation component in the main Livewire app

Add a "CMS" link in the main app sidebar (similar to how "HR Module" link exists) so users can navigate to `/cms`.

**Step 1: Find and update the main navigation component**

**Step 2: Verify link appears and navigates correctly**

**Step 3: Commit**

```bash
git commit -m "feat(cms): add CMS module link to main app navigation"
```

---

### Task 16: Run Pint & Final Build

**Step 1: Run Pint**

```bash
./vendor/bin/pint --dirty
```

**Step 2: Build frontend**

```bash
npm run build
```

**Step 3: Run full test suite**

```bash
php artisan test --compact
```

**Step 4: Final commit**

```bash
git commit -m "chore(cms): format code and verify build"
```

---

## Summary

| Phase | Tasks | What Gets Built |
|-------|-------|-----------------|
| Phase 1 | Tasks 1-4 | Database, Models, API, SPA Shell |
| Phase 2 | Tasks 5-11 | Dashboard, Content CRUD, Kanban, Calendar |
| Phase 3 | Tasks 12-13 | Marked Posts, Ads Campaigns |
| Phase 4 | Tasks 14-16 | Tests, Navigation, Polish |

**Total: 16 tasks across 4 phases**
