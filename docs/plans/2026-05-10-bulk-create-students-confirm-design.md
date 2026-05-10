# Bulk Class Assign — Auto-Create Missing Students with Confirmation

Date: 2026-05-10
Surface: `/admin/product-orders` (Volt `admin.orders.order-list`)
Builds on: `2026-05-10-bulk-assign-class-design.md`

## Problem

The bulk class-assign action silently skips orders with no linked student.
Many TikTok Shop / platform orders arrive with `customer_name` and
`customer_phone` populated but no `student_id` — so admins lose the bulk
benefit and have to fall back to the single-row flow for every such order.

## Goal

Let admins approve a bulk auto-create / auto-link of student records from
order data, so a single bulk submission can cover both classes-assignment
and the implied student creation.

## Decisions

- **Phone match → auto-link silently.** If the order's `customer_phone`
  matches an existing Student (or a User we can promote to Student), link
  it without an extra prompt. Treat matching phones as the same person.
- **Two-step modal flow.** Step 1 = class picker (current). Step 2 only
  appears when some selected orders need student creation. If every order
  already has a student, Step 2 is skipped.
- **No per-order opt-out** in Step 2 (YAGNI). Admin reviews and either
  confirms the entire batch or cancels.

## Component changes (`order-list.blade.php`)

### State

```php
public bool $bulkConfirmStudents = false; // shows Step 2
public array $bulkStudentPlans = [];      // ready/creatable/skipped buckets
```

### `prepareBulkStudentPlans(array $orderIds): array`

Returns:

```php
[
  'ready'     => [ ['order' => ProductOrder, 'student' => Student], ... ],
  'creatable' => [ ['order' => ProductOrder, 'name' => '...', 'phone' => '...',
                    'action' => 'create' | 'link_student' | 'link_user',
                    'student_id' => ?int, 'user_id' => ?int], ... ],
  'skipped'   => [ ['order' => ProductOrder, 'reason' => '...'], ... ],
]
```

Resolution per order:

1. If `$order->student_id` or `$order->customer_id` resolves a Student → `ready`.
2. Else validate `customer_name` (>= 2 chars) and `customer_phone`
   (`/^\+?[0-9]+$/`, no `*`). On failure → `skipped` with reason.
3. Else look up existing Student by phone → `link_student`.
4. Else look up existing User by phone with no Student → `link_user`
   (we'll create a Student record for that User).
5. Else → `create` (new User + Student).

### Two-step `submitClassAssignment()`

In bulk mode:
- Build plans via `prepareBulkStudentPlans($classAssignBulkOrderIds)`.
- If `creatable` is empty → run the existing assignment loop directly.
- Else if `$bulkConfirmStudents === false` → store `$bulkStudentPlans = $plans`,
  set `$bulkConfirmStudents = true`, return without assigning.
- Else → execute creates/links (Step 2 confirm), then run assignment loop.

`backToClassPicker()`: clears `$bulkConfirmStudents` and `$bulkStudentPlans`.

### Execute

For each `creatable` item:
- `action = create`: build user (random password, role=student, email derived
  from phone or slug), create Student, set `$order->student_id`.
- `action = link_user`: create Student for that user, set `$order->student_id`.
- `action = link_student`: set `$order->student_id` to the matched student.

Reuse the email-uniqueness loop and try/catch from the single-row
`createStudentForOrder` to handle race conditions.

After execution, the resulting Students are appended to the `ready` bucket
and the existing assignment loop runs over all of them.

### Toast

`"Created X student(s), linked Y, assigned Z order(s) to N class(es). Skipped K (no name/phone)."`

## Modal UI (Step 2)

Replaces Step 1 content when `$bulkConfirmStudents`:

- Header: "Create students for X orders?"
- Subhead: "These orders don't have a linked student yet. Confirm to
  create or link student records before assigning classes."
- Scrollable list, one row per `creatable` item: `order_number ·
  customer_name · customer_phone` + green/blue badge ("Will create" /
  "Will link to <name>").
- Collapsed "skipped" section (if any): `order_number` + reason.
- Footer buttons: **Back** and **Create students & assign**.

Step 1's submit button label gets a small annotation when plans contain
creatable items so the admin knows the next click reveals a confirmation
("Continue → Confirm students").

## Out of scope (YAGNI)

- Per-order toggle / inline editing of name/phone.
- Bulk un-link / bulk delete student.
- Audit trail entry for "bulk-created" students.
