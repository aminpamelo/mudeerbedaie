# Live Host Recruitment Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a recruitment module inside the Live Host Desk that accepts public applications to multi-stage campaigns and ends in auto-creating a `User` with the `live_host` role.

**Architecture:** Parallel tables under `/livehost/*` (Inertia + React). Four new tables (campaigns, stages, applicants, stage history). Public form is a plain Blade page (unauthenticated); admin UI is Inertia matching existing Host pages at [resources/js/livehost/pages/hosts/](resources/js/livehost/pages/hosts/). Hire action creates a `User` (role string column) and deep-links to the existing manual host-profile creation screen.

**Tech Stack:** Laravel 12, Livewire/Inertia (existing dual setup), React 19, Flux UI (admin kept consistent w/ blade admin where applicable; Live Host Desk uses plain Tailwind + the existing `components/ui/*` primitives), Pest 4 (browser + feature), MySQL + SQLite compat per `CLAUDE.md`.

**Design doc:** [docs/plans/2026-04-24-live-host-recruitment-design.md](2026-04-24-live-host-recruitment-design.md)

---

## Conventions for this plan

- **Role assignment:** The `User` model uses a single `role` string column (confirmed at [app/Models/User.php:104](app/Models/User.php#L104)). Assign via `$user->role = 'live_host'`.
- **Inertia page pattern:** Mirror [resources/js/livehost/pages/hosts/Index.jsx](resources/js/livehost/pages/hosts/Index.jsx) etc. Each feature lives under `resources/js/livehost/pages/<feature>/`.
- **Migrations:** Enum columns via `string` + in-app validation (NOT `$table->enum(...)`) so MySQL + SQLite behave identically. No `$table->change()` on enums, no `renameColumn` on enums.
- **Tests:** Pest feature tests at `tests/Feature/LiveHost/Recruitment/`, browser tests at `tests/Browser/LiveHost/Recruitment/`. Run with `php artisan test --compact --filter=<name>`.
- **Factories:** Use `php artisan make:factory` — follow existing factory conventions from [database/factories/](database/factories/).
- **Formatting:** Run `vendor/bin/pint --dirty` before each commit.
- **DO NOT** run `php artisan migrate:fresh` — only `php artisan migrate`.

---

# Milestone 1 — Database foundation

Create the 4 tables, models, and factories. Nothing user-visible yet.

## Task 1.1 — Migration: `live_host_recruitment_campaigns`

**Files:**
- Create: `database/migrations/2026_04_24_100000_create_live_host_recruitment_campaigns_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_live_host_recruitment_campaigns_table --no-interaction`

(Adjust generated timestamp prefix as needed; keep within this plan's ordering.)

**Step 2: Write the schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('status')->default('draft'); // draft|open|paused|closed
            $table->unsignedInteger('target_count')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['status', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_recruitment_campaigns');
    }
};
```

**Step 3: Run**

Run: `php artisan migrate`
Expected: `Migration table created successfully.` (if first time) and `INFO  Migrating: ... DONE`.

**Step 4: Commit**

```bash
git add database/migrations/2026_04_24_100000_create_live_host_recruitment_campaigns_table.php
git commit -m "feat(livehost): add live_host_recruitment_campaigns table"
```

---

## Task 1.2 — Migration: `live_host_recruitment_stages`

**Files:**
- Create: `database/migrations/2026_04_24_100001_create_live_host_recruitment_stages_table.php`

```php
Schema::create('live_host_recruitment_stages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')
        ->constrained('live_host_recruitment_campaigns')
        ->cascadeOnDelete();
    $table->unsignedInteger('position');
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('is_final')->default(false);
    $table->timestamps();

    $table->index(['campaign_id', 'position']);
});
```

Run migrate + commit:

```bash
git add database/migrations/2026_04_24_100001_create_live_host_recruitment_stages_table.php
git commit -m "feat(livehost): add live_host_recruitment_stages table"
```

---

## Task 1.3 — Migration: `live_host_applicants`

**Files:**
- Create: `database/migrations/2026_04_24_100002_create_live_host_applicants_table.php`

```php
Schema::create('live_host_applicants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')
        ->constrained('live_host_recruitment_campaigns')
        ->cascadeOnDelete();
    $table->string('applicant_number')->unique();
    $table->string('full_name');
    $table->string('email');
    $table->string('phone');
    $table->string('ic_number')->nullable();
    $table->string('location')->nullable();
    $table->json('platforms');
    $table->text('experience_summary')->nullable();
    $table->text('motivation')->nullable();
    $table->string('resume_path')->nullable();
    $table->string('source')->nullable();
    $table->foreignId('current_stage_id')
        ->nullable()
        ->constrained('live_host_recruitment_stages')
        ->nullOnDelete();
    $table->string('status')->default('active'); // active|rejected|hired|withdrawn
    $table->unsignedTinyInteger('rating')->nullable();
    $table->text('notes')->nullable();
    $table->timestamp('applied_at');
    $table->timestamp('hired_at')->nullable();
    $table->foreignId('hired_user_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->timestamps();

    $table->unique(['campaign_id', 'email']);
    $table->index(['campaign_id', 'status']);
    $table->index(['current_stage_id', 'status']);
});
```

Commit: `feat(livehost): add live_host_applicants table`

---

## Task 1.4 — Migration: `live_host_applicant_stage_history`

**Files:**
- Create: `database/migrations/2026_04_24_100003_create_live_host_applicant_stage_history_table.php`

```php
Schema::create('live_host_applicant_stage_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('applicant_id')
        ->constrained('live_host_applicants')
        ->cascadeOnDelete();
    $table->foreignId('from_stage_id')
        ->nullable()
        ->constrained('live_host_recruitment_stages')
        ->nullOnDelete();
    $table->foreignId('to_stage_id')
        ->nullable()
        ->constrained('live_host_recruitment_stages')
        ->nullOnDelete();
    $table->string('action'); // applied|advanced|reverted|rejected|hired|note
    $table->text('notes')->nullable();
    $table->foreignId('changed_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->timestamps();

    $table->index(['applicant_id', 'created_at']);
});
```

Commit: `feat(livehost): add live_host_applicant_stage_history table`

---

## Task 1.5 — Models with relationships

**Files:**
- Create: `app/Models/LiveHostRecruitmentCampaign.php`
- Create: `app/Models/LiveHostRecruitmentStage.php`
- Create: `app/Models/LiveHostApplicant.php`
- Create: `app/Models/LiveHostApplicantStageHistory.php`

Use `php artisan make:model <Name> -f` for each (creates factory stubs).

**LiveHostRecruitmentCampaign:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostRecruitmentCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'target_count',
        'opens_at', 'closes_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'target_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LiveHostRecruitmentStage::class, 'campaign_id')->orderBy('position');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(LiveHostApplicant::class, 'campaign_id');
    }

    public function isAcceptingApplications(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }
        return true;
    }
}
```

