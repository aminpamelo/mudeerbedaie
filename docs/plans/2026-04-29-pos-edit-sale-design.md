# POS — Edit Sale Design

**Date:** 2026-04-29
**Author:** Claude (with @aminpamelo)
**Status:** Approved

## Goal

From the POS Sales History detail panel, allow editing of an existing sale's customer info, items, discount, shipping, payment method/reference, and sales source — without changing salesperson. Edits work for sales in any status (pending, paid, cancelled).

## Non-goals

- Editing salesperson (deliberately locked).
- Editing payment status from inside the edit modal — Paid/Pending/Cancelled toggles in the detail panel keep that responsibility.
- Reworking the existing cancellation-stock behavior (today `markAsCancelled()` does not restore stock; that invariant is preserved).
- Audit history beyond a single system note per edit.

## UX

In [resources/js/pos/components/SalesHistory.jsx](../../resources/js/pos/components/SalesHistory.jsx), under the order number heading, add an **"Edit Order"** button next to the existing actions.

Clicking it opens a full-screen modal (mirrors `PaymentModal.jsx` styling) titled `Edit Sale {order_number}`. Sections:

1. **Customer** — name, phone, email, address (4 inputs).
2. **Items** — table of editable rows: product name (read-only), variant (read-only), unit price input, qty input, line total (computed), ✕ remove. **+ Add Item** opens a sub-picker that reuses [ProductSearch.jsx](../../resources/js/pos/components/ProductSearch.jsx).
3. **Payment** — payment method (cash / bank_transfer / cod), payment reference (required when method is bank_transfer), sales source dropdown.
4. **Totals** — discount type/amount, shipping cost, recalculated subtotal & total (live).

Salesperson is shown but disabled (read-only). Footer: `Cancel` | `Save Changes`. On save the sale is replaced in the list and the detail panel re-renders.

## Backend

### Endpoint

`PUT /api/pos/sales/{sale}` → `PosController@updateSale`.

### Form request

`App\Http\Requests\UpdatePosSaleRequest` — copy of `StorePosSaleRequest` minus `payment_status`, `receipt_attachment`, `upsell_class_session_id`. Adds `items.*.id` (nullable, integer) so existing rows can be matched to the incoming payload.

### Controller logic (DB transaction)

1. Authorize: 403 unless `$sale->source === 'pos'`.
2. **Stock diff (status-agnostic):**
   - Match incoming items to existing by `items.*.id`. Anything without an `id` is new; anything in the existing set not referenced is removed.
   - For matched items where `quantity_ordered` changed: adjust stock by `(new_qty - old_qty)`.
   - For removed items: restore full old qty.
   - For new items: deduct full new qty.
3. Persist item changes: delete removed, update matched (qty / unit_price / total_price / variant ref), insert new.
4. Recompute `subtotal`, `discount_amount` (using the same fixed/percentage logic as `createSale`), `shipping_cost`, `total_amount`.
5. Update order fields: `customer_id/_name/_phone`, `guest_email`, `shipping_address`, `payment_method`, `sales_source_id`, `internal_notes`, and `metadata.payment_reference / discount_type / discount_input`.
6. Update the linked payment row (`amount`, `payment_method`, `reference_number`). Status fields are left alone — that flow lives in `updateSaleStatus`.
7. If a `FunnelOrder` is linked, update `funnel_revenue` to match the new total.
8. Append a system note via `addSystemNote("Order edited from POS by {user->name}")`.

### Stock — why a diff

Currently `markAsCancelled()` only mutates the order status; it does not restore stock. That means `quantity_ordered` on each item always reflects deducted stock, regardless of order status. A diff against old vs new quantities preserves that invariant in one shot and avoids regressing cancellation behavior.

### Model addition

`App\Models\ProductOrderItem::restoreStock(): void` — mirror of `deductStock()`:

- Same package/product branching.
- Increments `stock_levels.quantity` instead of decrementing.
- Writes a `StockMovement` row with `type='in'` and a note `"POS edit: stock returned (Order #{number})"`.

## Frontend

- Add `saleApi.update(id, data)` in [resources/js/pos/services/api.js](../../resources/js/pos/services/api.js) — `PUT /sales/{id}` with JSON body.
- New `resources/js/pos/components/EditSaleModal.jsx`. Pre-populates from `selectedSale`, computes totals client-side for display, posts the full payload on save.
- Mount the modal from `SalesHistory.jsx`; on success replace the sale in `sales[]` and update `selectedSale`.

### Edit payload shape

```jsonc
{
  "customer_id": 12,
  "customer_name": "...",
  "customer_phone": "...",
  "customer_email": "...",
  "customer_address": "...",
  "payment_method": "cash",
  "payment_reference": null,
  "sales_source_id": 3,
  "discount_amount": 10,
  "discount_type": "fixed",
  "shipping_cost": 10,
  "notes": "...",
  "items": [
    { "id": 88, "itemable_type": "product", "itemable_id": 5, "product_variant_id": null, "quantity": 2, "unit_price": "39.00" },
    { "itemable_type": "product", "itemable_id": 7, "quantity": 1, "unit_price": "20.00" }
  ]
}
```

## Tests (Pest, `tests/Feature/Pos/`)

- Editing customer fields persists on the order row.
- Removing an item restores full qty to stock; adding an item deducts full qty; changing qty applies the delta only.
- `subtotal + shipping − discount === total_amount` after edit.
- Payment row `amount` and `payment_method` stay in sync after edit.
- 403 for non-POS `ProductOrder`.
- 422 when `payment_method=bank_transfer` and `payment_reference` is missing.

## Risks / Open questions

- **Cancelled-sale edit:** still applies stock diff. Acceptable per user decision; note in PR description.
- **Concurrent edits / double-submit:** mitigated by disabling the Save button while in flight; no optimistic locking added (out of scope).
- **Variant changes:** v1 does not allow swapping variant on an existing line — remove and re-add the item instead.
