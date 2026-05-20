# Upsell System Improvements — Design

**Date:** 2026-05-20
**Status:** Design — awaiting implementation plan
**Originating request:** Team feedback (Amin Suhardi) covering 7 upsell improvements

---

## Background

The class upsell module shipped in April 2026. It lets admins attach funnel(s) to a class session, track visitors and conversions, and surface revenue + commission to a per-session "upsell teacher". The team has used it long enough to identify gaps in the reporting and in the payment confirmation flow.

This design groups the 7 requests into three phases ordered by dependency. Phase A is foundational — the reports in Phase B are misleading until Phase A lands, because commission today is calculated against unpaid orders.

---

## The seven requests, mapped

| # | Team request | Phase | Disposition |
|---|---|---|---|
| 1 | Report performance by teacher in upsell dashboard | B | New table + drilldown |
| 2 | Report performance by product | B | New table |
| 3 | Commission only counts PAID orders | A | Change commission query + UI banner |
| 4 | Funnel orders stuck unpaid even after customer paid | A | New manual-approval flow for ProductOrder |
| 5 | Upsell section on teacher account | B | New tab on `teacher-show` |
| 6 | Download button + product type for accountant | C | Per-order PDF receipt |
| 7 | Mark commission as paid | C | Per-teacher per-period payout run |

---

## Diagnosis: why funnel orders look unpaid

The funnel checkout offers three payment methods today: Stripe (credit_card), Bayarcash FPX, and COD. Stripe and Bayarcash auto-flip the order to `payment_status='paid'` on a successful gateway callback. **COD orders stay at `'pending'` indefinitely** because there is no admin UI to confirm them.

When the team says "customer paid by online transfer but order shows unpaid", they mean: customer selected COD on the funnel, then manually transferred funds to a bank account out-of-band, and there is no flow to mark that order paid.

