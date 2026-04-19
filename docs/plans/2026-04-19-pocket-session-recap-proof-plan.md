# Pocket Session Recap — Proof of Live & Missed-Session Flow — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Add a proof-of-live attachment requirement and a structured "did not go live" flow to the Live Host Pocket session recap, so hosts can self-report outcomes with trustworthy artifacts and admins get clean reporting.

**Architecture:** Branch the existing `POST /live-host/sessions/{session}/recap` endpoint on a new `went_live` boolean. When `true`, require ≥1 image/video attachment before flipping status to `ended`. When `false`, flip status to a new `missed` value with a preset reason code + optional note. Expose a `canRecap` flag via the session DTO so the Sessions list can surface a CTA on past-scheduled cards. Migration adds `missed_reason_code`, `missed_reason_note`, and widens the `status` enum dual-driver style (MySQL + SQLite) per CLAUDE.md.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Pest 4, Tailwind 4. Files live under `app/Http/Controllers/LiveHostPocket/`, `app/Http/Requests/LiveHostPocket/`, `resources/js/livehost-pocket/`.

**Design doc:** [`docs/plans/2026-04-19-pocket-session-recap-proof-design.md`](2026-04-19-pocket-session-recap-proof-design.md)

---

## Task 1: Migration — add missed columns + widen status enum

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_missed_recap_to_live_sessions.php`

**Step 1: Generate the migration**

Run: `php artisan make:migration add_missed_recap_to_live_sessions --table=live_sessions --no-interaction`
Expected: creates a new file under `database/migrations/`.

**Step 2: Fill in the migration** (MySQL + SQLite dual-driver, per CLAUDE.md rule about MySQL-vs-SQLite enum handling)

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
        // Add the two new reason columns first — these are safe on both drivers.
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->string('missed_reason_code', 32)->nullable()->after('remarks');
            $table->text('missed_reason_note')->nullable()->after('missed_reason_code');
        });

        // Widen the status enum to include 'missed'. MySQL needs an explicit
        // ALTER with the new enum list; SQLite doesn't enforce enums so the
        // values are already accepted and no schema change is required.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE live_sessions MODIFY status ENUM('scheduled','live','ended','cancelled','missed') NOT NULL DEFAULT 'scheduled'"
            );
        }
    }

    public function down(): void
    {
        // Remap any 'missed' rows back to 'cancelled' so narrowing the enum
        // doesn't fail. Then drop the new columns.
        if (Schema::hasTable('live_sessions')) {
            DB::table('live_sessions')->where('status', 'missed')->update(['status' => 'cancelled']);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE live_sessions MODIFY status ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled'"
            );
        }

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['missed_reason_code', 'missed_reason_note']);
        });
    }
};
```

**Step 3: Run the migration**

Run: `php artisan migrate --no-interaction`
Expected: `Migrated: ..._add_missed_recap_to_live_sessions`.

**Step 4: Verify schema**

Run: `php artisan tinker --execute="echo json_encode(\\Schema::getColumnListing('live_sessions'));"`
Expected: output contains `missed_reason_code` and `missed_reason_note`.

**Step 5: Commit**

```bash
git add database/migrations/*_add_missed_recap_to_live_sessions.php
git commit -m "feat(livehost-pocket): add missed-reason columns + widen status enum"
```

---

## Task 2: Model updates — fillable, casts, helpers

**Files:**
- Modify: `app/Models/LiveSession.php`

**Step 1: Add fillable + status helper**

Open the file and find the `$fillable` array (near line 24). Extend it:

```php
protected $fillable = [
    // ... existing fields ...
    'missed_reason_code',
    'missed_reason_note',
];
```

Find the block of status helpers (`isScheduled`, `isLive`, `isEnded`, `isCancelled` around line 180–200) and add:

```php
public function isMissed(): bool
{
    return $this->status === 'missed';
}

/**
 * A session can receive a recap submission from the host when it has
 * already ended, already been marked missed, or was scheduled but the
 * clock has passed its scheduled start. Future scheduled sessions are
 * excluded — hosts shouldn't be recapping sessions that haven't happened.
 */
public function canRecap(): bool
{
    if (in_array($this->status, ['ended', 'missed'], true)) {
        return true;
    }

    return $this->status === 'scheduled'
        && $this->scheduled_start_at !== null
        && $this->scheduled_start_at->lte(now());
}

/**
 * Proof of live: at least one image or video attachment exists for this
 * session. Used by SaveRecapRequest when went_live=true to block the
 * status transition until the host has uploaded visible evidence.
 */
public function hasVisualProof(): bool
{
    return $this->attachments()
        ->where(function ($q) {
            $q->where('file_type', 'like', 'image/%')
              ->orWhere('file_type', 'like', 'video/%');
        })
        ->exists();
}
```

