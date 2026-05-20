# Upsell Phase A — Payment Correctness Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a manual payment-approval flow for funnel COD orders so commissions and reports stop counting unpaid revenue.

**Architecture:** Add a proper `payment_status` enum to `product_orders` (the column is referenced in code but missing from schema). Backfill existing rows from `status` + `paid_time`. Build an accountant-only approve/reject card on the order detail page, mirroring the existing course Order flow. Switch upsell-dashboard commission queries to filter on `payment_status='paid'`.

**Tech Stack:** Laravel 12, Livewire Volt, Flux UI, Pest, SQLite (dev) + MySQL (prod).

**Design doc:** [2026-05-20-upsell-system-improvements-design.md](2026-05-20-upsell-system-improvements-design.md)

---

## Pre-flight verification

Before starting, confirm the current state:

```bash
php artisan tinker --execute="echo json_encode(\Schema::getColumnListing('product_orders'));"
```

Expected: list does NOT contain `payment_status`. List DOES contain `status`, `paid_time`, `receipt_attachment`.

If `payment_status` already exists, skip Task 1 and proceed to Task 2.

---

## Task 1: Add `payment_status` column to `product_orders`

**Files:**
- Create: `database/migrations/2026_05_20_000001_add_payment_status_to_product_orders_table.php`
- Test: `tests/Feature/ProductOrderPaymentStatusTest.php`

**Step 1: Generate the migration**

```bash
php artisan make:migration add_payment_status_to_product_orders_table --table=product_orders --no-interaction
```

Rename the generated file to `2026_05_20_000001_add_payment_status_to_product_orders_table.php` (or use the suggested name if it matches).

**Step 2: Write migration with dual-driver support**

The column must work on both MySQL and SQLite. Use `string` type with default `'pending'`, then backfill, then convert to enum-like with check constraint on MySQL only. Values: `pending`, `paid`, `failed`, `refunded`.

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
        Schema::table('product_orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending')->after('status')->index();
            $table->foreignId('payment_confirmed_by_user_id')->nullable()->after('payment_status')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmed_by_user_id');
            $table->text('payment_rejection_reason')->nullable()->after('payment_confirmed_at');
        });

        // Backfill from existing data
        DB::table('product_orders')
            ->whereIn('status', ['confirmed', 'processing', 'partially_shipped', 'shipped', 'delivered'])
            ->orWhereNotNull('paid_time')
            ->update(['payment_status' => 'paid']);

        DB::table('product_orders')
            ->whereIn('status', ['cancelled', 'failed'])
            ->update(['payment_status' => 'failed']);

        DB::table('product_orders')
            ->where('status', 'refunded')
            ->update(['payment_status' => 'refunded']);
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_confirmed_by_user_id', 'payment_confirmed_at', 'payment_rejection_reason']);
        });
    }
};
```

**Step 3: Write the test**

```php
<?php

use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has payment_status column with pending default', function () {
    $order = ProductOrder::factory()->create();
    expect($order->payment_status)->toBe('pending');
});

it('backfills payment_status from status on existing rows', function () {
    $confirmed = ProductOrder::factory()->create(['status' => 'confirmed']);
    $cancelled = ProductOrder::factory()->create(['status' => 'cancelled']);

    expect($confirmed->fresh()->payment_status)->toBe('paid');
    expect($cancelled->fresh()->payment_status)->toBe('failed');
});
```

**Step 4: Run migration + test**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ProductOrderPaymentStatusTest
```

Expected: migration succeeds, both tests pass.

**Step 5: Commit**

```bash
git add database/migrations/2026_05_20_000001_add_payment_status_to_product_orders_table.php tests/Feature/ProductOrderPaymentStatusTest.php
git commit -m "feat(upsell): add payment_status column to product_orders"
```

---

## Task 2: Add fillable + casts + helper methods to ProductOrder