**LiveHostRecruitmentStage:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostRecruitmentStage extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'position', 'name', 'description', 'is_final'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentCampaign::class, 'campaign_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(LiveHostApplicant::class, 'current_stage_id');
    }
}
```

**LiveHostApplicant:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'applicant_number', 'full_name', 'email', 'phone',
        'ic_number', 'location', 'platforms', 'experience_summary', 'motivation',
        'resume_path', 'source', 'current_stage_id', 'status', 'rating', 'notes',
        'applied_at', 'hired_at', 'hired_user_id',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'rating' => 'integer',
            'applied_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentCampaign::class, 'campaign_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'current_stage_id');
    }

    public function hiredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LiveHostApplicantStageHistory::class, 'applicant_id')->latest();
    }

    public static function generateApplicantNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "LHA-{$yearMonth}-";
        $last = static::query()
            ->where('applicant_number', 'like', $prefix.'%')
            ->orderByDesc('applicant_number')
            ->first();
        $next = $last ? ((int) substr($last->applicant_number, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
```

**LiveHostApplicantStageHistory:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostApplicantStageHistory extends Model
{
    protected $table = 'live_host_applicant_stage_history';

    protected $fillable = [
        'applicant_id', 'from_stage_id', 'to_stage_id',
        'action', 'notes', 'changed_by',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(LiveHostApplicant::class, 'applicant_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'to_stage_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

**Verify:**

```bash
php artisan tinker --execute="dump(App\Models\LiveHostRecruitmentCampaign::query()->toRawSql());"
```
Expected: a valid SQL string, no errors.

**Commit:** `feat(livehost): add recruitment Eloquent models`

---

## Task 1.6 — Factories

**Files:**
- Modify: `database/factories/LiveHostRecruitmentCampaignFactory.php`
- Modify: `database/factories/LiveHostRecruitmentStageFactory.php`
- Modify: `database/factories/LiveHostApplicantFactory.php`

**LiveHostRecruitmentCampaignFactory:**

```php
public function definition(): array
{
    $title = $this->faker->unique()->sentence(3);
    return [
        'title' => $title,
        'slug' => \Illuminate\Support\Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
        'description' => $this->faker->paragraph(),
        'status' => 'draft',
        'target_count' => $this->faker->optional()->numberBetween(1, 20),
        'opens_at' => null,
        'closes_at' => null,
        'created_by' => User::factory(),
    ];
}

public function open(): static
{
    return $this->state(fn () => ['status' => 'open']);
}
```

**LiveHostRecruitmentStageFactory:**

```php
public function definition(): array
{
    return [
        'campaign_id' => LiveHostRecruitmentCampaign::factory(),
        'position' => 1,
        'name' => $this->faker->randomElement(['Review', 'Interview', 'Test Live', 'Final']),
        'description' => null,
        'is_final' => false,
    ];
}
```

**LiveHostApplicantFactory:**

```php
public function definition(): array
{
    $name = $this->faker->name();
    return [
        'campaign_id' => LiveHostRecruitmentCampaign::factory(),
        'applicant_number' => 'LHA-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        'full_name' => $name,
        'email' => $this->faker->unique()->safeEmail(),
        'phone' => $this->faker->phoneNumber(),
        'platforms' => ['tiktok'],
        'experience_summary' => $this->faker->paragraph(),
        'motivation' => $this->faker->paragraph(),
        'status' => 'active',
        'applied_at' => now(),
    ];
}
```

**Commit:** `feat(livehost): add recruitment model factories`

---

## Task 1.7 — Feature test: campaign creates 4 default stages

**Files:**
- Create: `tests/Feature/LiveHost/Recruitment/CampaignStagesTest.php`

**Step 1: Write failing test**

```php
<?php

use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('seeds 4 default stages when a campaign is created', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create();

    expect($campaign->stages()->count())->toBe(4);

    $names = $campaign->stages()->orderBy('position')->pluck('name')->all();
    expect($names)->toEqual(['Review', 'Interview', 'Test Live', 'Final']);

    expect($campaign->stages()->where('is_final', true)->count())->toBe(1);
    expect($campaign->stages()->where('is_final', true)->first()->name)->toBe('Final');
});
```

Run: `php artisan test --compact --filter=CampaignStagesTest`
Expected: FAIL (`Failed asserting that 0 is identical to 4.`)

**Step 2: Add a model observer (or `booted()` hook) that seeds stages**

Modify `app/Models/LiveHostRecruitmentCampaign.php`, add inside the class:

```php
protected static function booted(): void
{
    static::created(function (self $campaign) {
        $defaults = [
            ['position' => 1, 'name' => 'Review', 'is_final' => false],
            ['position' => 2, 'name' => 'Interview', 'is_final' => false],
            ['position' => 3, 'name' => 'Test Live', 'is_final' => false],
            ['position' => 4, 'name' => 'Final', 'is_final' => true],
        ];
        foreach ($defaults as $stage) {
            $campaign->stages()->create($stage);
        }
    });
}
```

Run: `php artisan test --compact --filter=CampaignStagesTest`
Expected: PASS.

**Commit:** `feat(livehost): seed default stages on campaign creation`

---

# Milestone 2 — Public application form

Unauthenticated form that creates applicants.

## Task 2.1 — Public routes + controller skeleton

**Files:**
- Modify: `routes/web.php` (add block near end, NOT inside livehost middleware)
- Create: `app/Http/Controllers/LiveHost/PublicRecruitmentController.php`

**routes/web.php (add near bottom, outside any middleware group):**

```php
Route::prefix('recruitment')->name('recruitment.')->group(function () {
    Route::get('{slug}', [\App\Http\Controllers\LiveHost\PublicRecruitmentController::class, 'show'])
        ->name('show');
    Route::post('{slug}', [\App\Http\Controllers\LiveHost\PublicRecruitmentController::class, 'apply'])
        ->name('apply');
    Route::get('{slug}/thank-you', [\App\Http\Controllers\LiveHost\PublicRecruitmentController::class, 'thankYou'])
        ->name('thank-you');
});
```

**PublicRecruitmentController (skeleton):**

```php
<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicRecruitmentController extends Controller
{
    public function show(string $slug)
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        if (! $campaign->isAcceptingApplications()) {
            return response()->view('recruitment.closed', ['campaign' => $campaign], Response::HTTP_GONE);
        }

        return view('recruitment.show', ['campaign' => $campaign]);
    }

    public function apply(Request $request, string $slug)
    {
        // filled in task 2.3
        return redirect()->route('recruitment.thank-you', $slug);
    }

    public function thankYou(string $slug)
    {
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        return view('recruitment.thank-you', ['campaign' => $campaign]);
    }
}
```

**Commit:** `feat(livehost): add public recruitment routes and controller skeleton`

---

## Task 2.2 — Blade views for public form + thank-you + closed

**Files:**
- Create: `resources/views/recruitment/show.blade.php`
- Create: `resources/views/recruitment/thank-you.blade.php`
- Create: `resources/views/recruitment/closed.blade.php`

**show.blade.php:** minimal standalone page — Tailwind via Vite, no app layout. Form posts to `{{ route('recruitment.apply', $campaign->slug) }}`. Fields per design Section 3. `enctype="multipart/form-data"` for resume upload. Use CSRF token.

Template skeleton:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->title }} — Live Host Recruitment</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
<div class="mx-auto max-w-2xl p-6">
    <h1 class="text-3xl font-bold">{{ $campaign->title }}</h1>
    @if ($campaign->closes_at)
        <p class="mt-1 text-sm text-gray-500">Closes {{ $campaign->closes_at->toFormattedDateString() }}</p>
    @endif
    <div class="prose mt-4">{!! $campaign->description !!}</div>

    @if ($errors->any())
        <div class="mt-4 rounded bg-red-50 p-3 text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('recruitment.apply', $campaign->slug) }}" enctype="multipart/form-data" class="mt-6 space-y-4">
        @csrf
        {{-- name, email, phone, ic_number, location, platforms[], experience_summary, motivation, resume --}}
        {{-- use flex/grid + standard Tailwind. Full fields per design section 3. --}}
        <button type="submit" class="rounded bg-gray-900 px-5 py-2 text-white">Submit application</button>
    </form>
</div>
</body>
</html>
```

(Flesh out all fields per design; keep visually simple.)

**thank-you.blade.php:** same layout, shows "Thanks, {{ $campaign->title }} team will review your application." with campaign title and a note to check email.

**closed.blade.php:** same layout, "This campaign is no longer accepting applications." — no form.

**Commit:** `feat(livehost): add public recruitment blade views`

---

## Task 2.3 — Application submission (happy path)

**Files:**
- Modify: `app/Http/Controllers/LiveHost/PublicRecruitmentController.php`
- Create: `app/Http/Requests/LiveHost/Recruitment/ApplyRequest.php`

**ApplyRequest:**

```php
<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'ic_number' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['in:tiktok,shopee,facebook'],
            'experience_summary' => ['nullable', 'string', 'max:5000'],
            'motivation' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}
