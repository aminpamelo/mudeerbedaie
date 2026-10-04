# Bayarcash FPX Direct Debit Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `direct_debit` as a third class-enrollment payment method: student authorises a monthly Bayarcash FPX Direct Debit mandate, Bayarcash auto-debits on days 3–5, and the class Payments grid reads the resulting `Order` rows unchanged.

**Architecture:** New `DirectDebitMandate` model owns mandate lifecycle. `BayarcashDirectDebitService` wraps the SDK (enrol / terminate / fetch). One checksum-verified callback endpoint dispatches the three Bayarcash record types to small handlers. Pending Orders are pre-generated on the 1st by the existing `subscriptions:generate-orders`; callbacks mark them paid/failed; a daily reconcile command catches missed callbacks.

**Tech Stack:** Laravel 12, `webimpian/bayarcash-php-sdk` v2 (already installed; trait `FpxDirectDebitPaymentIntent`, `CallbackVerifications`, constants in `Webimpian\BayarcashSdk\FpxDirectDebit`), Livewire Volt (admin `class-show`, student `/my`), Pest 4.

Design doc: `docs/plans/2026-10-04-bayarcash-direct-debit-design.md`

---

## Ground rules for the implementer

- Migrations must run on MySQL and SQLite. Name composite/unique indexes explicitly (<64 chars). Only `php artisan migrate` — never `migrate:fresh`.
- Run tests with `php artisan test --compact <file>`; mock Bayarcash with `$this->mock(BayarcashDirectDebitService::class)` or by mocking the SDK returned from `BayarcashService::getBayarcash()`.
- `vendor/bin/pint --dirty` before each commit.
- Small commits on `main` are fine; prefer a feature branch `feat/bayarcash-direct-debit` for this size.
- Existing facts you will rely on:
  - Manual "internal" subscriptions use `enrollments.stripe_subscription_id = 'INTERNAL-<uuid>'` and `StripeService::generateInternalSubscriptionOrder()` (`app/Services/StripeService.php`).
  - Grid groups Orders by `period_start` falling inside a calendar month (`resources/views/livewire/admin/class-show.blade.php` ~L1740–1890).
  - Existing FPX callback: `routes/web.php:1627` → `BayarcashWebhookController`; CSRF exemptions in `bootstrap/app.php:62`.
  - Bayarcash settings via `SettingsService::get('bayarcash_api_token' | 'bayarcash_api_secret_key' | 'bayarcash_portal_key' | 'bayarcash_sandbox')`; UI in `resources/views/livewire/admin/settings-payment.blade.php`.
  - Stripe cancel: `StripeService::cancelSubscription(string $id, bool $immediately = false)`.
  - WhatsApp: `WhatsAppService::send(string $phone, string $message)`.

---

## Phase 0 — Sandbox spike (no code merged)

### Task 0: Capture real callback payloads

**Why:** The SDK verifies three callback shapes but does not expose `record_type` values or debit `status` codes. We need real values before writing the dispatcher.