**Files:**
- Modify: `app/Models/ProductOrder.php`
- Test: `tests/Feature/ProductOrderPaymentTest.php`

**Step 1: Write failing test**

```php
<?php

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a pending order as paid', function () {
    $accountant = User::factory()->create();
    $order = ProductOrder::factory()->create(['payment_status' => 'pending']);

    $order->markPaymentAsConfirmed($accountant->id, 'receipts/test.pdf');

    expect($order->fresh())
        ->payment_status->toBe('paid')
        ->payment_confirmed_by_user_id->toBe($accountant->id)
        ->receipt_attachment->toBe('receipts/test.pdf')
        ->and($order->fresh()->payment_confirmed_at)->not->toBeNull();
});

it('rejects a pending order with a reason', function () {
    $accountant = User::factory()->create();
    $order = ProductOrder::factory()->create(['payment_status' => 'pending']);

    $order->markPaymentAsRejected($accountant->id, 'No bank transfer received');

    expect($order->fresh())
        ->payment_status->toBe('failed')
        ->payment_rejection_reason->toBe('No bank transfer received');
});

it('exposes scopes for paid and pending orders', function () {
    ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrder::factory()->create(['payment_status' => 'pending']);
    ProductOrder::factory()->create(['payment_status' => 'failed']);

    expect(ProductOrder::paid()->count())->toBe(1);
    expect(ProductOrder::awaitingPayment()->count())->toBe(1);
});
```

**Step 2: Run test, verify failure**

```bash
php artisan test --compact --filter=ProductOrderPaymentTest
```

Expected: FAIL — method not defined.

**Step 3: Implement in `app/Models/ProductOrder.php`**

Add to `$fillable`:
```php
'payment_status', 'payment_confirmed_by_user_id', 'payment_confirmed_at', 'payment_rejection_reason',
```

Add to `casts()`:
```php
'payment_confirmed_at' => 'datetime',
```

Add methods:
```php
public function markPaymentAsConfirmed(int $userId, string $receiptPath): void
{
    $this->update([
        'payment_status' => 'paid',
        'payment_confirmed_by_user_id' => $userId,
        'payment_confirmed_at' => now(),
        'receipt_attachment' => $receiptPath,
        'paid_time' => $this->paid_time ?? now(),
        'status' => $this->status === 'pending' ? 'confirmed' : $this->status,
    ]);
}

public function markPaymentAsRejected(int $userId, string $reason): void
{
    $this->update([
        'payment_status' => 'failed',
        'payment_confirmed_by_user_id' => $userId,
        'payment_confirmed_at' => now(),
        'payment_rejection_reason' => $reason,
    ]);
}

public function scopePaid($query)
{
    return $query->where('payment_status', 'paid');
}

public function scopeAwaitingPayment($query)
{
    return $query->where('payment_status', 'pending');
}

public function paymentConfirmedBy()
{
    return $this->belongsTo(User::class, 'payment_confirmed_by_user_id');
}
```

**Step 4: Verify test passes**

```bash
php artisan test --compact --filter=ProductOrderPaymentTest
```

Expected: all 3 tests pass.

**Step 5: Commit**

```bash
git add app/Models/ProductOrder.php tests/Feature/ProductOrderPaymentTest.php
git commit -m "feat(upsell): add payment confirmation methods to ProductOrder"
```

---

## Task 3: Wire FunnelCheckoutService to set payment_status correctly

**Files:**
- Modify: `app/Services/Funnel/FunnelCheckoutService.php` (around lines 175-257 and 334-423)
- Test: `tests/Feature/FunnelCheckoutPaymentStatusTest.php`

The service currently writes `payment_status` to `ProductOrder::create()` but the column didn't exist. Now it does. We also need to confirm Stripe and Bayarcash paths flip it to `'paid'`.

**Step 1: Write test**

