# Live Host — Session Replacement Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a self-serve "Request Replacement" workflow for live hosts on `/live-host/schedule`, plus a PIC-side queue at `/livehost/replacements` to assign replacements, with Malay email notifications, auto-expiry, and a durable `session_replacement_requests` audit table.

**Architecture:** New `SessionReplacementRequest` model with a 5-state lifecycle (`pending → assigned/withdrawn/expired/rejected`). Host-facing UI extends the existing Inertia React **Live Host Pocket** app at `resources/js/livehost-pocket/`. PIC-facing UI extends the existing Inertia React **PIC dashboard** at `resources/js/livehost/`. Three queued `mail+database` notifications (Malay copy) hooked to status transitions. A 5-minute cron flips overdue requests to `expired`. No payroll code change — replacement host earns naturally because `LiveSession.live_host_id` is whoever actually goes live; for the original host we add a guard in the "go live" controller to prevent post-assignment livestreaming on the same slot.

**Tech Stack:** Laravel 12, Livewire/Volt (existing admin host detail page), Inertia.js + React 19 (both pocket + PIC dashboards), Pest 4 (Feature + Browser tests), Tailwind v4. SQLite for dev, MySQL for prod — migrations stay portable per CLAUDE.md.

**Reference design doc:** [docs/plans/2026-04-25-live-host-session-replacement-design.md](2026-04-25-live-host-session-replacement-design.md). Read it before starting; it contains the full decision log.

---

## Conventions for every task

- **TDD red→green→commit.** Write the failing test first, run it, see it fail, write minimum code to green it, run it, commit.
- **Run only the affected tests** during a task: `php artisan test --compact --filter=<TestClass>`. Don't run the whole suite per task — that's for Phase 10.
- **Use `php artisan make:` commands** for every artifact (model, migration, controller, request, notification, command, factory, test) — never hand-write boilerplate.
- **Never run `migrate:fresh`** on this DB. Use `php artisan migrate` only. Tests use `RefreshDatabase` which is fine.
- **Run `vendor/bin/pint --dirty`** before each commit.
- **Commit messages:** lowercase prefix (`feat:`, `test:`, `fix:`, `refactor:`) + concise message + co-author trailer (matches recent project history).

```
Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```

- **Roles:** host routes use `role:live_host`. PIC routes use `role:admin,admin_livehost`. Existing middleware in `routes/web.php` shows the pattern.
- **Authorization beyond role:** use Laravel Policies (or inline `abort_unless`) so a host can only act on their own assignment / their own request.
- **Malay copy:** keep all user-visible strings (email subjects/bodies, validation messages, UI labels) in Bahasa Malaysia for this feature. Do not touch existing English strings elsewhere.

---

## Phase 1 — Backend foundation

### Task 1: Create migration, model, factory

**Files:**
- Create: `database/migrations/<timestamp>_create_session_replacement_requests_table.php`
- Create: `app/Models/SessionReplacementRequest.php`
- Create: `database/factories/SessionReplacementRequestFactory.php`

**Step 1: Generate the artifacts**

Run:
```bash
php artisan make:model SessionReplacementRequest -mf --no-interaction
```

Expected: creates the migration, model, and factory files.

**Step 2: Fill in the migration**

Replace the migration's `up()` body with:

```php
Schema::create('session_replacement_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('live_schedule_assignment_id')
        ->constrained('live_schedule_assignments')
        ->cascadeOnDelete();
    $table->foreignId('original_host_id')
        ->constrained('users')
        ->cascadeOnDelete();
    $table->foreignId('replacement_host_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->string('scope'); // 'one_date' | 'permanent'
    $table->date('target_date')->nullable();
    $table->string('reason_category'); // 'sick' | 'family' | 'personal' | 'other'
    $table->text('reason_note')->nullable();
    $table->string('status')->default('pending'); // 'pending'|'assigned'|'withdrawn'|'expired'|'rejected'
    $table->dateTime('requested_at');
    $table->dateTime('assigned_at')->nullable();
    $table->foreignId('assigned_by_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();
    $table->text('rejection_reason')->nullable();
    $table->dateTime('expires_at');
    $table->foreignId('live_session_id')
        ->nullable()
        ->constrained('live_sessions')
        ->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'expires_at']);
    $table->index(['original_host_id', 'requested_at']);
    $table->index(['live_schedule_assignment_id', 'target_date', 'status']);
});
```

`down()`:
```php
Schema::dropIfExists('session_replacement_requests');
```

**Step 3: Fill in the model**

Replace `app/Models/SessionReplacementRequest.php` body:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionReplacementRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const SCOPE_ONE_DATE = 'one_date';
    public const SCOPE_PERMANENT = 'permanent';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    public const REASON_CATEGORIES = ['sick', 'family', 'personal', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'requested_at' => 'datetime',
            'assigned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LiveScheduleAssignment::class, 'live_schedule_assignment_id');
    }

    public function originalHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_host_id');
    }

    public function replacementHost(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_host_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
```

**Step 4: Fill in the factory**

Replace `database/factories/SessionReplacementRequestFactory.php`:

```php
namespace Database\Factories;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionReplacementRequest>
 */
class SessionReplacementRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'live_schedule_assignment_id' => LiveScheduleAssignment::factory(),
            'original_host_id' => User::factory(),
            'replacement_host_id' => null,
            'scope' => SessionReplacementRequest::SCOPE_ONE_DATE,
            'target_date' => now()->addDay()->toDateString(),
            'reason_category' => 'sick',
            'reason_note' => null,
            'status' => SessionReplacementRequest::STATUS_PENDING,
            'requested_at' => now(),
            'assigned_at' => null,
            'assigned_by_id' => null,
            'rejection_reason' => null,
            'expires_at' => now()->addHours(24),
            'live_session_id' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => SessionReplacementRequest::STATUS_PENDING]);
    }

    public function assigned(?User $replacement = null): self
    {
        return $this->state([
            'status' => SessionReplacementRequest::STATUS_ASSIGNED,
            'replacement_host_id' => $replacement?->id ?? User::factory(),
            'assigned_at' => now(),
            'assigned_by_id' => User::factory(),
        ]);
    }

    public function permanent(): self
    {
        return $this->state([
            'scope' => SessionReplacementRequest::SCOPE_PERMANENT,
            'target_date' => null,
        ]);
    }

    public function expired(): self
    {
        return $this->state([
            'status' => SessionReplacementRequest::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }
}
```

**Step 5: Run the migration**

Run:
```bash
php artisan migrate
```

Expected: `session_replacement_requests` table created.

**Step 6: Smoke-check the model in tinker**

Run:
```bash
php artisan tinker --execute="echo \App\Models\SessionReplacementRequest::factory()->make()->status;"
```

Expected output: `pending`

**Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add database/migrations app/Models/SessionReplacementRequest.php database/factories/SessionReplacementRequestFactory.php
git commit -m "$(cat <<'EOF'
feat(live-host): add session_replacement_requests model + migration

Foundation for the replacement workflow: stores the full lifecycle
of a host's request to swap out of a scheduled slot, with FKs to the
assignment, original host, replacement host, and (eventually) the
LiveSession that fulfills the swap.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 2 — Host endpoints (TDD)

### Task 2: Host can submit a replacement request — happy path

**Files:**
- Test: `tests/Feature/SessionReplacement/RequestReplacementTest.php`
- Create: `app/Http/Requests/StoreReplacementRequest.php`
- Create: `app/Http/Controllers/LiveHostPocket/ReplacementRequestController.php`
- Modify: `routes/web.php` (add route inside the existing `live-host` Inertia group at line ~167)

**Step 1: Write the failing happy-path test**

Run:
```bash
php artisan make:test SessionReplacement/RequestReplacementTest --pest --no-interaction
```

Replace contents:

```php
<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '06:30:00',
        'end_time' => '08:30:00',
    ]);
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'time_slot_id' => $slot->id,
        'platform_account_id' => PlatformAccount::factory(),
        'day_of_week' => now()->addDay()->dayOfWeek,
        'is_template' => true,
    ]);
});