The course-side `Order` model has a complete receipt-upload + approve/reject pattern at [orders-show.blade.php:150-188](../../resources/views/livewire/admin/orders-show.blade.php#L150-L188). `ProductOrder` (which funnels use) has nothing equivalent. Phase A reuses the course-side pattern.

Secondary anomaly: `FunnelCheckoutService::createProductOrder` writes `payment_status` to `ProductOrder` ([FunnelCheckoutService.php:203](../../app/Services/Funnel/FunnelCheckoutService.php#L203)) but the original `product_orders` migration does not define that column. Verify the column actually exists before assuming the write succeeds; add the column if missing.

---

## Phase A — Data correctness foundation

### Scope

1. **Add manual payment approval to ProductOrder** for funnel COD orders, mirroring the course Order flow.
2. **Add a payment_status column** to `product_orders` if it does not already exist.
3. **Filter commission calculation** to only count orders where `payment_status='paid'`.
4. **Surface payment_status on the funnel order list** so logistics can pull the paid set themselves.

### Manual approval flow

**Trigger:** A ProductOrder where `source='funnel'`, `payment_method='cod'`, and `payment_status='pending'` displays a "Payment Actions" card on the order detail page.

**Permissions:** Only the accountant role sees the action buttons. Admins can view the card but cannot approve. Receipt upload is mandatory before approval.

**Approve action:**
- Required: upload receipt image/PDF (stored at `storage/funnel-payment-receipts/`).
- Sets `payment_status='paid'`, `paid_at=now()`, `payment_confirmed_by=auth()->id()`.
- Creates a `ProductOrderPayment` record with `status='completed'` and the receipt path.
- Appends a system note to the order timeline.

**Reject action:**
- Required: rejection reason (textarea).
- Sets `payment_status='failed'`, records reason in order notes.
- Order is then visible to logistics as "do not ship".

### Commission gate

The current calculation in [upsell-dashboard.blade.php:53-64](../../resources/views/livewire/admin/upsell-dashboard.blade.php#L53-L64) sums `funnel_revenue` across all funnel orders for a class session. Change this to join through `ProductOrder` and filter `payment_status='paid'`. Apply to every breakdown card and table on the dashboard.

**Migration impact:** Historical sessions are recalculated under the new rule. Past commission totals will drop for any session where some COD orders never got approved. Display a one-time dismissable banner on the dashboard explaining the change, with a link to this design doc.

### Logistics view

On the funnel order list, surface `payment_status` as a visible column and add a filter (paid / pending / failed). No new model — uses the existing `ProductOrder.payment_status` field.

### Open items before coding

- Confirm `payment_status` column exists on `product_orders`. If not, add it via a migration that supports both MySQL and SQLite (`DB::getDriverName()` branching).
- Confirm the existing `ProductOrderPayment` model and table support manual-confirmation flows the way we need.

---

## Phase B — Reporting additions

All three reports read from the same paid-orders dataset that Phase A creates. They share a query layer.

### By Teacher table

New section on the upsell dashboard below the existing "by PIC" breakdown.

Columns:
- Teacher name
- Sessions with upsell (count)
- Paid orders (count)
- Paid revenue (RM)
- Commission earned (RM, calculated from paid revenue × session's `upsell_teacher_commission_rate`)
- Commission paid (RM, from Phase C payout records — 0 until Phase C ships)
- Commission pending (RM, earned − paid)

Each row expands to a drilldown showing that teacher's top-selling products (across all their upsell sessions in the date range).

Sort: commission earned, descending.

### By Product table

New section on the dashboard.

**Scope:** Line-level — main funnel product, order bumps, and post-purchase upsells each count as their own product row. So a funnel selling Course A + Bump B + Upsell C produces three rows.

Columns:
- Product name
- Funnel(s) it appears in (comma-separated)
- Line type (main / bump / upsell)
- Units sold (paid orders only)
- Paid revenue
- Conversion rate (units / funnel visitors)

Sort: paid revenue, descending.

### Teacher upsell tab

New tab on [teacher-show.blade.php](../../resources/views/livewire/admin/teacher-show.blade.php), in the same tabbed pattern used elsewhere in the admin.

**Date scope:** Tab has its own date filter, defaults to last 90 days. Independent of the dashboard's date filter.

Contents:
- 4 stat cards: Sessions with upsell, Paid orders, Paid revenue, Commission earned
- Table of upsell sessions in the date range with: date, class title, funnel, paid revenue, commission, link to session detail
- Section showing Commission paid vs Commission pending totals

---

## Phase C — Accountant workflow

### Per-order PDF receipt

Add a "Download Receipt" button on each row of the admin order list (and on the order detail page). Generates a PDF receipt the accountant can email to a customer.

PDF contents (standard receipt):
- Mudeerbedaie header (logo, company info, SSM if applicable)
- Order number, date, payment confirmation date
- Customer details
- Line items with prices
- Total, tax (if applicable), grand total
- Payment method + reference number
- "Paid" stamp if applicable

Use existing PDF generation pattern in the codebase (check for DomPDF or similar). Receipt template lives at `resources/views/pdf/order-receipt.blade.php`.

### Commission payout runs

New model: `UpsellCommissionPayout` — mirrors `LiveHostPayrollRun`. Schema:

```
upsell_commission_payouts
- id
- teacher_user_id (fk users)
- period_start (date)
- period_end (date)
- total_commission (decimal)
- session_count (int)
- status (draft / locked / paid)
- paid_at (timestamp, nullable)
- payment_reference (string, nullable — bank txn ref)
- paid_by_user_id (fk users, nullable — the accountant)
- notes (text, nullable)
- created_at / updated_at
```

Pivot: `upsell_commission_payout_sessions` — links a payout to the class_sessions it covers (so we can audit which sessions were included and prevent double-counting).

### Workflow

1. Accountant opens new admin page `/admin/upsell-commissions`.
2. Picks a date range (period_start, period_end).
3. System shows a preview table: per teacher, commission owed in that period (sum of paid-revenue × rate across their sessions, minus any sessions already paid in a prior payout).
4. Accountant selects teachers (checkboxes) and clicks "Create payout run".
5. System creates one `UpsellCommissionPayout` per selected teacher with `status='draft'`.
6. Accountant reviews each payout, can edit notes, then clicks "Lock" (status → locked, can no longer be modified).
7. After bank transfer happens out-of-band, accountant clicks "Mark Paid", enters payment_reference and paid_at, status → paid.
8. The Phase B by-teacher report and teacher upsell tab read paid totals from these payout records (joined on `paid_at`).

### Guardrail

A class session's commission can be included in at most one non-rejected payout. Validate at preview time — sessions already in a draft/locked/paid payout are excluded from the next preview.

---

## Implementation order

1. **Phase A** — verify column, build manual approval flow, switch commission calculation, add logistics filter, ship behind a banner. Self-contained, can ship in isolation.
2. **Phase B** — build the shared paid-orders query layer, then the three views (by-teacher, by-product, teacher tab). Depends on Phase A for correct numbers.
3. **Phase C.1** — per-order PDF receipt. Independent, can be done in parallel with Phase B.
4. **Phase C.2** — commission payout runs. Depends on Phase B's by-teacher numbers being correct.

---

## Out of scope (deliberately)

- Adding a new "Bank Transfer" payment method to the funnel checkout. The team confirmed they want to keep using COD + out-of-band transfer; no new method is needed.
- Refund-aware commission. If a paid order is later refunded, the commission stays counted. Add later if needed.
- Auto-matching bank statement CSV uploads to pending orders. Considered for Phase A but rejected as too much complexity for the current volume.
- Real-time webhooks for Bayarcash failure cases. The diagnosis suggests Bayarcash works; only COD is the issue.

---

## Decisions log

| Decision | Choice | Reason |
|---|---|---|
| Approver role | Accountant only, receipt required | Audit trail; matches course Order flow |
| Historical recalc | Recalc all, show banner | Cleaner mental model than split-by-date |
| Product breakdown granularity | Line-level (main + bumps + upsells) | Captures where upsell revenue actually comes from |
| Teacher tab date scope | Own filter, default 90 days | Independent context from dashboard |
| Order history #6 interpretation | Per-order PDF receipt | Most useful single addition for accountant |
| Payout model | Per-teacher per-period payout run | Mirrors LiveHostPayrollRun, clean audit trail |