```php
<?php

use App\Models\Funnel;
use App\Models\ProductOrder;
use App\Services\Funnel\FunnelCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates funnel COD orders with pending payment_status', function () {
    // Arrange a funnel + checkout request that selects COD
    // (Use factories — see existing FunnelCheckoutControllerTest for setup pattern)
    // ...
    expect($order->payment_status)->toBe('pending');
});

it('flips payment_status to paid on Stripe confirm', function () {
    // Existing confirmPayment test scaffold
    // ...
    expect($order->fresh()->payment_status)->toBe('paid');
});
```

**Step 2: Run test, verify failure**

```bash
php artisan test --compact --filter=FunnelCheckoutPaymentStatusTest
```

**Step 3: Verify and fix the service code**

Read [FunnelCheckoutService.php:175-257](../../app/Services/Funnel/FunnelCheckoutService.php#L175-L257) and [FunnelCheckoutService.php:334-423](../../app/Services/Funnel/FunnelCheckoutService.php#L334-L423). Confirm:
- Initial create sets `payment_status => 'pending'`
- `confirmPayment()` sets `payment_status => 'paid'` and `payment_confirmed_at => now()`
- COD path does NOT auto-mark as paid

If any of these are wrong, fix them.

**Step 4: Verify test passes**

```bash
php artisan test --compact --filter=FunnelCheckoutPaymentStatusTest
```

**Step 5: Commit**

```bash
git add app/Services/Funnel/FunnelCheckoutService.php tests/Feature/FunnelCheckoutPaymentStatusTest.php
git commit -m "fix(upsell): ensure funnel checkout sets payment_status correctly"
```

---

## Task 4: Build accountant-only Payment Approval card

**Files:**
- Modify: `resources/views/livewire/admin/orders-show.blade.php` (existing course Order page) — DO NOT MODIFY, this is for `Order` not `ProductOrder`.
- Find/modify: the ProductOrder detail Volt component. Locate it via:
  ```bash
  grep -rln "ProductOrder" resources/views/livewire/admin/ | grep -i "show\|detail"
  ```
- Create: `app/Livewire/Admin/ProductOrderPaymentApproval.php` (or follow Volt pattern if used)
- Test: `tests/Feature/Livewire/Admin/ProductOrderPaymentApprovalTest.php`

**Step 1: Locate the ProductOrder detail view**

```bash
grep -rln "ProductOrder::find\|productOrder->" resources/views/livewire/admin/ | head -10
```

Read the file. Identify where to insert the new card — typically near where existing "actions" are shown.

**Step 2: Write the test (TDD)**

```php
<?php

use App\Models\ProductOrder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->accountant = User::factory()->create();
    $this->accountant->roles()->attach(Role::firstOrCreate(['name' => 'accountant']));
    $this->order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
    ]);
});

it('only allows accountants to see the approval card', function () {
    $regularUser = User::factory()->create();

    Volt::test('admin.product-order-payment-approval', ['order' => $this->order])
        ->actingAs($regularUser)
        ->assertDontSee('Approve Payment');

    Volt::test('admin.product-order-payment-approval', ['order' => $this->order])
        ->actingAs($this->accountant)
        ->assertSee('Approve Payment');
});

it('requires receipt upload before approving', function () {
    Volt::test('admin.product-order-payment-approval', ['order' => $this->order])
        ->actingAs($this->accountant)
        ->call('approve')
        ->assertHasErrors('receipt');
});

it('marks order paid when accountant uploads receipt and approves', function () {
    $receipt = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    Volt::test('admin.product-order-payment-approval', ['order' => $this->order])
        ->actingAs($this->accountant)
        ->set('receipt', $receipt)
        ->call('approve')
        ->assertHasNoErrors();

    expect($this->order->fresh())
        ->payment_status->toBe('paid')
        ->payment_confirmed_by_user_id->toBe($this->accountant->id);
});

it('rejects payment with reason', function () {
    Volt::test('admin.product-order-payment-approval', ['order' => $this->order])
        ->actingAs($this->accountant)
        ->set('rejectionReason', 'No transfer received after 7 days')
        ->call('reject')
        ->assertHasNoErrors();

    expect($this->order->fresh())
        ->payment_status->toBe('failed')
        ->payment_rejection_reason->toBe('No transfer received after 7 days');
});
```

**Step 3: Run test, verify failure**

```bash
php artisan test --compact --filter=ProductOrderPaymentApprovalTest
```

**Step 4: Create the Volt component**

```bash
php artisan make:volt admin/product-order-payment-approval --no-interaction
```

Implement the component. Use class-based Volt for consistency. Pattern:

```php
<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ProductOrder $order;
    public $receipt;
    public string $rejectionReason = '';

    public function approve(): void
    {
        $this->authorize('confirmPayment', $this->order);
        $this->validate(['receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096']);

        $path = $this->receipt->store('funnel-payment-receipts', 'public');
        $this->order->markPaymentAsConfirmed(auth()->id(), $path);

        $this->dispatch('payment-approved');
        session()->flash('success', 'Payment confirmed.');
    }

    public function reject(): void
    {
        $this->authorize('confirmPayment', $this->order);
        $this->validate(['rejectionReason' => 'required|string|min:5|max:500']);

        $this->order->markPaymentAsRejected(auth()->id(), $this->rejectionReason);
        $this->dispatch('payment-rejected');
        session()->flash('warning', 'Payment rejected.');
    }
}; ?>

<div>
    @can('confirmPayment', $order)
    @if($order->payment_status === 'pending')
    <flux:card class="mt-6">
        <flux:heading size="lg">Payment Actions</flux:heading>
        <flux:text class="mt-2">Confirm or reject this manual bank transfer.</flux:text>
        {{-- form: receipt upload + Approve button, rejectionReason textarea + Reject button --}}
    </flux:card>
    @endif
    @endcan
</div>
```

**Step 5: Add policy / gate**

Create or update `app/Policies/ProductOrderPolicy.php` with:

```php
public function confirmPayment(User $user, ProductOrder $order): bool
{
    return $user->hasRole('accountant') && $order->payment_status === 'pending';
}
```

Register in `AuthServiceProvider`.

**Step 6: Mount the component in the ProductOrder show view**

Add at the bottom of the existing ProductOrder show view:
```blade
<livewire:admin.product-order-payment-approval :order="$order" />
```

**Step 7: Verify tests pass**

```bash
php artisan test --compact --filter=ProductOrderPaymentApprovalTest
```

**Step 8: Commit**

```bash
git add app/Livewire/Admin/ resources/views/livewire/admin/product-order-payment-approval.blade.php app/Policies/ProductOrderPolicy.php tests/Feature/Livewire/Admin/ProductOrderPaymentApprovalTest.php
git commit -m "feat(upsell): accountant payment approval flow for funnel COD orders"
```

---

## Task 5: Filter upsell-dashboard commission to paid orders only

**Files:**
- Modify: `resources/views/livewire/admin/upsell-dashboard.blade.php` (PHP block, around lines 53-167)
- Test: `tests/Feature/Livewire/Admin/UpsellDashboardCommissionTest.php`

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

it('only counts paid orders toward commission', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin']));

    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_commission_rate' => 10.0,
    ]);

    $paidProductOrder = ProductOrder::factory()->create(['payment_status' => 'paid']);
    $pendingProductOrder = ProductOrder::factory()->create(['payment_status' => 'pending']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paidProductOrder->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $pendingProductOrder->id,
        'funnel_revenue' => 200,
    ]);

    Volt::test('admin.upsell-dashboard')
        ->actingAs($admin)
        ->assertSet('totalCommission', 10.0); // 10% of paid 100, NOT (100+200)
});
```

**Step 2: Run test, verify failure**

```bash
php artisan test --compact --filter=UpsellDashboardCommissionTest
```

Expected: FAIL — current code sums all funnel_revenue regardless of payment_status.

**Step 3: Update the PHP block in upsell-dashboard.blade.php**

Change every `FunnelOrder` query to join through `ProductOrder` and filter on `payment_status='paid'`. Example pattern:

```php
$paidFunnelOrders = FunnelOrder::query()
    ->whereHas('productOrder', fn($q) => $q->where('payment_status', 'paid'))
    ->whereIn('class_session_id', $sessionIds);

