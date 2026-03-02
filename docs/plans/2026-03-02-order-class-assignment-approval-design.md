# Order-to-Class Assignment with Approval List

## Summary

Allow admins to assign product orders to classes from the order detail page. Assignments go into an approval queue on the class page, where admins can approve (enroll the student) or reject the assignment. On approval, the admin can optionally toggle creating a course-level enrollment/subscription.

## Data Model

### New Table: `class_assignment_approvals`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `class_id` | FK → classes | Target class |
| `student_id` | FK → students | Student from the order |
| `product_order_id` | FK → product_orders | Source order |
| `status` | string | `pending`, `approved`, `rejected` |
| `enroll_with_subscription` | boolean, default false | Toggle: also create course enrollment on approve |
| `assigned_by` | FK → users | Admin who made the assignment |
| `approved_by` | FK → users, nullable | Admin who approved/rejected |
| `notes` | text, nullable | Admin notes (e.g. rejection reason) |
| `approved_at` | timestamp, nullable | When approved/rejected |
| `created_at` / `updated_at` | timestamps | Standard Laravel |

### New Model: `ClassAssignmentApproval`

Relationships:
- `belongsTo ClassModel` (class_id)
- `belongsTo Student` (student_id)
- `belongsTo ProductOrder` (product_order_id)
- `belongsTo User` as assignedBy (assigned_by)
- `belongsTo User` as approvedBy (approved_by)

Scopes: `pending()`, `approved()`, `rejected()`

## UI Changes

### 1. Order Detail Page — "Class Assignment" Card

**Location:** Below Order Items section in left column of `order-show.blade.php`

**Contents:**
- Card header: "Class Assignment" + "Assign to Class" button
- List of existing assignments with status badges (Pending/Approved/Rejected) and class name
- "Assign to Class" button opens a modal

**Assign to Class Modal:**
- Searchable class selector, all active classes grouped by course
- Classes linked to the order's product/course show a "Suggested" badge
- Multi-select: order can be assigned to multiple classes
- Submit creates `class_assignment_approvals` records with `status = pending`

**Student resolution:** Uses `product_orders.student_id` to determine which student to assign. If the order has no student linked, show a student selector or warning.

### 2. Class Show Page — "Approval List" Tab

**Location:** New tab in the class show page header, between Students and Timetable tabs

**Tab header:** "Approval List" with pending count badge

**Table columns:**

| Column | Content |
|--------|---------|
| Student | Avatar + name + phone |
| Order | Order number (clickable link to order detail) |
| Assigned By | Admin name who assigned |
| Assigned Date | created_at formatted |
| Subscription | Toggle switch for "Also create course enrollment" |
| Actions | Approve button + Reject button |

**Approve flow:**
1. Create `class_students` record: `status = active`, `order_id = product_order.order_number`
2. If subscription toggle ON: create `Enrollment` record for the course (manual payment type, status = active)
3. Update approval record: `status = approved`, set `approved_by`, `approved_at`

**Reject flow:**
1. Update approval record: `status = rejected`, set `approved_by`, `approved_at`
2. Optional: prompt for rejection note

**Bulk actions:** Checkbox + "Approve Selected" / "Reject Selected" buttons

## Class Suggestion Logic

When assigning an order to a class:
1. Check if any `ProductOrderItem` in the order links to a product/package that maps to a course
2. If a course mapping exists, classes under that course get a "Suggested" badge
3. If no mapping exists, all classes shown equally (no suggestions)

## Decisions Made

- **Approach:** Separate `class_assignment_approvals` table (not reusing `class_students` with pending status)
- **Scope:** Whole order assigned to multiple classes (not per-item)
- **Class selection:** Any active class, grouped by course, with suggestions
- **Enrollment toggle:** Per-student toggle on the approval list to optionally create course-level enrollment
- **Actions:** Approve + Reject with order info shown
