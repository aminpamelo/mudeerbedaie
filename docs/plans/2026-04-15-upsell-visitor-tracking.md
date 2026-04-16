# Upsell Visitor Tracking via Existing Funnel Data

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add visitor/click tracking to the upsell tab by promoting `class_session_id` from JSON metadata to an indexed column on `funnel_sessions`, then wiring that data into the upsell UI.

**Architecture:** Add a dedicated `class_session_id` FK column to `funnel_sessions` (matching the pattern already used on `funnel_orders`). Update the controller to write to the new column directly. Add a `funnelSessions` relationship on `ClassSession`. Wire visitor counts into the upsell tab's stats, table, and detail modal. Backfill existing metadata values.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest

---

### Task 1: Migration — Add `class_session_id` to `funnel_sessions`

**Files:**
- Create: `database/migrations/2026_04_15_XXXXXX_add_class_session_id_to_funnel_sessions_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration add_class_session_id_to_funnel_sessions_table --table=funnel_sessions --no-interaction
```

**Step 2: Write migration**

Follow the exact pattern from `2026_04_15_084915_add_class_session_id_to_funnel_orders_table.php`:

```php
public function up(): void
{
    Schema::table('funnel_sessions', function (Blueprint $table) {
        $table->foreignId('class_session_id')->nullable()->after('metadata')->constrained('class_sessions')->nullOnDelete();
        $table->index('class_session_id');
    });

    // Backfill from metadata JSON
    DB::table('funnel_sessions')
        ->whereNotNull('metadata')
        ->whereNull('class_session_id')
        ->orderBy('id')
        ->each(function ($row) {
            $metadata = json_decode($row->metadata, true);
            if (!empty($metadata['class_session_id'])) {
                DB::table('funnel_sessions')
                    ->where('id', $row->id)
                    ->update(['class_session_id' => $metadata['class_session_id']]);
            }
        });
}

public function down(): void
{
    Schema::table('funnel_sessions', function (Blueprint $table) {
        $table->dropForeign(['class_session_id']);
        $table->dropIndex(['class_session_id']);
        $table->dropColumn('class_session_id');
    });
}
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully.

**Step 4: Verify column exists**

Run tinker:
```php
Schema::hasColumn('funnel_sessions', 'class_session_id'); // true
```

**Step 5: Commit**

```bash
git add database/migrations/*add_class_session_id_to_funnel_sessions*
git commit -m "feat(upsell): add indexed class_session_id column to funnel_sessions"
```

---

### Task 2: Update FunnelSession Model

**Files:**
- Modify: `app/Models/FunnelSession.php` — `$fillable` array (line 16-42), add relationship

**Step 1: Add `class_session_id` to `$fillable`**

In `app/Models/FunnelSession.php`, add `'class_session_id'` to the `$fillable` array (after `'metadata'` at line 41).

**Step 2: Add `classSession()` relationship**

Add after the existing `orders()` relationship:

```php
public function classSession(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Models\ClassSession::class, 'class_session_id');
}
```

**Step 3: Commit**

```bash
git add app/Models/FunnelSession.php
git commit -m "feat(upsell): add class_session_id to FunnelSession model"
```

---

### Task 3: Add `funnelSessions` Relationship on ClassSession

**Files:**
- Modify: `app/Models/ClassSession.php` — add new relationship (near existing `funnelOrders()` at line 122)

**Step 1: Add relationship**

Add after the `funnelOrders()` method:

```php
public function funnelSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\FunnelSession::class, 'class_session_id');
}
```

**Step 2: Commit**

```bash
git add app/Models/ClassSession.php
git commit -m "feat(upsell): add funnelSessions relationship on ClassSession"
```

---

### Task 4: Update PublicFunnelController to Write Indexed Column

**Files:**
- Modify: `app/Http/Controllers/PublicFunnelController.php` (lines 115-120)

**Step 1: Update `cls` capture logic**

Replace the existing metadata-only write (lines 115-120) with a write to both the column and metadata (for backward compat):

```php
// Capture class session reference for upsell tracking
if ($request->has('cls') && $session) {
    $classSessionId = (int) $request->input('cls');
    
    // Write to indexed column
    $session->update(['class_session_id' => $classSessionId]);
    
    // Also keep in metadata for backward compatibility
    $metadata = $session->metadata ?? [];
    $metadata['class_session_id'] = $classSessionId;
    $session->update(['metadata' => $metadata]);
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/PublicFunnelController.php
git commit -m "feat(upsell): write class_session_id to indexed column on funnel visit"
```

---

### Task 5: Wire Visitor Data into Upsell Tab Stats

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php`
  - `getUpsellSessionsProperty()` (lines 3569-3586) — add `withCount` for visitors
  - `getUpsellStatsProperty()` (lines 3685-3708) — add total visitors
  - Stats cards template (lines 7401-7418) — add VISITORS card

**Step 1: Update `getUpsellSessionsProperty()` to include visitor count**

Add a `withCount` for `funnelSessions` alongside the existing `funnelOrders` counts (after line 3578):

```php
public function getUpsellSessionsProperty()
{
    return $this->class->sessions()
        ->with(['assignedTeacher.user'])
        ->withCount(['funnelOrders as upsell_orders_count' => function ($q) {
            $q->whereNotNull('class_session_id');
        }])
        ->withSum(['funnelOrders as upsell_revenue' => function ($q) {
            $q->whereNotNull('class_session_id');
        }], 'funnel_revenue')
        ->withCount(['funnelSessions as upsell_visitors_count'])
        ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
        ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo))
        ->when($this->upsellFilterPicId, fn ($q) => $q->whereJsonContains('upsell_pic_user_ids', (int) $this->upsellFilterPicId))
        ->when($this->upsellFilterFunnelId, fn ($q) => $q->whereJsonContains('upsell_funnel_ids', (int) $this->upsellFilterFunnelId))
        ->orderBy('session_date')
        ->orderBy('session_time')
        ->get();
}
```

**Step 2: Update `getUpsellStatsProperty()` to include total visitors**

Add visitor count query. Update the `conversionRate` to use visitors as denominator (not sessions):

```php
public function getUpsellStatsProperty(): array
{
    $sessions = $this->class->sessions()
        ->whereNotNull('upsell_funnel_ids')
        ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
        ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo));

    $totalSessions = $sessions->count();

    $sessionIds = $sessions->clone()->pluck('id');

    $totalVisitors = \App\Models\FunnelSession::whereIn('class_session_id', $sessionIds)->count();

    $orders = \App\Models\FunnelOrder::whereNotNull('class_session_id')
        ->whereIn('class_session_id', $sessionIds)
        ->get();

    $totalConversions = $orders->count();
    $totalRevenue = $orders->sum('funnel_revenue');
    $conversionRate = $totalVisitors > 0 ? round(($totalConversions / $totalVisitors) * 100, 1) : 0;

    return [
        'total_sessions' => $totalSessions,
        'total_visitors' => $totalVisitors,
        'total_conversions' => $totalConversions,
        'total_revenue' => $totalRevenue,
        'conversion_rate' => $conversionRate,
    ];
}
```

**Step 3: Update stats cards template (around line 7401)**

Add a VISITORS card between SESSIONS WITH UPSELL and CONVERSIONS. Change grid to `lg:grid-cols-5`:

```blade
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions with Upsell</span>
        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $this->upsellStats['total_sessions'] }}</p>
    </div>
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</span>
        <p class="text-xl font-semibold text-blue-600 dark:text-blue-400 mt-1 tabular-nums">{{ $this->upsellStats['total_visitors'] }}</p>
    </div>
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversions</span>
        <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ $this->upsellStats['total_conversions'] }}</p>
    </div>
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</span>
        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">RM {{ number_format($this->upsellStats['total_revenue'], 2) }}</p>
    </div>
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversion Rate</span>
        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $this->upsellStats['conversion_rate'] }}%</p>
    </div>
</div>
```

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php
git commit -m "feat(upsell): add visitor count to upsell tab stats and summary cards"
```

---

### Task 6: Add Visitors Column to Session Table

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php`
  - Table header (line ~7463, before Orders)
  - Table body (line ~7681, before orders cell)

**Step 1: Add VISITORS table header**

Insert before the `Orders` `<th>` (line 7463):

```blade
<th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</th>
```

**Step 2: Add visitors table cell**

Insert before the orders `<td>` (before line 7681):

```blade
<td class="py-2 px-3 text-right font-medium text-blue-600 dark:text-blue-400 tabular-nums">
    {{ $session->upsell_visitors_count ?? 0 }}
</td>
```

**Step 3: Update the colspan on empty state**

At line 7703, change `colspan="12"` to `colspan="13"` to account for the new column.

**Step 4: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php
git commit -m "feat(upsell): add visitors column to session upsell table"
```

---

### Task 7: Add Visitor Count to Session Detail Modal

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php` — the modal section (line ~7727)

**Step 1: Read the current modal stats section**

Read lines 7727-7800 of class-show.blade.php to see the exact modal structure for FUNNELS, ORDERS, REVENUE stats.

**Step 2: Add VISITORS stat card to the modal**

In the modal's stats grid (which shows FUNNELS, ORDERS, REVENUE), add a VISITORS card. It should use:

```blade
{{ \App\Models\FunnelSession::where('class_session_id', $detail->id)->count() }}
```

Insert as the first card (before FUNNELS) since visitors come first in the funnel flow.

**Step 3: Commit**

```bash
git add resources/views/livewire/admin/class-show.blade.php
git commit -m "feat(upsell): add visitor count to session detail modal"
```

---

### Task 8: Write Tests

**Files:**
- Modify: `tests/Feature/Livewire/Admin/ClassUpsellTest.php`

**Step 1: Write test for visitor count in upsell tab**

```php
test('upsell tab shows visitor count for sessions with funnel visits', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_ids' => [$this->funnel->id]]);

    // Create funnel sessions linked to this class session
    \App\Models\FunnelSession::factory()->count(3)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
    ]);

    Volt::test('admin.class-show', ['class' => $this->class])
        ->actingAs($this->admin)
        ->set('activeTab', 'upsell')
        ->assertSee('Visitors');
});
```

**Step 2: Write test for conversion rate based on visitors**

```php
test('upsell stats conversion rate uses visitors as denominator', function () {
    $session = $this->class->sessions()->first();
    $session->update(['upsell_funnel_ids' => [$this->funnel->id]]);

    // 10 visitors
    \App\Models\FunnelSession::factory()->count(10)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
    ]);

    // 2 orders
    \App\Models\FunnelOrder::factory()->count(2)->create([
        'funnel_id' => $this->funnel->id,
        'class_session_id' => $session->id,
        'funnel_revenue' => 50.00,
    ]);

    $component = Volt::test('admin.class-show', ['class' => $this->class])
        ->actingAs($this->admin)
        ->set('activeTab', 'upsell');

    // Conversion rate should be 2/10 = 20%
    $component->assertSee('20');
});
```

**Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/Livewire/Admin/ClassUpsellTest.php`
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/Livewire/Admin/ClassUpsellTest.php
git commit -m "test(upsell): add visitor tracking tests for upsell tab"
```

---

### Task 9: Run Pint and Full Test Suite

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: No new failures.

**Step 3: Final commit if Pint changed anything**

```bash
git add -A
git commit -m "style: apply Pint formatting to upsell visitor tracking"
```
