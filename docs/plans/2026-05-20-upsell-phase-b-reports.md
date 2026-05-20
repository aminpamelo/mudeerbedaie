# Upsell Phase B — Reports Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add by-teacher and by-product breakdowns to the upsell dashboard, plus an upsell tab on the teacher account page.

**Architecture:** Build a shared `UpsellPaidOrdersQuery` service that every report consumes. Add two new sections to the upsell dashboard. Add a new tab + 4 stat cards + sessions table to the teacher-show page.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest.

**Design doc:** [2026-05-20-upsell-system-improvements-design.md](2026-05-20-upsell-system-improvements-design.md)

**Depends on:** Phase A must be merged first. All reports read `payment_status='paid'`.

---

## Task 1: Shared paid-orders query service

**Files:**
- Create: `app/Services/Upsell/UpsellPaidOrdersQuery.php`
- Test: `tests/Unit/Services/UpsellPaidOrdersQueryTest.php`

**Step 1: Write test**

```php
<?php

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Services\Upsell\UpsellPaidOrdersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns paid funnel orders within date range', function () {
    $session = ClassSession::factory()->create([
        'session_date' => '2026-05-15',
        'upsell_funnel_ids' => [1],
    ]);
    $paidOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    $pendingOrder = ProductOrder::factory()->create(['payment_status' => 'pending']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paidOrder->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $pendingOrder->id,
        'funnel_revenue' => 50,
    ]);

    $query = app(UpsellPaidOrdersQuery::class);
    $rows = $query->forDateRange('2026-05-01', '2026-05-31')->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->funnel_revenue)->toBe('100.00');
});

it('groups paid revenue by teacher', function () {
    // Two sessions, two different teachers, each with paid orders
    // Assert each teacher's row has correct revenue + commission
});

it('groups paid revenue by product', function () {
    // Product order with line items: main product + bump
    // Assert by-product breakdown lists each line separately
});
```

**Step 2: Run test, verify failure**

```bash
php artisan test --compact --filter=UpsellPaidOrdersQueryTest
```

**Step 3: Implement the service**

```php
<?php

namespace App\Services\Upsell;

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use Illuminate\Database\Eloquent\Builder;

class UpsellPaidOrdersQuery
{
    private ?string $from = null;
    private ?string $to = null;

    public function forDateRange(?string $from, ?string $to): self
    {
        $this->from = $from;
        $this->to = $to;
        return $this;
    }

    public function baseQuery(): Builder
    {
        return FunnelOrder::query()
            ->whereHas('productOrder', fn($q) => $q->where('payment_status', 'paid'))
            ->whereHas('classSession', function ($q) {
                $q->whereNotNull('upsell_funnel_ids');
                if ($this->from) {
                    $q->whereDate('session_date', '>=', $this->from);
                }
                if ($this->to) {
                    $q->whereDate('session_date', '<=', $this->to);
                }
            });
    }

    public function get()
    {
        return $this->baseQuery()->with(['productOrder.items.product', 'classSession.upsellTeacher'])->get();
    }

    public function byTeacher(): \Illuminate\Support\Collection
    {
        return $this->baseQuery()
            ->with(['classSession'])
            ->get()
            ->groupBy(fn($order) => $order->classSession->upsell_teacher_id ?? null)
            ->map(function ($orders, $teacherId) {
                if (! $teacherId) { return null; }
                $sessions = $orders->pluck('classSession')->unique('id');
                $revenue = $orders->sum('funnel_revenue');
                $commission = $orders->sum(function ($o) {
                    return $o->funnel_revenue * ($o->classSession->upsell_teacher_commission_rate / 100);
                });

                return [
                    'teacher_id' => $teacherId,
                    'sessions_count' => $sessions->count(),
                    'paid_orders' => $orders->count(),
                    'paid_revenue' => $revenue,
                    'commission_earned' => $commission,
                ];
            })
            ->filter()
            ->sortByDesc('commission_earned')
            ->values();
    }

    public function byProduct(): \Illuminate\Support\Collection
    {
        $orders = $this->baseQuery()->with('productOrder.items')->get();
        $lines = collect();
        foreach ($orders as $order) {
            foreach ($order->productOrder->items as $item) {
                $lines->push([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_snapshot['name'] ?? 'Unknown',
                    'line_type' => $item->line_type ?? 'main', // 'main', 'bump', 'upsell'
                    'units' => $item->quantity,
                    'revenue' => $item->subtotal,
                    'funnel_id' => $order->funnel_id,
                ]);
            }
        }
        return $lines->groupBy('product_id')->map(function ($lines, $productId) {
            return [
                'product_id' => $productId,
                'product_name' => $lines->first()['product_name'],
                'line_type' => $lines->first()['line_type'],
                'funnel_ids' => $lines->pluck('funnel_id')->unique()->values(),
                'units' => $lines->sum('units'),
                'revenue' => $lines->sum('revenue'),
            ];
        })->sortByDesc('revenue')->values();
    }
}
```

