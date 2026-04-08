# Tier Approval System Design

**Date:** 2026-04-08  
**Status:** Approved  

## Overview

Add sequential tier-based approval to the department approver system. Currently, all approvers for a department/type are at the same level (any one can approve). This design introduces ordered tiers so requests flow through a chain of approvals.

## Requirements

- **Sequential flow:** Tier 1 must approve before Tier 2 sees the request, and so on
- **Unlimited tiers:** Admin can create as many tiers as needed per department per type
- **Independent per type:** Each approval type (OT, Leave, Claims, Exit Permission) has its own tier configuration
- **Any-one-per-tier:** Within a tier, any single approver can approve to advance
- **Immediate rejection:** Any one rejection at any tier = entire request rejected

## Database Changes

### 1. Add `tier` column to `department_approvers`

```sql
ALTER TABLE department_approvers ADD COLUMN tier INTEGER NOT NULL DEFAULT 1;
```

Each row already represents one approver for one department for one type. The `tier` column groups them into ordered levels.

### 2. Add `current_approval_tier` to request tables

Add to: `overtime_requests`, `leave_requests`, `claim_requests`, `office_exit_permissions`, `overtime_claim_requests`

```sql
ALTER TABLE <table> ADD COLUMN current_approval_tier INTEGER NOT NULL DEFAULT 1;
```

### 3. New `approval_logs` table (audit trail)

```
approval_logs
├── id
├── approvable_type (string - polymorphic)
├── approvable_id (unsignedBigInteger)
├── tier (integer)
├── approver_id (FK → employees)
├── action (enum: approved, rejected)
├── notes (text, nullable)
├── timestamps
```

## API Changes

### Store/Update Department Approvers

**Current format:**
```json
{
  "department_id": 1,
  "ot_approver_ids": [1, 2],
  "leave_approver_ids": [3],
  "claims_approver_ids": [4, 5]
}
```

**New format:**
```json
{
  "department_id": 1,
  "ot_approvers": [
    { "tier": 1, "employee_ids": [1, 2] },
    { "tier": 2, "employee_ids": [3] }
  ],
  "leave_approvers": [
    { "tier": 1, "employee_ids": [4] },
    { "tier": 2, "employee_ids": [5, 6] }
  ],
  "claims_approvers": [
    { "tier": 1, "employee_ids": [7] }
  ],
  "exit_permission_approvers": [
    { "tier": 1, "employee_ids": [8] }
  ]
}
```

### Index Response

**New format** (grouped by tier):
```json
{
  "id": 1,
  "department": { "id": 1, "name": "Logistik" },
  "ot_approvers": {
    "1": [{ "id": 1, "name": "Ali", "department": "Logistik" }],
    "2": [{ "id": 3, "name": "Ahmad", "department": "Logistik" }]
  },
  "leave_approvers": { ... },
  "claims_approvers": { ... },
  "exit_permission_approvers": { ... }
}
```

## UI Changes (Edit Modal)

Each approval type section becomes tier-based:

```
OT Approvers
┌─ Tier 1 ─────────────────────────────── [🗑 Remove] ─┐
│  🔍 Search employee...                                │
│  ☑ Ali (Logistik)                                     │
│  ☑ Siti (Logistik)                                    │
└───────────────────────────────────────────────────────┘
┌─ Tier 2 ─────────────────────────────── [🗑 Remove] ─┐
│  🔍 Search employee...                                │
│  ☑ Ahmad (Logistik)                                   │
└───────────────────────────────────────────────────────┘
                    [+ Add Tier]
```

- Each tier has its own search and employee checkboxes
- "Add Tier" button appends a new tier
- "Remove" button removes a tier (shifts remaining tiers down)
- Minimum 1 tier required per type

## Department Approvers List View

Update the table to show tier information:

```
Department | OT Approvers           | Leave Approvers        | ...
-----------+------------------------+------------------------+----
Logistik   | T1: Ali, Siti          | T1: Siti               |
           | T2: Ahmad              | T2: HR Director        |
```

## Approval Workflow

### When employee submits a request:
1. `current_approval_tier` = 1
2. System finds Tier 1 approvers for employee's department + type
3. Tier 1 approvers see the request in their "My Approvals" dashboard

### When an approver acts:
- **Approves:** 
  - Log to `approval_logs` (tier, approver, action)
  - Check if there's a next tier
  - If yes: increment `current_approval_tier`, notify next tier approvers
  - If no: mark request as fully approved (`status = approved`)
- **Rejects:**
  - Log to `approval_logs`
  - Mark request as rejected immediately (`status = rejected`)
  - Set `approved_by` and rejection reason

### My Approvals Dashboard:
- Only show requests where `current_approval_tier` matches the approver's tier for that department + type
- Pending count should only count requests at the approver's tier

## Files to Modify

### Backend:
- `database/migrations/` — new migration for `tier` column + `approval_logs` table + `current_approval_tier` columns
- `app/Models/DepartmentApprover.php` — add `tier` to fillable
- `app/Models/ApprovalLog.php` — new model
- `app/Http/Controllers/Api/Hr/HrDepartmentApproverController.php` — update store/update/index for tier format
- `app/Http/Controllers/Api/Hr/HrMyApprovalController.php` — filter by current_approval_tier
- Approval action controllers (overtime, leave, claims, exit permission) — add tier advancement logic
- `app/Http/Requests/Hr/StoreDepartmentApproverRequest.php` — update validation

### Frontend:
- `resources/js/hr/pages/attendance/DepartmentApprovers.jsx` — tier UI in modal + list view
- `resources/js/hr/lib/api.js` — update API payloads
- `resources/js/hr/pages/my/MyApprovals*.jsx` — show current tier status