Also update the `badgeColor()` match (around line 214) to cover `missed`:

```php
return match ($this->status) {
    'scheduled' => 'blue',
    'live'      => 'red',
    'ended'     => 'gray',
    'cancelled' => 'red',
    'missed'    => 'amber',
    default     => 'gray',
};
```

**Step 2: Run existing model tests to confirm nothing breaks**

Run: `php artisan test --compact --filter=LiveSession`
Expected: all existing tests still pass.

**Step 3: Commit**

```bash
git add app/Models/LiveSession.php
git commit -m "feat(livehost-pocket): add canRecap/hasVisualProof/isMissed helpers"
```

---

## Task 3: FormRequest — extend SaveRecapRequest with went_live + proof check

**Files:**
- Modify: `app/Http/Requests/LiveHostPocket/SaveRecapRequest.php`

**Step 1: Write the failing tests first**

Create: `tests/Feature/LiveHostPocket/SessionRecapTest.php`

```php
<?php

use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $this->host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(2),
        ]);
});

it('rejects went_live=true without any image or video attachment', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['proof']);
    expect($this->session->fresh()->status)->toBe('scheduled');
});

it('accepts went_live=true with an image attachment and flips status to ended', function () {
    actingAs($this->host);

    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'file_type' => 'image/png',
    ]);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
        'viewers_peak' => 42,
    ]);

    $response->assertRedirect();
    expect($this->session->fresh()->status)->toBe('ended');
});

it('accepts went_live=false with a valid reason code and flips status to missed', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'tech_issue',
        'missed_reason_note' => 'Internet dropped at start time.',
    ]);

    $response->assertRedirect();
    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe('missed');
    expect($fresh->missed_reason_code)->toBe('tech_issue');
    expect($fresh->missed_reason_note)->toBe('Internet dropped at start time.');
});

it('rejects went_live=false without a reason code', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['missed_reason_code']);
});

it('rejects went_live=false with an invalid reason code', function () {
    actingAs($this->host);

    $response = postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'bogus',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['missed_reason_code']);
});

it('preserves analytics when flipping from missed back to went_live', function () {
    actingAs($this->host);

    // First, mark as missed.
    postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => false,
        'missed_reason_code' => 'sick',
    ])->assertRedirect();

    // Seed analytics + attachment directly (simulating prior recap data kept on row).
    LiveSessionAttachment::factory()->create([
        'live_session_id' => $this->session->id,
        'file_type' => 'video/mp4',
    ]);
    $this->session->analytics()->create(['viewers_peak' => 100]);

    // Now flip to went_live=true.
    postJson("/live-host/sessions/{$this->session->id}/recap", [
        'went_live' => true,
        'actual_start_at' => now()->subHours(2)->toIso8601String(),
        'actual_end_at' => now()->subHour()->toIso8601String(),
        'viewers_peak' => 150,
    ])->assertRedirect();

    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe('ended');
    expect($fresh->missed_reason_code)->toBeNull();
    expect($fresh->missed_reason_note)->toBeNull();
    expect($fresh->analytics->viewers_peak)->toBe(150);
});
```

**Step 2: Run the tests to confirm they fail**

Run: `php artisan test --compact tests/Feature/LiveHostPocket/SessionRecapTest.php`
Expected: multiple failures — `proof` field not validated, `went_live` not respected, `missed_reason_code` unknown, etc.

**Step 3: Rewrite `SaveRecapRequest`** to branch on `went_live` + enforce proof