**Note:** This requires `class_sessions` to have an `upsell_teacher_id` field. Check via `Schema::getColumnListing('class_sessions')`. If only `upsell_teacher_ids` (JSON, plural) exists, adjust grouping to flatten the JSON array.

**Step 4: Verify tests pass**

```bash
php artisan test --compact --filter=UpsellPaidOrdersQueryTest
```

**Step 5: Commit**

```bash
git add app/Services/Upsell/UpsellPaidOrdersQuery.php tests/Unit/Services/UpsellPaidOrdersQueryTest.php
git commit -m "feat(upsell): shared paid-orders query service for reports"
```

---

## Task 2: By-teacher table on upsell dashboard

**Files:**
- Modify: `resources/views/livewire/admin/upsell-dashboard.blade.php`
- Test: extend `tests/Feature/Livewire/Admin/UpsellDashboardCommissionTest.php`

**Step 1: Write test**

```php
it('displays by-teacher breakdown sorted by commission', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin']));
    $teacher1 = User::factory()->create(['name' => 'Top Teacher']);
    $teacher2 = User::factory()->create(['name' => 'Other Teacher']);
    // Create sessions + paid orders for each
    // teacher1 earns RM 200 commission, teacher2 earns RM 50

    Volt::test('admin.upsell-dashboard')
        ->actingAs($admin)
        ->assertSeeInOrder(['Top Teacher', 'Other Teacher']);
});
```

**Step 2: Add the section to the dashboard view**

After the existing "by PIC" section, add:

```blade
<flux:card class="mt-6">
    <flux:heading size="lg">Performance by Teacher</flux:heading>
    <flux:text class="mt-2">Commission earned by each upsell teacher.</flux:text>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Teacher</th>
                    <th class="text-right py-2">Sessions</th>
                    <th class="text-right py-2">Paid Orders</th>
                    <th class="text-right py-2">Paid Revenue</th>
                    <th class="text-right py-2">Commission Earned</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->byTeacher as $row)
                <tr wire:key="teacher-{{ $row['teacher_id'] }}" class="border-b hover:bg-gray-50">
                    <td class="py-2">
                        <a href="{{ route('admin.teachers.show', $row['teacher_id']) }}" class="text-blue-600 hover:underline">
                            {{ $row['teacher_name'] ?? '—' }}
                        </a>
                    </td>
                    <td class="text-right py-2">{{ $row['sessions_count'] }}</td>
                    <td class="text-right py-2">{{ $row['paid_orders'] }}</td>
                    <td class="text-right py-2">RM {{ number_format($row['paid_revenue'], 2) }}</td>
                    <td class="text-right py-2 font-semibold">RM {{ number_format($row['commission_earned'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</flux:card>
```

**Step 3: Add the computed property in the Volt PHP block**

```php
public function getByTeacherProperty()
{
    return app(UpsellPaidOrdersQuery::class)
        ->forDateRange($this->startDate, $this->endDate)
        ->byTeacher()
        ->map(function ($row) {
            $row['teacher_name'] = \App\Models\User::find($row['teacher_id'])?->name;
            return $row;
        });
}
```

**Step 4: Verify**

```bash
php artisan test --compact --filter=UpsellDashboardCommissionTest
composer run dev
```

