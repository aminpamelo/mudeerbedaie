# Upsell Phase C — Accountant Workflow Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship per-order PDF receipts and a per-teacher per-period commission payout workflow.

**Architecture:** PDF generation uses DomPDF or the codebase's existing PDF pattern. Commission payouts mirror `LiveHostPayrollRun`: accountant picks date range → preview of unpaid commission per teacher → creates `UpsellCommissionPayout` records → locks → marks paid with bank reference.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest, DomPDF (or barryvdh/laravel-dompdf if installed).

**Design doc:** [2026-05-20-upsell-system-improvements-design.md](2026-05-20-upsell-system-improvements-design.md)

**Depends on:** Phase A (paid orders concept) and Phase B (by-teacher report) must be merged first.

---

## Pre-flight

Check the PDF library in use:

```bash
composer show | grep -i "pdf\|dompdf"
```

If `barryvdh/laravel-dompdf` is installed, use it. If not, install:

```bash
composer require barryvdh/laravel-dompdf --no-interaction
```

Also check for existing receipt templates:

```bash
find resources/views -name "*receipt*" -o -name "*invoice*" 2>/dev/null
```

---

## Task 1: Order receipt PDF template

**Files:**
- Create: `resources/views/pdf/order-receipt.blade.php`
- Create: `app/Services/Receipt/OrderReceiptGenerator.php`
- Test: `tests/Unit/Services/OrderReceiptGeneratorTest.php`

**Step 1: Write test**

```php
<?php

use App\Models\ProductOrder;
use App\Services\Receipt\OrderReceiptGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a PDF for a paid order', function () {
    $order = ProductOrder::factory()->create([
        'payment_status' => 'paid',
        'order_number' => 'MB-2026-0001',
        'total_amount' => 250.00,
    ]);

    $pdfContent = app(OrderReceiptGenerator::class)->generate($order);

    expect($pdfContent)->toBeString();
    expect(strlen($pdfContent))->toBeGreaterThan(1000); // valid PDF byte length
    expect(substr($pdfContent, 0, 4))->toBe('%PDF');
});
```

**Step 2: Run test, verify failure**

```bash
php artisan test --compact --filter=OrderReceiptGeneratorTest
```

**Step 3: Implement the generator**

```php
<?php

namespace App\Services\Receipt;

use App\Models\ProductOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderReceiptGenerator
{
    public function generate(ProductOrder $order): string
    {
        $pdf = Pdf::loadView('pdf.order-receipt', [
            'order' => $order->load(['items', 'customer', 'paymentConfirmedBy']),
            'company' => config('app.company'),
        ]);

        return $pdf->output();
    }
}
```

**Step 4: Create the receipt template**

Use a clean A4 layout. Include:
- Mudeerbedaie header (logo + company info from config)
- Order number, dates
- Customer details
- Line items table (name, qty, price, subtotal)
- Totals (subtotal, shipping, tax, discount, grand total)
- Payment method + reference + confirmation date
- "Paid" stamp if applicable

