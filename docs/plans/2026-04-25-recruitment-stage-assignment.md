# Recruitment Stage Assignment Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add per-applicant per-stage assignee, due-at, and stage notes to the Live Host recruitment kanban, surfaced through a modal that opens when an applicant card is clicked.

**Architecture:** New `live_host_applicant_stages` join table tracks one row per (applicant × stage they've been in), with `entered_at`/`exited_at` defining the open row. A service centralises stage-transition logic; a new `PATCH /current-stage` endpoint mutates the open row only. Frontend `Index.jsx` swaps card-click navigation for a `StageAssignmentModal` that auto-saves and shows overdue state.

**Tech Stack:** Laravel 12, Inertia + React 19, Pest 4, Eloquent, Tailwind v4. Branch policy: small fixes go straight to `main` (per project memory) — there is no feature branch for this work.

**Reference design doc:** [docs/plans/2026-04-25-recruitment-stage-assignment-design.md](2026-04-25-recruitment-stage-assignment-design.md)

---

## Task 1 — Migration: create `live_host_applicant_stages` table

**Files:**
- Create: `database/migrations/2026_04_25_100000_create_live_host_applicant_stages_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_live_host_applicant_stages_table --no-interaction`

(Move/rename to the timestamped path above if Artisan picks a different timestamp — it will. Use the timestamp Artisan generates.)

**Step 2: Author the migration**

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_applicant_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')
                ->constrained('live_host_applicants')
                ->cascadeOnDelete();
            $table->foreignId('stage_id')
                ->nullable()
                ->constrained('live_host_recruitment_stages')
                ->nullOnDelete();
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->text('stage_notes')->nullable();
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'exited_at']);
            $table->index(['assignee_id', 'due_at']);
        });

        // Backfill: open one row per existing applicant at its current stage.
        DB::table('live_host_applicants')
            ->whereNotNull('current_stage_id')
            ->orderBy('id')
            ->select(['id', 'current_stage_id', 'applied_at'])
            ->chunkById(500, function ($rows) {
                $now = now();
                $insert = $rows->map(fn ($row) => [
                    'applicant_id' => $row->id,
                    'stage_id' => $row->current_stage_id,
                    'assignee_id' => null,
                    'due_at' => null,
                    'stage_notes' => null,
                    'entered_at' => $row->applied_at ?? $now,
                    'exited_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                if (! empty($insert)) {
                    DB::table('live_host_applicant_stages')->insert($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_applicant_stages');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: migration runs without error and reports the new table.

**Step 4: Commit**

```bash
git add database/migrations/*_create_live_host_applicant_stages_table.php
git commit -m "feat(live-host): add live_host_applicant_stages table for per-stage state"
```

---

## Task 2 — Model: `LiveHostApplicantStage`

**Files:**
- Create: `app/Models/LiveHostApplicantStage.php`
- Modify: `app/Models/LiveHostApplicant.php` (add relations)

**Step 1: Generate the model**

Run: `php artisan make:model LiveHostApplicantStage --no-interaction`

**Step 2: Replace the model contents with:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostApplicantStage extends Model
{
    use HasFactory;

    protected $table = 'live_host_applicant_stages';

    protected $fillable = [
        'applicant_id', 'stage_id', 'assignee_id',
        'due_at', 'stage_notes', 'entered_at', 'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(LiveHostApplicant::class, 'applicant_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('exited_at');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && $this->exited_at === null
            && $this->due_at->isPast();
    }
}
```

**Step 3: Add relations to `LiveHostApplicant`**

Open `app/Models/LiveHostApplicant.php`. After the `history()` method (around line 100-103), add:

```php
    public function stageRows(): HasMany
    {
        return $this->hasMany(LiveHostApplicantStage::class, 'applicant_id');
    }

    public function currentStageRow(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LiveHostApplicantStage::class, 'applicant_id')
            ->whereNull('exited_at')
            ->latestOfMany('entered_at');
    }
```

Also add the `HasOne` import to the top of the file:

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

**Step 4: Smoke-test in tinker**

Run: `php artisan tinker --execute="echo App\Models\LiveHostApplicantStage::count();"`
Expected: prints an integer (the backfill count from Task 1).

Run: `php artisan tinker --execute="\$a = App\Models\LiveHostApplicant::with('currentStageRow')->first(); var_dump(\$a?->currentStageRow?->id);"`
Expected: prints int(...) for an applicant with a stage, or NULL if seed data is empty.

**Step 5: Commit**

```bash
git add app/Models/LiveHostApplicantStage.php app/Models/LiveHostApplicant.php
git commit -m "feat(live-host): add LiveHostApplicantStage model and relations"
```

---

## Task 3 — Factory for the new model

**Files:**
- Create: `database/factories/LiveHostApplicantStageFactory.php`

**Step 1: Generate factory**

Run: `php artisan make:factory LiveHostApplicantStageFactory --model=LiveHostApplicantStage --no-interaction`

**Step 2: Replace factory contents with:**

```php
<?php

namespace Database\Factories;

use App\Models\LiveHostApplicant;
use App\Models\LiveHostRecruitmentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostApplicantStage>
 */
class LiveHostApplicantStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => LiveHostApplicant::factory(),
            'stage_id' => LiveHostRecruitmentStage::factory(),
            'assignee_id' => null,
            'due_at' => null,
            'stage_notes' => null,
            'entered_at' => now(),
            'exited_at' => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => ['exited_at' => now()]);
    }

    public function dueAt(\DateTimeInterface $date): self
    {
        return $this->state(fn () => ['due_at' => $date]);
    }
}
```

**Step 3: Commit**

```bash
git add database/factories/LiveHostApplicantStageFactory.php
git commit -m "feat(live-host): add LiveHostApplicantStage factory"
```

---

## Task 4 — Service: `ApplicantStageTransition`

**Files:**
- Create: `app/Services/Recruitment/ApplicantStageTransition.php`

**Step 1: Create file**

Use the Write tool to create `app/Services/Recruitment/ApplicantStageTransition.php` with:

```php
<?php

namespace App\Services\Recruitment;

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicantStageTransition
{
    /**
     * Open the very first stage row for a freshly created applicant.
     * Idempotent: does nothing if the applicant already has an open row.
     */
    public function enterFirstStage(LiveHostApplicant $applicant): ?LiveHostApplicantStage
    {
        if ($applicant->current_stage_id === null) {
            return null;
        }

        $existing = LiveHostApplicantStage::query()
            ->where('applicant_id', $applicant->id)
            ->whereNull('exited_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return LiveHostApplicantStage::create([
            'applicant_id' => $applicant->id,
            'stage_id' => $applicant->current_stage_id,
            'entered_at' => $applicant->applied_at ?? now(),
        ]);
    }

    /**
     * Move the applicant to the destination stage.
     * Closes any open row, opens a new one, and updates current_stage_id
     * on the applicant. Caller is responsible for writing the audit-log
     * entry to live_host_applicant_stage_history (existing behaviour).
     */
    public function transition(
        LiveHostApplicant $applicant,
        LiveHostRecruitmentStage $toStage,
    ): LiveHostApplicantStage {
        return DB::transaction(function () use ($applicant, $toStage) {
            $now = now();

            LiveHostApplicantStage::query()
                ->where('applicant_id', $applicant->id)
                ->whereNull('exited_at')
                ->update(['exited_at' => $now, 'updated_at' => $now]);

            $applicant->update(['current_stage_id' => $toStage->id]);

            return LiveHostApplicantStage::create([
                'applicant_id' => $applicant->id,
                'stage_id' => $toStage->id,
                'entered_at' => $now,
            ]);
        });
    }

    /**
     * Close the open row without opening a new one — used on reject/hire/withdraw.
     */
    public function closeOpenRow(LiveHostApplicant $applicant): void
    {
        $now = now();
        LiveHostApplicantStage::query()
            ->where('applicant_id', $applicant->id)
            ->whereNull('exited_at')
            ->update(['exited_at' => $now, 'updated_at' => $now]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Recruitment/ApplicantStageTransition.php
git commit -m "feat(live-host): add ApplicantStageTransition service"
```

---

## Task 5 — Test: stage transition service

**Files:**
- Create: `tests/Feature/LiveHost/Recruitment/StageTransitionServiceTest.php`

**Step 1: Generate test**

Run: `php artisan make:test LiveHost/Recruitment/StageTransitionServiceTest --pest --no-interaction`

**Step 2: Replace test contents with:**

```php
<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Services\Recruitment\ApplicantStageTransition;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->campaign = LiveHostRecruitmentCampaign::factory()->create();
    $this->stageA = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id,
        'position' => 1,
        'name' => 'Review',
    ]);
    $this->stageB = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id,
        'position' => 2,
        'name' => 'Interview',
    ]);
});