1. In Bayarcash sandbox console, confirm the portal has the **FPX Direct Debit** channel; note its portal key.
2. In tinker, create a mandate enrolment against sandbox with `callback_url` = a RequestBin/`expose` URL, using `getBayarcash()->createFpxDirectDebitEnrollment($data)` (payload in Task 3), and complete it in the sandbox bank page.
3. Record in `tests/Fixtures/bayarcash/` as JSON: `dd_authorization.json`, `dd_bank_approval.json`, `dd_transaction_success.json`, `dd_transaction_failed.json` (secret replaced with `test-secret`, checksums recomputed with the SDK verifier's field list).
4. Write down: `record_type` values, `approval_status` values, transaction `status` success/fail codes, whether `cycle` increments, whether `return_url` is accepted on enrolment.
5. Update constants in Task 4 with the observed values. **If sandbox cannot simulate debits, keep the field-presence fallback in Task 4 and mark transaction status codes as TODO-verify-in-production.**

---

## Phase 1 — Data layer

### Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_10_04_000001_create_direct_debit_mandates_table.php`
- Create: `database/migrations/2026_10_04_000002_add_direct_debit_columns_to_enrollments_and_orders.php`

**Step 1:** `php artisan make:migration create_direct_debit_mandates_table --no-interaction` and fill:

```php
Schema::create('direct_debit_mandates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->string('order_number')->unique('ddm_order_number_unique');
    $table->string('mandate_id')->nullable()->unique('ddm_mandate_id_unique');
    $table->string('mandate_reference_number')->nullable();
    $table->string('status')->default('pending');
    $table->decimal('amount', 10, 2);
    $table->string('frequency', 2)->default('MT');
    $table->string('payer_name');
    $table->unsignedTinyInteger('payer_id_type')->default(1);
    $table->text('payer_id')->nullable();
    $table->string('payer_email');
    $table->string('payer_phone');
    $table->string('bank_name')->nullable();
    $table->date('effective_date')->nullable();
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('terminated_at')->nullable();
    $table->string('termination_reason')->nullable();
    $table->unsignedInteger('consecutive_failures')->default(0);
    $table->string('enrollment_token', 64)->unique('ddm_enrollment_token_unique');
    $table->json('last_callback_payload')->nullable();
    $table->timestamps();

    $table->index(['enrollment_id', 'status'], 'ddm_enrollment_status_idx');
});
```

**Step 2:** second migration:

```php
Schema::table('enrollments', function (Blueprint $table) {
    $table->decimal('monthly_amount', 10, 2)->nullable()->after('enrollment_fee');
});
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('direct_debit_mandate_id')->nullable()->constrained()->nullOnDelete();
    $table->string('bayarcash_transaction_id')->nullable()->unique('orders_bayarcash_txn_unique');
});
```
`down()` drops FK first (`dropConstrainedForeignId`), then columns. Check `orders.payment_method` column type: if it is an enum on MySQL, add `direct_debit` via the dual-driver pattern from CLAUDE.md (see `2026_02_04_222342_add_cod_to_product_order_payments_payment_method.php` for the house pattern).

**Step 3:** `php artisan migrate --no-interaction` → both run. Verify with `database-schema` tool.

**Step 4:** Commit `feat(dd): migrations for direct debit mandates`.

### Task 2: Model, enum, factory, relations

**Files:**
- Create: `app/Enums/DirectDebitMandateStatus.php` (string-backed: `Pending`, `WaitingApproval`, `Active`, `Rejected`, `Failed`, `Terminated`, `Cancelled`; `label()` in Malay; `fromBayarcash(int $code): self` mapping SDK `FpxDirectDebit::STATUS_*` — 0→Pending, 1→WaitingApproval, 2→Failed, 3→Active, 4→Terminated, 5→WaitingApproval (approved but not yet active), 6→Rejected, 7→Cancelled, 8→Failed)
- Create: `app/Models/DirectDebitMandate.php` (`php artisan make:model DirectDebitMandate -f --no-interaction`)
- Modify: `app/Models/Enrollment.php` (fillable `monthly_amount`, cast `decimal:2`; `directDebitMandates(): HasMany`; `activeDirectDebitMandate(): HasOne` → `latestOfMany()` where status active; `isDirectDebit(): bool`; `effectiveMonthlyAmount(): float` = `monthly_amount ?? course->feeSettings->fee_amount`; add `'direct_debit' => 'Direct Debit (FPX)'` to the label match ~L649)
- Modify: `app/Models/Order.php` (fillable + `PAYMENT_METHOD_DIRECT_DEBIT = 'direct_debit'` in `getPaymentMethods()`; `directDebitMandate(): BelongsTo`)
- Test: `tests/Feature/DirectDebit/DirectDebitMandateModelTest.php`

Model essentials:
```php
protected function casts(): array
{
    return [
        'status' => DirectDebitMandateStatus::class,
        'amount' => 'decimal:2',
        'payer_id' => 'encrypted',
        'effective_date' => 'date',
        'activated_at' => 'datetime',
        'terminated_at' => 'datetime',
        'last_callback_payload' => 'array',
    ];
}

protected static function booted(): void
{
    static::creating(function (self $mandate): void {
        $mandate->order_number ??= 'DD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        $mandate->enrollment_token ??= Str::random(48);
    });
}

public function isLive(): bool
{
    return in_array($this->status, [DirectDebitMandateStatus::Pending, DirectDebitMandateStatus::WaitingApproval, DirectDebitMandateStatus::Active], true);
}
```

**Tests (write first, watch fail, implement, pass):**
- creating a mandate auto-fills `order_number` (prefix `DD-`) and a 48-char token
- `payer_id` is encrypted at rest (raw DB value ≠ plain IC)
- `Enrollment::effectiveMonthlyAmount()` returns `monthly_amount` when set, else course fee
- `activeDirectDebitMandate` ignores terminated mandates
- `fromBayarcash()` maps each SDK code

Run: `php artisan test --compact tests/Feature/DirectDebit/DirectDebitMandateModelTest.php` → PASS. Commit.

---

## Phase 2 — Bayarcash integration

### Task 3: Settings + `BayarcashDirectDebitService`

**Files:**
- Modify: `app/Services/SettingsService.php` (~L246: add `'dd_portal_key' => $this->get('bayarcash_dd_portal_key')`)
- Modify: `resources/views/livewire/admin/settings-payment.blade.php` (new input "Direct Debit portal key", saved as `bayarcash_dd_portal_key`; helper text: "Portal Bayarcash yang ada saluran FPX Direct Debit")
- Create: `app/Services/BayarcashDirectDebitService.php`
- Test: `tests/Feature/DirectDebit/BayarcashDirectDebitServiceTest.php`

```php
class BayarcashDirectDebitService
{
    public function __construct(
        private BayarcashService $bayarcash,
        private SettingsService $settings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->bayarcash->isConfigured() && filled($this->settings->get('bayarcash_dd_portal_key'));
    }

    /**
     * Submit the mandate to Bayarcash and return the bank authorisation URL.
     */
    public function startEnrolment(DirectDebitMandate $mandate): string
    {
        $sdk = $this->bayarcash->getBayarcash();
        $data = [
            'portal_key' => $this->settings->get('bayarcash_dd_portal_key'),
            'order_number' => $mandate->order_number,
            'amount' => number_format((float) $mandate->amount, 2, '.', ''),
            'payer_name' => $mandate->payer_name,
            'payer_email' => $mandate->payer_email,
            'payer_telephone_number' => $mandate->payer_phone, // already digits-only 60xxxxxxxxx
            'payer_id_type' => (string) $mandate->payer_id_type,
            'payer_id' => $mandate->payer_id,
            'application_reason' => Str::limit('Yuran bulanan '.$mandate->enrollment->course->name, 120, ''),
            'frequency_mode' => FpxDirectDebit::MODE_MONTHLY,
            'return_url' => route('direct-debit.return', $mandate->enrollment_token),
            'success_url' => route('direct-debit.return', $mandate->enrollment_token),
            'failed_url' => route('direct-debit.return', $mandate->enrollment_token),
        ];
        $data['checksum'] = $sdk->createFpxDIrectDebitEnrolmentChecksumValue(
            $this->settings->get('bayarcash_api_secret_key'), $data
        );

        $response = $sdk->createFpxDirectDebitEnrollment($data);

        return $response->url;
    }

    public function terminate(DirectDebitMandate $mandate, string $reason): void
    {
        if ($mandate->mandate_id) {
            $this->bayarcash->getBayarcash()->createFpxDirectDebitTermination($mandate->mandate_id, [
                'application_reason' => Str::limit($reason, 120, ''),
            ]);
        }

        $mandate->update([
            'status' => DirectDebitMandateStatus::Terminated,
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ]);
    }

    public function fetch(DirectDebitMandate $mandate): ?FpxDirectDebitResource
    {
        return $mandate->mandate_id
            ? $this->bayarcash->getBayarcash()->getFpxDirectDebit($mandate->mandate_id)
            : null;
    }
}
```
Reuse `BayarcashService::normalizePhoneNumber` by making it `public` (it is private today; existing test uses reflection — leave the test, it still passes). Wrap `BayarcashValidationException` exactly as `createPaymentIntent()` does (log + RuntimeException with `formatValidationErrors`; make that method public too).

**Tests:** mock the SDK (`Mockery::mock(Bayarcash::class)`, bind via partial mock of `BayarcashService::getBayarcash`):
- `startEnrolment` sends the 9 checksum fields + portal key + MT and returns the URL
- validation exception becomes a readable RuntimeException
- `terminate` calls SDK when `mandate_id` set, skips SDK when null, always marks Terminated
- `isConfigured` false without DD portal key

Commit.

### Task 4: Callback endpoint + dispatcher

**Files:**
- Create: `app/Http/Controllers/BayarcashDirectDebitCallbackController.php`
- Create: `app/Services/DirectDebit/DirectDebitCallbackProcessor.php`
- Modify: `routes/web.php` (beside L1627): `Route::post('bayarcash/direct-debit/callback', BayarcashDirectDebitCallbackController::class)->name('bayarcash.direct-debit.callback');`
- Modify: `bootstrap/app.php:62` add `'bayarcash/direct-debit/callback'`
- Test: `tests/Feature/DirectDebit/DirectDebitCallbackTest.php` (uses Task 0 fixtures)

Controller: always respond `200 OK` text (Bayarcash retries on non-2xx), log everything, delegate:
```php
public function __invoke(Request $request, DirectDebitCallbackProcessor $processor): Response
{
    $payload = $request->all();
    Log::info('Bayarcash DD callback', Arr::except($payload, ['payer_bank_account_no']));

    if (! $processor->handle($payload)) {
        return response('invalid', 400);
    }

    return response('OK');
}
```

Processor:
```php
public function handle(array $payload): bool
{
    $type = $this->detectType($payload);
    if ($type === null || ! $this->verify($type, $payload)) {
        Log::warning('Bayarcash DD callback rejected', ['type' => $type, 'order_number' => $payload['order_number'] ?? null]);
        return false;
    }

    return DB::transaction(fn () => match ($type) {
        'authorization' => $this->mandates->handleAuthorization($payload),
        'bank_approval' => $this->mandates->handleBankApproval($payload),
        'transaction' => $this->debits->handleTransaction($payload),
    });
}

/** Field-presence detection; replace/augment with record_type values from Task 0. */
private function detectType(array $p): ?string
{
    return match (true) {
        isset($p['approval_status'], $p['mandate_reference_number']) => 'bank_approval',
        isset($p['batch_number'], $p['cycle']) => 'transaction',
        isset($p['exchange_reference_number'], $p['mandate_id']) => 'authorization',
        default => null,
    };
}
```
`verify()` calls the matching SDK method (`verifyDirectDebitBankApprovalCallbackData` / `...Authorization...` / `...Transaction...`) with `bayarcash_api_secret_key`, catching missing-key `ErrorException` → false.

**Tests (fixtures with recomputed checksums):**
- bad checksum → 400, no state change
- unknown shape → 400
- each valid shape routes to the right handler (mock handlers, assert called once)
- route is CSRF-exempt (pattern from `tests/Feature/BayarcashWebhookCsrfTest.php`)

Commit.

### Task 5: Mandate lifecycle handler (authorization, approval, switch-over)

**Files:**
- Create: `app/Services/DirectDebit/DirectDebitMandateHandler.php`
- Create: `app/Events/DirectDebitMandateActivated.php`, `app/Events/DirectDebitMandateEnded.php` (for notifications, Task 14)
- Test: `tests/Feature/DirectDebit/DirectDebitMandateHandlerTest.php`

Behaviour:
- `handleAuthorization($p)`: find mandate by `order_number` (unknown → log + return true so Bayarcash stops retrying). Store `mandate_id`, `bank_name = payer_bank_name`, payload. Status → `WaitingApproval` if authorisation `status` is success, else `Failed`. **Idempotent**: never downgrade an `Active`/`Terminated` mandate.
- `handleBankApproval($p)`: approved → `activate($mandate)`; rejected → `Rejected` + event `DirectDebitMandateEnded(reason: 'rejected')`. Old method untouched on rejection.
- `activate(DirectDebitMandate $m)`:
  1. If already Active → return (idempotent).
  2. Mark `Active`, `activated_at = now()`, `mandate_reference_number`.
  3. Terminate any *other* live mandate on the same enrollment (re-registration case) via `BayarcashDirectDebitService::terminate(..., 'Diganti mandat baru')`.
  4. Enrollment switch:
     - real Stripe sub (`stripe_subscription_id` not null and not `INTERNAL-%`) → `StripeService::cancelSubscription($id, immediately: false)`; on Stripe exception log + continue (admin can cancel manually; record `metadata`/note).
     - `payment_method_type = 'direct_debit'`, `monthly_amount = $m->amount`, `manual_payment_required = false`, `subscription_status = 'active'`.
     - Keep `stripe_subscription_id` for internal `INTERNAL-%` (harmless; generate-orders filters by `payment_method_type`).
  5. Dispatch `DirectDebitMandateActivated`.

**Tests:** authorization sets waiting_approval + mandate_id; duplicate authorization after Active doesn't downgrade; approval activates and flips enrollment to direct_debit; Stripe enrollment → `cancelSubscription` called with `immediately: false` (mock `StripeService`); Stripe failure doesn't block activation; rejection keeps `payment_method_type` unchanged; second mandate activation terminates the first.

Commit.

### Task 6: Debit transaction handler (paid / retry / 2x rule)

**Files:**
- Create: `app/Services/DirectDebit/DirectDebitTransactionHandler.php`
- Test: `tests/Feature/DirectDebit/DirectDebitTransactionHandlerTest.php`

```php
public function handleTransaction(array $p): bool
{
    if (Order::where('bayarcash_transaction_id', $p['transaction_id'])->where('status', Order::STATUS_PAID)->exists()) {
        return true; // duplicate
    }

    $mandate = DirectDebitMandate::where('mandate_id', $p['mandate_id'])->first();
    if (! $mandate) { Log::warning(...); return true; }

    $debitDate = Carbon::parse($p['datetime'])->timezone(config('app.timezone'));
    $order = $this->findOrCreateMonthOrder($mandate, $debitDate); // period = debit month; creates pending if generate-orders missed it

    if ($this->isSuccess($p)) {
        $order->update(['bayarcash_transaction_id' => $p['transaction_id'], 'paid_at' => $debitDate]);
        $order->markAsPaid();
        $mandate->update(['consecutive_failures' => 0, 'last_callback_payload' => $p]);
        return true;
    }

    $isRetryWindow = $debitDate->day >= 20; // Bayarcash retries on days 25–28
    if (! $isRetryWindow) {
        $order->update(['metadata' => array_merge($order->metadata ?? [], ['dd_first_attempt_failed_at' => now()->toISOString(), 'dd_failure_reason' => $p['status_description'] ?? null])]);
        event(new DirectDebitFirstAttemptFailed($order));
        return true;
    }

    $order->markAsFailed(['reason' => $p['status_description'] ?? 'Direct debit gagal', 'source' => 'bayarcash_dd']);
    $mandate->increment('consecutive_failures');
    event(new DirectDebitMonthFailed($order)); // → manual pay link to student

    if ($mandate->fresh()->consecutive_failures >= 2) {
        $this->switchToManual($mandate, 'Gagal debit 2 bulan berturut-turut');
    }

    return true;
}
```
- `isSuccess()`: compare `status` to success code from Task 0 (constant `DD_TXN_SUCCESS`); fallback: `status_description` contains "success"/"berjaya" case-insensitive.
- `switchToManual($mandate, $reason)` (public; reused by Tasks 10/13): `BayarcashDirectDebitService::terminate`, enrollment → `payment_method_type = 'manual'`, ensure an `INTERNAL-<uuid>` subscription id + `next_payment_date = first day of next month` so `generate-orders` picks it up (mirror what `class-show.blade.php` ~L2638 does when switching to manual — extract that into `Enrollment::convertToInternalManual()` if not already a method), dispatch `DirectDebitMandateEnded(reason: 'failed_twice')`.
- `findOrCreateMonthOrder`: match `direct_debit_mandate_id` + `period_start` in debit month; else create with `period_start = startOfMonth`, `period_end = endOfMonth`, `amount = mandate amount`, `billing_reason = Order::REASON_SUBSCRIPTION_CYCLE` (check the constant name in `Order::getBillingReasons()`), `payment_method = direct_debit`.

**Tests:** success marks paid + resets counter; duplicate transaction id no-op; first-attempt fail keeps order pending + flags metadata; retry fail → failed + counter 1; second month retry fail → mandate terminated + enrollment manual + INTERNAL id + next_payment_date set; success after one failed month resets counter; transaction for a month without pre-generated order creates one.

Commit.

---

## Phase 3 — Scheduling & reconcile

### Task 7: Generate pending DD orders on the 1st

**Files:**
- Create: `app/Services/DirectDebit/DirectDebitOrderGenerator.php` (`generateForMonth(Carbon $month): int`)
- Modify: `app/Console/Commands/GenerateSubscriptionOrders.php` (after the manual loop, call the generator; respect `--dry-run`)
- Test: `tests/Feature/DirectDebit/DirectDebitOrderGeneratorTest.php`

Rules: enrollments with `payment_method_type = direct_debit`, `subscription_status = active`, `collection_status` not paused, with an Active mandate whose `activated_at` is before the month start. One order per enrollment per calendar month (check existing order with `period_start` in month regardless of status). Amount = mandate amount. Command already runs daily 01:00, so idempotency makes daily runs safe.

**Tests:** creates one pending order; second run creates none; skips paused/cancelled/no active mandate; skips mandate activated mid-month (first debit is next month); never touches `manual` enrollments. Also run `tests/Feature/SubscriptionPaymentSchedulingTest.php` to confirm manual path unaffected.

Commit.

### Task 8: Reconcile command

**Files:**
- Create: `app/Console/Commands/ReconcileDirectDebits.php` (`direct-debit:reconcile {--enrollment=} {--dry-run}`)
- Modify: `routes/console.php` → `Schedule::command('direct-debit:reconcile')->dailyAt('10:00')->withoutOverlapping();`
- Modify: `class-show.blade.php` `reconcilePayments()` (~L1600): also call reconcile for DD enrollments in this class
- Test: `tests/Feature/DirectDebit/ReconcileDirectDebitsTest.php`

Logic: for mandates `Pending/WaitingApproval` older than 1 day → `fetch()` and apply status through `DirectDebitMandateHandler` (activate / reject / terminate). For DD orders still pending where today ≥ 6th (first attempt) or ≥ 29th / next month (retry) → query transactions (`getfpxDirectDebitransaction` / mandate resource; confirm list endpoint in Task 0) and feed results through `DirectDebitTransactionHandler::handleTransaction` (same idempotency). If API has no transaction list, only reconcile mandate status and flag stale pending orders in the log + admin badge.

**Tests:** waiting mandate becomes active when API says Active; terminated in API → enrollment switched to manual; dry-run changes nothing.

Commit.

---

## Phase 4 — Mandate signup page

### Task 9: Public mandate page `/direct-debit/{token}`

**Files:**
- Create: `app/Http/Controllers/DirectDebitEnrolmentController.php` (`show`, `store`, `return`)
- Create: `app/Http/Requests/StoreDirectDebitEnrolmentRequest.php`
- Create: `resources/views/direct-debit/show.blade.php`, `resources/views/direct-debit/return.blade.php` (plain Blade + Tailwind, mobile-first, Malay copy, BeDaie light style like `/my`)
- Modify: `routes/web.php`:
  ```php
  Route::get('direct-debit/{token}', [DirectDebitEnrolmentController::class, 'show'])->name('direct-debit.show');
  Route::post('direct-debit/{token}', [DirectDebitEnrolmentController::class, 'store'])->middleware('throttle:10,1')->name('direct-debit.store');
  Route::get('direct-debit/{token}/return', [DirectDebitEnrolmentController::class, 'return'])->name('direct-debit.return');
  ```
- Test: `tests/Feature/DirectDebit/DirectDebitEnrolmentPageTest.php`

Token resolves a `DirectDebitMandate` in `Pending`. 404 for unknown token; "Mandat sudah aktif" for Active; "Link tamat" for Terminated/Cancelled/Rejected (with "Minta link baru" text).
Page shows: class name, monthly amount, "Potongan pertama: 3–5 <bulan depan>", explanation, form (nama, jenis ID [IC default / Passport], No. ID, emel, telefon) prefilled from student/user.
FormRequest rules: `payer_name` required max:255; `payer_id_type` in:1,3; `payer_id` required, IC = `digits:12` when type 1; `payer_email` required email; `payer_phone` required; Malay messages.
`store`: update mandate payer fields (phone normalised), call `startEnrolment`, `redirect()->away($url)`; on RuntimeException back with error.
`return`: show current status (it may still be Pending until callback) — "Permohonan dihantar, bank akan sahkan dalam 3–5 hari bekerja."

**Tests:** show 200 with prefill; unknown token 404; active mandate shows already-active; invalid IC → validation error; valid submit redirects to mocked URL and stores encrypted IC; service not configured → friendly error.

Commit.

---

## Phase 5 — Entry points UI

### Task 10: Admin — Payments grid

**Files:**
- Modify: `resources/views/livewire/admin/class-show.blade.php`
- Create: `app/Actions/DirectDebit/CreateDirectDebitMandate.php` (`handle(Enrollment $e, float $amount): DirectDebitMandate` — cancels any existing Pending mandate for the enrollment, creates new with payer defaults from student/user)
- Test: `tests/Feature/DirectDebit/AdminDirectDebitTest.php`

UI (follow existing icon row at ~L6700 next to the Stripe "S" icon; use `tooltip=` attribute + `wire:key`, not nested `<flux:tooltip>`):
- New icon (`building-library`) per student row; colour by state: grey none / amber waiting / green active / red ended.
- Modal "Direct Debit": amount input (default `effectiveMonthlyAmount()`), status + bank + activated date + consecutive failures, history list.
  - "Jana link" → `CreateDirectDebitMandate`, then buttons: **Hantar WhatsApp** (`WhatsAppService::send` with template text + link), **Hantar emel** (simple Mailable `DirectDebitLinkMail`), **Salin link**.
  - "Tamatkan Direct Debit" (Active only, confirmation modal — not browser confirm) → `DirectDebitTransactionHandler::switchToManual($m, 'Ditamatkan oleh admin')`.
- Month cell for pending DD order: clock icon "Debit 3–5hb"; with `dd_first_attempt_failed_at` metadata: "Gagal — cuba semula 25–28hb". Failed DD order reuses the existing red "Click to pay" cell.
- Student-row badge "DD ditamatkan — gagal 2x" when latest mandate ended with that reason in last 60 days.
- Add `'direct_debit'` option to the "All Types" filter.

**Tests (Volt::test on `admin.class-show`):** admin generates link → mandate Pending with amount; WhatsApp send calls mocked `WhatsAppService::send` containing the token URL; terminate switches to manual; non-admin cannot call the actions.

Commit; verify visually in Playwright at `/admin/classes/{id}?tab=payment-reports` (light + dark).

### Task 11: Student — /my class page card

**Files:**
- Locate the student class/course Volt page under `routes/web.php:269` prefix `my` (course hub `/my/courses/{course}` per project memory) and modify it
- Test: `tests/Feature/DirectDebit/StudentDirectDebitCardTest.php`

Card "Bayaran Automatik (Direct Debit)":
- none → amount + "Aktifkan Direct Debit" → `CreateDirectDebitMandate` (amount = `effectiveMonthlyAmount()`; student cannot edit amount) → redirect to `direct-debit.show`
- waiting → "Menunggu pengesahan bank"
- active → bank, amount, next debit "3–5 <bulan>", "Batalkan" → confirmation → `switchToManual($m, 'Dibatalkan oleh pelajar')`
- Guard null `$request->user()->student` (known 500 issue).

**Tests:** student sees card only for own enrollment; activate creates mandate + redirects; cancelling another student's mandate is forbidden.

Commit.

### Task 12: Checkout / enroll option

**Files:**
- Identify the class enrollment checkout that creates Enrollments with `payment_method_type` (grep `payment_method_type` in `app/` and `resources/views/livewire/`) and modify it
- Test: extend that flow's existing test or create `tests/Feature/DirectDebit/CheckoutDirectDebitTest.php`

Add option "Direct Debit (auto tolak bulanan)" when `BayarcashDirectDebitService::isConfigured()`. First month is paid with the existing FPX/card flow; on that payment's success redirect, create the mandate via `CreateDirectDebitMandate` and redirect to `direct-debit.show`. Enrollment stays on its first-month method until activation (Task 5 flips it).

**Tests:** option hidden when not configured; choosing DD after paid first month lands on the mandate page with a Pending mandate.

Commit.

---

## Phase 6 — Safety nets & notifications

### Task 13: Auto-terminate on cancel / pause

**Files:**
- Create: `app/Observers/EnrollmentDirectDebitObserver.php` (register in `AppServiceProvider::boot`)
- Test: `tests/Feature/DirectDebit/EnrollmentDirectDebitObserverTest.php`

On `updated`: if `isDirectDebit()` and (`subscription_status` changed to cancelled/canceled, or `academic_status` withdrawn/completed, or `collection_status` changed to paused) → terminate live mandate (`'Enrollment dibatalkan'` / `'Kutipan dihentikan'`). Pause → also set `payment_method_type = manual` so resume is an explicit admin choice. Queue the API call (`dispatch(fn () => ...)->afterCommit()`) so admin UI isn't blocked; mandate status updated synchronously.

**Tests:** cancel terminates; pause terminates + manual; unrelated update does nothing; non-DD enrollment untouched.

Commit.

### Task 14: Notifications

**Files:**
- Create listeners in `app/Listeners/DirectDebit/` for `DirectDebitMandateActivated`, `DirectDebitFirstAttemptFailed`, `DirectDebitMonthFailed`, `DirectDebitMandateEnded`
- Test: `tests/Feature/DirectDebit/DirectDebitNotificationsTest.php`

Student WhatsApp (via `WhatsAppService`, fall back to email when no phone):
- Activated: "Direct debit aktif. RM{amount} akan ditolak 3–5hb setiap bulan, bermula {bulan}."
- First attempt failed: "Potongan RM{amount} gagal. Bank akan cuba semula 25–28hb — pastikan baki mencukupi."
- Month failed: "Bayaran {bulan} gagal. Sila bayar di sini: {existing manual pay link for the order}." (reuse the existing click-to-pay URL generator used by the grid)
- Ended (failed twice / rejected): explanation + how to re-register.
Admin: database notification to class PIC + admins for Rejected and failed-twice.

**Tests:** each event sends once (fake `WhatsAppService`), message contains amount/link; no phone → email queued.

Commit.

---

## Phase 7 — Verification & release

### Task 15: End-to-end in sandbox + polish

1. `php artisan test --compact tests/Feature/DirectDebit` → all PASS. Also `tests/Feature/BayarcashServiceTest.php tests/Feature/BayarcashWebhookCsrfTest.php tests/Feature/SubscriptionPaymentSchedulingTest.php tests/Feature/StripeSubscriptionIntegrationTest.php` → no regressions.
2. `vendor/bin/pint --dirty`; `npm run build` (commit built assets per repo convention: `build: rebuild assets for ...`).
3. Sandbox walk-through with Playwright: admin generates link → student page → sandbox bank → callbacks (via `expose`/tunnel to Herd) → grid shows waiting → approval → active badge; tinker-simulate a transaction callback with fixture → month cell paid.
4. Production checklist (to user): DD portal key in Settings, Bayarcash console callback URL = `https://kelasify.com/bayarcash/direct-debit/callback`, queue worker running, scheduler running. Pilot with 2–3 students before announcing.
5. Write project memory `project_bayarcash_direct_debit.md` + MEMORY.md line.
6. Commit; push / merge per user.