Keep styles inline (DomPDF doesn't load external CSS). Don't add comments — the template is self-explanatory.

**Step 5: Verify test passes**

```bash
php artisan test --compact --filter=OrderReceiptGeneratorTest
```

**Step 6: Commit**

```bash
git add app/Services/Receipt/ resources/views/pdf/order-receipt.blade.php tests/Unit/Services/OrderReceiptGeneratorTest.php
git commit -m "feat(orders): per-order PDF receipt generator"
```

---

## Task 2: Download receipt button on order list and detail page

**Files:**
- Modify: `resources/views/livewire/admin/orders/order-list.blade.php`
- Modify: ProductOrder show view (find via grep)
- Create: route + controller method for `download-receipt`

**Step 1: Add route**

In `routes/web.php`:

```php
Route::get('/admin/orders/{order}/receipt', [ProductOrderController::class, 'downloadReceipt'])
    ->middleware(['auth', 'role:admin|accountant'])
    ->name('admin.orders.receipt');
```

**Step 2: Add controller method**

```php
public function downloadReceipt(ProductOrder $order, OrderReceiptGenerator $generator)
{
    $pdf = $generator->generate($order);
    return response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="receipt-' . $order->order_number . '.pdf"',
    ]);
}
```

**Step 3: Add button to order list rows**

```blade
<flux:button as="a" href="{{ route('admin.orders.receipt', $order) }}" size="sm" variant="ghost">
    <div class="flex items-center justify-center">
        <flux:icon name="arrow-down-tray" class="w-4 h-4" />
    </div>
</flux:button>
```

(Use the Flux button icon alignment pattern documented in CLAUDE.md.)

**Step 4: Test in browser**

```bash
composer run dev
```

Visit `/admin/orders`, click download on any order, verify PDF opens.

**Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/Admin/ProductOrderController.php resources/views/livewire/admin/orders/order-list.blade.php
git commit -m "feat(orders): download receipt button"
```

---

## Task 3: `UpsellCommissionPayout` model + migration

**Files:**
- Create: `database/migrations/2026_05_20_000010_create_upsell_commission_payouts_table.php`
- Create: `database/migrations/2026_05_20_000011_create_upsell_commission_payout_sessions_table.php`
- Create: `app/Models/UpsellCommissionPayout.php`
- Create: `database/factories/UpsellCommissionPayoutFactory.php`

**Step 1: Create migrations**

```bash
php artisan make:migration create_upsell_commission_payouts_table --no-interaction
php artisan make:migration create_upsell_commission_payout_sessions_table --no-interaction
```

**Payouts schema:**

```php
Schema::create('upsell_commission_payouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
    $table->date('period_start');
    $table->date('period_end');
    $table->decimal('total_commission', 12, 2);
    $table->integer('session_count');
    $table->string('status', 20)->default('draft')->index(); // draft | locked | paid
    $table->timestamp('locked_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->string('payment_reference', 100)->nullable();
    $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Pivot schema:**

```php
Schema::create('upsell_commission_payout_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('upsell_commission_payout_id')->constrained()->cascadeOnDelete();
    $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
    $table->decimal('paid_revenue', 12, 2);
    $table->decimal('commission_rate', 5, 2);
    $table->decimal('commission_amount', 12, 2);
    $table->timestamps();

    $table->unique(['upsell_commission_payout_id', 'class_session_id'], 'payout_session_unique');
});
```

**Step 2: Create model**

```bash
php artisan make:model UpsellCommissionPayout --factory --no-interaction
```

Add fillable, casts, relationships:

```php
protected $fillable = ['teacher_user_id', 'period_start', 'period_end', 'total_commission', 'session_count', 'status', 'locked_at', 'paid_at', 'payment_reference', 'paid_by_user_id', 'notes'];

protected function casts(): array
{
    return [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_commission' => 'decimal:2',
    ];
}

public function teacher() { return $this->belongsTo(User::class, 'teacher_user_id'); }
public function paidBy() { return $this->belongsTo(User::class, 'paid_by_user_id'); }
public function sessions() { return $this->hasMany(UpsellCommissionPayoutSession::class); }

public function lock(): void { $this->update(['status' => 'locked', 'locked_at' => now()]); }
public function markPaid(int $userId, string $reference): void {
    $this->update(['status' => 'paid', 'paid_at' => now(), 'paid_by_user_id' => $userId, 'payment_reference' => $reference]);
}
```

**Step 3: Run migration**

```bash
php artisan migrate --no-interaction
```

**Step 4: Commit**

```bash
git add database/migrations/2026_05_20_*upsell_commission_payout* app/Models/UpsellCommissionPayout* database/factories/UpsellCommissionPayoutFactory.php
git commit -m "feat(upsell): UpsellCommissionPayout model and schema"
```

---

## Task 4: Payout preview service

**Files:**
- Create: `app/Services/Upsell/CommissionPayoutService.php`
- Test: `tests/Unit/Services/CommissionPayoutServiceTest.php`

**Step 1: Write test**

```php
<?php

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\User;
use App\Services\Upsell\CommissionPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews unpaid commission per teacher in a date range', function () {
    $teacher = User::factory()->create();
    $session = ClassSession::factory()->create([
        'upsell_teacher_id' => $teacher->id,
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-05-10',
    ]);
    $paidOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paidOrder->id,
        'funnel_revenue' => 1000,
    ]);

    $preview = app(CommissionPayoutService::class)->preview('2026-05-01', '2026-05-31');

    expect($preview)->toHaveCount(1);
    expect($preview->first()['teacher_id'])->toBe($teacher->id);
    expect($preview->first()['commission_total'])->toBe(100.0);
});

it('excludes sessions already in a payout', function () {
    // Create session, paid order, then a payout covering it
    // Preview should return zero / not list that session
});

it('creates a payout from preview rows', function () {
    // Call createPayout(teacherId, periodStart, periodEnd, sessionIds)
    // Assert UpsellCommissionPayout + pivot rows created with correct amounts
});

it('marks a payout as paid', function () {
    // Lock + markPaid flow
});
```

**Step 2: Run, verify failure**

**Step 3: Implement the service**

```php
<?php

namespace App\Services\Upsell;

use App\Models\ClassSession;
use App\Models\UpsellCommissionPayout;
use App\Models\UpsellCommissionPayoutSession;
use App\Services\Upsell\UpsellPaidOrdersQuery;
use Illuminate\Support\Facades\DB;

class CommissionPayoutService
{
    public function preview(string $from, string $to): \Illuminate\Support\Collection
    {
        $alreadyPaid = UpsellCommissionPayoutSession::query()
            ->whereHas('payout', fn($q) => $q->whereIn('status', ['draft', 'locked', 'paid']))
            ->pluck('class_session_id');

        return app(UpsellPaidOrdersQuery::class)
            ->forDateRange($from, $to)
            ->baseQuery()
            ->whereNotIn('class_session_id', $alreadyPaid)
            ->with('classSession')
            ->get()
            ->groupBy(fn($o) => $o->classSession->upsell_teacher_id)
            ->map(function ($orders, $teacherId) {
                $sessions = $orders->pluck('classSession')->unique('id');
                $commissionTotal = $orders->sum(fn($o) =>
                    $o->funnel_revenue * ($o->classSession->upsell_teacher_commission_rate / 100)
                );
                return [
                    'teacher_id' => $teacherId,
                    'teacher_name' => \App\Models\User::find($teacherId)?->name,
                    'session_ids' => $sessions->pluck('id')->values(),
                    'session_count' => $sessions->count(),
                    'commission_total' => $commissionTotal,
                ];
            })
            ->filter(fn($r) => $r['teacher_id'])
            ->values();
    }

    public function createPayout(int $teacherId, string $from, string $to, array $sessionIds): UpsellCommissionPayout
    {
        return DB::transaction(function () use ($teacherId, $from, $to, $sessionIds) {
            $sessions = ClassSession::with('funnelOrders.productOrder')
                ->whereIn('id', $sessionIds)
                ->get();

            $totalCommission = 0;
            $pivotRows = [];

            foreach ($sessions as $session) {
                $paidRevenue = $session->funnelOrders
                    ->where('productOrder.payment_status', 'paid')
                    ->sum('funnel_revenue');
                $commission = $paidRevenue * ($session->upsell_teacher_commission_rate / 100);
                $totalCommission += $commission;

                $pivotRows[] = [
                    'class_session_id' => $session->id,
                    'paid_revenue' => $paidRevenue,
                    'commission_rate' => $session->upsell_teacher_commission_rate,
                    'commission_amount' => $commission,
                ];
            }

            $payout = UpsellCommissionPayout::create([
                'teacher_user_id' => $teacherId,
                'period_start' => $from,
                'period_end' => $to,
                'total_commission' => $totalCommission,
                'session_count' => count($sessionIds),
                'status' => 'draft',
            ]);

            foreach ($pivotRows as $row) {
                $payout->sessions()->create($row);
            }

            return $payout;
        });
    }
}
```

**Step 4: Verify**

```bash
php artisan test --compact --filter=CommissionPayoutServiceTest
```

**Step 5: Commit**

```bash
git add app/Services/Upsell/CommissionPayoutService.php tests/Unit/Services/CommissionPayoutServiceTest.php
git commit -m "feat(upsell): commission payout preview and creation service"
```

---

## Task 5: Accountant payout admin page

**Files:**
- Create: `resources/views/livewire/admin/upsell-commission-payouts.blade.php` (Volt)
- Add route: `Volt::route('upsell-commissions', 'admin.upsell-commission-payouts')->name('admin.upsell-commissions');`
- Test: `tests/Feature/Livewire/Admin/UpsellCommissionPayoutsTest.php`

**Step 1: Write test**

```php
<?php

use App\Models\UpsellCommissionPayout;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->accountant = User::factory()->create();
    $this->accountant->roles()->attach(Role::firstOrCreate(['name' => 'accountant']));
});

it('shows preview of unpaid commission per teacher', function () {
    // Seed teacher with paid funnel order
    Volt::test('admin.upsell-commission-payouts')
        ->actingAs($this->accountant)
        ->set('from', '2026-05-01')
        ->set('to', '2026-05-31')
        ->call('loadPreview')
        ->assertSee('RM 100.00');
});

it('creates payout from preview', function () {
    // Seed, load preview, call createPayout for selected teacher
    // Assert UpsellCommissionPayout record exists with status='draft'
});

it('locks a draft payout', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'draft']);
    Volt::test('admin.upsell-commission-payouts')
        ->actingAs($this->accountant)
        ->call('lock', $payout->id)
        ->assertHasNoErrors();
    expect($payout->fresh()->status)->toBe('locked');
});

it('marks a locked payout as paid with bank reference', function () {
    $payout = UpsellCommissionPayout::factory()->create(['status' => 'locked']);
    Volt::test('admin.upsell-commission-payouts')
        ->actingAs($this->accountant)
        ->set('paymentReference', 'TXN-12345')
        ->call('markPaid', $payout->id)
        ->assertHasNoErrors();
    expect($payout->fresh())
        ->status->toBe('paid')
        ->payment_reference->toBe('TXN-12345');
});
```

**Step 2: Build the Volt page**

Three sections on one page:

1. **Preview section** — date range pickers + "Load Preview" button + table of (teacher, session count, commission, "Create Payout" button per row).
2. **Draft payouts list** — show payouts with status='draft', each with "Edit notes", "Lock" buttons.
3. **Locked + paid history** — table with status badges; locked payouts have "Mark Paid" modal (paymentReference input + paid date picker).

PHP block structure:

```php
<?php
use App\Services\Upsell\CommissionPayoutService;
use App\Models\UpsellCommissionPayout;
use Livewire\Volt\Component;

new class extends Component {
    public string $from;
    public string $to;
    public array $preview = [];
    public string $paymentReference = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function loadPreview(): void
    {
        $this->authorize('manageUpsellCommissions');
        $this->preview = app(CommissionPayoutService::class)
            ->preview($this->from, $this->to)
            ->toArray();
    }

    public function createPayout(int $teacherId): void
    {
        $row = collect($this->preview)->firstWhere('teacher_id', $teacherId);
        if (!$row) return;
        app(CommissionPayoutService::class)
            ->createPayout($teacherId, $this->from, $this->to, $row['session_ids']);
        $this->loadPreview();
    }

    public function lock(int $payoutId): void
    {
        UpsellCommissionPayout::findOrFail($payoutId)->lock();
    }

    public function markPaid(int $payoutId): void
    {
        $this->validate(['paymentReference' => 'required|string|min:3|max:100']);
        UpsellCommissionPayout::findOrFail($payoutId)
            ->markPaid(auth()->id(), $this->paymentReference);
        $this->paymentReference = '';
    }

    public function with(): array
    {
        return [
            'draftPayouts' => UpsellCommissionPayout::where('status', 'draft')->with('teacher')->get(),
            'lockedPayouts' => UpsellCommissionPayout::where('status', 'locked')->with('teacher')->get(),
            'paidHistory' => UpsellCommissionPayout::where('status', 'paid')->with('teacher')->latest('paid_at')->limit(50)->get(),
        ];
    }
}; ?>

<div>
    {{-- Sections here --}}
</div>
```

**Step 3: Register route**

In `routes/web.php` (under the admin prefix):

```php
Volt::route('upsell-commissions', 'admin.upsell-commission-payouts')
    ->middleware(['auth', 'role:accountant'])
    ->name('admin.upsell-commissions');
```

**Step 4: Add nav link**

In the admin sidebar layout, add a link to the new page (only visible to accountant role).

**Step 5: Verify**

```bash
php artisan test --compact --filter=UpsellCommissionPayoutsTest
composer run dev
```

Smoke test the full flow in the browser.

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/upsell-commission-payouts.blade.php routes/web.php tests/Feature/Livewire/Admin/UpsellCommissionPayoutsTest.php
git commit -m "feat(upsell): accountant commission payout admin page"
```

---

## Task 6: Wire payout data into Phase B reports

After payouts exist, the by-teacher table and the teacher upsell tab should show:
- Commission earned (total from query)
- Commission paid (sum from UpsellCommissionPayoutSession where payout.status='paid')
- Commission pending (earned − paid)

**Files:**
- Modify: `app/Services/Upsell/UpsellPaidOrdersQuery.php`
- Modify: `resources/views/livewire/admin/upsell-dashboard.blade.php` (by-teacher table)
- Modify: `resources/views/livewire/admin/teacher-show.blade.php` (upsell tab)

Add a `commission_paid` field to `byTeacher()` output by joining `upsell_commission_payout_sessions` on `class_session_id` where the payout status is `paid`.

**Step 1: Update byTeacher in the query service**

```php
public function byTeacher(): \Illuminate\Support\Collection
{
    $rows = /* existing logic */;
    return $rows->map(function ($row) {
        $row['commission_paid'] = \App\Models\UpsellCommissionPayoutSession::query()
            ->whereHas('payout', fn($q) => $q->where('teacher_user_id', $row['teacher_id'])->where('status', 'paid'))
            ->sum('commission_amount');
        $row['commission_pending'] = $row['commission_earned'] - $row['commission_paid'];
        return $row;
    });
}
```

**Step 2: Add columns to the by-teacher table**

```blade
<th class="text-right py-2">Earned</th>
<th class="text-right py-2">Paid</th>
<th class="text-right py-2">Pending</th>
```

**Step 3: Update teacher upsell tab stat cards**

Add a "Commission Paid" card next to "Commission Earned" so the teacher's outstanding balance is visible at a glance.

**Step 4: Commit**

```bash
git add app/Services/Upsell/UpsellPaidOrdersQuery.php resources/views/livewire/admin/upsell-dashboard.blade.php resources/views/livewire/admin/teacher-show.blade.php
git commit -m "feat(upsell): expose commission paid/pending in reports"
```

---

## Phase C done — verification

```bash
php artisan test --compact
vendor/bin/pint --dirty
composer run dev
```

End-to-end smoke test:
1. Accountant logs in.
2. Goes to `/admin/orders`, downloads PDF receipt for a paid order → opens cleanly.
3. Goes to `/admin/upsell-commissions`, picks May 1-31, clicks Load Preview → sees unpaid commission per teacher.
4. Clicks "Create Payout" on one teacher → draft payout appears in draft list.
5. Clicks "Lock" → moves to locked section.
6. Clicks "Mark Paid", enters bank reference → moves to paid history.
7. Visits the upsell dashboard → by-teacher table now shows "Paid" column with the amount.
8. Visits the teacher's account page → Commission Paid card reflects the same amount.