it('lets a host submit a one-date replacement request', function () {
    $targetDate = now()->addDay()->toDateString();

    $response = $this->actingAs($this->host)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'one_date',
            'target_date' => $targetDate,
            'reason_category' => 'sick',
            'reason_note' => 'Demam tinggi.',
        ]);

    $response->assertRedirect(route('live-host.schedule'));

    $this->assertDatabaseHas('session_replacement_requests', [
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
        'scope' => 'one_date',
        'target_date' => $targetDate,
        'reason_category' => 'sick',
        'status' => 'pending',
    ]);

    $created = SessionReplacementRequest::query()->latest('id')->first();
    expect($created->expires_at->toDateTimeString())
        ->toBe(now()->parse($targetDate)->setTimeFromTimeString('06:30:00')->toDateTimeString());
});
```

**Step 2: Run, see it fail**

Run:
```bash
php artisan test --compact --filter=RequestReplacementTest
```

Expected: route `live-host.replacement-requests.store` not defined.

**Step 3: Write the form request**

Run:
```bash
php artisan make:request StoreReplacementRequest --no-interaction
```

Replace the body:

```php
namespace App\Http\Requests;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = LiveScheduleAssignment::find($this->input('live_schedule_assignment_id'));

        return $assignment !== null
            && $assignment->live_host_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'live_schedule_assignment_id' => ['required', 'integer', 'exists:live_schedule_assignments,id'],
            'scope' => ['required', Rule::in([SessionReplacementRequest::SCOPE_ONE_DATE, SessionReplacementRequest::SCOPE_PERMANENT])],
            'target_date' => [
                'nullable',
                'required_if:scope,one_date',
                'date',
                'after_or_equal:today',
            ],
            'reason_category' => ['required', Rule::in(SessionReplacementRequest::REASON_CATEGORIES)],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'Sila pilih skop penggantian.',
            'target_date.required_if' => 'Sila pilih tarikh untuk penggantian sekali sahaja.',
            'target_date.after_or_equal' => 'Tarikh tidak boleh berada pada masa lampau.',
            'reason_category.required' => 'Sila pilih sebab permohonan.',
            'reason_note.max' => 'Catatan tidak boleh melebihi 500 aksara.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $assignment = LiveScheduleAssignment::find($this->input('live_schedule_assignment_id'));

            if ($this->input('scope') === SessionReplacementRequest::SCOPE_ONE_DATE) {
                $target = Carbon::parse($this->input('target_date'));
                if ($assignment && (int) $target->dayOfWeek !== (int) $assignment->day_of_week) {
                    $v->errors()->add('target_date', 'Tarikh yang dipilih tidak sepadan dengan hari slot ini.');

                    return;
                }
            }

            $duplicate = SessionReplacementRequest::query()
                ->where('live_schedule_assignment_id', $this->input('live_schedule_assignment_id'))
                ->where('status', SessionReplacementRequest::STATUS_PENDING)
                ->when(
                    $this->input('scope') === SessionReplacementRequest::SCOPE_ONE_DATE,
                    fn ($q) => $q->whereDate('target_date', $this->input('target_date')),
                    fn ($q) => $q->where('scope', SessionReplacementRequest::SCOPE_PERMANENT)
                )
                ->exists();

            if ($duplicate) {
                $v->errors()->add('live_schedule_assignment_id', 'Sudah ada permohonan tertunda untuk slot ini.');
            }
        });
    }
}
```

**Step 4: Write the controller**

Create `app/Http/Controllers/LiveHostPocket/ReplacementRequestController.php`:

```php
namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReplacementRequest;
use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReplacementRequestController extends Controller
{
    public function store(StoreReplacementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assignment = LiveScheduleAssignment::with('timeSlot')->findOrFail($data['live_schedule_assignment_id']);

        $expiresAt = $this->computeExpiresAt($data, $assignment);

        SessionReplacementRequest::create([
            'live_schedule_assignment_id' => $assignment->id,
            'original_host_id' => $request->user()->id,
            'scope' => $data['scope'],
            'target_date' => $data['target_date'] ?? null,
            'reason_category' => $data['reason_category'],
            'reason_note' => $data['reason_note'] ?? null,
            'status' => SessionReplacementRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return redirect()->route('live-host.schedule')
            ->with('success', 'Permohonan ganti telah dihantar.');
    }

    public function destroy(Request $request, SessionReplacementRequest $replacementRequest): RedirectResponse
    {
        abort_unless(
            $replacementRequest->original_host_id === $request->user()->id,
            403,
            'Anda tidak dibenarkan menarik balik permohonan ini.'
        );
        abort_unless(
            $replacementRequest->isPending(),
            422,
            'Permohonan ini tidak lagi boleh ditarik balik.'
        );

        $replacementRequest->update([
            'status' => SessionReplacementRequest::STATUS_WITHDRAWN,
        ]);

        return redirect()->route('live-host.schedule')
            ->with('success', 'Permohonan telah ditarik balik.');
    }

    private function computeExpiresAt(array $data, LiveScheduleAssignment $assignment): Carbon
    {
        if ($data['scope'] === SessionReplacementRequest::SCOPE_ONE_DATE) {
            $startTime = $assignment->timeSlot?->start_time ?? '00:00:00';
            $time = $startTime instanceof Carbon ? $startTime->format('H:i:s') : substr((string) $startTime, 0, 8);

            return Carbon::parse($data['target_date'])->setTimeFromTimeString($time);
        }

        return now()->addHours(24);
    }
}
```

**Step 5: Add the routes**

Open `routes/web.php` and locate the existing `Route::middleware(['auth', 'role:live_host', \App\Http\Middleware\HandlePocketInertiaRequests::class])->prefix('live-host')->name('live-host.')` group around line 166. Inside that group (alongside `Route::get('schedule', ...)`), add:

```php
Route::post('replacement-requests', [\App\Http\Controllers\LiveHostPocket\ReplacementRequestController::class, 'store'])
    ->name('replacement-requests.store');

Route::delete('replacement-requests/{replacementRequest}', [\App\Http\Controllers\LiveHostPocket\ReplacementRequestController::class, 'destroy'])
    ->name('replacement-requests.destroy');
```

**Step 6: Run the test, expect green**

```bash
php artisan test --compact --filter=RequestReplacementTest
```

Expected: 1 passed.

**Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add tests/Feature/SessionReplacement app/Http/Requests/StoreReplacementRequest.php app/Http/Controllers/LiveHostPocket/ReplacementRequestController.php routes/web.php
git commit -m "feat(live-host): host can submit a session replacement request

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Host store — validation cases

**Files:**
- Modify: `tests/Feature/SessionReplacement/RequestReplacementTest.php`

**Step 1: Add the failing tests**

Append to the test file:

```php
it('rejects past target_date', function () {
    $response = $this->actingAs($this->host)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'one_date',
            'target_date' => now()->subDay()->toDateString(),
            'reason_category' => 'sick',
        ]);

    $response->assertSessionHasErrors('target_date');
    expect(SessionReplacementRequest::count())->toBe(0);
});

it('rejects target_date that does not match the slot day_of_week', function () {
    // Pick a date whose day_of_week differs from the assignment's.
    $mismatch = now();
    while ((int) $mismatch->dayOfWeek === (int) $this->assignment->day_of_week) {
        $mismatch = $mismatch->addDay();
    }

    $response = $this->actingAs($this->host)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'one_date',
            'target_date' => $mismatch->toDateString(),
            'reason_category' => 'sick',
        ]);

    $response->assertSessionHasErrors('target_date');
});

it('forbids requesting against another hosts assignment', function () {
    $intruder = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($intruder)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'permanent',
            'reason_category' => 'personal',
        ]);

    $response->assertForbidden();
    expect(SessionReplacementRequest::count())->toBe(0);
});

it('blocks duplicate pending request for the same one_date slot', function () {
    SessionReplacementRequest::factory()
        ->pending()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
            'scope' => 'one_date',
            'target_date' => now()->addDay()->toDateString(),
        ]);

    $response = $this->actingAs($this->host)
        ->post(route('live-host.replacement-requests.store'), [
            'live_schedule_assignment_id' => $this->assignment->id,
            'scope' => 'one_date',
            'target_date' => now()->addDay()->toDateString(),
            'reason_category' => 'sick',
        ]);

    $response->assertSessionHasErrors('live_schedule_assignment_id');
    expect(SessionReplacementRequest::count())->toBe(1);
});
```

**Step 2: Run — all should already pass** (the form request was written to satisfy them):

```bash
php artisan test --compact --filter=RequestReplacementTest
```

Expected: 5 passed.

If any fail, fix the form request rules (most likely the `withValidator` after-hook or `authorize()`) — do NOT change the tests to match.

**Step 3: Commit**

```bash
git add tests/Feature/SessionReplacement
git commit -m "test(live-host): cover replacement request validation cases

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Host can withdraw a pending request

