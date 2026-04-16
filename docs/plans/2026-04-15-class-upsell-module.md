# Class Schedule Upsell Module — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow admins to assign funnels and PICs to class timetable slots, inherit config into generated sessions, and track conversions back to specific sessions for reporting.

**Architecture:** New `class_timetable_upsells` table links timetable slots to funnels and PICs. When sessions are generated, they inherit upsell config. Funnel checkout captures a `class_session_id` query parameter for attribution. A new "Upsell" tab on the class detail page provides configuration and conversion reporting.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), Flux UI Free, Pest tests, MySQL+SQLite dual-driver migrations.

**Design Doc:** `docs/plans/2026-04-15-class-upsell-module-design.md`

---

## Task 1: Create Migration — `class_timetable_upsells` Table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_class_timetable_upsells_table.php`

**Step 1: Generate migration**

```bash
php artisan make:migration create_class_timetable_upsells_table --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_timetable_upsells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_timetable_id')->constrained('class_timetables')->cascadeOnDelete();
            $table->string('day_of_week'); // monday, tuesday, etc.
            $table->string('time_slot'); // 09:00, 14:00, etc.
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->foreignId('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_timetable_id', 'day_of_week', 'time_slot'], 'timetable_upsell_slot_unique');
            $table->index('funnel_id');
            $table->index('pic_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable_upsells');
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: Migration runs successfully, table created.

**Step 4: Commit**

```bash
git add database/migrations/*create_class_timetable_upsells*
git commit -m "feat(upsell): create class_timetable_upsells table"
```

---

## Task 2: Create Migration — Add Upsell Columns to `class_sessions`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_upsell_fields_to_class_sessions_table.php`

**Step 1: Generate migration**

```bash
php artisan make:migration add_upsell_fields_to_class_sessions_table --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('upsell_funnel_id')->nullable()->after('payout_status')->constrained('funnels')->nullOnDelete();
            $table->foreignId('upsell_pic_user_id')->nullable()->after('upsell_funnel_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropForeign(['upsell_funnel_id']);
            $table->dropForeign(['upsell_pic_user_id']);
            $table->dropColumn(['upsell_funnel_id', 'upsell_pic_user_id']);
        });
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*add_upsell_fields_to_class_sessions*
git commit -m "feat(upsell): add upsell_funnel_id and upsell_pic_user_id to class_sessions"
```

---

## Task 3: Create Migration — Add `class_session_id` to `funnel_orders`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_class_session_id_to_funnel_orders_table.php`

**Step 1: Generate migration**

```bash
php artisan make:migration add_class_session_id_to_funnel_orders_table --no-interaction
```

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_orders', function (Blueprint $table) {
            $table->foreignId('class_session_id')->nullable()->after('bumps_accepted')->constrained('class_sessions')->nullOnDelete();
            $table->index('class_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_orders', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropIndex(['class_session_id']);
            $table->dropColumn('class_session_id');
        });
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*add_class_session_id_to_funnel_orders*
git commit -m "feat(upsell): add class_session_id to funnel_orders for conversion tracking"
```

---

## Task 4: Create `ClassTimetableUpsell` Model

**Files:**
- Create: `app/Models/ClassTimetableUpsell.php`
- Test: `tests/Feature/Models/ClassTimetableUpsellTest.php`

**Step 1: Generate model**

```bash
php artisan make:model ClassTimetableUpsell --no-interaction
```

**Step 2: Write the failing test**

Create `tests/Feature/Models/ClassTimetableUpsellTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassTimetable;
use App\Models\ClassTimetableUpsell;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\User;

test('class timetable upsell belongs to timetable', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['created_by' => $user->id]);
    $class = ClassModel::factory()->create(['course_id' => $course->id]);
    $timetable = ClassTimetable::create([
        'class_id' => $class->id,
        'weekly_schedule' => ['monday' => ['09:00']],
        'recurrence_pattern' => 'weekly',
        'start_date' => now(),
        'is_active' => true,
    ]);
    $funnel = Funnel::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel',
        'type' => 'sales',
        'status' => 'published',
    ]);

    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $funnel->id,
        'pic_user_id' => $user->id,
        'is_active' => true,
    ]);

    expect($upsell->timetable)->toBeInstanceOf(ClassTimetable::class);
    expect($upsell->funnel)->toBeInstanceOf(Funnel::class);
    expect($upsell->pic)->toBeInstanceOf(User::class);
});
```

**Step 3: Run test to verify it fails**

```bash
php artisan test --compact --filter=ClassTimetableUpsellTest
```

Expected: FAIL — model missing relationships.

**Step 4: Implement the model**

Edit `app/Models/ClassTimetableUpsell.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTimetableUpsell extends Model
{
    protected $fillable = [
        'class_timetable_id',
        'day_of_week',
        'time_slot',
        'funnel_id',
        'pic_user_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(ClassTimetable::class, 'class_timetable_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSlot($query, string $dayOfWeek, string $timeSlot)
    {
        return $query->where('day_of_week', $dayOfWeek)->where('time_slot', $timeSlot);
    }
}
```

**Step 5: Run test to verify it passes**

```bash
php artisan test --compact --filter=ClassTimetableUpsellTest
```

Expected: PASS

**Step 6: Commit**

```bash
git add app/Models/ClassTimetableUpsell.php tests/Feature/Models/ClassTimetableUpsellTest.php
git commit -m "feat(upsell): add ClassTimetableUpsell model with relationships"
```

---

## Task 5: Add Relationships to Existing Models

**Files:**
- Modify: `app/Models/ClassTimetable.php` (add `upsells()` relationship)
- Modify: `app/Models/ClassSession.php` (add `upsellFunnel()`, `upsellPic()` relationships + fillable)
- Modify: `app/Models/FunnelOrder.php` (add `classSession()` relationship + fillable)

**Step 1: Add `upsells()` to ClassTimetable**

In `app/Models/ClassTimetable.php`, add import and relationship:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

After the `class()` relationship (line 33), add:

```php
    public function upsells(): HasMany
    {
        return $this->hasMany(ClassTimetableUpsell::class, 'class_timetable_id');
    }
```

**Step 2: Add upsell relationships to ClassSession**

In `app/Models/ClassSession.php`:

Add to `$fillable` array: `'upsell_funnel_id'`, `'upsell_pic_user_id'`

Add relationships:

```php
    public function upsellFunnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class, 'upsell_funnel_id');
    }

    public function upsellPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'upsell_pic_user_id');
    }
```

**Step 3: Add `classSession()` to FunnelOrder**

In `app/Models/FunnelOrder.php`:

Add `'class_session_id'` to `$fillable` array.

Add relationship:

```php
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }
```

**Step 4: Run existing tests**

```bash
php artisan test --compact
```

Expected: All existing tests pass (no breaking changes).

**Step 5: Commit**

```bash
git add app/Models/ClassTimetable.php app/Models/ClassSession.php app/Models/FunnelOrder.php
git commit -m "feat(upsell): add upsell relationships to ClassTimetable, ClassSession, FunnelOrder"
```

---

## Task 6: Modify Session Generation to Inherit Upsell Config

**Files:**
- Modify: `app/Models/ClassTimetable.php:35-91` — update `generateSessions()` to include upsell data
- Modify: `app/Models/ClassModel.php:407-428` — update `createSessionsFromTimetable()` upsert to include new fields
- Test: `tests/Feature/Models/ClassTimetableUpsellTest.php` — add test for session generation

**Step 1: Write the failing test**

Add to `tests/Feature/Models/ClassTimetableUpsellTest.php`:

```php
test('generated sessions inherit upsell config from timetable slot', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create(['created_by' => $user->id]);
    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'duration_minutes' => 60,
    ]);
    $timetable = ClassTimetable::create([
        'class_id' => $class->id,
        'weekly_schedule' => ['monday' => ['09:00'], 'wednesday' => ['14:00']],
        'recurrence_pattern' => 'weekly',
        'start_date' => now()->startOfWeek(),
        'total_sessions' => 4,
        'is_active' => true,
    ]);

    $funnel = Funnel::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel-inherit',
        'type' => 'sales',
        'status' => 'published',
    ]);

    // Only assign upsell to Monday 09:00 slot
    ClassTimetableUpsell::create([
        'class_timetable_id' => $timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $funnel->id,
        'pic_user_id' => $user->id,
        'is_active' => true,
    ]);

    $class->createSessionsFromTimetable();

    // Monday sessions should have upsell config
    $mondaySessions = $class->sessions()->where('session_time', '09:00')->get();
    foreach ($mondaySessions as $session) {
        expect($session->upsell_funnel_id)->toBe($funnel->id);
        expect($session->upsell_pic_user_id)->toBe($user->id);
    }

    // Wednesday sessions should NOT have upsell config
    $wednesdaySessions = $class->sessions()->where('session_time', '14:00')->get();
    foreach ($wednesdaySessions as $session) {
        expect($session->upsell_funnel_id)->toBeNull();
        expect($session->upsell_pic_user_id)->toBeNull();
    }
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="generated sessions inherit"
```

Expected: FAIL — sessions don't have upsell fields populated.

**Step 3: Update `generateSessions()` in `ClassTimetable.php`**

Modify `app/Models/ClassTimetable.php` — the `generateSessions()` method (lines 35-91). After building each session array (line 71-79), look up the matching upsell config:

Replace the session-building block inside the `foreach ($timesForDay as $time)` loop (lines 71-79) with:

```php
                // Look up upsell config for this slot
                $upsellConfig = $this->upsells()
                    ->active()
                    ->forSlot($dayOfWeek, $time)
                    ->first();

                $sessions[] = [
                    'class_id' => $this->class_id,
                    'session_date' => $currentDate->toDateString(),
                    'session_time' => $time,
                    'duration_minutes' => $this->class->duration_minutes ?? 60,
                    'status' => 'scheduled',
                    'upsell_funnel_id' => $upsellConfig?->funnel_id,
                    'upsell_pic_user_id' => $upsellConfig?->pic_user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
```

**Step 4: Update `createSessionsFromTimetable()` in `ClassModel.php`**

Modify `app/Models/ClassModel.php` (lines 421-424). Update the upsert to include the new fields:

```php
        ClassSession::upsert(
            $sessionsData,
            ['class_id', 'session_date', 'session_time'],
            ['duration_minutes', 'upsell_funnel_id', 'upsell_pic_user_id']
        );
```

**Step 5: Run test to verify it passes**

```bash
php artisan test --compact --filter="generated sessions inherit"
```

Expected: PASS

**Step 6: Run all existing tests to ensure no regressions**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 7: Commit**

```bash
git add app/Models/ClassTimetable.php app/Models/ClassModel.php tests/Feature/Models/ClassTimetableUpsellTest.php
git commit -m "feat(upsell): sessions inherit upsell config from timetable slots"
```

---

## Task 7: Capture `class_session_id` in Funnel Checkout

**Files:**
- Modify: `app/Http/Controllers/PublicFunnelController.php:73` — capture `cls` query param into funnel session
- Modify: `app/Models/FunnelSession.php` — add `class_session_id` field (if needed)
- Modify: `app/Services/Funnel/FunnelCheckoutService.php:269` — pass `class_session_id` to FunnelOrder creation

**Step 1: Understand the flow**

The funnel URL will include `?cls={session_id}`. When a student opens this link:
1. `PublicFunnelController::show()` creates/retrieves a `FunnelSession`
2. The `cls` param is stored on the FunnelSession metadata
3. During checkout, `FunnelCheckoutService::createFunnelOrder()` reads it and saves as `class_session_id`

**Step 2: Modify `PublicFunnelController::show()` to capture `cls` param**

In `app/Http/Controllers/PublicFunnelController.php`, inside the `show()` method, after the FunnelSession is created/retrieved, add:

```php
        // Capture class session reference for upsell tracking
        if ($request->has('cls')) {
            $funnelSession = FunnelSession::where(...)->first(); // use existing session retrieval
            if ($funnelSession) {
                $funnelSession->update(['metadata' => array_merge(
                    $funnelSession->metadata ?? [],
                    ['class_session_id' => (int) $request->input('cls')]
                )]);
            }
        }
```

**Note:** The exact integration point depends on how `FunnelSession` is created in the `show()` method. Read the full method to determine exact placement. The key is storing `class_session_id` in the session's metadata or a direct column.

**Step 3: Modify `FunnelCheckoutService::createFunnelOrder()` (line 269)**

In `app/Services/Funnel/FunnelCheckoutService.php`, update the `createFunnelOrder()` method (line 262-279) to include `class_session_id`:

```php
    protected function createFunnelOrder(
        FunnelSession $session,
        FunnelStep $step,
        ProductOrder $productOrder,
        $bumps,
        float $total
    ): FunnelOrder {
        return FunnelOrder::create([
            'funnel_id' => $session->funnel_id,
            'session_id' => $session->id,
            'product_order_id' => $productOrder->id,
            'step_id' => $step->id,
            'order_type' => 'main',
            'funnel_revenue' => $total,
            'bumps_offered' => $step->orderBumps()->where('is_active', true)->count(),
            'bumps_accepted' => $bumps->count(),
            'class_session_id' => $session->metadata['class_session_id'] ?? null,
        ]);
    }
```

Also update the upsell FunnelOrder creation (line 544-551) similarly:

```php
            'class_session_id' => $session->metadata['class_session_id'] ?? null,
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/PublicFunnelController.php app/Services/Funnel/FunnelCheckoutService.php
git commit -m "feat(upsell): capture class_session_id from funnel URL for conversion tracking"
```

---

## Task 8: Build Upsell Tab — Configuration Section

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php`
  - Add "Upsell" tab to tab navigation (~line 3625)
  - Add Upsell tab content section
  - Add Livewire properties and methods for upsell management

**Step 1: Add `upsell` tab to the tabs array**

In `resources/views/livewire/admin/class-show.blade.php`, find the `$tabs` array (~line 3625-3636). Add a new entry after the `pic-performance` tab:

```php
        ['key' => 'upsell', 'label' => 'Upsell', 'icon' => 'gift', 'count' => $class->timetable?->upsells()->active()->count() ?: null],
```

**Step 2: Add Livewire properties for upsell management**

In the PHP section of the Volt component (top of file), add:

```php
    // Upsell tab properties
    public bool $showUpsellModal = false;
    public ?int $editingUpsellId = null;
    public string $upsellDayOfWeek = '';
    public string $upsellTimeSlot = '';
    public ?int $upsellFunnelId = null;
    public ?int $upsellPicUserId = null;
    public string $upsellNotes = '';
    public bool $upsellIsActive = true;
```

**Step 3: Add Livewire methods**

```php
    public function getAvailableFunnelsProperty()
    {
        return \App\Models\Funnel::where('status', 'published')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function getAvailablePicsProperty()
    {
        return \App\Models\User::whereIn('role', ['admin', 'class_admin', 'sales', 'employee'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getTimetableUpsellsProperty()
    {
        if (! $this->class->timetable) {
            return collect();
        }

        return $this->class->timetable->upsells()
            ->with(['funnel', 'pic'])
            ->get();
    }

    public function getAvailableSlotsProperty()
    {
        if (! $this->class->timetable) {
            return [];
        }

        $schedule = $this->class->timetable->weekly_schedule;
        $slots = [];

        if ($this->class->timetable->recurrence_pattern === 'monthly') {
            foreach ($schedule as $weekKey => $days) {
                foreach ($days as $day => $times) {
                    foreach ($times as $time) {
                        $slots[] = ['day' => $day, 'time' => $time, 'label' => ucfirst($day) . ' ' . $time . ' (' . str_replace('_', ' ', ucfirst($weekKey)) . ')'];
                    }
                }
            }
        } else {
            foreach ($schedule as $day => $times) {
                foreach ($times as $time) {
                    $slots[] = ['day' => $day, 'time' => $time, 'label' => ucfirst($day) . ' ' . $time];
                }
            }
        }

        return $slots;
    }

    public function openUpsellModal(?int $upsellId = null): void
    {
        if ($upsellId) {
            $upsell = \App\Models\ClassTimetableUpsell::findOrFail($upsellId);
            $this->editingUpsellId = $upsell->id;
            $this->upsellDayOfWeek = $upsell->day_of_week;
            $this->upsellTimeSlot = $upsell->time_slot;
            $this->upsellFunnelId = $upsell->funnel_id;
            $this->upsellPicUserId = $upsell->pic_user_id;
            $this->upsellNotes = $upsell->notes ?? '';
            $this->upsellIsActive = $upsell->is_active;
        } else {
            $this->resetUpsellForm();
        }
        $this->showUpsellModal = true;
    }

    public function resetUpsellForm(): void
    {
        $this->editingUpsellId = null;
        $this->upsellDayOfWeek = '';
        $this->upsellTimeSlot = '';
        $this->upsellFunnelId = null;
        $this->upsellPicUserId = null;
        $this->upsellNotes = '';
        $this->upsellIsActive = true;
    }

    public function saveUpsell(): void
    {
        $this->validate([
            'upsellDayOfWeek' => 'required|string',
            'upsellTimeSlot' => 'required|string',
            'upsellFunnelId' => 'required|exists:funnels,id',
            'upsellPicUserId' => 'required|exists:users,id',
        ]);

        \App\Models\ClassTimetableUpsell::updateOrCreate(
            [
                'class_timetable_id' => $this->class->timetable->id,
                'day_of_week' => $this->upsellDayOfWeek,
                'time_slot' => $this->upsellTimeSlot,
            ],
            [
                'funnel_id' => $this->upsellFunnelId,
                'pic_user_id' => $this->upsellPicUserId,
                'is_active' => $this->upsellIsActive,
                'notes' => $this->upsellNotes ?: null,
            ]
        );

        $this->showUpsellModal = false;
        $this->resetUpsellForm();

        // Sync upsell config to future sessions
        $this->syncUpsellToSessions();

        session()->flash('success', 'Upsell configuration saved successfully.');
    }

    public function deleteUpsell(int $upsellId): void
    {
        \App\Models\ClassTimetableUpsell::where('id', $upsellId)
            ->where('class_timetable_id', $this->class->timetable->id)
            ->delete();

        session()->flash('success', 'Upsell configuration removed.');
    }

    public function toggleUpsellActive(int $upsellId): void
    {
        $upsell = \App\Models\ClassTimetableUpsell::findOrFail($upsellId);
        $upsell->update(['is_active' => ! $upsell->is_active]);
    }

    public function syncUpsellToSessions(): void
    {
        if (! $this->class->timetable) {
            return;
        }

        $upsells = $this->class->timetable->upsells()->active()->get();

        // Get all future scheduled sessions
        $sessions = $this->class->sessions()
            ->where('status', 'scheduled')
            ->where('session_date', '>=', now()->toDateString())
            ->get();

        foreach ($sessions as $session) {
            $dayOfWeek = strtolower(\Carbon\Carbon::parse($session->session_date)->format('l'));
            $upsell = $upsells->first(fn ($u) => $u->day_of_week === $dayOfWeek && $u->time_slot === $session->session_time);

            $session->update([
                'upsell_funnel_id' => $upsell?->funnel_id,
                'upsell_pic_user_id' => $upsell?->pic_user_id,
            ]);
        }
    }
```

**Step 4: Add Upsell tab Blade content**

After the last tab content section (e.g., after the PIC/Notifikasi tab content), add the Upsell tab:

```blade
{{-- Upsell Tab --}}
<div class="{{ $activeTab === 'upsell' ? 'block' : 'hidden' }}">
    @if($class->timetable)
        {{-- Upsell Configuration Section --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Upsell Configuration</flux:heading>
                    <flux:text class="mt-1">Assign funnels and PICs to timetable slots</flux:text>
                </div>
                <flux:button variant="primary" size="sm" wire:click="openUpsellModal()">
                    + Add Upsell
                </flux:button>
            </div>

            @if($this->timetableUpsells->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Day</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Time</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Funnel</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">PIC</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Status</th>
                                <th class="text-right py-3 px-4 font-medium text-zinc-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->timetableUpsells as $upsell)
                                <tr wire:key="upsell-{{ $upsell->id }}" class="border-b border-zinc-100 dark:border-zinc-700/50">
                                    <td class="py-3 px-4 font-medium">{{ ucfirst($upsell->day_of_week) }}</td>
                                    <td class="py-3 px-4">{{ \Carbon\Carbon::createFromFormat('H:i', $upsell->time_slot)->format('h:i A') }}</td>
                                    <td class="py-3 px-4">{{ $upsell->funnel->name }}</td>
                                    <td class="py-3 px-4">{{ $upsell->pic->name }}</td>
                                    <td class="py-3 px-4">
                                        <button wire:click="toggleUpsellActive({{ $upsell->id }})" class="inline-flex items-center gap-1.5">
                                            @if($upsell->is_active)
                                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                                <span class="text-green-700 dark:text-green-400">Active</span>
                                            @else
                                                <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                                                <span class="text-zinc-500">Inactive</span>
                                            @endif
                                        </button>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button variant="ghost" size="xs" wire:click="openUpsellModal({{ $upsell->id }})">
                                                Edit
                                            </flux:button>
                                            <flux:button variant="ghost" size="xs" wire:click="deleteUpsell({{ $upsell->id }})" wire:confirm="Remove this upsell configuration?">
                                                Delete
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon name="gift" class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                    <flux:heading size="md">No Upsell Configured</flux:heading>
                    <flux:text class="mt-1">Add upsell funnels to your timetable slots to start tracking conversions.</flux:text>
                </div>
            @endif
        </div>

        {{-- Conversion Report Section — Task 9 --}}

    @else
        {{-- No Timetable Empty State --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-12 text-center">
            <flux:icon name="calendar" class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
            <flux:heading size="md">No Timetable Configured</flux:heading>
            <flux:text class="mt-1">This class doesn't have a recurring timetable. Configure a timetable first to set up upsells.</flux:text>
        </div>
    @endif

    {{-- Upsell Modal --}}
    <flux:modal wire:model="showUpsellModal" class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingUpsellId ? 'Edit' : 'Add' }} Upsell Configuration</flux:heading>

            <div class="space-y-4">
                {{-- Slot Selection --}}
                <flux:select wire:model="upsellDayOfWeek" label="Day" placeholder="Select day...">
                    @foreach($this->availableSlots as $slot)
                        @if($loop->first || $slot['day'] !== $this->availableSlots[$loop->index - 1]['day'])
                            <flux:select.option value="{{ $slot['day'] }}">{{ ucfirst($slot['day']) }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>

                <flux:select wire:model="upsellTimeSlot" label="Time Slot" placeholder="Select time...">
                    @foreach($this->availableSlots as $slot)
                        @if($slot['day'] === $upsellDayOfWeek)
                            <flux:select.option value="{{ $slot['time'] }}">{{ \Carbon\Carbon::createFromFormat('H:i', $slot['time'])->format('h:i A') }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>

                {{-- Funnel Selection --}}
                <flux:select wire:model="upsellFunnelId" label="Funnel" placeholder="Select funnel...">
                    @foreach($this->availableFunnels as $funnel)
                        <flux:select.option value="{{ $funnel->id }}">{{ $funnel->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- PIC Selection --}}
                <flux:select wire:model="upsellPicUserId" label="Person In Charge (PIC)" placeholder="Select PIC...">
                    @foreach($this->availablePics as $pic)
                        <flux:select.option value="{{ $pic->id }}">{{ $pic->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Notes --}}
                <flux:textarea wire:model="upsellNotes" label="Notes (optional)" placeholder="Instructions for the PIC..." rows="3" />

                {{-- Active Toggle --}}
                <flux:switch wire:model="upsellIsActive" label="Active" description="Enable upsell for this slot" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showUpsellModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveUpsell">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
```

**Step 5: Verify visually with Playwright or manual browser testing**

Navigate to a class with a timetable, click the "Upsell" tab, verify:
- Empty state shows when no upsells configured
- "+ Add Upsell" opens modal with slot/funnel/PIC dropdowns
- Saving creates a record and shows in the table
- Edit/Delete/Toggle work correctly

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php
git commit -m "feat(upsell): add Upsell tab with configuration UI to class detail page"
```

---

## Task 9: Build Upsell Tab — Conversion Report Section

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php` — add conversion report section + methods

**Step 1: Add computed properties for conversion stats**

In the PHP section of the Volt component, add:

```php
    public string $upsellDateFrom = '';
    public string $upsellDateTo = '';
    public ?int $upsellFilterPicId = null;
    public ?int $upsellFilterFunnelId = null;

    public function getUpsellStatsProperty(): array
    {
        $query = \App\Models\FunnelOrder::whereNotNull('class_session_id')
            ->whereHas('classSession', function ($q) {
                $q->where('class_id', $this->class->id);
            });

        if ($this->upsellDateFrom) {
            $query->whereHas('classSession', fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom));
        }
        if ($this->upsellDateTo) {
            $query->whereHas('classSession', fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo));
        }

        $orders = $query->with(['classSession', 'funnel'])->get();

        $totalSessions = $this->class->sessions()
            ->whereNotNull('upsell_funnel_id')
            ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
            ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo))
            ->count();

        $totalConversions = $orders->count();
        $totalRevenue = $orders->sum('funnel_revenue');
        $conversionRate = $totalSessions > 0 ? round(($totalConversions / $totalSessions) * 100, 1) : 0;

        return [
            'total_sessions' => $totalSessions,
            'total_conversions' => $totalConversions,
            'total_revenue' => $totalRevenue,
            'conversion_rate' => $conversionRate,
        ];
    }

    public function getUpsellSessionReportsProperty()
    {
        return $this->class->sessions()
            ->whereNotNull('upsell_funnel_id')
            ->with(['upsellFunnel', 'upsellPic'])
            ->withCount(['funnelOrders as upsell_orders_count' => function ($q) {
                $q->whereNotNull('class_session_id');
            }])
            ->withSum(['funnelOrders as upsell_revenue' => function ($q) {
                $q->whereNotNull('class_session_id');
            }], 'funnel_revenue')
            ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
            ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo))
            ->when($this->upsellFilterPicId, fn ($q) => $q->where('upsell_pic_user_id', $this->upsellFilterPicId))
            ->when($this->upsellFilterFunnelId, fn ($q) => $q->where('upsell_funnel_id', $this->upsellFilterFunnelId))
            ->orderByDesc('session_date')
            ->limit(50)
            ->get();
    }
```

**Note:** The `funnelOrders` relationship needs to be added to `ClassSession`:

```php
    public function funnelOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\FunnelOrder::class, 'class_session_id');
    }
```

**Step 2: Add the Blade template for conversion report**

In the Upsell tab content, after the Upsell Configuration section and before the `@else` (no timetable), add:

```blade
        {{-- Conversion Report Section --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="mb-4">
                <flux:heading size="lg">Conversion Report</flux:heading>
                <flux:text class="mt-1">Track upsell performance per session</flux:text>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4">
                    <flux:text class="text-xs font-medium text-zinc-500 uppercase">Sessions with Upsell</flux:text>
                    <p class="text-2xl font-bold mt-1">{{ $this->upsellStats['total_sessions'] }}</p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4">
                    <flux:text class="text-xs font-medium text-zinc-500 uppercase">Conversions</flux:text>
                    <p class="text-2xl font-bold mt-1 text-green-600">{{ $this->upsellStats['total_conversions'] }}</p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4">
                    <flux:text class="text-xs font-medium text-zinc-500 uppercase">Revenue</flux:text>
                    <p class="text-2xl font-bold mt-1">RM {{ number_format($this->upsellStats['total_revenue'], 2) }}</p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-lg p-4">
                    <flux:text class="text-xs font-medium text-zinc-500 uppercase">Conversion Rate</flux:text>
                    <p class="text-2xl font-bold mt-1">{{ $this->upsellStats['conversion_rate'] }}%</p>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap gap-3 mb-4">
                <flux:input type="date" wire:model.live="upsellDateFrom" label="From" size="sm" />
                <flux:input type="date" wire:model.live="upsellDateTo" label="To" size="sm" />
                <flux:select wire:model.live="upsellFilterFunnelId" label="Funnel" size="sm" placeholder="All Funnels">
                    @foreach($this->availableFunnels as $funnel)
                        <flux:select.option value="{{ $funnel->id }}">{{ $funnel->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="upsellFilterPicId" label="PIC" size="sm" placeholder="All PICs">
                    @foreach($this->availablePics as $pic)
                        <flux:select.option value="{{ $pic->id }}">{{ $pic->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Session Report Table --}}
            @if($this->upsellSessionReports->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Session</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Date</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">PIC</th>
                                <th class="text-left py-3 px-4 font-medium text-zinc-500">Funnel</th>
                                <th class="text-right py-3 px-4 font-medium text-zinc-500">Orders</th>
                                <th class="text-right py-3 px-4 font-medium text-zinc-500">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->upsellSessionReports as $report)
                                <tr wire:key="upsell-report-{{ $report->id }}" class="border-b border-zinc-100 dark:border-zinc-700/50">
                                    <td class="py-3 px-4 font-medium">
                                        {{ ucfirst(\Carbon\Carbon::parse($report->session_date)->format('D')) }}
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $report->session_time)->format('h:i A') }}
                                    </td>
                                    <td class="py-3 px-4">{{ \Carbon\Carbon::parse($report->session_date)->format('M d, Y') }}</td>
                                    <td class="py-3 px-4">{{ $report->upsellPic?->name ?? '—' }}</td>
                                    <td class="py-3 px-4">{{ $report->upsellFunnel?->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-right font-medium">{{ $report->upsell_orders_count ?? 0 }}</td>
                                    <td class="py-3 px-4 text-right font-medium">RM {{ number_format($report->upsell_revenue ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-zinc-400">
                    <flux:text>No conversion data available yet.</flux:text>
                </div>
            @endif
        </div>
```

**Step 3: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php app/Models/ClassSession.php
git commit -m "feat(upsell): add conversion report section with stats and filters"
```

---

## Task 10: Write Feature Tests for Upsell Tab

**Files:**
- Create: `tests/Feature/Livewire/Admin/ClassUpsellTest.php`

**Step 1: Generate test file**

```bash
php artisan make:test Livewire/Admin/ClassUpsellTest --pest --no-interaction
```

**Step 2: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\ClassTimetable;
use App\Models\ClassTimetableUpsell;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->course = Course::factory()->create(['created_by' => $this->admin->id]);
    $this->class = ClassModel::factory()->create([
        'course_id' => $this->course->id,
        'duration_minutes' => 60,
    ]);
    $this->timetable = ClassTimetable::create([
        'class_id' => $this->class->id,
        'weekly_schedule' => ['monday' => ['09:00'], 'wednesday' => ['14:00']],
        'recurrence_pattern' => 'weekly',
        'start_date' => now()->startOfWeek(),
        'total_sessions' => 10,
        'is_active' => true,
    ]);
    $this->funnel = Funnel::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'user_id' => $this->admin->id,
        'name' => 'Test Funnel',
        'slug' => 'test-funnel-' . \Illuminate\Support\Str::random(5),
        'type' => 'sales',
        'status' => 'published',
    ]);
});

test('admin can view upsell tab on class detail page', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.show', $this->class) . '?tab=upsell')
        ->assertOk();
});

test('admin can create upsell configuration', function () {
    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('setActiveTab', 'upsell')
        ->call('openUpsellModal')
        ->set('upsellDayOfWeek', 'monday')
        ->set('upsellTimeSlot', '09:00')
        ->set('upsellFunnelId', $this->funnel->id)
        ->set('upsellPicUserId', $this->admin->id)
        ->call('saveUpsell')
        ->assertHasNoErrors();

    expect(ClassTimetableUpsell::where('class_timetable_id', $this->timetable->id)->count())->toBe(1);
});

test('admin can delete upsell configuration', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('deleteUpsell', $upsell->id);

    expect(ClassTimetableUpsell::find($upsell->id))->toBeNull();
});

test('admin can toggle upsell active status', function () {
    $upsell = ClassTimetableUpsell::create([
        'class_timetable_id' => $this->timetable->id,
        'day_of_week' => 'monday',
        'time_slot' => '09:00',
        'funnel_id' => $this->funnel->id,
        'pic_user_id' => $this->admin->id,
        'is_active' => true,
    ]);

    Volt::actingAs($this->admin)
        ->test('admin.class-show', ['class' => $this->class])
        ->call('toggleUpsellActive', $upsell->id);

    expect($upsell->fresh()->is_active)->toBeFalse();
});
```

**Step 3: Run tests**

```bash
php artisan test --compact --filter=ClassUpsellTest
```

Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/Livewire/Admin/ClassUpsellTest.php
git commit -m "test(upsell): add feature tests for upsell tab CRUD operations"
```

---

## Task 11: Run Pint & Full Test Suite

**Step 1: Run Pint code formatter**

```bash
./vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass, code is formatted.

**Step 3: Final commit if Pint made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting to upsell module files"
```

---

## Summary of Files

| Action | File |
|--------|------|
| Create | `database/migrations/*_create_class_timetable_upsells_table.php` |
| Create | `database/migrations/*_add_upsell_fields_to_class_sessions_table.php` |
| Create | `database/migrations/*_add_class_session_id_to_funnel_orders_table.php` |
| Create | `app/Models/ClassTimetableUpsell.php` |
| Create | `tests/Feature/Models/ClassTimetableUpsellTest.php` |
| Create | `tests/Feature/Livewire/Admin/ClassUpsellTest.php` |
| Modify | `app/Models/ClassTimetable.php` — add `upsells()` relationship |
| Modify | `app/Models/ClassSession.php` — add upsell relationships + fillable + funnelOrders |
| Modify | `app/Models/FunnelOrder.php` — add `classSession()` relationship + fillable |
| Modify | `app/Models/ClassModel.php:421-424` — update upsert fields |
| Modify | `app/Http/Controllers/PublicFunnelController.php` — capture `cls` param |
| Modify | `app/Services/Funnel/FunnelCheckoutService.php:269,544` — pass `class_session_id` |
| Modify | `resources/views/livewire/admin/class-show.blade.php` — add Upsell tab + UI |