$totalRevenue = $paidFunnelOrders->sum('funnel_revenue');
```

Apply the same filter to every breakdown (by-class, by-funnel, by-PIC).

**Step 4: Verify test passes**

```bash
php artisan test --compact --filter=UpsellDashboardCommissionTest
```

**Step 5: Visually verify**

```bash
composer run dev
```

Visit `/admin/upsell-dashboard` in browser. Confirm numbers look right.

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/upsell-dashboard.blade.php tests/Feature/Livewire/Admin/UpsellDashboardCommissionTest.php
git commit -m "fix(upsell): commission counts paid orders only"
```

---

## Task 6: Add payment_status filter + column to funnel order list

**Files:**
- Modify: `resources/views/livewire/admin/orders/order-list.blade.php` (filters around line 200-241, columns in the table)

**Step 1: Add filter dropdown**

In the filters section, add a Flux select bound to a new public property `paymentStatusFilter` with options: `all`, `paid`, `pending`, `failed`.

**Step 2: Apply filter to query**

In the `getOrders()` method (or equivalent), add:

```php
if ($this->paymentStatusFilter !== 'all') {
    $query->where('payment_status', $this->paymentStatusFilter);
}
```

**Step 3: Add column to table**

Add a "Payment" column showing a Flux badge with the status colored:
- `paid` → green
- `pending` → yellow
- `failed` → red
- `refunded` → gray