**Files:**
- Modify: `tests/Feature/SessionReplacement/RequestReplacementTest.php`

**Step 1: Add the test**

Append:

```php
it('lets the original host withdraw a pending request', function () {
    $req = SessionReplacementRequest::factory()
        ->pending()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $response = $this->actingAs($this->host)
        ->delete(route('live-host.replacement-requests.destroy', $req));

    $response->assertRedirect(route('live-host.schedule'));
    expect($req->fresh()->status)->toBe('withdrawn');
});

it('blocks withdrawal of a non-pending request', function () {
    $req = SessionReplacementRequest::factory()
        ->assigned()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $response = $this->actingAs($this->host)
        ->delete(route('live-host.replacement-requests.destroy', $req));

    $response->assertStatus(422);
    expect($req->fresh()->status)->toBe('assigned');
});

it('blocks withdrawal by someone other than the original host', function () {
    $req = SessionReplacementRequest::factory()
        ->pending()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $intruder = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($intruder)
        ->delete(route('live-host.replacement-requests.destroy', $req));

    $response->assertForbidden();
    expect($req->fresh()->status)->toBe('pending');
});
```

**Step 2: Run — should pass** (controller's `destroy` already handles all three cases):

```bash
php artisan test --compact --filter=RequestReplacementTest
```

Expected: 8 passed.

**Step 3: Commit**

```bash
git add tests
git commit -m "test(live-host): cover replacement request withdrawal

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 3 — PIC endpoints (TDD)

### Task 5: PIC index — list pending replacements

**Files:**
- Test: `tests/Feature/SessionReplacement/AssignReplacementTest.php`
- Create: `app/Http/Controllers/LiveHost/ReplacementRequestController.php`
- Create: `resources/js/livehost/pages/Replacements/Index.jsx` (stub for now — UI fleshed out in Phase 9)
- Modify: `routes/web.php` (add inside the `livehost` admin group at line ~234)

**Step 1: Write the test**

Run:
```bash
php artisan make:test SessionReplacement/AssignReplacementTest --pest --no-interaction
```

Replace contents:

```php
<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
    ]);
});

it('shows pending replacement requests on the PIC index', function () {
    $pending = SessionReplacementRequest::factory()
        ->pending()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $resolved = SessionReplacementRequest::factory()
        ->expired()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);

    $response = $this->actingAs($this->pic)
        ->get(route('livehost.replacements.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Replacements/Index')
            ->where('counts.pending', 1)
            ->where('counts.expired', 1)
            ->has('requests', 1, fn ($r) => $r->where('id', $pending->id)->etc())
    );
});

it('forbids non-PIC users from the index', function () {
    $response = $this->actingAs($this->host)->get(route('livehost.replacements.index'));
    $response->assertForbidden();
});
```

**Step 2: Run, see fail**

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: route `livehost.replacements.index` not defined.

**Step 3: Create the controller**

`app/Http/Controllers/LiveHost/ReplacementRequestController.php`:

```php
namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\SessionReplacementRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReplacementRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', SessionReplacementRequest::STATUS_PENDING);

        $requests = SessionReplacementRequest::query()
            ->with([
                'assignment.timeSlot',
                'assignment.platformAccount.platform',
                'originalHost:id,name,email',
                'replacementHost:id,name,email',
            ])
            ->where('status', $status)
            ->orderBy('expires_at')
            ->get()
            ->map(fn (SessionReplacementRequest $req) => [
                'id' => $req->id,
                'scope' => $req->scope,
                'targetDate' => $req->target_date?->toDateString(),
                'reasonCategory' => $req->reason_category,
                'reasonNote' => $req->reason_note,
                'status' => $req->status,
                'requestedAt' => $req->requested_at?->toIso8601String(),
                'expiresAt' => $req->expires_at?->toIso8601String(),
                'originalHost' => [
                    'id' => $req->originalHost?->id,
                    'name' => $req->originalHost?->name,
                ],
                'replacementHost' => $req->replacementHost ? [
                    'id' => $req->replacementHost->id,
                    'name' => $req->replacementHost->name,
                ] : null,
                'slot' => [
                    'dayOfWeek' => $req->assignment?->day_of_week,
                    'startTime' => $req->assignment?->timeSlot?->start_time,
                    'endTime' => $req->assignment?->timeSlot?->end_time,
                    'platformAccount' => $req->assignment?->platformAccount?->name,
                ],
            ]);

        $counts = SessionReplacementRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Replacements/Index', [
            'requests' => $requests,
            'currentStatus' => $status,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'assigned' => (int) ($counts['assigned'] ?? 0),
                'expired' => (int) ($counts['expired'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'withdrawn' => (int) ($counts['withdrawn'] ?? 0),
            ],
        ]);
    }
}
```

**Step 4: Add the route**

In `routes/web.php`, inside the existing `Route::middleware('role:admin,admin_livehost')->group(...)` at line ~234 of the `livehost` group, add:

```php
Route::get('replacements', [\App\Http\Controllers\LiveHost\ReplacementRequestController::class, 'index'])
    ->name('replacements.index');
```

**Step 5: Create the Inertia page stub**

Create `resources/js/livehost/pages/Replacements/Index.jsx` with a minimal component sufficient for `assertInertia` to find it:

```jsx
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/livehost/layouts/AdminLayout';

export default function Index() {
  const { requests, counts, currentStatus } = usePage().props;
  return (
    <>
      <Head title="Replacement Requests" />
      <div className="p-6">
        <h1 className="text-2xl font-semibold mb-4">Replacement Requests</h1>
        <p className="text-sm text-gray-600 mb-6">
          Pending: {counts?.pending ?? 0} · Assigned: {counts?.assigned ?? 0} ·
          Expired: {counts?.expired ?? 0}
        </p>
        <ul className="space-y-2">
          {requests.map((r) => (
            <li key={r.id} className="border rounded p-3">
              <div className="font-medium">{r.originalHost.name} — {r.slot.platformAccount}</div>
              <div className="text-xs text-gray-500">{r.scope} · {r.reasonCategory} · {r.status}</div>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}

Index.layout = (page) => <AdminLayout>{page}</AdminLayout>;
```

**Note:** confirm the actual layout component name by reading a sibling file under `resources/js/livehost/pages/` (e.g. `Dashboard.jsx`) and copy its `layout` import. Adjust if different.

**Step 6: Build assets**

```bash
npm run build
```

Expected: no Vite errors.

**Step 7: Run the test**

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: 2 passed.

**Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/ReplacementRequestController.php resources/js/livehost/pages/Replacements/Index.jsx routes/web.php tests/Feature/SessionReplacement/AssignReplacementTest.php
git commit -m "feat(livehost): PIC index for replacement requests

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: PIC show — single request + available hosts

**Files:**
- Modify: `tests/Feature/SessionReplacement/AssignReplacementTest.php`
- Modify: `app/Http/Controllers/LiveHost/ReplacementRequestController.php`
- Create: `resources/js/livehost/pages/Replacements/Show.jsx` (stub)
- Modify: `routes/web.php`

**Step 1: Add the failing test**

Append to `AssignReplacementTest.php`:

```php
it('shows the request with available replacement hosts excluding overlapping ones', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $candidate = User::factory()->create(['role' => 'live_host']);
    $busyHost = User::factory()->create(['role' => 'live_host']);

    // busyHost has an assignment overlapping the same time slot on the same day_of_week
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $busyHost->id,
        'day_of_week' => $this->assignment->day_of_week,
        'time_slot_id' => $this->assignment->time_slot_id,
    ]);

    $response = $this->actingAs($this->pic)
        ->get(route('livehost.replacements.show', $req));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Replacements/Show')
            ->where('request.id', $req->id)
            ->has(
                'availableHosts',
                fn ($hosts) => $hosts->where('id', $candidate->id)->etc()
            )
    );

    // Assert busyHost is NOT in availableHosts.
    $payload = $response->viewData('page')['props']['availableHosts'];
    expect(collect($payload)->pluck('id')->all())->not->toContain($busyHost->id);
});
```

**Step 2: Run, see fail**

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: route `livehost.replacements.show` not defined.

**Step 3: Add `show()` to the controller**

Append to `LiveHost\ReplacementRequestController`:

```php
public function show(SessionReplacementRequest $replacementRequest): Response
{
    $replacementRequest->load([
        'assignment.timeSlot',
        'assignment.platformAccount.platform',
        'originalHost:id,name,email',
        'replacementHost:id,name,email',
        'assignedBy:id,name',
    ]);

    $assignment = $replacementRequest->assignment;
    $availableHosts = $this->resolveAvailableHosts($replacementRequest);

    $repeatStat = SessionReplacementRequest::query()
        ->where('original_host_id', $replacementRequest->original_host_id)
        ->whereIn('status', [
            SessionReplacementRequest::STATUS_ASSIGNED,
            SessionReplacementRequest::STATUS_EXPIRED,
            SessionReplacementRequest::STATUS_WITHDRAWN,
        ])
        ->where('requested_at', '>=', now()->subDays(90))
        ->count();

    return Inertia::render('Replacements/Show', [
        'request' => [
            'id' => $replacementRequest->id,
            'scope' => $replacementRequest->scope,
            'status' => $replacementRequest->status,
            'targetDate' => $replacementRequest->target_date?->toDateString(),
            'reasonCategory' => $replacementRequest->reason_category,
            'reasonNote' => $replacementRequest->reason_note,
            'requestedAt' => $replacementRequest->requested_at?->toIso8601String(),
            'expiresAt' => $replacementRequest->expires_at?->toIso8601String(),
            'rejectionReason' => $replacementRequest->rejection_reason,
            'originalHost' => [
                'id' => $replacementRequest->originalHost?->id,
                'name' => $replacementRequest->originalHost?->name,
                'priorRequests90d' => $repeatStat,
            ],
            'replacementHost' => $replacementRequest->replacementHost ? [
                'id' => $replacementRequest->replacementHost->id,
                'name' => $replacementRequest->replacementHost->name,
            ] : null,
            'slot' => [
                'dayOfWeek' => $assignment?->day_of_week,
                'startTime' => $assignment?->timeSlot?->start_time,
                'endTime' => $assignment?->timeSlot?->end_time,
                'platformAccount' => $assignment?->platformAccount?->name,
            ],
        ],
        'availableHosts' => $availableHosts,
    ]);
}