```

**apply() implementation:**

```php
public function apply(ApplyRequest $request, string $slug)
{
    $campaign = LiveHostRecruitmentCampaign::with('stages')->where('slug', $slug)->firstOrFail();

    abort_unless($campaign->isAcceptingApplications(), 410);

    if (LiveHostApplicant::where('campaign_id', $campaign->id)->where('email', $request->email)->exists()) {
        return back()->withInput()->withErrors([
            'email' => 'You have already applied to this campaign with this email.',
        ]);
    }

    $resumePath = $request->file('resume')?->store('recruitment/resumes', 'local');

    DB::transaction(function () use ($request, $campaign, $resumePath) {
        $firstStage = $campaign->stages->sortBy('position')->first();

        $applicant = LiveHostApplicant::create([
            'campaign_id' => $campaign->id,
            'applicant_number' => LiveHostApplicant::generateApplicantNumber(),
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'ic_number' => $request->ic_number,
            'location' => $request->location,
            'platforms' => $request->platforms,
            'experience_summary' => $request->experience_summary,
            'motivation' => $request->motivation,
            'resume_path' => $resumePath,
            'current_stage_id' => $firstStage?->id,
            'status' => 'active',
            'applied_at' => now(),
        ]);

        $applicant->history()->create([
            'to_stage_id' => $firstStage?->id,
            'action' => 'applied',
        ]);

        // email sent in task 2.5
    });

    return redirect()->route('recruitment.thank-you', $slug);
}
```

Imports: `use App\Http\Requests\LiveHost\Recruitment\ApplyRequest; use App\Models\LiveHostApplicant; use Illuminate\Support\Facades\DB;`

**Commit:** `feat(livehost): accept public recruitment applications`

---

## Task 2.4 — Feature test: public form happy path + dedupe + closed

**Files:**
- Create: `tests/Feature/LiveHost/Recruitment/PublicApplicationTest.php`

```php
<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('accepts a valid application', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $response = $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Ahmad Test',
        'email' => 'ahmad.test@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
        'experience_summary' => 'Some experience',
        'motivation' => 'Because I love live selling',
    ]);

    $response->assertRedirect(route('recruitment.thank-you', $campaign->slug));

    $applicant = LiveHostApplicant::where('email', 'ahmad.test@example.com')->firstOrFail();
    expect($applicant->campaign_id)->toBe($campaign->id);
    expect($applicant->status)->toBe('active');
    expect($applicant->current_stage_id)->not->toBeNull();
    expect($applicant->history()->where('action', 'applied')->exists())->toBeTrue();
});

