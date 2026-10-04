# Bayarcash FPX Direct Debit for Class Subscriptions — Design

Date: 2026-10-04 · Status: Approved

## Goal

Add a third enrollment payment method, `direct_debit`, beside Stripe card (`automatic`) and `manual`. Students authorise a monthly FPX Direct Debit mandate via Bayarcash; Bayarcash auto-debits each month and the class Payment Reports grid keeps working off `Order` rows unchanged.

## Decisions

| Topic | Decision |
|---|---|
| Gateway | Bayarcash API direct, via the installed `webimpian/bayarcash-php-sdk` (has enrolment/maintenance/termination + 3 callback verifiers) and the existing `BayarcashService`. Not BCL. |
| Scope | Third option per enrollment; Stripe + manual stay. |
| Amount | Per-student `enrollments.monthly_amount` (defaults to class fee, admin-editable). |
| Entry points | Admin sends link (WhatsApp/email/copy) from class Payments grid; student self-serve on /my; option at checkout/enroll. All share one public page `/direct-debit/{token}`. |
| Switch-over | Old method stays until mandate is `active`; then Stripe sub `cancel_at_period_end`, `payment_method_type` → `direct_debit`. |
| Failed debit | Bayarcash retries once (days 25–28). Failed after retry → Order `failed` + manual FPX pay link. 2 consecutive failed months → terminate mandate, switch to `manual`, notify admin + student. |
| Order recording | Approach C: pending Order generated on 1st + callback marks paid/failed + daily reconcile against Bayarcash API. |

## Bayarcash facts (docs.bayarcash.com/380)

- Bayarcash triggers debits automatically; merchant cannot choose the day.
- Monthly: bank verification 3–5 days; first debit on days 3–5 of the month **after** registration.
- One retry on days 25–28 of the same month if the first attempt fails.
- Consequence: DD billing is calendar-month aligned; the registration month must be paid by the old method / one-off FPX.

## Data model

**New table `direct_debit_mandates`**
- `enrollment_id`, `student_id` (FKs)
- `order_number` (ours, unique), `mandate_id` (Bayarcash, nullable unique)
- `status` string: pending, waiting_approval, active, rejected, failed, terminated, cancelled
- `amount` decimal(10,2), `frequency` (`MT`)
- `payer_name`, `payer_id_type`, `payer_id` (encrypted cast), `payer_email`, `payer_phone`
- `bank_name`, `effective_date`, `activated_at`, `terminated_at`, `termination_reason`
- `consecutive_failures` unsigned int default 0
- `enrollment_token` (unique, used for the public page), `last_callback_payload` json
- Explicitly named indexes (MySQL 64-char limit).

**`enrollments`**: `payment_method_type` gains value `direct_debit` (string column, no enum change); add `monthly_amount` decimal nullable.

**`orders`**: add `direct_debit_mandate_id` nullable FK, `bayarcash_transaction_id` nullable; `payment_method = 'direct_debit'`. Add `direct_debit` to `Order::getPaymentMethods()`.

All migrations MySQL + SQLite compatible.

## Mandate enrolment flow

1. Public page `/direct-debit/{token}` shows class, monthly amount, form (name, IC, email, phone; prefilled from student).
2. Submit → `createFpxDirectDebitEnrollment()` with checksum → redirect payer to bank.
3. Authorization callback → `waiting_approval` (grid badge "DD: Menunggu bank").
4. Bank approval callback → `active`: cancel Stripe sub at period end if any, set `payment_method_type = direct_debit`, notify student ("potongan pertama 3–5hb <bulan>").
5. Rejected/failed → status set, old method untouched, admin notified.

Entry points:
- **Admin**: new "Direct Debit" icon per student row in class Payments grid → modal (set amount) → send via WhatsApp (existing WAHA/Meta channel) or email, or copy link. Also shows mandate status + "Tamatkan Direct Debit".
- **Student /my**: "Bayaran Automatik" card on class page → activate / terminate.
- **Checkout/enroll**: "Direct Debit" option; first month paid via FPX/card, then redirect to mandate page.

Settings: new "Direct Debit portal key" in existing Bayarcash settings (`SettingsService`).

## Monthly cycle

- **1st 01:00** — `subscriptions:generate-orders` also creates a pending Order for each `direct_debit` enrollment with an `active` mandate (period = calendar month, amount = mandate amount). Idempotent per enrollment+period.
- **Days 3–5** transaction callback (checksum verified, idempotent on transaction id):
  - success → `markAsPaid()`, store transaction id, reset `consecutive_failures`.
  - fail → Order stays pending, flagged "Gagal — cuba semula 25–28hb"; student warned.
- **Days 25–28** retry callback: success → paid; fail → Order `failed`, `consecutive_failures++`, student gets manual FPX pay link (existing click-to-pay flow).
- **Daily reconcile** (new command, also invoked by the grid's Reconcile button): pending DD Orders past day 6 / day 29 are checked against the Bayarcash API and corrected.

## Termination & failure rules

- `consecutive_failures >= 2` → terminate via API, `payment_method_type = manual`, admin notification + grid badge "DD ditamatkan — gagal 2x", student told how to re-register.
- Admin "Tamatkan" → API termination → manual.
- Student → Bayarcash termination page.
- Bank/Bayarcash `terminated` callback → manual + admin notified.
- Enrollment cancelled or collection paused → mandate auto-terminated.

## Security

- All three callbacks verified with SDK `verifyDirectDebit*CallbackData`; CSRF exemption only for those checksum-verified routes.
- Idempotent processing (duplicate callbacks never double-create or double-pay).
- Payer IC stored encrypted; public page reachable only via unguessable token; token page never reveals other students' data.

## Testing

Feature tests (Bayarcash SDK mocked): each callback type success/fail/duplicate/bad checksum; retry → failed → manual link; 2x failure rule; Stripe → DD switch on activation; generate-orders DD branch idempotency; reconcile command; token page validation; admin modal + /my card authorization.