it('opens the first stage row when the applicant has a current stage', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    LiveHostApplicantStage::query()->where('applicant_id', $applicant->id)->delete();

    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    $row = LiveHostApplicantStage::where('applicant_id', $applicant->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->stage_id)->toBe($this->stageA->id)
        ->and($row->exited_at)->toBeNull();
});

it('does not open a duplicate row if one is already open', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);

    app(ApplicantStageTransition::class)->enterFirstStage($applicant);

    expect(LiveHostApplicantStage::where('applicant_id', $applicant->id)->count())->toBe(1);
});

it('closes the old row and opens a new one on transition', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);

    app(ApplicantStageTransition::class)->transition($applicant, $this->stageB);

    $rows = LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->stage_id)->toBe($this->stageA->id)
        ->and($rows[0]->exited_at)->not->toBeNull()
        ->and($rows[1]->stage_id)->toBe($this->stageB->id)
        ->and($rows[1]->exited_at)->toBeNull();

    expect($applicant->fresh()->current_stage_id)->toBe($this->stageB->id);
});

it('clears assignee/due_at on stage change because new row starts blank', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);
    LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->whereNull('exited_at')
        ->update([
            'assignee_id' => \App\Models\User::factory()->create()->id,
            'due_at' => now()->addDays(3),
            'stage_notes' => 'discussed availability',
        ]);

    app(ApplicantStageTransition::class)->transition($applicant, $this->stageB);

    $current = LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->whereNull('exited_at')->first();

    expect($current->assignee_id)->toBeNull()
        ->and($current->due_at)->toBeNull()
        ->and($current->stage_notes)->toBeNull();
});

