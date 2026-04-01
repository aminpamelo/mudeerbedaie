# Design: Office Exit Permission (Surat Pelepasan) Module

**Date:** 2026-04-01
**Status:** Approved

---

## Overview

Digitalize the "Borang Permohonan Kebenaran Meninggalkan Pejabat Dalam Waktu Bekerja" (Office Exit Permission Form). Employees submit a request to leave the office during working hours, an assigned approver approves/rejects it, and a configurable list of per-department notifiers receives a CC email on approval. An approved permission auto-flags the employee's attendance record and generates a printable PDF.

---

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Approval levels | 1-level (single approver) + CC notification | Simpler flow, management visibility via CC |
| Attendance link | Auto-create attendance note on approval | Keeps attendance record accurate |
| Errand type limits | None — track type only | Manager decides case-by-case |
| PDF export | Yes — on approval, BeDaie-branded | Replaces physical form |
| Module placement | Standalone top-level sidebar section | Clean separation from Attendance |
| CC scope | Per-department notifiers | Each department configures its own notification recipients |

---

## Data Model

### Table: `office_exit_permissions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `permission_number` | varchar unique | Auto-gen: `OEP-YYYYMM-0001` |
| `employee_id` | FK → employees | Requester |
| `exit_date` | date | Date of exit |
| `exit_time` | time | Time leaving office |
| `return_time` | time | Expected return time |
| `errand_type` | enum: `company`, `personal` | Urusan Syarikat / Peribadi |
| `purpose` | text | Reason / destination |
| `addressed_to` | varchar | "Kepada:" field — free text |
| `status` | enum: `pending`, `approved`, `rejected`, `cancelled` | Default: `pending` |
| `approved_by` | FK → users, nullable | |
| `approved_at` | timestamp, nullable | |
| `rejection_reason` | text, nullable | |
| `cc_notified_at` | timestamp, nullable | When CC notification was dispatched |
| `attendance_note_created` | boolean default false | Whether attendance was flagged |
| `created_at`, `updated_at` | timestamps | |

**Indexes:** `(employee_id, status)`, `(exit_date)`, `permission_number`

**Status flow:**
```
pending → approved (attendance flagged, notifications sent)
        → rejected (reason required)
pending → cancelled (employee self-cancel only)
```

---

### Table: `exit_permission_notifiers`

Stores per-department CC notification recipients.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `department_id` | FK → departments | |
| `employee_id` | FK → employees | Person to notify |
| `created_at`, `updated_at` | timestamps | |

**Unique constraint:** `(department_id, employee_id)`

---

### Approvers

Reuse existing `department_approvers` table with a new `approval_type = 'exit_permission'`. No schema change needed.

---

## Backend Architecture

### New Files

```
app/Models/OfficeExitPermission.php
app/Models/ExitPermissionNotifier.php
database/migrations/..._create_office_exit_permissions_table.php
database/migrations/..._create_exit_permission_notifiers_table.php
app/Http/Controllers/Api/Hr/HrOfficeExitPermissionController.php
app/Http/Controllers/Api/Hr/HrMyExitPermissionController.php
app/Http/Controllers/Api/Hr/HrExitPermissionNotifierController.php
app/Http/Requests/Hr/StoreExitPermissionRequest.php
app/Notifications/Hr/ExitPermissionApproved.php
app/Notifications/Hr/ExitPermissionRejected.php
```

### API Endpoints

**Admin:**
```
GET    /api/hr/exit-permissions                    → list with filters
GET    /api/hr/exit-permissions/{id}               → detail
PATCH  /api/hr/exit-permissions/{id}/approve       → approve
PATCH  /api/hr/exit-permissions/{id}/reject        → reject
GET    /api/hr/exit-permissions/{id}/pdf           → download PDF

GET    /api/hr/exit-permission-notifiers           → list notifiers (by department)
POST   /api/hr/exit-permission-notifiers           → add notifier
DELETE /api/hr/exit-permission-notifiers/{id}      → remove notifier
```

