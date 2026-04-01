# OT Claim Feature Design

**Date:** 2026-04-01  
**Status:** Approved

## Overview

Employees accumulate replacement hours when their overtime requests are completed. This feature adds a formal claim workflow that allows employees to "use" those hours as Time Off In Lieu (TOIL) — arriving late or leaving early on a specific working day, with approval from their department OT approver.

## Business Rules

- Minimum claim duration: 30 minutes
- Maximum claim duration: 230 minutes per claim request
- Employee must have sufficient OT balance (earned hours minus previously approved claims)
- One claim per `claim_date` per employee (no duplicate claims for the same day)
- Claims can be submitted in advance (before the day) or retroactively (after the day)
- Only pending claims can be cancelled by the employee
- Same department OT approvers handle claim approvals (reuses existing `DepartmentApprover` with `approval_type = 'overtime'`)

## Data Model

### New Table: `overtime_claim_requests`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `employee_id` | FK → employees | Cascade delete |
| `claim_date` | date | The working day the TOIL applies to |
| `start_time` | time | When the leave starts (e.g., 08:00 for late arrival) |
| `duration_minutes` | int | 30–230 |
| `status` | enum | `pending`, `approved`, `rejected`, `cancelled` |
| `approved_by` | FK → users (nullable) | Set on approval |
| `approved_at` | datetime (nullable) | Set on approval |
| `rejection_reason` | text (nullable) | Set on rejection |
| `notes` | text (nullable) | Optional employee note |
| `attendance_id` | FK → attendance_records (nullable) | Linked when attendance record exists for that date |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Balance Calculation (Global, Approach A)

```
available_hours = SUM(replacement_hours_earned) - SUM(approved_claims.duration_minutes / 60)
```

- `replacement_hours_earned` comes from completed `overtime_requests`
- `replacement_hours_used` on `overtime_requests` is not actively updated (computed from claim table instead)

## Status Flow

```
[Employee submits] → pending
  → [Approver approves] → approved
      → Attendance record for claim_date flagged as "OT Claim" if it exists
      → If no attendance record yet: claim sits approved; attendance processing checks on clock-in
  → [Approver rejects] → rejected (with rejection_reason)
  → [Employee cancels] → cancelled (only from pending state)
```

## Attendance Integration

When a claim is approved:
1. Query for an existing attendance record matching `employee_id` + `claim_date`
2. If found: set `attendance.ot_claim_id = claim.id` — this signals the late/early departure should not count as an infraction
3. If not found: leave `attendance_id` null on the claim — attendance processing later will check for an approved claim on that date and auto-link

A nullable `ot_claim_id` FK column will be added to the `attendance_records` table.

## API Endpoints

### Employee (My)
| Method | URI | Action |
|--------|-----|--------|
| GET | `/me/overtime/claims` | List own claims (paginated, filterable by status) |
| POST | `/me/overtime/claims` | Submit new claim |
| DELETE | `/me/overtime/claims/{id}` | Cancel a pending claim |

### Approver
| Method | URI | Action |
|--------|-----|--------|
| GET | `/my-approvals/overtime-claims` | List claims from approver's departments |
| PATCH | `/my-approvals/overtime-claims/{id}/approve` | Approve claim |
| PATCH | `/my-approvals/overtime-claims/{id}/reject` | Reject claim with reason |

### HR Admin
| Method | URI | Action |
|--------|-----|--------|
| GET | `/hr/overtime/claims` | List all claims (all employees, filterable) |

## UI Components

### MyOvertime.jsx (Employee)
- Add "My Claims" tab alongside existing OT requests list
- Balance cards (`Earned / Used / Available`) updated to use new calculation
- "New Claim" button opens a modal:
  - Date picker
  - Start time picker
  - Duration presets: 30 / 60 / 90 / 120 / 150 / 180 / 210 / 230 min
  - Optional notes field
  - Live balance preview showing remaining balance after this claim

### MyApprovalsOvertime.jsx (Approver)
- Add "OT Claims" sub-tab alongside existing overtime approvals
- Card per claim: employee name/dept, claim date, start time, duration, notes
- Approve and Reject (with reason) action buttons

### OvertimeManagement.jsx (HR Admin)
- Add "OT Claims" tab
- Table view: employee, date, start time, duration, status, approver
- View-only (HR observes; approvers action the claims)

## Notifications

Following existing notification patterns (`BaseHrNotification`):
- On claim submission: notify the employee's department OT approvers
- On approval: notify the employee
- On rejection: notify the employee with rejection reason

## Chosen Approach

**Approach A — Simple Global Balance** was selected. Balance is computed globally from the claim table, not linked per OT request. This keeps implementation clean and matches how the existing UI presents the balance (total Earned / Used / Available).