```php
<?php

namespace App\Http\Requests\LiveHostPocket;

use App\Models\LiveSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request payload for POST /live-host/sessions/{session}/recap.
 *
 * Branches on the required `went_live` boolean. When true, all timing +
 * analytics fields are accepted but nullable, and an after-hook requires
 * at least one image/video attachment on the session row as proof. When
 * false, a `missed_reason_code` enum is required and everything else is
 * ignored.
 */
class SaveRecapRequest extends FormRequest
{
    public const MISSED_REASONS = [
        'tech_issue',
        'sick',
        'account_issue',
        'schedule_conflict',
        'other',
    ];

    public function authorize(): bool
    {
        return $this->user()?->role === 'live_host';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'went_live' => ['required', 'boolean'],

            // went_live === true branch
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'actual_start_at' => ['nullable', 'date'],
            'actual_end_at' => ['nullable', 'date', 'after:actual_start_at'],
            'viewers_peak' => ['nullable', 'integer', 'min:0'],
            'viewers_avg' => ['nullable', 'integer', 'min:0'],
            'total_likes' => ['nullable', 'integer', 'min:0'],
            'total_comments' => ['nullable', 'integer', 'min:0'],
            'total_shares' => ['nullable', 'integer', 'min:0'],
            'gifts_value' => ['nullable', 'numeric', 'min:0'],

            // went_live === false branch
            'missed_reason_code' => [
                'required_if:went_live,false',
                'nullable',
                'in:' . implode(',', self::MISSED_REASONS),
            ],
            'missed_reason_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Proof-of-live guard: when the host claims they went live, require at
     * least one image or video attachment on this session. Runs after the
     * rule-based validation so we don't waste a DB query on a payload that
     * already failed the basic shape.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('went_live')) {
                return;
            }

            /** @var LiveSession|null $session */
            $session = $this->route('session');
            if (! $session instanceof LiveSession) {
                return;
            }

            if (! $session->hasVisualProof()) {
                $validator->errors()->add(
                    'proof',
                    'Upload at least one image or video as proof you went live.'
                );
            }
        });
    }
}
```

**Step 4: Run the tests again — they should still be red because the controller hasn't branched yet**

Run: `php artisan test --compact tests/Feature/LiveHostPocket/SessionRecapTest.php`
Expected: the first proof test now passes (422 on no attachment), but the others still fail because the controller doesn't route on `went_live`.

**Step 5: Commit (progress checkpoint — validation layer green)**

```bash
git add app/Http/Requests/LiveHostPocket/SaveRecapRequest.php tests/Feature/LiveHostPocket/SessionRecapTest.php
git commit -m "test(livehost-pocket): red tests + SaveRecapRequest with proof guard"
```

---

## Task 4: Controller — branch saveRecap on went_live

**Files:**
- Modify: `app/Http/Controllers/LiveHostPocket/SessionDetailController.php`

**Step 1: Replace the `saveRecap` method body**

Find the `saveRecap` method (currently spanning roughly lines 58–106). Replace the whole method with:

```php
public function saveRecap(SaveRecapRequest $request, LiveSession $session): RedirectResponse
{
    abort_unless($session->live_host_id === $request->user()->id, 403);

    $data = $request->validated();

    if ($request->boolean('went_live')) {
        $this->persistWentLive($request, $session, $data);
    } else {
        $this->persistMissed($request, $session, $data);
    }

    return redirect()
        ->route('live-host.sessions.show', $session)
        ->with('success', $request->boolean('went_live') ? 'Recap saved.' : 'Session marked as missed.');
}

/**
 * Persist a "went live" recap: cover image, timings, analytics, remarks,
 * and flip status to `ended`. Previously-captured missed-reason fields
 * are cleared so the row stays clean.
 */
private function persistWentLive(SaveRecapRequest $request, LiveSession $session, array $data): void
{
    if ($request->hasFile('cover_image')) {
        if ($session->image_path) {
            Storage::disk('public')->delete($session->image_path);
        }
        $session->image_path = $request->file('cover_image')->store('live-sessions', 'public');
    }

    $actualStart = $data['actual_start_at'] ?? null;
    $actualEnd = $data['actual_end_at'] ?? null;

    $duration = $session->duration_minutes;
    if ($actualStart && $actualEnd) {
        $duration = (int) Carbon::parse($actualStart)->diffInMinutes(Carbon::parse($actualEnd));
    }

    $session->update([
        'image_path' => $session->image_path,
        'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $session->remarks,
        'actual_start_at' => $actualStart ?? $session->actual_start_at,
        'actual_end_at' => $actualEnd ?? $session->actual_end_at,
        'duration_minutes' => $duration,
        'uploaded_at' => now(),
        'uploaded_by' => $request->user()->id,
        'status' => 'ended',
        'missed_reason_code' => null,
        'missed_reason_note' => null,
    ]);

    LiveAnalytics::updateOrCreate(
        ['live_session_id' => $session->id],
        [
            'viewers_peak' => $data['viewers_peak'] ?? 0,
            'viewers_avg' => $data['viewers_avg'] ?? 0,
            'total_likes' => $data['total_likes'] ?? 0,
            'total_comments' => $data['total_comments'] ?? 0,
            'total_shares' => $data['total_shares'] ?? 0,
            'gifts_value' => $data['gifts_value'] ?? 0,
            'duration_minutes' => $duration ?? 0,
        ]
    );
}

/**
 * Persist a "did not go live" recap: set status to `missed` with the
 * supplied reason code + note. Analytics and attachments are intentionally
 * left untouched so a host who flips back to "went live" doesn't lose the
 * data they already entered.
 */
private function persistMissed(SaveRecapRequest $request, LiveSession $session, array $data): void
{
    $session->update([
        'status' => 'missed',
        'missed_reason_code' => $data['missed_reason_code'],
        'missed_reason_note' => $data['missed_reason_note'] ?? null,
        'uploaded_at' => now(),
        'uploaded_by' => $request->user()->id,
    ]);
}
```