private function resolveAvailableHosts(SessionReplacementRequest $req): array
{
    $assignment = $req->assignment;
    if (! $assignment) {
        return [];
    }

    $busyHostIds = LiveScheduleAssignment::query()
        ->where('day_of_week', $assignment->day_of_week)
        ->where('time_slot_id', $assignment->time_slot_id)
        ->where('status', '!=', 'cancelled')
        ->pluck('live_host_id')
        ->filter()
        ->all();

    return User::query()
        ->where('role', 'live_host')
        ->where('id', '!=', $req->original_host_id)
        ->whereNotIn('id', $busyHostIds)
        ->orderBy('name')
        ->get(['id', 'name', 'email'])
        ->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'priorReplacementsCount' => SessionReplacementRequest::query()
                ->where('replacement_host_id', $u->id)
                ->where('status', SessionReplacementRequest::STATUS_ASSIGNED)
                ->where('assigned_at', '>=', now()->subDays(90))
                ->count(),
        ])
        ->all();
}
```

Add the matching `use` statements at the top of the controller for `LiveScheduleAssignment` and `User`.

**Step 4: Add the route**

Inside the same admin group:

```php
Route::get('replacements/{replacementRequest}', [\App\Http\Controllers\LiveHost\ReplacementRequestController::class, 'show'])
    ->name('replacements.show');
```

**Step 5: Create the page stub**

`resources/js/livehost/pages/Replacements/Show.jsx`:

```jsx
import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/livehost/layouts/AdminLayout';

export default function Show() {
  const { request: req, availableHosts } = usePage().props;
  return (
    <>
      <Head title={`Replacement #${req.id}`} />
      <div className="p-6 max-w-3xl">
        <h1 className="text-2xl font-semibold mb-2">Replacement #{req.id}</h1>
        <p className="text-sm text-gray-600 mb-4">
          {req.originalHost.name} · {req.slot.platformAccount} · {req.scope}
        </p>
        <p className="text-xs text-gray-500 mb-6">
          Prior replacements (90d): {req.originalHost.priorRequests90d}
        </p>
        <h2 className="text-sm font-medium mb-2">Available hosts</h2>
        <ul className="space-y-1">
          {availableHosts.map((h) => (
            <li key={h.id} className="text-sm">
              {h.name} <span className="text-xs text-gray-500">({h.priorReplacementsCount} replacements done)</span>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}

Show.layout = (page) => <AdminLayout>{page}</AdminLayout>;
```

**Step 6: Build + run test**

```bash
npm run build
php artisan test --compact --filter=AssignReplacementTest
```

Expected: 3 passed.

**Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/ReplacementRequestController.php resources/js/livehost/pages/Replacements/Show.jsx routes/web.php tests
git commit -m "feat(livehost): PIC show page with available-host list

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: PIC assigns a replacement (one_date)

**Files:**
- Modify: `tests/Feature/SessionReplacement/AssignReplacementTest.php`
- Create: `app/Http/Requests/AssignReplacementRequest.php`
- Modify: `app/Http/Controllers/LiveHost/ReplacementRequestController.php` (add `assign` method)
- Modify: `routes/web.php`

**Step 1: Add failing tests**

Append:

```php
it('lets PIC assign a one_date replacement', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $candidate = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $candidate->id,
        ]);

    $response->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('assigned');
    expect($req->replacement_host_id)->toBe($candidate->id);
    expect($req->assigned_by_id)->toBe($this->pic->id);
    expect($req->assigned_at)->not->toBeNull();
});

it('rejects assigning a host who already has an overlapping slot', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $busy = User::factory()->create(['role' => 'live_host']);
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $busy->id,
        'day_of_week' => $this->assignment->day_of_week,
        'time_slot_id' => $this->assignment->time_slot_id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $busy->id,
        ]);

    $response->assertSessionHasErrors('replacement_host_id');
    expect($req->fresh()->status)->toBe('pending');
});

it('cannot re-assign an already-assigned request', function () {
    $candidate = User::factory()->create(['role' => 'live_host']);
    $req = SessionReplacementRequest::factory()->assigned($candidate)->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);
    $other = User::factory()->create(['role' => 'live_host']);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $other->id,
        ]);

    $response->assertStatus(422);
});
```

**Step 2: Run, see fail**

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: route `livehost.replacements.assign` not defined.

**Step 3: Create the form request**

```bash
php artisan make:request AssignReplacementRequest --no-interaction
```

Body:

```php
namespace App\Http\Requests;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'admin_livehost'], true);
    }

    public function rules(): array
    {
        return [
            'replacement_host_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_host_id.required' => 'Sila pilih pengganti.',
            'replacement_host_id.exists' => 'Pengganti yang dipilih tidak sah.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $req = $this->route('replacementRequest');
            if (! $req instanceof SessionReplacementRequest) {
                return;
            }

            $candidateId = (int) $this->input('replacement_host_id');

            if ($candidateId === (int) $req->original_host_id) {
                $v->errors()->add('replacement_host_id', 'Pengganti tidak boleh sama dengan pemohon.');

                return;
            }

            $busy = LiveScheduleAssignment::query()
                ->where('day_of_week', $req->assignment->day_of_week)
                ->where('time_slot_id', $req->assignment->time_slot_id)
                ->where('status', '!=', 'cancelled')
                ->where('live_host_id', $candidateId)
                ->exists();

            if ($busy) {
                $v->errors()->add('replacement_host_id', 'Pengganti sudah ada slot bertindih pada masa ini.');
            }
        });
    }
}
```

**Step 4: Add `assign()` to the controller**

```php
use App\Http\Requests\AssignReplacementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