it('closes the open row on closeOpenRow', function () {
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stageA->id,
    ]);

    app(ApplicantStageTransition::class)->closeOpenRow($applicant);

    expect(
        LiveHostApplicantStage::where('applicant_id', $applicant->id)
            ->whereNull('exited_at')->count()
    )->toBe(0);
});
```

**Step 3: Run the tests**

Run: `php artisan test --compact tests/Feature/LiveHost/Recruitment/StageTransitionServiceTest.php`
Expected: 5 tests pass.

If any fail because the existing applicant factory does not call `enterFirstStage`, the tests already account for that by deleting/reinserting rows manually where needed. Investigate any failure before moving on — do not skip.

**Step 4: Commit**

```bash
git add tests/Feature/LiveHost/Recruitment/StageTransitionServiceTest.php
git commit -m "test(live-host): cover ApplicantStageTransition service"
```

---

## Task 6 — Wire the service into existing controller actions

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`

The existing `moveStage`, `reject`, and `hire` methods directly mutate `current_stage_id` and write history. We add stage-row plumbing alongside, without changing existing audit behaviour.

**Step 1: Inject the service**

Add the import at the top of the controller:

```php
use App\Services\Recruitment\ApplicantStageTransition;
```

In `moveStage`, replace the existing `DB::transaction` block (currently `$applicant->update(['current_stage_id' => $toStage->id]); $applicant->history()->create([...]);`) with:

```php
DB::transaction(function () use ($applicant, $toStage, $fromStageId, $data, $action, $request) {
    app(ApplicantStageTransition::class)->transition($applicant, $toStage);
    $applicant->history()->create([
        'from_stage_id' => $fromStageId,
        'to_stage_id' => $toStage->id,
        'action' => $action,
        'notes' => $data['notes'] ?? null,
        'changed_by' => $request->user()?->id,
    ]);
});
```

(`transition()` already updates `current_stage_id` and runs in its own transaction, but nesting via `DB::transaction` is safe in Laravel — it uses savepoints.)

In `reject`, inside the existing `DB::transaction`, after the history insert and before the status update, add:

```php
app(ApplicantStageTransition::class)->closeOpenRow($applicant);
```

In `hire`, inside the existing `DB::transaction`, after the history insert and before `$applicant->update([...])`, add the same line:

```php
app(ApplicantStageTransition::class)->closeOpenRow($applicant);
```

**Step 2: Open the first stage row when the public form creates an applicant**

The applicant-creation flow lives in `app/Http/Controllers/LiveHost/PublicRecruitmentController.php`. Read the file to find the line that creates the `LiveHostApplicant`. After that creation (and after the applicant has `current_stage_id` set), call:

```php
app(\App\Services\Recruitment\ApplicantStageTransition::class)->enterFirstStage($applicant);
```

If the applicant is created without `current_stage_id`, locate the place where `current_stage_id` is assigned and call `enterFirstStage` after that point.

**Step 3: Run existing recruitment tests to verify no regression**

Run: `php artisan test --compact tests/Feature/LiveHost/Recruitment`
Expected: all existing tests still pass. Stage-row backfill from the migration plus the new service calls must not break the audit-log behaviour.

**Step 4: Commit**

```bash
git add app/Http/Controllers/LiveHost/RecruitmentApplicantController.php app/Http/Controllers/LiveHost/PublicRecruitmentController.php
git commit -m "feat(live-host): keep applicant_stages rows in sync on move/reject/hire/apply"
```

