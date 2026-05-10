# Bulk Assign-to-Class on Orders & Package Sales

Date: 2026-05-10
Surface: `/admin/product-orders` (Volt `admin.orders.order-list`)

## Goal

Let admins select multiple orders on the Orders & Package Sales page and assign
all selected orders' students to one or more classes in a single action.

## Decisions

- **Classes per bulk**: multi-select (matches existing single-row modal).
- **Selection scope**: current page only. Changing page or filters clears the
  selection to avoid "ghost" selections that the user can't see.
- **No new backend**: reuse `ClassAssignmentApproval::firstOrCreate` and the
  existing `resolveStudentForOrder` helper. Bulk is pure UI orchestration.

## Frontend changes (`order-list.blade.php`)

### State

- New `public array $selectedOrderIds = []`.
- Generalise the existing `public ?int $classAssignOrderId = null` to
  `public array $classAssignOrderIds = []` so the same modal can drive single
  or bulk submissions.

### Row + bulk selection UI

- Leftmost column: row checkbox bound to `selectedOrderIds`.
- Header cell: "select all on page" checkbox that toggles every visible
  order id in/out of `selectedOrderIds`.
- Sticky bulk action bar shown above the table when `selectedOrderIds` is
  non-empty: count, "Assign to Class" button, "Clear selection".
- Resetting `$page` (pagination, search, filter changes) also resets
  `selectedOrderIds`.

### Generalised modal

- `openClassAssignModal($orderId)` becomes a thin wrapper that sets
  `classAssignOrderIds = [$orderId]`.
- New `openBulkClassAssignModal()` sets
  `classAssignOrderIds = $selectedOrderIds`.
- Title: "Assign 1 order to class(es)" or "Assign N orders to class(es)".
- Available classes computed: intersection of classes not yet assigned
  across all orders in `classAssignOrderIds` (avoids surfacing classes that
  are already on every selected order; `firstOrCreate` is still idempotent
  if intersection is loosened).

### Submit

- For each `order_id` in `classAssignOrderIds`:
  - Resolve student via `resolveStudentForOrder`. If null, skip and increment
    a `skipped` counter.
  - For each `class_id` in `classAssignSelectedIds`:
    `ClassAssignmentApproval::firstOrCreate(['class_id', 'student_id', 'product_order_id'], ['status' => 'pending', 'assigned_by' => auth()->id()])`.
- Flash result: `"Assigned N order(s) to M class(es). Skipped K (no linked student)."`
- Clear `classAssignSelectedIds`, `selectedOrderIds`, close modal, dispatch
  `order-updated`.

## Edge cases

- Empty selection: bulk button disabled (don't show the action bar).
- Order with no resolvable student: skipped, counted in toast.
- Selected order disappears from current page (filter/search): selection
  state is reset on those events, so this can't strand a selection.

## Out of scope (YAGNI)

- Across-page selection (would need either fetching all matching IDs or a
  server-side bulk endpoint).
- Bulk remove / unassign.
- Audit-trail badge that shows "assigned by bulk action".