public function assign(AssignReplacementRequest $request, SessionReplacementRequest $replacementRequest): RedirectResponse
{
    abort_unless(
        $replacementRequest->isPending(),
        422,
        'Permohonan ini tidak lagi tertunda.'
    );

    DB::transaction(function () use ($request, $replacementRequest) {
        $replacementRequest->update([
            'status' => SessionReplacementRequest::STATUS_ASSIGNED,
            'replacement_host_id' => $request->validated('replacement_host_id'),
            'assigned_at' => now(),
            'assigned_by_id' => $request->user()->id,
        ]);

        if ($replacementRequest->scope === SessionReplacementRequest::SCOPE_PERMANENT) {
            $replacementRequest->assignment()->update([
                'live_host_id' => $request->validated('replacement_host_id'),
            ]);
        }
    });

    return redirect()
        ->route('livehost.replacements.show', $replacementRequest)
        ->with('success', 'Pengganti telah ditetapkan.');
}
```

**Step 5: Add the route**

Inside the same admin group:

```php
Route::post('replacements/{replacementRequest}/assign', [\App\Http\Controllers\LiveHost\ReplacementRequestController::class, 'assign'])
    ->name('replacements.assign');
```

**Step 6: Run the tests**

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: 6 passed.

**Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/AssignReplacementRequest.php app/Http/Controllers/LiveHost/ReplacementRequestController.php routes/web.php tests
git commit -m "feat(livehost): PIC can assign a replacement to a pending request

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: PIC assigns a permanent replacement → ownership transfers

**Files:**
- Modify: `tests/Feature/SessionReplacement/AssignReplacementTest.php`

**Step 1: Add the failing test**

Append:

```php
it('transfers assignment ownership when scope is permanent', function () {
    $req = SessionReplacementRequest::factory()
        ->pending()
        ->permanent()
        ->create([
            'live_schedule_assignment_id' => $this->assignment->id,
            'original_host_id' => $this->host->id,
        ]);
    $candidate = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($this->pic)
        ->post(route('livehost.replacements.assign', $req), [
            'replacement_host_id' => $candidate->id,
        ]);

    expect($this->assignment->fresh()->live_host_id)->toBe($candidate->id);
    expect($req->fresh()->status)->toBe('assigned');
});
```

**Step 2: Run — should pass already** (Task 7's `assign()` already handles permanent):

```bash
php artisan test --compact --filter=AssignReplacementTest
```

Expected: 7 passed. If it fails, the transaction body in `assign()` is the suspect.

**Step 3: Commit**

```bash
git add tests
git commit -m "test(livehost): permanent replacement transfers slot ownership

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: PIC rejects a request

**Files:**
- Modify: `tests/Feature/SessionReplacement/AssignReplacementTest.php`
- Modify: `app/Http/Controllers/LiveHost/ReplacementRequestController.php` (add `reject` method)
- Modify: `routes/web.php`

**Step 1: Add the failing test**

Append:

```php
it('lets PIC reject a pending request with a reason', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.reject', $req), [
            'rejection_reason' => 'Slot tidak boleh diganti minggu ini.',
        ]);

    $response->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('rejected');
    expect($req->rejection_reason)->toBe('Slot tidak boleh diganti minggu ini.');
});

it('requires a rejection_reason', function () {
    $req = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $this->assignment->id,
        'original_host_id' => $this->host->id,
    ]);

    $response = $this->actingAs($this->pic)
        ->post(route('livehost.replacements.reject', $req), []);

    $response->assertSessionHasErrors('rejection_reason');
});
```

**Step 2: Run, see fail.**

**Step 3: Add `reject()` method**

In `LiveHost\ReplacementRequestController`:

```php
public function reject(Request $request, SessionReplacementRequest $replacementRequest): RedirectResponse
{
    abort_unless(in_array($request->user()->role, ['admin', 'admin_livehost'], true), 403);
    abort_unless($replacementRequest->isPending(), 422, 'Permohonan ini tidak lagi tertunda.');

    $data = $request->validate([
        'rejection_reason' => ['required', 'string', 'max:500'],
    ], [
        'rejection_reason.required' => 'Sila berikan sebab penolakan.',
        'rejection_reason.max' => 'Sebab tidak boleh melebihi 500 aksara.',
    ]);

    $replacementRequest->update([
        'status' => SessionReplacementRequest::STATUS_REJECTED,
        'rejection_reason' => $data['rejection_reason'],
    ]);

    return redirect()
        ->route('livehost.replacements.show', $replacementRequest)
        ->with('success', 'Permohonan telah ditolak.');
}
```

**Step 4: Add the route**

```php
Route::post('replacements/{replacementRequest}/reject', [\App\Http\Controllers\LiveHost\ReplacementRequestController::class, 'reject'])
    ->name('replacements.reject');
```

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=AssignReplacementTest
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/ReplacementRequestController.php routes/web.php tests
git commit -m "feat(livehost): PIC can reject a replacement request

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 4 — Notifications (Malay)

### Task 10: `ReplacementRequestedNotification` (host → PIC)

**Files:**
- Test: `tests/Feature/SessionReplacement/ReplacementNotificationsTest.php`
- Create: `app/Notifications/ReplacementRequestedNotification.php`
- Modify: `app/Http/Controllers/LiveHostPocket/ReplacementRequestController.php` (dispatch on `store`)

**Step 1: Write the failing test**

Run:
```bash
php artisan make:test SessionReplacement/ReplacementNotificationsTest --pest --no-interaction
```

Replace contents:

```php
<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Notifications\ReplacementRequestedNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('notifies admins (admin + admin_livehost) when a host submits a request', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);
    $host = User::factory()->create(['role' => 'live_host']);

    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '06:30:00', 'end_time' => '08:30:00',
    ]);
    $assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $host->id,
        'time_slot_id' => $slot->id,
        'platform_account_id' => PlatformAccount::factory(),
        'day_of_week' => now()->addDay()->dayOfWeek,
    ]);

    $this->actingAs($host)->post(route('live-host.replacement-requests.store'), [
        'live_schedule_assignment_id' => $assignment->id,
        'scope' => 'one_date',
        'target_date' => now()->addDay()->toDateString(),
        'reason_category' => 'sick',
    ]);

    Notification::assertSentTo([$admin, $pic], ReplacementRequestedNotification::class);
    Notification::assertNotSentTo($assistant, ReplacementRequestedNotification::class);
    Notification::assertNotSentTo($host, ReplacementRequestedNotification::class);
});
```

**Step 2: Run, see fail**

```bash
php artisan test --compact --filter=ReplacementNotificationsTest
```

Expected: class `ReplacementRequestedNotification` not found.

**Step 3: Create the notification**

```bash
php artisan make:notification ReplacementRequestedNotification --no-interaction
```

Replace body:

```php
namespace App\Notifications;

use App\Models\SessionReplacementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReplacementRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SessionReplacementRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $req = $this->request->loadMissing(['originalHost', 'assignment.timeSlot', 'assignment.platformAccount']);
        $host = $req->originalHost?->name ?? 'Live host';
        $platform = $req->assignment?->platformAccount?->name ?? 'Platform';
        $dayName = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'][$req->assignment?->day_of_week ?? 0];
        $time = sprintf('%s – %s',
            substr((string) ($req->assignment?->timeSlot?->start_time ?? ''), 0, 5),
            substr((string) ($req->assignment?->timeSlot?->end_time ?? ''), 0, 5)
        );
        $when = $req->scope === SessionReplacementRequest::SCOPE_ONE_DATE
            ? $req->target_date?->format('Y-m-d').' (sekali sahaja)'
            : 'Mulai segera (penggantian kekal)';

        $reasonLabel = [
            'sick' => 'Sakit',
            'family' => 'Kecemasan keluarga',
            'personal' => 'Urusan peribadi',
            'other' => 'Lain-lain',
        ][$req->reason_category] ?? $req->reason_category;

        return (new MailMessage)
            ->subject("Permohonan Ganti Slot — {$host} ({$dayName} ".substr((string) ($req->assignment?->timeSlot?->start_time ?? ''), 0, 5).')')
            ->greeting('Salam,')
            ->line("{$host} telah memohon penggantian untuk slot berikut:")
            ->line("**Platform:** {$platform}")
            ->line("**Slot:** {$dayName}, {$time}")
            ->line("**Tarikh:** {$when}")
            ->line("**Sebab:** {$reasonLabel}")
            ->line('**Catatan:** '.($req->reason_note ?: '—'))
            ->line('Sila tetapkan pengganti di pautan di bawah.')
            ->action('Lihat Permohonan', route('livehost.replacements.show', $req))
            ->salutation('Terima kasih, Mudeer Bedaie');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replacement_request_id' => $this->request->id,
            'original_host_id' => $this->request->original_host_id,
            'scope' => $this->request->scope,
            'target_date' => $this->request->target_date?->toDateString(),
        ];
    }
}
```