---

## Task 7 — Form Request for the new endpoint

**Files:**
- Create: `app/Http/Requests/LiveHost/Recruitment/UpdateApplicantCurrentStageRequest.php`

**Step 1: Generate request**

Run: `php artisan make:request LiveHost/Recruitment/UpdateApplicantCurrentStageRequest --no-interaction`

**Step 2: Replace contents with:**

```php
<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicantCurrentStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->isLiveHostAssistant() === false;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->whereIn('role', ['admin', 'admin_livehost'])
                ),
            ],
            'due_at' => ['nullable', 'date'],
            'stage_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Requests/LiveHost/Recruitment/UpdateApplicantCurrentStageRequest.php
git commit -m "feat(live-host): add UpdateApplicantCurrentStageRequest"
```

---

## Task 8 — Controller endpoint: update current stage

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`
- Modify: `routes/web.php` (around line 440)

**Step 1: Add the controller method**

In the controller (after `updateNotes`), add:

```php
use App\Http\Requests\LiveHost\Recruitment\UpdateApplicantCurrentStageRequest;
use App\Models\LiveHostApplicantStage;
```

```php
public function updateCurrentStage(
    UpdateApplicantCurrentStageRequest $request,
    LiveHostApplicant $applicant,
): HttpResponse {
    $data = $request->validated();

    $affected = LiveHostApplicantStage::query()
        ->where('applicant_id', $applicant->id)
        ->whereNull('exited_at')
        ->update([
            'assignee_id' => $data['assignee_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'stage_notes' => $data['stage_notes'] ?? null,
            'updated_at' => now(),
        ]);

    abort_if($affected === 0, HttpResponse::HTTP_CONFLICT, 'No open stage row.');

    return response()->noContent();
}
```

**Step 2: Register the route**

Open `routes/web.php`. After the `applicants/{applicant}/notes` line (around line 440), add:

```php
Route::patch('applicants/{applicant}/current-stage', [\App\Http\Controllers\LiveHost\RecruitmentApplicantController::class, 'updateCurrentStage'])
    ->name('applicants.current-stage');
```

**Step 3: Verify route registration**

Run: `php artisan route:list --path=livehost/recruitment/applicants --no-interaction | grep current-stage`
Expected: prints the new PATCH route.

**Step 4: Commit**

```bash
git add app/Http/Controllers/LiveHost/RecruitmentApplicantController.php routes/web.php
git commit -m "feat(live-host): add PATCH applicants/{applicant}/current-stage endpoint"
```

---

## Task 9 — Test: current-stage endpoint

**Files:**
- Create: `tests/Feature/LiveHost/Recruitment/UpdateApplicantCurrentStageTest.php`

**Step 1: Generate test**

Run: `php artisan make:test LiveHost/Recruitment/UpdateApplicantCurrentStageTest --pest --no-interaction`

**Step 2: Replace contents with:**

```php
<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin_livehost']);
    $this->campaign = LiveHostRecruitmentCampaign::factory()->create();
    $this->stage = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $this->campaign->id, 'position' => 1, 'name' => 'Review',
    ]);
    $this->applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $this->campaign->id,
        'current_stage_id' => $this->stage->id,
    ]);
});

function url(LiveHostApplicant $a): string
{
    return "/livehost/recruitment/applicants/{$a->id}/current-stage";
}

it('updates assignee, due_at, and stage_notes on the open row', function () {
    $assignee = User::factory()->create(['role' => 'admin']);
    $due = now()->addDays(3)->startOfMinute();

    $this->actingAs($this->admin)
        ->patch(url($this->applicant), [
            'assignee_id' => $assignee->id,
            'due_at' => $due->toIso8601String(),
            'stage_notes' => 'Schedule with candidate.',
        ])
        ->assertNoContent();

    $row = LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->whereNull('exited_at')->first();

    expect($row->assignee_id)->toBe($assignee->id)
        ->and($row->due_at?->toIso8601String())->toBe($due->toIso8601String())
        ->and($row->stage_notes)->toBe('Schedule with candidate.');
});

it('rejects an assignee whose role is not admin or admin_livehost', function () {
    $hostUser = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->admin)
        ->patch(url($this->applicant), ['assignee_id' => $hostUser->id])
        ->assertSessionHasErrors('assignee_id');
});

it('returns 409 when there is no open stage row', function () {
    LiveHostApplicantStage::where('applicant_id', $this->applicant->id)
        ->update(['exited_at' => now()]);

    $this->actingAs($this->admin)
        ->patch(url($this->applicant), ['stage_notes' => 'late note'])
        ->assertStatus(409);
});