**Step 2: Run the full test file — all 6 tests should pass now**

Run: `php artisan test --compact tests/Feature/LiveHostPocket/SessionRecapTest.php`
Expected: all tests in the file PASS.

**Step 3: Run Pint**

Run: `vendor/bin/pint --dirty`
Expected: `{"result":"pass"}`.

**Step 4: Commit**

```bash
git add app/Http/Controllers/LiveHostPocket/SessionDetailController.php
git commit -m "feat(livehost-pocket): branch saveRecap on went_live + persist missed-reason"
```

---

## Task 5: DTO updates — expose canRecap + missed fields

**Files:**
- Modify: `app/Http/Controllers/LiveHostPocket/SessionsController.php`
- Modify: `app/Http/Controllers/LiveHostPocket/SessionDetailController.php`

**Step 1: Write the failing test for the `canRecap` flag**

Append to `tests/Feature/LiveHostPocket/SessionRecapTest.php`:

```php
it('exposes canRecap=true on scheduled sessions past their start time', function () {
    actingAs($this->host);

    $response = $this->get('/live-host/sessions?filter=upcoming');
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $row = collect($props['sessions']['data'])->firstWhere('id', $this->session->id);

    expect($row)->not->toBeNull();
    expect($row['canRecap'])->toBeTrue();
});

it('exposes canRecap=false on scheduled sessions still in the future', function () {
    actingAs($this->host);

    $future = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $this->host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->addHours(5),
        ]);

    $response = $this->get('/live-host/sessions?filter=upcoming');
    $props = $response->viewData('page')['props'];
    $row = collect($props['sessions']['data'])->firstWhere('id', $future->id);

    expect($row['canRecap'])->toBeFalse();
});
```

**Step 2: Run the tests to confirm they fail**

Run: `php artisan test --compact tests/Feature/LiveHostPocket/SessionRecapTest.php`
Expected: the two new tests fail — `canRecap` key missing from DTO.

**Step 3: Extend `SessionsController::sessionDto`**

In `SessionsController.php`, update `sessionDto()` to include the new fields:

```php
private function sessionDto(LiveSession $session): array
{
    $analytics = $session->analytics;

    return [
        'id' => $session->id,
        'title' => $session->title,
        'status' => $session->status,
        'platformAccount' => $session->platformAccount?->name,
        'platformType' => $session->platformAccount?->platform?->slug,
        'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
        'actualStartAt' => $session->actual_start_at?->toIso8601String(),
        'actualEndAt' => $session->actual_end_at?->toIso8601String(),
        'durationMinutes' => $session->duration_minutes,
        'canRecap' => $session->canRecap(),
        'missedReasonCode' => $session->missed_reason_code,
        'missedReasonNote' => $session->missed_reason_note,
        'analytics' => $analytics ? [
            'viewersPeak' => (int) $analytics->viewers_peak,
            'totalLikes' => (int) $analytics->total_likes,
            'giftsValue' => (float) $analytics->gifts_value,
        ] : null,
    ];
}
```

Also update the `match` on line 34 of the same file so the Upcoming tab surfaces past-scheduled rows (they should be visible there with a CTA):

```php
match ($filter) {
    'upcoming' => $query
        ->whereIn('status', ['scheduled', 'live'])
        ->orderByRaw("CASE WHEN status = 'live' THEN 0
                            WHEN status = 'scheduled' AND scheduled_start_at <= ? THEN 1
                            ELSE 2 END", [now()])
        ->orderBy('scheduled_start_at'),
    'ended' => $query
        ->whereIn('status', ['ended', 'cancelled', 'missed'])
        ->orderByDesc('scheduled_start_at'),
    default => $query->orderByDesc('scheduled_start_at'),
};
```

**Step 4: Extend `SessionDetailController::sessionDto`** (same class, its own private helper)