**Step 4: Wire dispatch into the host controller**

In `LiveHostPocket\ReplacementRequestController::store()`, after `SessionReplacementRequest::create([...])`, capture the model and notify admins:

```php
$replacement = SessionReplacementRequest::create([...]);

\Illuminate\Support\Facades\Notification::send(
    \App\Models\User::query()->whereIn('role', ['admin', 'admin_livehost'])->get(),
    new \App\Notifications\ReplacementRequestedNotification($replacement)
);
```

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=ReplacementNotificationsTest
vendor/bin/pint --dirty
git add app/Notifications/ReplacementRequestedNotification.php app/Http/Controllers/LiveHostPocket/ReplacementRequestController.php tests
git commit -m "feat(live-host): notify PIC + admins when host requests replacement (Malay)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: `ReplacementAssignedToYouNotification` (PIC → replacement host)

**Files:**
- Modify: `tests/Feature/SessionReplacement/ReplacementNotificationsTest.php`
- Create: `app/Notifications/ReplacementAssignedToYouNotification.php`
- Modify: `app/Http/Controllers/LiveHost/ReplacementRequestController.php` (dispatch in `assign`)

**Step 1: Add the failing test**

Append to `ReplacementNotificationsTest`:

```php
it('notifies the replacement host when PIC assigns them', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $candidate = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.assign', $req), [
        'replacement_host_id' => $candidate->id,
    ]);

    Notification::assertSentTo($candidate, \App\Notifications\ReplacementAssignedToYouNotification::class);
    Notification::assertNotSentTo($host, \App\Notifications\ReplacementAssignedToYouNotification::class);
});
```

**Step 2: Run, see fail**

**Step 3: Create the notification**

```bash
php artisan make:notification ReplacementAssignedToYouNotification --no-interaction
```

Body — same shape as Task 10, but:
- Subject: `"Anda Telah Ditugaskan Sebagai Pengganti — {dayName} {time}"`
- Greeting: `"Salam {$notifiable->name},"`
- Body lines: as in design doc Section 7.2 (Malay)
- Action button: `Lihat Jadual Saya` → `route('live-host.schedule')`

**Step 4: Wire dispatch into PIC controller's `assign()`**

After the `DB::transaction` block:

```php
$replacementRequest->refresh()->loadMissing('replacementHost');
$replacementRequest->replacementHost->notify(
    new \App\Notifications\ReplacementAssignedToYouNotification($replacementRequest)
);
```

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=ReplacementNotificationsTest
vendor/bin/pint --dirty
git add app/Notifications/ReplacementAssignedToYouNotification.php app/Http/Controllers/LiveHost/ReplacementRequestController.php tests
git commit -m "feat(livehost): notify replacement host when assigned by PIC (Malay)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: `ReplacementResolvedNotification` (→ original host) — assigned, rejected, expired

**Files:**
- Modify: `tests/Feature/SessionReplacement/ReplacementNotificationsTest.php`
- Create: `app/Notifications/ReplacementResolvedNotification.php`
- Modify: PIC controller `assign()` and `reject()` (already touched), and the cron command in Phase 5 will dispatch this too.

**Step 1: Add tests for assigned + rejected branches**

Append:

```php
it('notifies original host when PIC assigns', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $candidate = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.assign', $req), [
        'replacement_host_id' => $candidate->id,
    ]);

    Notification::assertSentTo($host, \App\Notifications\ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'assigned'
    );
});

it('notifies original host when PIC rejects', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.reject', $req), [
        'rejection_reason' => 'Tidak boleh diganti.',
    ]);

    Notification::assertSentTo($host, \App\Notifications\ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'rejected'
    );
});
```

**Step 2: Run, see fail**

**Step 3: Create the notification**

```bash
php artisan make:notification ReplacementResolvedNotification --no-interaction
```

Body:

```php
namespace App\Notifications;

use App\Models\SessionReplacementRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class ReplacementResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const RESOLUTION_ASSIGNED = 'assigned';
    public const RESOLUTION_REJECTED = 'rejected';
    public const RESOLUTION_EXPIRED = 'expired';

    public function __construct(
        public SessionReplacementRequest $request,
        public string $resolution,
    ) {
        if (! in_array($resolution, [self::RESOLUTION_ASSIGNED, self::RESOLUTION_REJECTED, self::RESOLUTION_EXPIRED], true)) {
            throw new InvalidArgumentException("Unknown resolution: {$resolution}");
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $req = $this->request->loadMissing('replacementHost');
        $body = match ($this->resolution) {
            self::RESOLUTION_ASSIGNED => 'Permohonan anda telah diluluskan. Pengganti: '.($req->replacementHost?->name ?? '—').'.',
            self::RESOLUTION_REJECTED => 'Permohonan anda ditolak oleh PIC. Sebab: '.($req->rejection_reason ?? '—').'. Anda masih bertanggungjawab untuk slot ini.',
            self::RESOLUTION_EXPIRED => 'Permohonan anda telah tamat tempoh tanpa pengganti dipilih. Sila pastikan anda hadir untuk slot ini, atau hubungi PIC dengan segera.',
        };

        $subject = match ($this->resolution) {
            self::RESOLUTION_ASSIGNED => 'Permohonan Ganti Telah Diluluskan',
            self::RESOLUTION_REJECTED => 'Permohonan Ganti Ditolak',
            self::RESOLUTION_EXPIRED => 'Permohonan Ganti Tamat Tempoh',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Salam {$notifiable->name},")
            ->line($body)
            ->action('Lihat Jadual Saya', route('live-host.schedule'))
            ->salutation('Terima kasih, Mudeer Bedaie');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replacement_request_id' => $this->request->id,
            'resolution' => $this->resolution,
        ];
    }
}
```

**Step 4: Wire dispatch in PIC controller**

In `assign()` after the existing notify-replacement line:

```php
$replacementRequest->originalHost->notify(
    new \App\Notifications\ReplacementResolvedNotification($replacementRequest, 'assigned')
);
```

In `reject()` after the `update()` call:

```php
$replacementRequest->originalHost->notify(
    new \App\Notifications\ReplacementResolvedNotification($replacementRequest, 'rejected')
);
```

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=ReplacementNotificationsTest
vendor/bin/pint --dirty
git add app/Notifications/ReplacementResolvedNotification.php app/Http/Controllers/LiveHost/ReplacementRequestController.php tests
git commit -m "feat(livehost): notify original host on resolution (assigned/rejected, Malay)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 5 — Auto-expiry

### Task 13: `ExpireReplacementRequestsCommand` + schedule

**Files:**
- Test: `tests/Feature/SessionReplacement/ExpireReplacementRequestsTest.php`
- Create: `app/Console/Commands/ExpireReplacementRequestsCommand.php`
- Modify: `routes/console.php`

**Step 1: Write the test**

```bash
php artisan make:test SessionReplacement/ExpireReplacementRequestsTest --pest --no-interaction
```

Body:

```php
<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('flips overdue pending requests to expired and notifies original host', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);

    $overdue = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->subMinute(),
    ]);

    $stillFresh = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('replacements:expire')->assertExitCode(0);

    expect($overdue->fresh()->status)->toBe('expired');
    expect($stillFresh->fresh()->status)->toBe('pending');

    Notification::assertSentTo($host, ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'expired' && $n->request->id === $overdue->id
    );
});

it('is idempotent — re-running does not double-fire notifications', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('replacements:expire')->assertExitCode(0);
    $this->artisan('replacements:expire')->assertExitCode(0);

    Notification::assertSentToTimes($host, ReplacementResolvedNotification::class, 1);
});
```

**Step 2: Run, see fail**

```bash
php artisan test --compact --filter=ExpireReplacementRequestsTest
```

Expected: command `replacements:expire` not registered.

**Step 3: Create the command**

```bash
php artisan make:command ExpireReplacementRequestsCommand --no-interaction
```

Body:

```php
namespace App\Console\Commands;

use App\Models\SessionReplacementRequest;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Console\Command;

class ExpireReplacementRequestsCommand extends Command
{
    protected $signature = 'replacements:expire';

    protected $description = 'Expire pending session replacement requests whose expires_at has passed.';

    public function handle(): int
    {
        $expired = 0;

        SessionReplacementRequest::query()
            ->where('status', SessionReplacementRequest::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->with('originalHost')
            ->each(function (SessionReplacementRequest $req) use (&$expired): void {
                $req->update(['status' => SessionReplacementRequest::STATUS_EXPIRED]);
                $req->originalHost?->notify(
                    new ReplacementResolvedNotification($req, ReplacementResolvedNotification::RESOLUTION_EXPIRED)
                );
                $expired++;
            });

        $this->info("Expired {$expired} replacement request(s).");

        return self::SUCCESS;
    }
}
```

**Step 4: Schedule it**

In `routes/console.php`, append:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('replacements:expire')->everyFiveMinutes();
```

(If a `Schedule::` import already exists at the top, skip the `use` line.)

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=ExpireReplacementRequestsTest
vendor/bin/pint --dirty
git add app/Console/Commands/ExpireReplacementRequestsCommand.php routes/console.php tests
git commit -m "feat(livehost): auto-expire overdue replacement requests

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 6 — Go-live guard

### Task 14: Block original host from going live on a replaced slot

**Files:**
- Test: `tests/Feature/SessionReplacement/AssignReplacementTest.php` (append)
- Modify: the controller responsible for creating a `LiveSession` when a host clicks "Go Live". Likely `app/Http/Controllers/LiveHostPocket/GoLiveController.php` — confirm by reading the file before editing.

**Step 1: Read the go-live controller**

```bash
grep -rn 'class GoLiveController\|LiveSession::create\|liveSession.*create' app/Http/Controllers/LiveHostPocket/
```

Read the file to confirm the entry point that creates a `LiveSession` for the current host. Note its method name and route.

**Step 2: Add a failing test**

Append to `AssignReplacementTest.php` (adjust the route name to match the actual go-live route discovered in Step 1):

```php
it('blocks the original host from going live on a slot already replaced today', function () {
    $candidate = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => now()->dayOfWeek,
    ]);
    \App\Models\SessionReplacementRequest::factory()->assigned($candidate)->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $this->host->id,
        'scope' => 'one_date',
        'target_date' => now()->toDateString(),
    ]);

    // Replace `live-host.go-live.start` below with the actual route name from Step 1.
    $response = $this->actingAs($this->host)
        ->post(route('live-host.go-live.start'), [
            'live_schedule_assignment_id' => $assignment->id,
        ]);

    $response->assertStatus(422);
    expect(\App\Models\LiveSession::where('live_host_id', $this->host->id)->count())->toBe(0);
});
```

**Step 3: Run, see fail.** If the route name guess is wrong, the test will error out — fix the route name in the test using what you found in Step 1, not by skipping the test.

**Step 4: Add the guard**

In the go-live controller's session-creation method, BEFORE creating the `LiveSession`:

```php
$replaced = \App\Models\SessionReplacementRequest::query()
    ->where('live_schedule_assignment_id', $assignmentId)
    ->where('status', \App\Models\SessionReplacementRequest::STATUS_ASSIGNED)
    ->where(function ($q) {
        $q->where('scope', \App\Models\SessionReplacementRequest::SCOPE_PERMANENT)
          ->orWhere(fn ($q) => $q->where('scope', \App\Models\SessionReplacementRequest::SCOPE_ONE_DATE)
              ->whereDate('target_date', now()->toDateString()));
    })
    ->where('replacement_host_id', '!=', $request->user()->id)
    ->exists();

abort_if($replaced, 422, 'Slot ini telah diganti. Sila hubungi PIC.');
```

**Step 5: Run + commit**

```bash
php artisan test --compact --filter=AssignReplacementTest
vendor/bin/pint --dirty
git add app/Http/Controllers tests
git commit -m "feat(live-host): block original host from going live on a replaced slot

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 7 — Reporting stat

### Task 15: Surface "Replacement requests (90d): N" on admin host detail page

**Files:**
- Modify: `app/Livewire/Admin/...` (the Volt or class-based component that renders the host detail page) — locate via `grep -rn 'live-host\|LiveHost' resources/views/livewire/admin/` and `grep -rn 'live-host\|LiveHost' app/Livewire/Admin/`
- No new tests for the read-only stat (covered by visual review).

**Step 1: Locate the host detail page**

```bash
grep -rln 'live[-_]host\|LiveHost' resources/views/livewire/admin/ app/Livewire/Admin/ | grep -i 'host\|live' | head
```

Identify the Volt file rendering the live-host (or live-host-applicant) detail page. If there's no admin host detail page yet, surface this stat on `/livehost/hosts/{host}` (Inertia React) — locate `resources/js/livehost/pages/hosts/Show.jsx` or similar.

**Step 2: Add the stat**

In the chosen view's data loader, compute:

```php
$replacementsLast90Days = \App\Models\SessionReplacementRequest::query()
    ->where('original_host_id', $host->id)
    ->where('requested_at', '>=', now()->subDays(90))
    ->count();
```

Then render it next to other stats:

```html
<div class="text-xs text-gray-500">
    Permohonan ganti (90 hari): <span class="font-medium">{{ $replacementsLast90Days }}</span>
</div>
```