Visit dashboard, confirm teacher table appears.

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/upsell-dashboard.blade.php tests/Feature/Livewire/Admin/UpsellDashboardCommissionTest.php
git commit -m "feat(upsell): by-teacher breakdown on dashboard"
```

---

## Task 3: By-product table on upsell dashboard

**Files:**
- Modify: `resources/views/livewire/admin/upsell-dashboard.blade.php`

**Step 1: Write test**

```php
it('displays by-product breakdown including bumps and upsells', function () {
    // Create paid orders with main product + bump + upsell line items
    // Assert all three appear as separate rows
});
```

**Step 2: Add section to view**

```blade
<flux:card class="mt-6">
    <flux:heading size="lg">Performance by Product</flux:heading>
    <flux:text class="mt-2">Products sold through funnels (paid orders only).</flux:text>

    <table class="w-full text-sm mt-4">
        <thead>
            <tr class="border-b">
                <th class="text-left py-2">Product</th>
                <th class="text-left py-2">Type</th>
                <th class="text-left py-2">Funnels</th>
                <th class="text-right py-2">Units</th>
                <th class="text-right py-2">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($this->byProduct as $row)
            <tr wire:key="product-{{ $row['product_id'] }}" class="border-b">
                <td class="py-2">{{ $row['product_name'] }}</td>
                <td class="py-2"><flux:badge>{{ ucfirst($row['line_type']) }}</flux:badge></td>
                <td class="py-2">{{ $row['funnel_ids']->count() }} funnel(s)</td>
                <td class="text-right py-2">{{ $row['units'] }}</td>
                <td class="text-right py-2">RM {{ number_format($row['revenue'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</flux:card>
```

**Step 3: Add computed property**

```php
public function getByProductProperty()
{
    return app(UpsellPaidOrdersQuery::class)
        ->forDateRange($this->startDate, $this->endDate)
        ->byProduct();
}
```

**Step 4: Verify line_type detection**

Check `product_order_items` schema for whether it has a `line_type` column or some other way to distinguish main / bump / upsell. If absent, inspect `metadata` JSON or trace how funnel order bumps are recorded. Likely candidates:
- `product_order_items.metadata->is_bump`
- `product_order_items.metadata->source` (`main`, `bump`, `upsell`)
- A separate `funnel_order_bumps` table

Adjust the query accordingly.

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/upsell-dashboard.blade.php
git commit -m "feat(upsell): by-product breakdown on dashboard"
```

---

## Task 4: Upsell tab on teacher-show page

**Files:**
- Modify: `resources/views/livewire/admin/teacher-show.blade.php`
- Test: `tests/Feature/Livewire/Admin/TeacherShowUpsellTabTest.php`

**Step 1: Write test**

```php
<?php

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('shows upsell tab with paid revenue and commission for teacher', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin']));
    $teacher = User::factory()->create();
    $teacher->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'teacher']));

    $session = ClassSession::factory()->create([
        'upsell_teacher_id' => $teacher->id,
        'upsell_teacher_commission_rate' => 15.0,
        'upsell_funnel_ids' => [1],
    ]);
    $paidOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paidOrder->id,
        'funnel_revenue' => 1000,
    ]);

    Volt::test('admin.teacher-show', ['teacher' => $teacher])
        ->actingAs($admin)
        ->set('activeTab', 'upsell')
        ->assertSee('RM 1,000.00') // paid revenue
        ->assertSee('RM 150.00');  // commission (15% of 1000)
});
```

**Step 2: Run, verify failure**

**Step 3: Add tab + content to teacher-show.blade.php**

If the page already uses a tab pattern (check for `$activeTab` property), add a new tab "Upsell". Otherwise, add the tabs structure first.

Tab content:
- 4 stat cards: Sessions with upsell, Paid orders, Paid revenue, Commission earned
- Date range filter (own, defaults to last 90 days)
- Table of sessions in range

```blade
@if($activeTab === 'upsell')
<div class="space-y-6">
    {{-- Date filter --}}
    <div class="flex gap-4 items-end">
        <flux:input type="date" wire:model.live="upsellDateFrom" label="From" />
        <flux:input type="date" wire:model.live="upsellDateTo" label="To" />
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <flux:card>
            <flux:text>Sessions with Upsell</flux:text>
            <flux:heading size="xl">{{ $this->upsellStats['sessions'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text>Paid Orders</flux:text>
            <flux:heading size="xl">{{ $this->upsellStats['paid_orders'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text>Paid Revenue</flux:text>
            <flux:heading size="xl">RM {{ number_format($this->upsellStats['paid_revenue'], 2) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text>Commission Earned</flux:text>
            <flux:heading size="xl">RM {{ number_format($this->upsellStats['commission_earned'], 2) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Sessions table --}}
    <flux:card>
        <flux:heading size="lg">Upsell Sessions</flux:heading>
        <table class="w-full text-sm mt-4">
            {{-- columns: date, class, funnel, paid revenue, commission, link --}}
        </table>
    </flux:card>
</div>
@endif
```

**Step 4: Add the PHP**

```php
public string $activeTab = 'overview';
public string $upsellDateFrom;
public string $upsellDateTo;

public function mount(): void
{
    $this->upsellDateFrom = now()->subDays(90)->toDateString();
    $this->upsellDateTo = now()->toDateString();
}

public function getUpsellStatsProperty(): array
{
    $rows = app(UpsellPaidOrdersQuery::class)
        ->forDateRange($this->upsellDateFrom, $this->upsellDateTo)
        ->byTeacher()
        ->firstWhere('teacher_id', $this->teacher->id);

    return $rows ?? [
        'sessions' => 0,
        'paid_orders' => 0,
        'paid_revenue' => 0,
        'commission_earned' => 0,
    ];
}
```

**Step 5: Verify**

```bash
php artisan test --compact --filter=TeacherShowUpsellTabTest
```

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/teacher-show.blade.php tests/Feature/Livewire/Admin/TeacherShowUpsellTabTest.php
git commit -m "feat(upsell): upsell tab on teacher account page"
```

---

## Phase B done — verification

```bash
php artisan test --compact
vendor/bin/pint --dirty
composer run dev
```

Smoke test:
1. Upsell dashboard shows by-teacher and by-product sections.
2. Teacher detail page has Upsell tab with correct numbers.
3. Date filters update the data correctly.
4. Numbers match Phase A's paid-only commission.