**Employee self-service:**
```
GET    /api/hr/my/exit-permissions                 → own list
POST   /api/hr/my/exit-permissions                 → submit request
GET    /api/hr/my/exit-permissions/{id}            → detail + PDF link
DELETE /api/hr/my/exit-permissions/{id}            → cancel (pending only)
```

**Approver (via my-approvals):**
```
GET    /api/hr/my-approvals/exit-permissions       → list pending in approver's departments
PATCH  /api/hr/my-approvals/exit-permissions/{id}/approve
PATCH  /api/hr/my-approvals/exit-permissions/{id}/reject
```

### Approval Logic (on approve)

1. Validate status is `pending`
2. DB transaction:
   a. Update `status = approved`, `approved_by`, `approved_at`
   b. Create/update attendance record note for `exit_date` with `out_of_office` tag, storing `exit_time` and `return_time`
   c. Set `attendance_note_created = true`
   d. Notify employee (`ExitPermissionApproved`)
   e. Query `exit_permission_notifiers` for the employee's department → send CC notifications
   f. Set `cc_notified_at`

### PDF Generation

- Use Laravel DomPDF (`barryvdh/laravel-dompdf`)
- Template: `resources/views/pdf/exit-permission.blade.php`
- Matches physical form layout with BeDaie branding
- Only available once status is `approved`

---

## Frontend Architecture

### Admin Pages (HrLayout)

| Route | Component | Description |
|---|---|---|
| `/exit-permissions` | `ExitPermissions.jsx` | List with filters: status, department, errand type, date range. Approve/reject inline. |
| `/exit-permissions/notifiers` | `ExitPermissionNotifiers.jsx` | Manage CC notifiers per department. |

### Employee Self-Service Pages (EmployeeAppLayout)

| Route | Component | Description |
|---|---|---|
| `/my/exit-permissions` | `MyExitPermissions.jsx` | Own requests list with status badges. Apply button. |
| `/my/exit-permissions/apply` | `ApplyExitPermission.jsx` | Submit form. |
| `/my/exit-permissions/:id` | `MyExitPermissionDetail.jsx` | Detail + Download PDF button (if approved). |

### Approver Pages (EmployeeAppLayout)

| Route | Component | Description |
|---|---|---|
| `/my/approvals/exit-permissions` | `MyApprovalsExitPermissions.jsx` | Pending list in approver's departments. Approve/reject. |

### Navigation Changes

**Admin Sidebar (HrLayout.jsx)** — new top-level entry:
```
Exit Permissions  (icon: DoorOpen or LogOut)
  ├── Requests     → /exit-permissions
  └── Notifiers   → /exit-permissions/notifiers
```

**Employee Sidebar (EmployeeAppLayout.jsx)** — add between My Claims and My Approvals:
```
My Exit Permissions  (icon: DoorOpen)  → /my/exit-permissions
```

**My Approvals summary (MyApprovals.jsx)** — add new card:
```
Exit Permissions  (icon: DoorOpen)
  N pending  → /my/approvals/exit-permissions
```

**My Approvals summary API (HrMyApprovalController.php)** — extend `summary()` to include `exit_permission` pending count.

---

## Notifications

### ExitPermissionApproved
- **To:** Employee
- **Content:** Permission number, date, approved exit/return time, errand type, approver name, PDF download link

### ExitPermissionRejected
- **To:** Employee
- **Content:** Permission number, date, rejection reason, approver name

### CC Notification (part of Approved)
- **To:** All `exit_permission_notifiers` for the employee's department
- **Content:** Same as approved notification, marked as "For your information"

---

## Testing Plan

- Feature tests for submit, approve, reject, cancel flows
- Test that attendance note is created on approval
- Test that CC notifications are sent to the correct department notifiers
- Test that PDF endpoint returns 403 for non-approved records
- Test employee cannot cancel approved/rejected requests
- Test approver can only see their assigned department's requests