it('forbids livehost assistants', function () {
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    $this->actingAs($assistant)
        ->patch(url($this->applicant), ['stage_notes' => 'nope'])
        ->assertForbidden();
});
```

**Step 3: Run the tests**

Run: `php artisan test --compact tests/Feature/LiveHost/Recruitment/UpdateApplicantCurrentStageTest.php`
Expected: 4 tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/LiveHost/Recruitment/UpdateApplicantCurrentStageTest.php
git commit -m "test(live-host): cover update-current-stage endpoint"
```

---

## Task 10 — Index controller: expose current-stage payload + assignable users

**Files:**
- Modify: `app/Http/Controllers/LiveHost/RecruitmentApplicantController.php`

**Step 1: Eager-load the open stage row**

In `index()`, change the applicants query (around line 39-45) to:

```php
$applicants = $campaign
    ? LiveHostApplicant::query()
        ->where('campaign_id', $campaign->id)
        ->where('status', $statusTab)
        ->with(['currentStageRow.assignee'])
        ->orderByDesc('applied_at')
        ->get()
    : collect();
```

**Step 2: Extend the payload**

In the `applicants` mapping inside the `Inertia::render(...)` array (around line 80-91), add three fields after `current_stage_id`:

```php
$row = $a->currentStageRow;
return [
    'id' => $a->id,
    'applicant_number' => $a->applicant_number,
    'full_name' => $a->name,
    'email' => $a->email,
    'platforms' => $a->valueByRole('platforms') ?? [],
    'rating' => $a->rating,
    'current_stage_id' => $a->current_stage_id,
    'status' => $a->status,
    'applied_at' => $a->applied_at?->toIso8601String(),
    'applied_at_human' => $a->applied_at?->diffForHumans(),
    'assignment' => $row ? [
        'assignee' => $row->assignee ? [
            'id' => $row->assignee->id,
            'name' => $row->assignee->name,
            'initials' => initials($row->assignee->name),
        ] : null,
        'due_at' => $row->due_at?->toIso8601String(),
        'is_overdue' => $row->is_overdue,
        'stage_notes' => $row->stage_notes,
    ] : null,
];
```

The `initials()` helper does not exist yet — define it inline as a private method on the controller:

```php
private static function initials(?string $name): string
{
    if (! $name) {
        return '?';
    }
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first.$last);
}
```

…and replace `initials($row->assignee->name)` with `self::initials($row->assignee->name)` in the mapping.

**Step 3: Add `assignableUsers` to the page props**

After the `'campaigns' => ...` line in the `Inertia::render(...)` array, add:

```php
'assignableUsers' => User::query()
    ->whereIn('role', ['admin', 'admin_livehost'])
    ->orderBy('name')
    ->get(['id', 'name', 'email'])
    ->map(fn (User $u) => [
        'id' => $u->id,
        'name' => $u->name,
        'email' => $u->email,
        'initials' => self::initials($u->name),
    ])
    ->values(),
```

**Step 4: Smoke test**

Run: `php artisan route:list --path=livehost/recruitment/applicants --no-interaction`
Expected: routes still register.

Run: `php artisan test --compact tests/Feature/LiveHost/Recruitment`
Expected: all tests pass — no regression in existing index tests.

**Step 5: Commit**

```bash
git add app/Http/Controllers/LiveHost/RecruitmentApplicantController.php
git commit -m "feat(live-host): expose stage assignment + assignable users on applicants index"
```

---

## Task 11 — Frontend: card footer (assignee avatar + due-date pill)

**Files:**
- Modify: `resources/js/livehost/pages/recruitment/applicants/Index.jsx`

**Step 1: Add a footer to `ApplicantCard`**

Inside `ApplicantCard`, after the existing `applied_at_human` block (around line 122-124), but still inside the `<Link>` component, add:

```jsx
{applicant.assignment && (applicant.assignment.assignee || applicant.assignment.due_at) && (
  <div className="mt-2 flex items-center gap-1.5">
    {applicant.assignment.assignee && (
      <span
        title={applicant.assignment.assignee.name}
        className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#E5E7EB] text-[9px] font-semibold text-[#374151]"
      >
        {applicant.assignment.assignee.initials}
      </span>
    )}
    {applicant.assignment.due_at && (
      <span
        className={[
          'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10.5px] font-medium ring-1 ring-inset',
          applicant.assignment.is_overdue
            ? 'bg-[#FEE2E2] text-[#B91C1C] ring-[#FECACA]'
            : 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]',
        ].join(' ')}
      >
        Due {new Date(applicant.assignment.due_at).toLocaleDateString(undefined, {
          weekday: 'short', day: 'numeric', month: 'short',
        })}
      </span>
    )}
  </div>
)}
```

