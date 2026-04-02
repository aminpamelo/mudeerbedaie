# HOD Approver Portal — Design Document

**Date:** 2026-04-01  
**Status:** Approved

---

## Problem

Employees assigned as department approvers (HOD) in `department_approvers` currently have no scoped view. To action overtime, leave, or claims they would need full admin access — which exposes all departments' data and sensitive employee information.

---

## Goal

Add a **My Approvals** section to the employee self-service ("My") area of the HR module. It is visible only to employees who are assigned as an approver for at least one department and approval type. It shows only their assigned department's requests, with limited employee data (no salary, IC, or address).

---

## Decisions

| Question | Decision |
|----------|----------|
| Access method | Inside "My" section — new sidebar item, conditionally shown |
| Content | All statuses, tab-filtered: Pending / Approved / Rejected (/ Completed for OT) |
| Employee data visible | Name, position, department only |
| Entry screen | Summary dashboard with 3 stat cards |
| Architecture | New dedicated pages + new scoped API controller |

---

## Architecture

### Backend — `HrMyApprovalController`

New controller at `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`.

**How department scoping works:**
1. Resolve the logged-in user's `employee_id` via `auth()->user()->employee`
2. Query `department_approvers` filtered by `approver_employee_id = employee_id` to get assigned department IDs per type
3. For Claims: additionally check `claim_approvers` where `approver_id = employee_id` for individual-level assignments
4. All request queries are `whereHas('employee', fn($q) => $q->whereIn('department_id', $deptIds))`

**Employee data returned (intentionally limited):**
```json
{
  "employee": {
    "id": 1,
    "name": "Ahmad Ali",
    "position": { "name": "Software Engineer" },
    "department": { "name": "Sistem" }
  }
}
```
No salary, no IC, no address, no bank details.

**New API endpoints:**

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/my/approvals/summary` | Pending counts + `isApprover` flag |
| GET | `/api/my/approvals/overtime` | OT requests scoped to HOD's departments |
| PATCH | `/api/my/approvals/overtime/{id}/approve` | Approve OT |
| PATCH | `/api/my/approvals/overtime/{id}/reject` | Reject OT with reason |
| PATCH | `/api/my/approvals/overtime/{id}/complete` | Complete OT with actual hours |
| GET | `/api/my/approvals/leave` | Leave requests scoped to HOD's departments |
| PATCH | `/api/my/approvals/leave/{id}/approve` | Approve leave |
| PATCH | `/api/my/approvals/leave/{id}/reject` | Reject leave with reason |
| GET | `/api/my/approvals/claims` | Claim requests scoped to HOD's departments |
| PATCH | `/api/my/approvals/claims/{id}/approve` | Approve claim with approved_amount |
| PATCH | `/api/my/approvals/claims/{id}/reject` | Reject claim with reason |

The approve/reject business logic delegates to the existing service/controller methods to avoid duplication.

---

### Frontend — React Pages

4 new pages under `resources/js/hr/pages/my/`:

#### 1. `MyApprovals.jsx` — Summary Dashboard
- Route: `/hr/my/approvals`
- Shows 3 stat cards: Overtime, Leave, Claims — each with pending count
- Clicking a card navigates to that type's list page
- If the user is not an approver for a type, that card shows "Not assigned"

#### 2. `MyApprovalsOvertime.jsx`
- Route: `/hr/my/approvals/overtime`
- Tabs: Pending | Approved | Rejected | Completed
- Table: Employee name, Date, Reason, Estimated hours, Status, Actions
- Actions: Approve, Reject (with reason dialog), Complete (with actual hours input)
- Detail modal: request info + basic employee info only

#### 3. `MyApprovalsLeave.jsx`
- Route: `/hr/my/approvals/leave`
- Tabs: Pending | Approved | Rejected
- Table: Employee name, Leave type, Dates, Total days, Reason, Status, Actions
- Actions: Approve, Reject (with reason dialog)
- Shows leave balance remaining (available days) in the detail modal

#### 4. `MyApprovalsClaims.jsx`
- Route: `/hr/my/approvals/claims`
- Tabs: Pending | Approved | Rejected
- Table: Employee name, Claim type, Amount, Date, Description, Status, Actions
- Actions: Approve (with approved_amount input), Reject (with reason dialog)
- Note: "Mark as Paid" is admin-only and NOT available in the HOD portal

---

### Sidebar

The `My Approvals` nav item is rendered conditionally:
- The summary endpoint returns `{ isApprover: true/false }` 
- If `isApprover === false`, the nav item is hidden entirely
- Badge shows total pending count when > 0

---

## Files to Create

| File | Type |
|------|------|
| `app/Http/Controllers/Api/Hr/HrMyApprovalController.php` | New controller |
| `resources/js/hr/pages/my/MyApprovals.jsx` | New React page |
| `resources/js/hr/pages/my/MyApprovalsOvertime.jsx` | New React page |
| `resources/js/hr/pages/my/MyApprovalsLeave.jsx` | New React page |
| `resources/js/hr/pages/my/MyApprovalsClaims.jsx` | New React page |

## Files to Modify

| File | Change |
|------|--------|
| `routes/api.php` | Add `/api/my/approvals/*` routes |
| `resources/js/hr/App.jsx` (or router) | Add 4 new frontend routes |
| Sidebar component | Add conditional `My Approvals` nav item |

---

## What Is NOT Included

- Mark as Paid (admin-only action, stays in admin Claims page)
- Employee profile links/navigation from the HOD portal
- Payroll, salary, IC, bank details — never returned in API responses
- Org chart or team overview — this is approval-action focused only