**Step 4: Visually test**

```bash
composer run dev
```

Visit `/admin/orders`. Filter by `pending` → only shows pending. Filter by `paid` → only shows paid. Column displays correctly.

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/orders/order-list.blade.php
git commit -m "feat(upsell): payment_status filter and column on order list"
```

---

## Task 7: Add one-time dashboard banner explaining the commission change

**Files:**
- Modify: `resources/views/livewire/admin/upsell-dashboard.blade.php`

**Step 1: Add dismissable banner**

At the top of the dashboard view, add a Flux callout that's dismissable. Persistence: store dismissal in the user's session or a `user_preferences` table key.

```blade
@if(!session('upsell_commission_change_banner_dismissed'))
<flux:callout variant="warning" class="mb-6">
    <flux:callout.heading>Commission calculation updated</flux:callout.heading>
    <flux:callout.text>
        Upsell commission now only counts orders with confirmed payment (paid status).
        Historical totals have been recalculated. See <a href="/docs/plans/2026-05-20-upsell-system-improvements-design.md" class="underline">design doc</a> for details.
    </flux:callout.text>
    <flux:button wire:click="dismissBanner" size="sm" variant="ghost">Got it</flux:button>
</flux:callout>
@endif
```

**Step 2: Add the dismiss method to the Volt PHP block**

```php
public function dismissBanner(): void
{
    session()->put('upsell_commission_change_banner_dismissed', true);
}
```

**Step 3: Commit**

```bash
git add resources/views/livewire/admin/upsell-dashboard.blade.php
git commit -m "feat(upsell): one-time banner for commission rule change"
```

---

## Phase A done — verification checklist

Run before merging:

```bash
php artisan test --compact
vendor/bin/pint --dirty
php artisan migrate:status
```

Manual smoke test:
1. Place a COD order via the funnel checkout (or seed one).
2. Log in as an accountant, navigate to the order detail page, upload a receipt, click Approve.
3. Verify `payment_status='paid'` in tinker.
4. Open the upsell dashboard, confirm the order's revenue is now included in commission.
5. Reject another order, confirm `payment_status='failed'`.