```php
private function sessionDto(LiveSession $session): array
{
    return [
        'id' => $session->id,
        'title' => $session->title,
        'description' => $session->description,
        'status' => $session->status,
        'remarks' => $session->remarks,
        'platformAccount' => $session->platformAccount?->name,
        'platformType' => $session->platformAccount?->platform?->slug,
        'platformName' => $session->platformAccount?->platform?->name,
        'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
        'actualStartAt' => $session->actual_start_at?->toIso8601String(),
        'actualEndAt' => $session->actual_end_at?->toIso8601String(),
        'durationMinutes' => $session->duration_minutes,
        'imagePath' => $session->image_path,
        'imageUrl' => $session->image_path ? Storage::url($session->image_path) : null,
        'uploadedAt' => $session->uploaded_at?->toIso8601String(),
        'canRecap' => $session->canRecap(),
        'missedReasonCode' => $session->missed_reason_code,
        'missedReasonNote' => $session->missed_reason_note,
    ];
}
```

**Step 5: Run the tests — everything should be green**

Run: `php artisan test --compact tests/Feature/LiveHostPocket/SessionRecapTest.php`
Expected: all 8 tests PASS.

**Step 6: Commit**

```bash
git add app/Http/Controllers/LiveHostPocket/SessionsController.php app/Http/Controllers/LiveHostPocket/SessionDetailController.php tests/Feature/LiveHostPocket/SessionRecapTest.php
git commit -m "feat(livehost-pocket): expose canRecap + missed fields + sort awaiting-recap to top"
```

---

## Task 6: Frontend — Sessions list card states

**Files:**
- Modify: `resources/js/livehost-pocket/pages/Sessions.jsx`

**Step 1: Add the reason-label helper**

At the top of `Sessions.jsx` (after the imports), add:

```jsx
const MISSED_REASON_LABELS = {
  tech_issue: 'Tech issue',
  sick: 'Sick',
  account_issue: 'Account issue',
  schedule_conflict: 'Schedule conflict',
  other: 'Other',
};

function labelForMissedReason(code) {
  return MISSED_REASON_LABELS[code] ?? 'Missed';
}
```

**Step 2: Replace the `SessionCard` body** to handle the new derived states

Find `SessionCard` (around line 113) and replace the `{(isEnded || isLive) ? ...` ternary + `{isCancelled ? ...` + `{isScheduled ? ...` block with:

```jsx
const isMissed = status === 'missed';
const canRecap = Boolean(session.canRecap);
const isAwaitingRecap = isScheduled && canRecap;
```

Then replace the footer section (currently from the `(isEnded || isLive)` link down to the `isScheduled` footer) with:

```jsx
{isLive ? (
  <Link
    href={`/live-host/sessions/${session.id}`}
    className="mt-[10px] block border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]"
  >
    Manage session &rarr;
  </Link>
) : null}

{isEnded ? (
  <Link
    href={`/live-host/sessions/${session.id}`}
    className="mt-[10px] block border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]"
  >
    Open recap &amp; upload &rarr;
  </Link>
) : null}

{isAwaitingRecap ? (
  <div className="mt-[10px] flex gap-[6px] border-t border-[var(--hair)] pt-[10px]">
    <Link
      href={`/live-host/sessions/${session.id}?recap=yes`}
      className="flex-[2] rounded-[10px] bg-[var(--accent)] px-[10px] py-[8px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent-ink)]"
    >
      Submit recap &rarr;
    </Link>
    <Link
      href={`/live-host/sessions/${session.id}?recap=no`}
      className="flex-1 rounded-[10px] border border-[var(--hair)] px-[10px] py-[8px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
    >
      Didn&apos;t go live
    </Link>
  </div>
) : null}

{isMissed ? (
  <div className="mt-[10px] border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--hot)]">
    Missed &middot; {labelForMissedReason(session.missedReasonCode)}
  </div>
) : null}

{isCancelled ? (
  <div className="mt-[10px] border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
    Session cancelled
  </div>
) : null}

{isScheduled && !isAwaitingRecap ? <ScheduledFooter session={session} /> : null}
```

**Step 3: Add the new `StatusChip` variants** — extend the component (around line 251) to handle `missed` and a new `awaiting-recap` label

Add two new branches before the final `ended` fallback:

```jsx
if (status === 'missed') {
  return (
    <span
      className={cn(base, 'text-[var(--hot)]')}
      style={{ backgroundColor: 'rgba(225,29,72,0.1)' }}
    >
      MISSED
    </span>
  );
}
```

And add a visual distinction for awaiting-recap — the simplest approach is to pass the derived state in. Add a prop `awaitingRecap` to `StatusChip`:

```jsx
function StatusChip({ status, awaitingRecap = false }) {
  const base = 'inline-flex items-center rounded-full px-[7px] py-[3px] font-mono text-[8.5px] font-extrabold uppercase tracking-[0.14em]';

  if (awaitingRecap) {
    return (
      <span
        className={cn(base, 'text-[var(--hot)]')}
        style={{ backgroundColor: 'rgba(245,158,11,0.12)' }}
      >
        RECAP PENDING
      </span>
    );
  }

  // ... existing branches, plus the new `missed` branch above
}
```

And update the call site in `SessionCard`:

```jsx
<StatusChip status={status} awaitingRecap={isAwaitingRecap} />
```

**Step 4: Manual smoke check**

Run: `npm run dev` (in a separate terminal if not already running).
Then navigate to `/live-host/sessions?filter=upcoming` in a browser signed in as a host with a past-scheduled session.
Expected: card shows `RECAP PENDING` chip + `Submit recap →` and `Didn't go live` buttons.

**Step 5: Commit**

```bash
git add resources/js/livehost-pocket/pages/Sessions.jsx
git commit -m "feat(livehost-pocket): awaiting-recap card state + missed chip + dual CTAs"
```

---

## Task 7: Frontend — SessionDetail Yes/No switch + proof validation + missed form

**Files:**
- Modify: `resources/js/livehost-pocket/pages/SessionDetail.jsx`

**Step 1: Add reason constants near the top (below the imports)**

```jsx
const MISSED_REASONS = [
  { code: 'tech_issue', label: 'Tech / connection issue' },
  { code: 'sick', label: 'Sick' },
  { code: 'account_issue', label: 'Platform account issue' },
  { code: 'schedule_conflict', label: 'Schedule conflict' },
  { code: 'other', label: 'Other' },
];
```

**Step 2: Extend the `useForm` state** — add `went_live`, `missed_reason_code`, `missed_reason_note`

Find `const recap = useForm({` (around line 29). Replace with:

```jsx
const recap = useForm({
  went_live: session?.status === 'missed' ? false : (session?.status === 'ended' ? true : null),
  cover_image: null,
  actual_start_at: toLocalDatetime(session?.actualStartAt),
  actual_end_at: toLocalDatetime(session?.actualEndAt),
  remarks: session?.remarks ?? '',
  viewers_peak: analytics?.viewersPeak ?? 0,
  viewers_avg: analytics?.viewersAvg ?? 0,
  total_likes: analytics?.totalLikes ?? 0,
  total_comments: analytics?.totalComments ?? 0,
  total_shares: analytics?.totalShares ?? 0,
  gifts_value: analytics?.giftsValue ?? 0,
  missed_reason_code: session?.missedReasonCode ?? '',
  missed_reason_note: session?.missedReasonNote ?? '',
});
```

**Step 3: Read the query-string hint from the list CTAs**

Below the `useForm`, pre-seed `went_live` from the URL (`?recap=yes` / `?recap=no`) if set and current value is null:

```jsx
useEffect(() => {
  const params = new URLSearchParams(window.location.search);
  const hint = params.get('recap');
  if (recap.data.went_live === null) {
    if (hint === 'yes') recap.setData('went_live', true);
    if (hint === 'no') recap.setData('went_live', false);
  }
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, []);
```

Add `useEffect` to the lucide-react import line already has `useRef, useState` — extend:

```jsx
import { useEffect, useRef, useState } from 'react';
```

**Step 4: Compute `hasVisualProof` client-side from the attachments prop**

Right after the state hooks:

```jsx
const hasVisualProof = (attachments ?? []).some((a) =>
  a.fileType?.startsWith('image/') || a.fileType?.startsWith('video/')
);
```

**Step 5: Update `handleSave` to send `went_live`**

Replace with:

```jsx
const handleSave = (event) => {
  event.preventDefault();
  recap.post(`/live-host/sessions/${session.id}/recap`, {
    preserveScroll: true,
    forceFormData: true,
  });
};

const handleMarkMissed = () => {
  recap.post(`/live-host/sessions/${session.id}/recap`, {
    preserveScroll: true,
  });
};

const handleSwitchPath = (nextWentLive) => {
  if (recap.data.went_live === nextWentLive) {
    return;
  }
  // Warn if flipping away from a saved "missed" decision
  if (recap.data.went_live === false && nextWentLive === true && session?.status === 'missed') {
    const ok = window.confirm('Switch from "Did not go live" to "Went live"? Your reason will be cleared.');
    if (!ok) return;
  }
  recap.setData('went_live', nextWentLive);
  recap.clearErrors();
};
```