(Use Inertia React equivalent if that's the chosen surface.)

**Step 3: Manual verification**

```bash
php artisan migrate
php artisan tinker --execute="echo \App\Models\SessionReplacementRequest::factory()->create(['original_host_id' => 1])->count();"
```

Browse to the host detail page in the dev server and confirm the stat appears. Mention this to the user as "verify visually before commit".

**Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Livewire app/Http/Controllers resources
git commit -m "feat(admin): show 90-day replacement count on host detail

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 8 — Host UI (Inertia React)

### Task 16: "Mohon ganti" link + badge states on the schedule card

**Files:**
- Modify: `resources/js/livehost-pocket/pages/Schedule.jsx`
- Modify: `app/Http/Controllers/LiveHostPocket/ScheduleController.php` (include each slot's pending-request status in the props)

**Step 1: Extend `ScheduleController` props**

In `index()`, when mapping each `LiveScheduleAssignment`, also load and surface a `replacementRequest` field:

```php
$activeRequests = \App\Models\SessionReplacementRequest::query()
    ->whereIn('live_schedule_assignment_id', $schedules->pluck('id'))
    ->whereIn('status', ['pending', 'assigned'])
    ->with('replacementHost:id,name')
    ->get()
    ->groupBy('live_schedule_assignment_id');
```

Then in the per-slot mapping, add:

```php
'replacementRequest' => optional($activeRequests->get($assignment->id)?->first(), function ($r) {
    return [
        'id' => $r->id,
        'status' => $r->status,
        'targetDate' => $r->target_date?->toDateString(),
        'replacementHostName' => $r->replacementHost?->name,
    ];
}),
```

(For the data flow, look at existing schedule controller around line 41 — adapt around the existing `$schedules->map(...)` call. You may need to first `->all()` and merge after, since you can't query inside the map.)

**Step 2: Update `Schedule.jsx`**

Modify `SlotCard` to render the badge + button states from the design (Section 5.1 of the design doc). Add a `<RequestModal>` component triggered by clicking "Mohon ganti".

Modal uses `useForm` from Inertia:

```jsx
import { useForm } from '@inertiajs/react';

const form = useForm({
  live_schedule_assignment_id: slot.id,
  scope: 'one_date',
  target_date: '',
  reason_category: 'sick',
  reason_note: '',
});

const submit = (e) => {
  e.preventDefault();
  form.post(route('live-host.replacement-requests.store'), {
    preserveScroll: true,
    onSuccess: () => setOpen(false),
  });
};
```

Withdraw button uses `useForm({}).delete(route('live-host.replacement-requests.destroy', req.id))`.

Reason dropdown options (bilingual labels, value is the enum):
- `sick` → "Sakit / Sick"
- `family` → "Kecemasan keluarga / Family emergency"
- `personal` → "Urusan peribadi / Personal"
- `other` → "Lain-lain / Other"

Date picker: `<input type="date">` with `min` set to today and an inline note: "Hanya tarikh pada hari {dayName} sahaja." Server-side validation already enforces day_of_week match, so no JS-level filtering needed.

**Step 3: Build assets**

```bash
npm run build
```

Then start dev server and visually verify on `/live-host/schedule` (login as `user@example.com` / `password`, but ensure that user has role `live_host` first, or use a seeded host).

**Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHostPocket/ScheduleController.php resources/js/livehost-pocket/pages/Schedule.jsx
git commit -m "feat(live-host): request-replacement modal on schedule card

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 9 — PIC UI (Inertia React)

### Task 17: Flesh out the PIC `Replacements/Index.jsx`

**Files:**
- Modify: `resources/js/livehost/pages/Replacements/Index.jsx`

**Step 1: Add tab navigation, filters, expiry countdown**

Replace the stub with a real index per the design's Section 6.1 mockup:

- Tabs: Pending, Assigned, Expired, Rejected, Withdrawn — each shows its count
- Filters: Host (search input), Platform (select), Date range (two date inputs)
- Each row shows host name, slot day/time, scope (`one_date` shows the `targetDate`, `permanent` shows a "PERMANENT" pill), reason category, and a live `Expires in Xh Ym` countdown computed from `expiresAt`
- Tab click triggers `router.get(route('livehost.replacements.index', { status: '<tab>' }))` with `preserveState: true`
- Each row links to `route('livehost.replacements.show', row.id)`

**Step 2: Build + visual check**

```bash
npm run build
```

Browse to `/livehost/replacements` as `admin@example.com` and verify visually.

**Step 3: Commit**

```bash
git add resources/js/livehost/pages/Replacements/Index.jsx
git commit -m "feat(livehost): full PIC replacement-requests index UI

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 18: Flesh out PIC `Replacements/Show.jsx` with assign/reject actions

**Files:**
- Modify: `resources/js/livehost/pages/Replacements/Show.jsx`

**Step 1: Build the show page** per Section 6.2 of the design:

- Top: request details (host, slot, scope, target date, reason, note, expiry countdown, prior 90-day count)
- Middle: "Available replacement hosts" list with radio selection. Each row shows name + `priorReplacementsCount` (sorted ascending).
- Footer: two forms — "Assign to selected" (POST `livehost.replacements.assign`) and "Reject request" (button opens an inline reason textarea, then POST `livehost.replacements.reject`).
- If `request.status !== 'pending'`, show a banner with the resolution and hide the action footer.

Use `useForm` for both actions. Show server-side validation errors inline.

**Step 2: Build + visual check**

```bash
npm run build
```

**Step 3: Commit**

```bash
git add resources/js/livehost/pages/Replacements/Show.jsx
git commit -m "feat(livehost): full PIC replacement-request show + assign/reject UI

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 19: Pending-replacements badge on the PIC dashboard home

**Files:**
- Modify: `app/Http/Controllers/LiveHost/DashboardController.php` (or whichever controller backs `/livehost`'s landing page — confirm via `grep -n '/livehost' routes/web.php`)
- Modify: `resources/js/livehost/pages/Dashboard.jsx`

**Step 1: Add the count to dashboard props**

```php
$pendingReplacements = \App\Models\SessionReplacementRequest::query()
    ->where('status', 'pending')
    ->count();
```

Pass as `pendingReplacements` in the Inertia render array.

**Step 2: Render a small badge in `Dashboard.jsx`**

Link the badge to `route('livehost.replacements.index')` so PIC clicks straight through.

**Step 3: Build + commit**

```bash
npm run build
vendor/bin/pint --dirty
git add app/Http/Controllers/LiveHost/DashboardController.php resources/js/livehost/pages/Dashboard.jsx
git commit -m "feat(livehost): pending-replacements badge on PIC dashboard

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 10 — Browser smoke test + final polish

### Task 20: Pest 4 browser smoke test — full happy path

**Files:**
- Test: `tests/Browser/SessionReplacementSmokeTest.php`

**Step 1: Write the test**

```bash
php artisan make:test --pest SessionReplacementSmokeTest --no-interaction
```

Move the file to `tests/Browser/SessionReplacementSmokeTest.php` if not there already, and replace contents:

```php
<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('host requests, PIC assigns, replacement sees the slot', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Sarah Chen']);
    $candidate = User::factory()->create(['role' => 'live_host', 'name' => 'Aiman Razak']);
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '06:30:00', 'end_time' => '08:30:00',
    ]);
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => PlatformAccount::factory(),
        'time_slot_id' => $slot->id,
        'day_of_week' => now()->addDay()->dayOfWeek,
    ]);

    // 1. Host opens schedule and requests replacement
    $page = visit('/login');
    $page->fill('email', $host->email)
        ->fill('password', 'password')
        ->click('Sign In')
        ->assertNoJavascriptErrors();

    $page = visit('/live-host/schedule');
    $page->click('Mohon ganti')
        ->select('reason_category', 'sick')
        ->click('Hantar permohonan')
        ->assertSee('Permohonan ganti telah dihantar');

    // 2. PIC logs in and assigns
    $this->actingAs($pic);
    $page = visit('/livehost/replacements');
    $page->click('Sarah Chen')
        ->click('Aiman Razak')
        ->click('Assign to selected')
        ->assertSee('Pengganti telah ditetapkan');

    // 3. Candidate sees the slot
    $this->actingAs($candidate);
    $page = visit('/live-host/schedule');
    $page->assertSee('06:30')->assertNoJavascriptErrors();
});
```

**Note:** Pest 4's `visit()` and `actingAs()` together within a single test file may need the helper from `Pest\Laravel\actingAs` — confirm by reading an existing browser test in the repo (`grep -rn 'visit(' tests/Browser/ | head`). Adjust if the auth flow differs.

**Step 2: Run**

```bash
php artisan test --compact tests/Browser/SessionReplacementSmokeTest.php
```

Expected: pass. If browser drivers need to be installed, run `npx playwright install` (or whatever Pest 4 expects).

**Step 3: Commit**

```bash
git add tests/Browser
git commit -m "test(livehost): browser smoke test for full replacement flow

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 21: Full suite + Pint + final cleanup

**Step 1: Run the entire suite**

```bash
php artisan test --compact
```

Expected: all green. If anything is red, fix before proceeding — do NOT mark this task complete with a red suite.

**Step 2: Pint everything**

```bash
vendor/bin/pint
```

Expected: all clean.

**Step 3: Visual smoke** — start dev server, log in as host, walk the whole flow, then as PIC:

```bash
composer run dev
```

Open in browser:
1. `http://mudeerbedaie.test/live-host/schedule` as a `live_host` — request replacement → see badge
2. `http://mudeerbedaie.test/livehost/replacements` as `admin_livehost` — assign → see flash
3. Back to host schedule — confirm "Telah diganti" badge
4. Switch to replacement host — confirm slot appears on their schedule

If the user sees no UI changes after edits, ask them to run `npm run dev` (per CLAUDE.md note about Vite manifest issues).

**Step 4: Update CLAUDE.md note about paradigm**

The project's `CLAUDE.md` claims `/live-host/*` is Volt. This work confirms it's actually Inertia React (Live Host Pocket). Add a short correction in `CLAUDE.md` under the "UI Paradigms" section:

> *Update 2026-04-25: `/live-host/*` is now an Inertia React "Live Host Pocket" app at `resources/js/livehost-pocket/` (separate from `/livehost/*` PIC dashboard). New live-host-facing host UI extends this pocket app.*

**Step 5: Final commit**

```bash
git add CLAUDE.md
git commit -m "docs: correct CLAUDE.md note on /live-host/* paradigm (Inertia, not Volt)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Done criteria

- [ ] All 21 tasks committed.
- [ ] `php artisan test --compact` green.
- [ ] `vendor/bin/pint --test` green.
- [ ] Visual smoke walkthrough passes (host → PIC → replacement host).
- [ ] CLAUDE.md updated with paradigm correction.
- [ ] Design doc and plan doc both committed under `docs/plans/`.

## Out of scope (deferred to future iterations)

- `live_session_id` on `session_replacement_requests` is nullable and not populated in v1. Add a hook in the "go live" controller in a follow-up to link the resulting `LiveSession` to the replacement request for tighter audit traceability.
- Configurable per-platform expiry windows (currently hardcoded: slot start for `one_date`, +24h for `permanent`).
- Automated lockout / cap on requests per host per period (we surface the count; we don't act on it).
- SMS / WhatsApp notifications (mail + database channels only in v1).