**Step 2: Build assets**

Run: `npm run build`
Expected: build succeeds, no errors.

**Step 3: Visually verify in browser** (manual)

Open `https://mudeerbedaie.test/livehost/recruitment/applicants?campaign=1`. No assignment is set yet, so cards should look unchanged.

**Step 4: Commit**

```bash
git add resources/js/livehost/pages/recruitment/applicants/Index.jsx
git commit -m "feat(live-host): show assignee avatar and due-date pill on applicant cards"
```

---

## Task 12 — Frontend: `StageAssignmentModal` component

**Files:**
- Create: `resources/js/livehost/components/recruitment/StageAssignmentModal.jsx`

**Step 1: Create the component**

Use the Write tool to create `resources/js/livehost/components/recruitment/StageAssignmentModal.jsx` with:

```jsx
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowRight, Check, Loader2, X, XCircle } from 'lucide-react';

function toLocalDateTimeInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromLocalDateTimeInput(value) {
  if (!value) return null;
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  return d.toISOString();
}

export default function StageAssignmentModal({
  applicant,
  stages,
  assignableUsers,
  onClose,
}) {
  const initial = applicant.assignment ?? {};
  const [assigneeId, setAssigneeId] = useState(initial.assignee?.id ?? '');
  const [dueAt, setDueAt] = useState(toLocalDateTimeInput(initial.due_at));
  const [stageNotes, setStageNotes] = useState(initial.stage_notes ?? '');
  const [saveState, setSaveState] = useState('idle');
  const debounceRef = useRef(null);

  const orderedStages = useMemo(
    () => (stages ?? []).slice().sort((a, b) => Number(a.position) - Number(b.position)),
    [stages],
  );
  const currentIndex = orderedStages.findIndex((s) => s.id === applicant.current_stage_id);
  const nextStage = currentIndex >= 0 ? orderedStages[currentIndex + 1] : null;

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const save = (next = {}) => {
    setSaveState('saving');
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/current-stage`,
      {
        assignee_id: next.assignee_id !== undefined ? next.assignee_id : (assigneeId || null),
        due_at: next.due_at !== undefined ? next.due_at : fromLocalDateTimeInput(dueAt),
        stage_notes: next.stage_notes !== undefined ? next.stage_notes : (stageNotes || null),
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['applicants'],
        onSuccess: () => {
          setSaveState('saved');
          setTimeout(() => setSaveState('idle'), 1500);
        },
        onError: () => setSaveState('error'),
      },
    );
  };

  const handleAssigneeChange = (e) => {
    const value = e.target.value;
    setAssigneeId(value);
    save({ assignee_id: value || null });
  };

  const handleDueChange = (e) => {
    const value = e.target.value;
    setDueAt(value);
    save({ due_at: fromLocalDateTimeInput(value) });
  };

  const handleNotesChange = (e) => {
    const value = e.target.value;
    setStageNotes(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      save({ stage_notes: value || null });
    }, 500);
  };

  const moveTo = (stageId) => {
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/stage`,
      { to_stage_id: stageId },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const reject = () => {
    router.patch(
      `/livehost/recruitment/applicants/${applicant.id}/reject`,
      { notes: null },
      { preserveScroll: true, onSuccess: () => onClose() },
    );
  };

  const overdue = applicant.assignment?.is_overdue;

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="w-full max-w-md rounded-[16px] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="font-mono text-[11px] text-[#737373]">{applicant.applicant_number}</div>
            <div className="mt-0.5 truncate text-[18px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
              {applicant.full_name}
            </div>
            <div className="mt-1 inline-flex items-center gap-1.5 rounded-md bg-[#F5F5F5] px-1.5 py-0.5 text-[11px] font-medium text-[#525252]">
              {orderedStages.find((s) => s.id === applicant.current_stage_id)?.name ?? 'Unassigned'}
              {overdue && (
                <span className="ml-1 inline-flex items-center rounded bg-[#FEE2E2] px-1 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-[#B91C1C]">
                  Overdue
                </span>
              )}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
            aria-label="Close"
          >
            <X className="h-4 w-4" strokeWidth={2} />
          </button>
        </div>

        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Assignee
            </label>
            <select
              value={assigneeId}
              onChange={handleAssigneeChange}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">— Unassigned —</option>
              {(assignableUsers ?? []).map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name} ({u.email})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Due
            </label>
            <input
              type="datetime-local"
              value={dueAt}
              onChange={handleDueChange}
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>

          <div>
            <label className="mb-1 block text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
              Stage notes
            </label>
            <textarea
              rows={4}
              value={stageNotes}
              onChange={handleNotesChange}
              placeholder="Notes scoped to this stage…"
              className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <div className="mt-1 text-[11px] text-[#A3A3A3]">
              {saveState === 'saving' && (
                <span className="inline-flex items-center gap-1"><Loader2 className="h-3 w-3 animate-spin" /> Saving…</span>
              )}
              {saveState === 'saved' && (
                <span className="inline-flex items-center gap-1 text-[#047857]"><Check className="h-3 w-3" strokeWidth={3} /> Saved</span>
              )}
              {saveState === 'error' && <span className="text-[#B91C1C]">Failed to save</span>}
              {saveState === 'idle' && 'Auto-saves on blur / 500 ms after typing.'}
            </div>
          </div>
        </div>

        <div className="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-[#F0F0F0] pt-4">
          <Link
            href={`/livehost/recruitment/applicants/${applicant.id}`}
            className="text-[12.5px] font-medium text-[#0A0A0A] underline-offset-2 hover:underline"
          >
            Open full profile
          </Link>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={reject}
              className="inline-flex items-center gap-1 rounded-md border border-[#FCA5A5] bg-white px-2.5 py-1.5 text-[12.5px] font-medium text-[#B91C1C] hover:bg-[#FEF2F2]"
            >
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Reject
            </button>
            {nextStage && (
              <button
                type="button"
                onClick={() => moveTo(nextStage.id)}
                className="inline-flex items-center gap-1 rounded-md bg-[#0A0A0A] px-2.5 py-1.5 text-[12.5px] font-medium text-white hover:bg-[#262626]"
              >
                Move to {nextStage.name}
                <ArrowRight className="h-3.5 w-3.5" strokeWidth={2.25} />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
```

**Step 2: Commit**

```bash
git add resources/js/livehost/components/recruitment/StageAssignmentModal.jsx
git commit -m "feat(live-host): add StageAssignmentModal component"
```

---

## Task 13 — Frontend: open modal on card click

**Files:**
- Modify: `resources/js/livehost/pages/recruitment/applicants/Index.jsx`

**Step 1: Replace the card's `<Link>` with a button + modal trigger**

At the top of `Index.jsx`, add the import:

```js
import StageAssignmentModal from '@/livehost/components/recruitment/StageAssignmentModal';
```

Inside `ApplicantCard`, replace the outer `<Link href=...>` (lines ~90-125) with a `<button>`:

```jsx
<button
  type="button"
  onClick={(e) => {
    if (snapshot.isDragging) {
      e.preventDefault();
      return;
    }
    if (typeof onOpen === 'function') {
      onOpen(applicant);
    }
  }}
  className="min-w-0 flex-1 cursor-pointer text-left"
>
  {/* existing children unchanged */}
</button>
```

(Move the children inside the new `<button>`. The `onOpen` prop is added in the next steps.)

Update the `ApplicantCard` signature to accept `onOpen`:

```jsx
function ApplicantCard({ applicant, index, isDragDisabled = false, onOpen }) {
```

Pass `onOpen` through `StageColumn`:

```jsx
function StageColumn({ stage, applicants, isDropDisabled = false, dragDisabled = false, onOpen }) {
```

…and pass it on the `<ApplicantCard ... onOpen={onOpen} />` line.

**Step 2: Wire modal state into `ApplicantsIndex`**

Inside `ApplicantsIndex`, after `const { campaign, campaigns, stages, applicants, counts, filters } = usePage().props;`, add:

```jsx
const { assignableUsers } = usePage().props;
const [openApplicantId, setOpenApplicantId] = useState(null);
const openApplicant = useMemo(
  () => effectiveApplicants.find((a) => a.id === openApplicantId) ?? null,
  [effectiveApplicants, openApplicantId],
);
```

Pass `onOpen={(a) => setOpenApplicantId(a.id)}` into every `<StageColumn />` rendered inside the kanban.

At the end of the JSX (just before the closing `</> ` of `ApplicantsIndex`), add:

```jsx
{openApplicant && (
  <StageAssignmentModal
    applicant={openApplicant}
    stages={stages}
    assignableUsers={assignableUsers ?? []}
    onClose={() => setOpenApplicantId(null)}
  />
)}
```

**Step 3: Build assets**

Run: `npm run build`
Expected: build succeeds.

**Step 4: Manual smoke test**

Open `https://mudeerbedaie.test/livehost/recruitment/applicants?campaign=1`. Click a card — modal opens. Pick an assignee → "Saved" indicator appears, card footer shows the avatar after the modal saves. Set a due date → pill appears on the card. Refresh → state persists. Close modal with Esc and backdrop click.

**Step 5: Commit**

```bash
git add resources/js/livehost/pages/recruitment/applicants/Index.jsx
git commit -m "feat(live-host): open StageAssignmentModal on applicant card click"
```

---

## Task 14 — Browser test (Pest 4) for the full flow

**Files:**
- Create: `tests/Browser/LiveHost/Recruitment/StageAssignmentModalTest.php`

**Step 1: Generate test**

Run: `php artisan make:test Browser/LiveHost/Recruitment/StageAssignmentModalTest --pest --no-interaction`

Move it (or recreate) at `tests/Browser/LiveHost/Recruitment/StageAssignmentModalTest.php`.

**Step 2: Replace contents with:**

```php
<?php

declare(strict_types=1);

use App\Models\LiveHostApplicant;
use App\Models\LiveHostApplicantStage;
use App\Models\LiveHostRecruitmentCampaign;
use App\Models\LiveHostRecruitmentStage;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('opens the modal, assigns a user, and shows the avatar on the card', function () {
    $admin = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Admin User']);
    $assignee = User::factory()->create(['role' => 'admin', 'name' => 'Sarah Lee']);
    $campaign = LiveHostRecruitmentCampaign::factory()->create(['status' => 'open']);
    $stage = LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $campaign->id, 'position' => 1, 'name' => 'Review',
    ]);
    LiveHostRecruitmentStage::factory()->create([
        'campaign_id' => $campaign->id, 'position' => 2, 'name' => 'Interview',
    ]);
    $applicant = LiveHostApplicant::factory()->create([
        'campaign_id' => $campaign->id,
        'current_stage_id' => $stage->id,
    ]);

    $this->actingAs($admin);

    $page = visit('/livehost/recruitment/applicants?campaign='.$campaign->id);

    $page->assertSee($applicant->name)
        ->click($applicant->name)
        ->assertSee('Stage notes')
        ->select('Sarah Lee (' . $assignee->email . ')')
        ->wait(500)
        ->keys('Escape')
        ->assertSee('SL'); // initials chip on the card

    $row = LiveHostApplicantStage::where('applicant_id', $applicant->id)
        ->whereNull('exited_at')->first();
    expect($row->assignee_id)->toBe($assignee->id);
});
```

> Pest 4 browser API uses `visit()`, `click()`, `select()`, `keys()`. If the test runner's Pest plugin uses different selector helpers (e.g. `clickLink`, `selectOption`), match the existing pattern from sibling browser tests in `tests/Browser/LiveHost/Recruitment/`.

**Step 3: Run the test**

Run: `php artisan test --compact tests/Browser/LiveHost/Recruitment/StageAssignmentModalTest.php`
Expected: passes. If browser binaries aren't installed, the test runner will print a clear instruction (typically `php artisan pest:install-browser`); follow it then re-run.

**Step 4: Commit**

```bash
git add tests/Browser/LiveHost/Recruitment/StageAssignmentModalTest.php
git commit -m "test(live-host): browser-test stage assignment modal flow"
```

---

## Task 15 — Format, full test sweep, sanity check

**Step 1: Format**

Run: `vendor/bin/pint --dirty`
Expected: changes formatted in place.

**Step 2: Full live-host recruitment test pass**

Run: `php artisan test --compact tests/Feature/LiveHost/Recruitment tests/Browser/LiveHost/Recruitment`
Expected: all green.

**Step 3: Build assets one more time**

Run: `npm run build`
Expected: clean build.

**Step 4: Manual final check**

Open `https://mudeerbedaie.test/livehost/recruitment/applicants?campaign=1` and confirm:
- Card click → modal opens
- Assignee select → saves → avatar appears on card
- Due-at picker → pill appears, red ring + text if past
- Stage notes → debounced save, "Saved" indicator
- "Move to next stage" closes the modal and the new card has empty assignment
- Old applicant's history table shows the transition row (visit detail page)

**Step 5: Commit any formatting changes**

```bash
git add -u
git diff --cached --quiet || git commit -m "chore(live-host): pint on stage assignment changes"
```

---

## YAGNI exclusions (do NOT implement)

- Email / db notifications on assign or overdue.
- Daily overdue digest job.
- Per-stage column defaults / SLAs.
- Calendar view of due dates.
- Mobile (Live Host Pocket) surfacing.
- Editing assignment from the detail page (modal is sufficient for v1).
- Assignment changes recorded into `live_host_applicant_stage_history` (the new join table is its own record; history table stays a transition log).