**Step 6: Insert the top-level switch + branched form body**

Replace everything inside the outer `<div className="-mx-5 min-h-full ...">` — after `<SessionHead session={session} />` — with:

```jsx
<PathSwitch
  value={recap.data.went_live}
  onChange={handleSwitchPath}
/>

{recap.data.went_live === true ? (
  <>
    <Section title="Cover image" hint="image_path">
      <CoverUpload
        preview={coverPreview}
        onPick={() => coverInputRef.current?.click()}
      />
      <input
        ref={coverInputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleCoverChange}
      />
      {recap.errors.cover_image ? <FieldError>{recap.errors.cover_image}</FieldError> : null}
    </Section>

    {/* existing Timing / Analytics / Remarks / Attachments sections — unchanged */}

    <ProofHint hasVisualProof={hasVisualProof} error={recap.errors.proof} />

    <div className="pt-3">
      <button
        type="button"
        onClick={handleSave}
        disabled={recap.processing || !hasVisualProof}
        className="w-full rounded-[12px] bg-[var(--accent)] px-4 py-[13px] font-sans text-[14px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] transition active:scale-[0.98] disabled:opacity-60"
      >
        {recap.processing ? 'Saving...' : 'Save recap'}
      </button>
    </div>
  </>
) : null}

{recap.data.went_live === false ? (
  <MissedReasonForm
    reasonCode={recap.data.missed_reason_code}
    onReasonCodeChange={(v) => recap.setData('missed_reason_code', v)}
    note={recap.data.missed_reason_note}
    onNoteChange={(v) => recap.setData('missed_reason_note', v)}
    errors={recap.errors}
    processing={recap.processing}
    onSubmit={handleMarkMissed}
  />
) : null}

{recap.data.went_live === null ? (
  <div className="mb-[10px] rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center text-[12px] text-[var(--fg-3)]">
    Choose above whether you went live so we can show the right form.
  </div>
) : null}
```

Note: make sure the *existing* Timing / Analytics / Remarks / Attachments JSX (currently between "Cover image" Section and the "Save recap" button) is nested under `{recap.data.went_live === true ? ( ... ) : null}` too.

**Step 7: Add the three new subcomponents near the bottom of the file**

```jsx
function PathSwitch({ value, onChange }) {
  return (
    <div className="mb-4">
      <div className="mb-2 px-1">
        <h4 className="font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Did you go live?
        </h4>
      </div>
      <div className="grid grid-cols-2 gap-[8px]">
        <PathButton
          active={value === true}
          onClick={() => onChange(true)}
          accent
        >
          Yes, I went live
        </PathButton>
        <PathButton
          active={value === false}
          onClick={() => onChange(false)}
        >
          No, I missed it
        </PathButton>
      </div>
    </div>
  );
}

function PathButton({ active, onClick, accent = false, children }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'rounded-[12px] border px-[12px] py-[12px] text-left font-display text-[13px] font-medium leading-tight tracking-[-0.01em] transition',
        active && accent
          ? 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent)]'
          : active
            ? 'border-[var(--hot)] bg-[rgba(225,29,72,0.08)] text-[var(--hot)]'
            : 'border-[var(--hair)] bg-[var(--app-bg-2)] text-[var(--fg-2)]'
      )}
    >
      {children}
    </button>
  );
}

function ProofHint({ hasVisualProof, error }) {
  if (hasVisualProof && !error) {
    return (
      <div className="mb-3 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Proof attached &check;
      </div>
    );
  }
  return (
    <div className="mb-3 rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--hot)]">
      {error ?? 'Proof · image or video attachment required'}
    </div>
  );
}

function MissedReasonForm({
  reasonCode,
  onReasonCodeChange,
  note,
  onNoteChange,
  errors,
  processing,
  onSubmit,
}) {
  return (
    <>
      <Section title="Why didn't you go live?" hint="missed_reason_code">
        <div className="space-y-[6px]">
          {MISSED_REASONS.map((r) => (
            <label
              key={r.code}
              className={cn(
                'flex cursor-pointer items-center gap-[10px] rounded-[10px] border bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px]',
                reasonCode === r.code ? 'border-[var(--hot)]' : 'border-[var(--hair)]'
              )}
            >
              <input
                type="radio"
                name="missed_reason_code"
                value={r.code}
                checked={reasonCode === r.code}
                onChange={() => onReasonCodeChange(r.code)}
                className="h-[14px] w-[14px] accent-[var(--hot)]"
              />
              <span className="text-[var(--fg)]">{r.label}</span>
            </label>
          ))}
        </div>
        {errors.missed_reason_code ? <FieldError>{errors.missed_reason_code}</FieldError> : null}
      </Section>

      <Section title="Note (optional)" hint="missed_reason_note">
        <textarea
          value={note}
          onChange={(e) => onNoteChange(e.target.value)}
          placeholder="Add any context admin should see"
          rows={3}
          maxLength={500}
          className="w-full resize-none rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px] leading-snug text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--hot)] focus:outline-none"
        />
        {errors.missed_reason_note ? <FieldError>{errors.missed_reason_note}</FieldError> : null}
      </Section>

      <div className="pt-3">
        <button
          type="button"
          onClick={onSubmit}
          disabled={processing || !reasonCode}
          className="w-full rounded-[12px] bg-[var(--hot)] px-4 py-[13px] font-sans text-[14px] font-bold tracking-[-0.005em] text-white transition active:scale-[0.98] disabled:opacity-60"
        >
          {processing ? 'Saving...' : 'Mark as missed'}
        </button>
      </div>
    </>
  );
}
```