it('rejects duplicate applications for the same campaign+email', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'email' => 'dupe@example.com',
    ]);

    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Dupe',
        'email' => 'dupe@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
    ])->assertSessionHasErrors('email');
});

it('rejects applications to closed campaigns', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'closed']);

    $this->get(route('recruitment.show', $campaign->slug))->assertStatus(410);
    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Test',
        'email' => 'test@example.com',
        'phone' => '60123456789',
        'platforms' => ['tiktok'],
    ])->assertStatus(410);
});
```

Run: `php artisan test --compact --filter=PublicApplicationTest`
Expected: all green.

**Commit:** `test(livehost): cover public recruitment submission paths`

---

## Task 2.5 — Confirmation email

**Files:**
- Create: `app/Mail/LiveHost/Recruitment/ApplicationReceivedMail.php`
- Create: `resources/views/emails/recruitment/application-received.blade.php`
- Modify: `PublicRecruitmentController::apply()` to dispatch

Use `php artisan make:mail LiveHost/Recruitment/ApplicationReceivedMail --markdown=emails.recruitment.application-received`.

**Mailable class:**

```php
<?php

namespace App\Mail\LiveHost\Recruitment;

use App\Models\LiveHostApplicant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LiveHostApplicant $applicant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your application');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.recruitment.application-received');
    }
}
```

**Dispatch** inside the transaction in `apply()`:

```php
Mail::to($applicant->email)->queue(new ApplicationReceivedMail($applicant));
```

**Test addition:**

```php
it('sends a confirmation email on application', function () {
    Mail::fake();
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();

    $this->post(route('recruitment.apply', $campaign->slug), [
        'full_name' => 'Ahmad', 'email' => 'ahmad@example.com',
        'phone' => '60123456789', 'platforms' => ['tiktok'],
    ]);

    Mail::assertQueued(ApplicationReceivedMail::class,
        fn ($m) => $m->hasTo('ahmad@example.com'));
});
```

**Commit:** `feat(livehost): send confirmation email on application`

---

# Milestone 3 — Admin: Campaign management

## Task 3.1 — Admin routes + controller skeleton

**Files:**
- Modify: `routes/web.php` (inside the `livehost.` group)
- Create: `app/Http/Controllers/LiveHost/RecruitmentCampaignController.php`

Add inside the `Route::middleware(['auth', 'role:admin_livehost,admin'])->prefix('livehost')->name('livehost.')->group(...)`:

```php
Route::prefix('recruitment')->name('recruitment.')->group(function () {
    Route::get('campaigns', [RecruitmentCampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/create', [RecruitmentCampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns', [RecruitmentCampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [RecruitmentCampaignController::class, 'show'])->name('campaigns.show');
    Route::get('campaigns/{campaign}/edit', [RecruitmentCampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campaigns/{campaign}', [RecruitmentCampaignController::class, 'update'])->name('campaigns.update');
    Route::patch('campaigns/{campaign}/publish', [RecruitmentCampaignController::class, 'publish'])->name('campaigns.publish');
    Route::patch('campaigns/{campaign}/pause', [RecruitmentCampaignController::class, 'pause'])->name('campaigns.pause');
    Route::patch('campaigns/{campaign}/close', [RecruitmentCampaignController::class, 'close'])->name('campaigns.close');
    Route::delete('campaigns/{campaign}', [RecruitmentCampaignController::class, 'destroy'])->name('campaigns.destroy');
});
```

**Controller skeleton:** return Inertia responses; reference `\App\Http\Controllers\LiveHost\HostController` for the established pattern (use `Inertia::render('recruitment/campaigns/Index', [...])`).

**Commit:** `feat(livehost): add admin recruitment campaign routes`

---

## Task 3.2 — Campaigns Index (React Inertia page)

**Files:**
- Create: `resources/js/livehost/pages/recruitment/campaigns/Index.jsx`
- Modify: controller `index()` to pass `campaigns` paginator

**Controller index:**

```php
public function index(): Response
{
    $campaigns = LiveHostRecruitmentCampaign::query()
        ->withCount('applicants')
        ->latest()
        ->paginate(20)
        ->through(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'status' => $c->status,
            'applicants_count' => $c->applicants_count,
            'target_count' => $c->target_count,
            'opens_at' => $c->opens_at?->toIso8601String(),
            'closes_at' => $c->closes_at?->toIso8601String(),
            'public_url' => $c->status === 'open' ? route('recruitment.show', $c->slug) : null,
        ]);

    return Inertia::render('recruitment/campaigns/Index', [
        'campaigns' => $campaigns,
    ]);
}
```

**Index.jsx:** Table with columns per design section 4. Action menu with Edit / Copy link / Publish / Pause / Close / Delete. Use existing primitives in `resources/js/livehost/components/ui/`.

**Commit:** `feat(livehost): render recruitment campaigns list`

---

## Task 3.3 — Campaign create + edit

**Files:**
- Create: `resources/js/livehost/pages/recruitment/campaigns/Create.jsx`
- Create: `resources/js/livehost/pages/recruitment/campaigns/Edit.jsx`
- Create: `app/Http/Requests/LiveHost/Recruitment/CampaignRequest.php`

**CampaignRequest rules:**

```php
public function rules(): array
{
    $campaignId = $this->route('campaign')?->id;
    return [
        'title' => ['required', 'string', 'max:255'],
        'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('live_host_recruitment_campaigns', 'slug')->ignore($campaignId)],
        'description' => ['nullable', 'string'],
        'status' => ['required', 'in:draft,open'],
        'target_count' => ['nullable', 'integer', 'min:1'],
        'opens_at' => ['nullable', 'date'],
        'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
    ];
}
```

**store()** uses `$validated + ['created_by' => $request->user()->id]`. Redirect to campaign show.

**update()** only updates these fields; status lifecycle changes go through dedicated publish/pause/close endpoints.

**Commit:** `feat(livehost): create and update recruitment campaigns`

---

## Task 3.4 — Stage editor (on campaign edit page)

**Files:**
- Modify: `resources/js/livehost/pages/recruitment/campaigns/Edit.jsx` (add stage editor component)
- Create: `app/Http/Controllers/LiveHost/RecruitmentStageController.php`
- Modify: `routes/web.php` inside the recruitment group:

```php
Route::post('campaigns/{campaign}/stages', [RecruitmentStageController::class, 'store'])->name('campaigns.stages.store');
Route::put('campaigns/{campaign}/stages/reorder', [RecruitmentStageController::class, 'reorder'])->name('campaigns.stages.reorder');
Route::put('campaigns/{campaign}/stages/{stage}', [RecruitmentStageController::class, 'update'])->name('campaigns.stages.update');
Route::delete('campaigns/{campaign}/stages/{stage}', [RecruitmentStageController::class, 'destroy'])->name('campaigns.stages.destroy');
```

**Constraints enforced in controller:**
- `store`: next `position` = max+1.
- `update`: can rename, set `description`, set `is_final` (clearing `is_final` on all other stages of the campaign).
- `destroy`: 422 if any applicant is currently on this stage or if it's the only `is_final`.
- `reorder`: accepts `['stage_ids' => [id, id, ...]]`; updates `position` per array index.

**Feature test:** `tests/Feature/LiveHost/Recruitment/StageEditorTest.php` covers reorder, rename, is_final enforcement (exactly one), delete guarded.

**Commit:** `feat(livehost): configurable recruitment stage editor`

---

## Task 3.5 — Publish / pause / close / delete

**Files:**
- Modify: `RecruitmentCampaignController` — implement `publish`, `pause`, `close`, `destroy`

Rules:
- `publish`: only from `draft`. Transitions to `open`. Validates at least one stage with `is_final=true`.
- `pause`: only from `open`. Transitions to `paused`. Public page returns 410.
- `close`: from `open` or `paused` → `closed`. Cannot reopen.
- `destroy`: only if `applicants_count === 0`. Otherwise 422.

**Feature test:** asserts each transition + guards.

**Commit:** `feat(livehost): campaign lifecycle transitions`

---

# Milestone 4 — Admin: Applicant review

## Task 4.1 — Applicant list (kanban)

**Files:**
- Modify: `routes/web.php` inside recruitment group:

```php
Route::get('applicants', [RecruitmentApplicantController::class, 'index'])->name('applicants.index');
Route::get('applicants/{applicant}', [RecruitmentApplicantController::class, 'show'])->name('applicants.show');
```

- Create: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`
- Create: `resources/js/livehost/pages/recruitment/applicants/Index.jsx`

**Controller index:**

```php
public function index(Request $request): Response
{
    $campaign = $request->integer('campaign')
        ? LiveHostRecruitmentCampaign::findOrFail($request->integer('campaign'))
        : LiveHostRecruitmentCampaign::where('status', 'open')->oldest('created_at')->first();

    $statusTab = $request->input('status', 'active'); // active|rejected|hired

    $applicants = $campaign
        ? LiveHostApplicant::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', $statusTab)
            ->orderByDesc('applied_at')
            ->get()
        : collect();

    return Inertia::render('recruitment/applicants/Index', [
        'campaign' => $campaign?->only(['id', 'title', 'slug', 'status']),
        'stages' => $campaign?->stages()->orderBy('position')->get(['id', 'name', 'position', 'is_final']) ?? [],
        'applicants' => $applicants->map(fn ($a) => [
            'id' => $a->id, 'applicant_number' => $a->applicant_number,
            'full_name' => $a->full_name, 'platforms' => $a->platforms,
            'rating' => $a->rating,
            'current_stage_id' => $a->current_stage_id,
            'applied_at' => $a->applied_at?->toIso8601String(),
        ]),
        'campaigns' => LiveHostRecruitmentCampaign::orderByDesc('created_at')->get(['id', 'title']),
        'filters' => ['campaign' => $campaign?->id, 'status' => $statusTab],
    ]);
}
```

**Index.jsx:** columns = stages, cards = applicants grouped by `current_stage_id`. Clicking a card opens detail route (task 4.2).

**Commit:** `feat(livehost): recruitment applicants kanban board`

---

## Task 4.2 — Applicant detail (drawer or dedicated page)

**Files:**
- Create: `resources/js/livehost/pages/recruitment/applicants/Show.jsx`
- Modify: `RecruitmentApplicantController::show`

**show():**

```php
public function show(LiveHostApplicant $applicant): Response
{
    $applicant->load(['campaign.stages', 'currentStage', 'history.fromStage', 'history.toStage', 'history.changedByUser']);

    return Inertia::render('recruitment/applicants/Show', [
        'applicant' => [... complete payload including platforms, notes, resume_path, hired_user_id],
        'history' => $applicant->history->map(fn ($h) => [...]),
        'stages' => $applicant->campaign->stages->map(fn ($s) => $s->only(['id', 'name', 'position', 'is_final'])),
    ]);
}
```

**Show.jsx:** tabs — Application / Activity / Notes. Action bar at bottom (Move next, Move to..., Reject, Hire).

**Commit:** `feat(livehost): applicant detail page with tabs`

---

## Task 4.3 — Stage actions (advance / move to specific / revert)

**Files:**
- Modify: `routes/web.php`:

```php
Route::patch('applicants/{applicant}/stage', [RecruitmentApplicantController::class, 'moveStage'])->name('applicants.stage');
```

- Modify: `RecruitmentApplicantController::moveStage`

```php
public function moveStage(Request $request, LiveHostApplicant $applicant)
{
    abort_if($applicant->status !== 'active', 422, 'Applicant is not active.');

    $data = $request->validate([
        'to_stage_id' => ['required', 'integer', 'exists:live_host_recruitment_stages,id'],
        'notes' => ['nullable', 'string'],
    ]);

    $toStage = LiveHostRecruitmentStage::findOrFail($data['to_stage_id']);
    abort_unless($toStage->campaign_id === $applicant->campaign_id, 422, 'Stage does not belong to this campaign.');

    $fromStageId = $applicant->current_stage_id;
    $action = 'advanced';
    if ($fromStageId && $applicant->currentStage && $toStage->position < $applicant->currentStage->position) {
        $action = 'reverted';
    }

    DB::transaction(function () use ($applicant, $toStage, $fromStageId, $data, $action, $request) {
        $applicant->update(['current_stage_id' => $toStage->id]);
        $applicant->history()->create([
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStage->id,
            'action' => $action,
            'notes' => $data['notes'] ?? null,
            'changed_by' => $request->user()->id,
        ]);
    });

    return back();
}
```

**Feature test:** asserts status, history row, cross-campaign guard.

**Commit:** `feat(livehost): move applicants between stages`

---

## Task 4.4 — Reject applicant

**Files:**
- Modify: `routes/web.php`:

```php
Route::patch('applicants/{applicant}/reject', [RecruitmentApplicantController::class, 'reject'])->name('applicants.reject');
```

- Modify: `RecruitmentApplicantController::reject`

```php
public function reject(Request $request, LiveHostApplicant $applicant)
{
    abort_if($applicant->status !== 'active', 422);
    $data = $request->validate(['notes' => ['nullable', 'string']]);

    DB::transaction(function () use ($applicant, $data, $request) {
        $applicant->update(['status' => 'rejected']);
        $applicant->history()->create([
            'from_stage_id' => $applicant->current_stage_id,
            'to_stage_id' => null,
            'action' => 'rejected',
            'notes' => $data['notes'] ?? null,
            'changed_by' => $request->user()->id,
        ]);
    });

    return back();
}
```

**Feature test:** rejection flips status + history row.

**Commit:** `feat(livehost): reject recruitment applicants`

---

## Task 4.5 — Admin notes auto-save

**Files:**
- Modify: `routes/web.php`:

```php
Route::patch('applicants/{applicant}/notes', [RecruitmentApplicantController::class, 'updateNotes'])->name('applicants.notes');
```

- Modify: `RecruitmentApplicantController::updateNotes`

```php
public function updateNotes(Request $request, LiveHostApplicant $applicant)
{
    $data = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
    $applicant->update(['notes' => $data['notes']]);

    return response()->noContent();
}
```

In `Show.jsx` Notes tab, debounce-save on change (500 ms via Inertia `router.patch` with `preserveScroll: true`).

**Commit:** `feat(livehost): admin notes on recruitment applicants`

---

# Milestone 5 — Hire action

## Task 5.1 — Hire endpoint

**Files:**
- Modify: `routes/web.php`:

```php
Route::post('applicants/{applicant}/hire', [RecruitmentApplicantController::class, 'hire'])->name('applicants.hire');
```

- Modify: `RecruitmentApplicantController::hire`

```php
public function hire(Request $request, LiveHostApplicant $applicant)
{
    abort_if($applicant->status !== 'active', 422, 'Applicant is not active.');
    abort_unless(optional($applicant->currentStage)->is_final, 422, 'Applicant is not at the final stage.');

    $data = $request->validate([
        'full_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'phone' => ['required', 'string', 'max:50'],
    ]);

    $user = DB::transaction(function () use ($applicant, $data, $request) {
        $user = User::create([
            'name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => bcrypt(\Illuminate\Support\Str::random(40)),
            'role' => 'live_host',
            'email_verified_at' => now(),
        ]);

        $applicant->update([
            'status' => 'hired',
            'hired_at' => now(),
            'hired_user_id' => $user->id,
        ]);

        $applicant->history()->create([
            'from_stage_id' => $applicant->current_stage_id,
            'to_stage_id' => null,
            'action' => 'hired',
            'notes' => "Hired as user #{$user->id}",
            'changed_by' => $request->user()->id,
        ]);

        return $user;
    });

    return back()->with('hired_user_id', $user->id);
}
```

**Feature test:**

```php
it('hires an applicant at the final stage', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create();
    $finalStage = $campaign->stages()->where('is_final', true)->first();
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $finalStage->id,
        'status' => 'active',
    ]);
    $admin = User::factory()->create(['role' => 'admin_livehost']);

    $this->actingAs($admin)->post(route('livehost.recruitment.applicants.hire', $applicant), [
        'full_name' => 'Ahmad Rahman',
        'email' => 'ahmad@livehost.com',
        'phone' => '60187654321',
    ])->assertRedirect();

    $hired = User::where('email', 'ahmad@livehost.com')->first();
    expect($hired->role)->toBe('live_host');
    expect($applicant->fresh()->status)->toBe('hired');
    expect($applicant->fresh()->hired_user_id)->toBe($hired->id);
});

it('blocks hiring when not at final stage', function () {
    // asserts 422 ...
});
```

**Commit:** `feat(livehost): hire action creates live_host user`

---

## Task 5.2 — Post-hire UI (password reset link + create host profile CTA)

**Files:**
- Modify: `resources/js/livehost/pages/recruitment/applicants/Show.jsx`
- Modify: `routes/web.php`:

```php
Route::post('applicants/{applicant}/password-reset-link', [RecruitmentApplicantController::class, 'passwordResetLink'])->name('applicants.password-reset-link');
```

- Modify: `RecruitmentApplicantController::passwordResetLink`

```php
public function passwordResetLink(LiveHostApplicant $applicant)
{
    abort_unless($applicant->status === 'hired' && $applicant->hired_user_id, 404);

    $user = $applicant->hiredUser;
    $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
    $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

    return response()->json(['url' => $url]);
}
```

Front-end: after `hire`, show a panel with:
- Email
- "Copy password reset link" (calls endpoint, copies URL to clipboard)
- Button: "Create Live Host profile →" linking to `/livehost/hosts/create?user_id={hired_user_id}`

**Commit:** `feat(livehost): post-hire actions for hired applicant`

---

## Task 5.3 — Deep-link: prefill `/livehost/hosts/create` from `?user_id=`

**Files:**
- Modify: `app/Http/Controllers/LiveHost/HostController.php` — `create()`
- Modify: `resources/js/livehost/pages/hosts/Create.jsx`

In `HostController::create`, read `$request->integer('user_id')` and if present, pass a `prefilledUser` payload (name/email/phone) to the Inertia render so the form is pre-filled. No change to `store()` logic needed (validation stays identical).

**Feature test:** asserts the Inertia payload contains prefilled fields when `?user_id=` is supplied with an existing `live_host` user.

**Commit:** `feat(livehost): prefill host create form from hired user_id`

---

# Milestone 6 — Polish & integration

## Task 6.1 — Add Recruitment to Live Host Desk sidebar

**Files:**
- Modify: `resources/js/livehost/layouts/LiveHostLayout.jsx`

Add under OPERATIONS, after `Live Hosts`:

```jsx
{ label: 'Recruitment', href: '/livehost/recruitment/campaigns', icon: Megaphone /* or ClipboardList */ },
```

Import icon from `lucide-react`. No counts key for v1 (can add later).

**Commit:** `feat(livehost): add Recruitment to Live Host Desk sidebar`

---

## Task 6.2 — Pest 4 browser test: end-to-end public form submission

**Files:**
- Create: `tests/Browser/LiveHost/Recruitment/PublicApplicationBrowserTest.php`

```php
<?php

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentCampaign;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lets a candidate complete the public recruitment form', function () {
    $campaign = LiveHostRecruitmentCampaign::factory()->open()->create([
        'title' => 'Hiring TikTok Live Hosts April 2026',
    ]);

    $page = visit(route('recruitment.show', $campaign->slug));

    $page->assertSee('Hiring TikTok Live Hosts April 2026')
        ->fill('full_name', 'Ahmad Rahman')
        ->fill('email', 'ahmad.rahman@test.example')
        ->fill('phone', '60187654321')
        ->check('platforms[]', 'tiktok')
        ->fill('motivation', 'I love live selling')
        ->click('Submit application')
        ->assertSee('Thanks')
        ->assertNoJavascriptErrors();

    expect(LiveHostApplicant::where('email', 'ahmad.rahman@test.example')->exists())->toBeTrue();
});
```

Run: `php artisan test --compact tests/Browser/LiveHost/Recruitment/PublicApplicationBrowserTest.php`

**Commit:** `test(livehost): browse public recruitment form end-to-end`

---

## Task 6.3 — Run full test suite + pint

Run:

```bash
vendor/bin/pint --dirty
php artisan test --compact --filter=LiveHost/Recruitment
```

Fix any failures. Then optionally run the complete test suite with user approval:

```bash
php artisan test --compact
```

**Commit (if needed):** `chore(livehost): pint formatting pass for recruitment module`

---

# Final checklist

Before declaring done:

- [ ] Public form accepts applications (manual check via browser at `/recruitment/{slug}`)
- [ ] Duplicate email rejected per campaign
- [ ] Closed/paused campaigns return 410
- [ ] Confirmation email queued on apply
- [ ] Admin can create, edit, publish, pause, close, delete (empty) campaigns
- [ ] Stage editor enforces exactly one `is_final`
- [ ] Kanban shows applicants grouped by stage
- [ ] Move / reject / hire actions write history rows
- [ ] Hire creates `User` with `role=live_host`, `email_verified_at` set
- [ ] Password reset link copyable post-hire
- [ ] "Create Live Host profile →" prefills `/livehost/hosts/create`
- [ ] Sidebar shows Recruitment under OPERATIONS
- [ ] All tests pass (`php artisan test --compact --filter=LiveHost/Recruitment`)
- [ ] `vendor/bin/pint --dirty` clean

---

## Out-of-scope reminders (do NOT build in this iteration)

- Stage-movement, rejection, or onboarding emails
- Structured interview scheduling
- Custom form fields per campaign
- Auto-creation of LiveHost record / platform accounts / commission profile
- Recruitment analytics / conversion reporting
- Bulk applicant actions
- Candidate self-service portal
- Reopening closed campaigns