**Step 8: Manual smoke check**

Restart or let HMR pick up, then open `/live-host/sessions/{id}?recap=no` in the browser — expect the missed-reason form to show. Open `/live-host/sessions/{id}?recap=yes` on a session with no attachments — expect the red "proof required" banner and a disabled Save button.

**Step 9: Commit**

```bash
git add resources/js/livehost-pocket/pages/SessionDetail.jsx
git commit -m "feat(livehost-pocket): Yes/No recap switch + proof validation + missed form"
```

---

## Task 8: Browser test — end-to-end flow

**Files:**
- Create: `tests/Browser/LiveHostPocket/RecapProofFlowTest.php`

**Step 1: Write the browser test**

```php
<?php

use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('host can mark a past-scheduled session as missed from the Sessions list', function () {
    Storage::fake('public');
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
            'title' => 'Tonight stream',
        ]);

    $this->actingAs($host);

    $page = visit('/live-host/sessions?filter=upcoming');

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Tonight stream')
        ->assertSee('RECAP PENDING')
        ->click("Didn't go live")
        ->assertSee("Why didn't you go live?")
        ->click('Tech / connection issue')
        ->fill('missed_reason_note', 'Internet outage')
        ->click('Mark as missed');

    expect($session->fresh()->status)->toBe('missed');
    expect($session->fresh()->missed_reason_code)->toBe('tech_issue');
});

it('host cannot save went_live recap without an image/video attachment', function () {
    Storage::fake('public');
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()
        ->for(PlatformAccount::factory())
        ->create([
            'live_host_id' => $host->id,
            'status' => 'scheduled',
            'scheduled_start_at' => now()->subHours(3),
        ]);

    $this->actingAs($host);

    $page = visit("/live-host/sessions/{$session->id}?recap=yes");

    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Proof')
        ->assertSee('image or video attachment required');

    // Save button should be disabled; asserting state rather than clicking.
    expect($page->element('button:has-text("Save recap")')->isDisabled())->toBeTrue();
});
```

**Step 2: Run the browser tests**

Run: `php artisan test --compact tests/Browser/LiveHostPocket/RecapProofFlowTest.php`
Expected: both tests PASS.

**Step 3: Run the full feature test suite to confirm no regressions**

Run: `php artisan test --compact --filter=LiveHostPocket`
Expected: all pass.

**Step 4: Run Pint**

Run: `vendor/bin/pint --dirty`
Expected: `{"result":"pass"}`.

**Step 5: Commit**

```bash
git add tests/Browser/LiveHostPocket/RecapProofFlowTest.php
git commit -m "test(livehost-pocket): browser coverage for recap proof + missed flow"
```

---

## Task 9: Final verification

**Step 1: Run the whole suite once**

Run: `composer run test`
Expected: everything green.

**Step 2: Build frontend**

Run: `npm run build`
Expected: builds without warnings relevant to these files.

**Step 3: Summary commit (no code) — if needed**

If there are stray formatting fixes from Pint or any leftover hunks:

```bash
git status
# If clean, nothing to do. Otherwise:
git add -u && vendor/bin/pint --dirty
git commit -m "chore: formatting pass after recap proof feature"
```

Feature complete.

---

## Out-of-scope reminders (from design doc)

These are intentionally NOT in this plan and should be tracked separately if needed:

- Admin-side dashboards for missed-reason analytics
- Auto-nudging via email/WhatsApp for past-scheduled sessions still in `scheduled`
- Fraud detection (reused proof images across sessions)
- i18n on reason labels — English only for v1
