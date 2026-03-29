# Module 3: Leave Management — Detailed Design

**Date:** 2026-03-27
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 2 (Day-to-Day Operations)
**Dependencies:** Module 4 (Attendance) — shared department_approvers, attendance_logs sync, OT replacement hours

---

## Key Decisions

- **Leave Types:** Standard Malaysian + OT Replacement + custom types (HR can create new types)
- **Entitlements:** Configurable per employment type AND years of service (tiered)
- **Carry Forward:** Allowed with configurable cap per leave type
- **Approval:** Multiple approvers per department (any one can approve). Uses shared department_approvers table
- **Attachments:** Required for MC, configurable per leave type
- **Team Calendar:** With overlap warning (not blocking)
- **Attendance Sync:** Auto-create attendance_logs with "on_leave" status when leave approved
- **OT Integration:** Replacement Leave deducts from OT replacement hours balance
- **Half Day:** Supported (morning/afternoon)
- **Pro-rating:** Mid-year joiners get pro-rated entitlement

---

## Data Models

### `leave_types` — Leave type configuration

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Annual Leave", "Medical Leave" |
| code | string (unique) | e.g., "AL", "MC", "HL", "ML", "PL" |
| description | text | nullable |
| is_paid | boolean | Paid or unpaid leave |
| is_attachment_required | boolean | Force document upload |
| is_system | boolean | System-defined (can't delete) |
| is_active | boolean | Can be disabled |
| max_consecutive_days | int | nullable, e.g., MC max 2 days without cert |
| gender_restriction | enum | null (both), male, female |
| color | string | Hex color for calendar display |
| sort_order | int | Display order |
| created_at | timestamp | |
| updated_at | timestamp | |

### `leave_entitlements` — Entitlement rules per leave type

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| leave_type_id | FK → leave_types | |
| employment_type | enum | full_time, part_time, contract, intern, all |
| min_service_months | int | e.g., 0, 24, 60 |
| max_service_months | int | nullable (null = no upper limit) |
| days_per_year | decimal(4,1) | e.g., 8, 12, 16, 98 |
| is_prorated | boolean | Pro-rate for mid-year joiners |
| carry_forward_max | int | Max days to carry forward (0 = no carry) |
| created_at | timestamp | |
| updated_at | timestamp | |

### `leave_balances` — Employee leave balances per year

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| leave_type_id | FK → leave_types | |
| year | int | |
| entitled_days | decimal(4,1) | Total entitlement for the year |
| carried_forward_days | decimal(4,1) | From previous year |
| used_days | decimal(4,1) | Days taken (approved) |
| pending_days | decimal(4,1) | Days in pending requests |
| available_days | decimal(4,1) | entitled + carried - used - pending |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `employee_id + leave_type_id + year`

### `leave_requests` — Leave applications

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| leave_type_id | FK → leave_types | |
| start_date | date | |
| end_date | date | |
| total_days | decimal(3,1) | Calculated (excludes weekends/holidays) |
| is_half_day | boolean | |
| half_day_period | enum | nullable: morning, afternoon |
| reason | text | |
| attachment_path | string | nullable, uploaded file |
| status | enum | pending, approved, rejected, cancelled |
| approved_by | FK → users | nullable |
| approved_at | datetime | nullable |
| rejection_reason | text | nullable |
| is_replacement_leave | boolean | Uses OT replacement hours |
| replacement_hours_deducted | decimal(5,1) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### Updated `department_approvers` table (shared with Module 4)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| department_id | FK → departments | |
| approver_employee_id | FK → employees | |
| approval_type | enum | overtime, leave, claims |
| created_at | timestamp | |
| updated_at | timestamp | |

**Allows multiple rows per department+type** (multiple approvers). Any one can approve.

---

## Default Leave Types (Malaysian)

| Code | Name | Days | Paid | Attach | Gender | System | Notes |
|------|------|------|------|--------|--------|--------|-------|
| AL | Annual Leave | 8/12/16 | Yes | No | Both | Yes | Tiered by service years |
| MC | Medical Leave | 14/18/22 | Yes | **Yes** | Both | Yes | MC slip required |
| HL | Hospitalization Leave | 60 | Yes | Yes | Both | Yes | Serious illness |
| ML | Maternity Leave | 98 | Yes | Yes | Female | Yes | Employment Act 1955 |
| PL | Paternity Leave | 7 | Yes | No | Male | Yes | |
| CL | Compassionate Leave | 3 | Yes | No | Both | Yes | Death of immediate family |
| RL | Replacement Leave | OT balance | Yes | No | Both | Yes | Deducts from OT hours |
| UL | Unpaid Leave | Unlimited | No | No | Both | Yes | Salary deduction |
| EL | Emergency Leave | 2 | Yes | No | Both | No | Custom |
| MRL | Marriage Leave | 3 | Yes | Yes | Both | No | Custom |
| SL | Study Leave | 5 | Yes | No | Both | No | Custom |

### Default Entitlement Rules (Employment Act 1955)

**Annual Leave (AL):**
| Employment Type | Service | Days/Year | Pro-rated | Carry Forward |
|----------------|---------|-----------|-----------|---------------|
| Full-time | 0-23 months | 8 | Yes | 5 days max |
| Full-time | 24-59 months | 12 | No | 5 days max |
| Full-time | 60+ months | 16 | No | 5 days max |
| Part-time | All | 4 | Yes | 0 |
| Contract | All | 8 | Yes | 0 |
| Intern | All | 0 | No | 0 |

**Medical Leave (MC):**
| Employment Type | Service | Days/Year | Carry Forward |
|----------------|---------|-----------|---------------|
| Full-time | 0-23 months | 14 | 0 |
| Full-time | 24-59 months | 18 | 0 |
| Full-time | 60+ months | 22 | 0 |
| Part-time | All | 7 | 0 |
| Contract | All | 14 | 0 |
| Intern | All | 7 | 0 |

---

## Screens

### Admin Pages (7)

#### 1. Leave Dashboard (`/hr/leave`)

**Stats Cards:**
- Pending Requests (yellow)
- Approved This Month (green)
- On Leave Today (blue)
- Upcoming Leaves (purple)

**Charts:**
- Leave Type Distribution (pie chart)
- Monthly Leave Trend (bar chart, last 12 months)

**Pending Approvals Table:**
- Employee | Type | Dates | Days | Status | Quick Actions [Approve] [Reject]

#### 2. Leave Requests (`/hr/leave/requests`)

- Full paginated table of all leave requests
- Filters: Status, Leave Type, Department, Employee, Date Range
- Columns: Employee | Type (color badge) | Start-End | Days | Half Day | Attachment | Status | Approved By | Actions
- Click row → detail modal with attachment preview
- Approve/Reject with optional reason
- Export CSV

#### 3. Team Leave Calendar (`/hr/leave/calendar`)

- Month grid view showing approved leaves
- Color-coded by leave type
- Filter by department
- Overlap warning banner when multiple from same department on same day
- Click day → sidebar showing all leaves for that day
- Month/week toggle

#### 4. Leave Balances (`/hr/leave/balances`)

- Table: Employee | Dept | AL (Used/Total) | MC (Used/Total) | ... per type | Actions
- Filter by department, employment type, year
- Click employee → detailed balance breakdown modal
- "Initialize Year" button → generate balances for selected year
- Manual adjustment: Admin can add/deduct days with reason
- Export CSV

#### 5. Leave Types (`/hr/leave/types`)

- Table: Name | Code | Days | Paid | Attachment | Gender | System | Active | Sort | Actions
- System types: Edit settings, cannot delete
- Custom types: Full CRUD
- "+ Add Leave Type" → modal form (name, code, description, paid, attachment required, gender, color)
- Toggle active/inactive

#### 6. Leave Entitlements (`/hr/leave/entitlements`)

- Grouped by leave type (accordion)
- Per type table: Employment Type | Service Range | Days/Year | Pro-rated | Carry Forward Max | Actions
- "+ Add Rule" → modal form
- "Recalculate All Balances" button → applies rules to all employees

#### 7. Leave Approvers (`/hr/leave/approvers`)

- Table: Department | Approvers (multi-chip tags) | Actions
- Edit → multi-select employees as approvers for the department
- Shows "(Any one can approve)" helper text
- Uses shared `department_approvers` table (type: leave)

### Employee Self-Service (2 pages)

#### 8. My Leave (`/hr/my/leave`)

- **Balance Cards:** One card per leave type: Available / Used / Entitled + Carried
- **OT Replacement Balance:** X hours available (from Attendance module)
- **My Requests:** Table with Status, option to cancel pending/future approved
- **Calendar:** Current month with my leaves highlighted
- **"+ Apply for Leave" button**

#### 9. Apply for Leave (`/hr/my/leave/apply`)

- Leave Type selector (dropdown, filtered by gender/active)
  - Shows remaining balance next to each type
  - Replacement Leave shows OT hours balance
- Date Range: Start date + End date pickers
  - Auto-calculate working days (exclude weekends + holidays)
  - Show "X working days" preview
- Half Day toggle → Morning/Afternoon radio
- Overlap Warning: "⚠ 2 others from your department on leave on [date]"
- Reason (textarea, required)
- Attachment upload (required indicator if type needs it)
- Balance check: "After this request: X days remaining"
- Submit button

---

## Workflows

### Apply for Leave Flow

```
Employee opens Apply for Leave form
  → Selects leave type
  → System shows: remaining balance, attachment requirement, gender check
  → Selects dates
  → System calculates working days (excludes weekends + holidays from holidays table)
  → If Replacement Leave:
    - Check OT replacement hours balance
    - Convert: 8 hours = 1 day (configurable)
    - Show: "Using X hours of replacement time"
  → Validates:
    1. Sufficient balance (available_days >= requested_days)
    2. Gender restriction (maternity = female only)
    3. Max consecutive days check
    4. Attachment present if required
  → Shows overlap warning (non-blocking)
  → Submit:
    1. Create leave_request (status: pending)
    2. Update leave_balance: pending_days += total_days, available -= total_days
    3. Find all leave approvers for employee's department
    4. Push notification to ALL approvers
  → Shows: "Leave request submitted, pending approval"
```

### Approval Flow

```
Any department leave approver sees pending request
  → Reviews: dates, reason, attachment, overlap warning, balance info

  → APPROVE:
    1. leave_request: status → approved, approved_by, approved_at
    2. leave_balance: used_days += total_days, pending_days -= total_days
    3. If Replacement Leave:
       - Find related overtime_requests
       - Increment replacement_hours_used
       - Set leave_request.replacement_hours_deducted
    4. For each working day in leave range:
       - Create attendance_log with status: "on_leave"
       - Skip weekends (check work_schedule.working_days)
       - Skip holidays (check holidays table)
    5. Push notification to employee: "Leave approved"

  → REJECT:
    1. leave_request: status → rejected, rejection_reason
    2. leave_balance: pending_days -= total_days, available += total_days
    3. Push notification to employee: "Leave rejected — [reason]"
```

### Cancel Leave Flow

```
Employee cancels leave request:
  → If status = "pending":
    1. Status → cancelled
    2. leave_balance: pending_days -= total_days, available += total_days
  → If status = "approved" AND all dates in future:
    1. Status → cancelled
    2. leave_balance: used_days -= total_days, available += total_days
    3. Delete auto-created attendance_logs for those dates
    4. If Replacement Leave: restore OT replacement hours
    5. Notify approvers: "[Employee] cancelled their approved leave"
  → If status = "approved" AND some dates in past:
    → Cannot cancel (dates already taken)
```

### Year-End Balance Processing

```
Scheduled: Jan 1st each year
  → For each active employee:
    1. Get previous year balance for each leave type
    2. Calculate carry forward:
       carry = min(previous.available_days, entitlement.carry_forward_max)
    3. Determine new year entitlement:
       - Match employment_type + service_months to leave_entitlement rules
       - If is_prorated AND joined this year:
         entitled = days_per_year × (remaining_months / 12)
       - Else: entitled = days_per_year
    4. Create leave_balance:
       - entitled_days = calculated
       - carried_forward_days = carry
       - used_days = 0, pending_days = 0
       - available_days = entitled + carry
  → Log processing results
  → Notify HR Admin: "Year-end leave balances processed"
```

---

## API Endpoints

### Leave Requests (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/requests` | List all (filter: status, type, dept, date range) |
| GET | `/api/hr/leave/requests/{id}` | Request detail with attachment |
| PATCH | `/api/hr/leave/requests/{id}/approve` | Approve request |
| PATCH | `/api/hr/leave/requests/{id}/reject` | Reject request (with reason) |
| GET | `/api/hr/leave/requests/export` | Export CSV |

### Leave Balances (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/balances` | All employees' balances (filter: dept, year) |
| GET | `/api/hr/leave/balances/{employeeId}` | Single employee detail |
| POST | `/api/hr/leave/balances/initialize` | Initialize balances for year |
| PUT | `/api/hr/leave/balances/{id}/adjust` | Manual adjustment (add/deduct) |
| GET | `/api/hr/leave/balances/export` | Export CSV |

### Leave Types (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/types` | List all leave types |
| POST | `/api/hr/leave/types` | Create custom type |
| PUT | `/api/hr/leave/types/{id}` | Update type |
| DELETE | `/api/hr/leave/types/{id}` | Delete custom type |

### Leave Entitlements (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/entitlements` | List all entitlement rules |
| POST | `/api/hr/leave/entitlements` | Create rule |
| PUT | `/api/hr/leave/entitlements/{id}` | Update rule |
| DELETE | `/api/hr/leave/entitlements/{id}` | Delete rule |
| POST | `/api/hr/leave/entitlements/recalculate` | Recalculate all balances |

### Leave Calendar (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/calendar` | Calendar data (month, dept filter) |
| GET | `/api/hr/leave/calendar/overlaps` | Overlap warnings for date range |

### Employee Self-Service — Leave

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/me/leave/balances` | My leave balances (current year) |
| GET | `/api/hr/me/leave/requests` | My leave requests |
| POST | `/api/hr/me/leave/requests` | Apply for leave |
| DELETE | `/api/hr/me/leave/requests/{id}` | Cancel my request |
| GET | `/api/hr/me/leave/calculate-days` | Calculate working days for date range |

### Leave Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/leave/dashboard/stats` | Overview stats |
| GET | `/api/hr/leave/dashboard/pending` | Pending approvals list |
| GET | `/api/hr/leave/dashboard/distribution` | Leave type distribution |
| GET | `/api/hr/leave/dashboard/trend` | Monthly leave trend |

---

## Scheduled Jobs

| Command | Schedule | Description |
|---------|----------|-------------|
| `hr:initialize-leave-balances` | Jan 1st, 12:01 AM | Calculate new year entitlements + carry forward |
| `hr:expire-carried-leave` | Configurable (e.g., Mar 31st) | Forfeit unused carried forward days |

---

## Validation Rules

### Apply for Leave
- leave_type_id: required, exists:leave_types,id
- start_date: required, date, after_or_equal:today
- end_date: required, date, after_or_equal:start_date
- is_half_day: boolean
- half_day_period: required_if:is_half_day,true, in:morning,afternoon
- reason: required, string, min:5, max:500
- attachment: required_if (based on leave type), file, max:5120, mimes:pdf,jpg,jpeg,png

### Leave Type
- name: required, string, max:255
- code: required, string, max:10, unique
- is_paid: required, boolean
- is_attachment_required: required, boolean
- gender_restriction: nullable, in:male,female
- color: required, string, regex:/^#[0-9A-Fa-f]{6}$/

### Leave Entitlement
- leave_type_id: required, exists:leave_types,id
- employment_type: required, in:full_time,part_time,contract,intern,all
- min_service_months: required, integer, min:0
- max_service_months: nullable, integer, gt:min_service_months
- days_per_year: required, numeric, min:0, max:365
- is_prorated: required, boolean
- carry_forward_max: required, integer, min:0

### Manual Balance Adjustment
- adjustment_days: required, numeric (positive to add, negative to deduct)
- reason: required, string, min:5

---

## React File Structure

```
resources/js/hr/
├── pages/
│   ├── leave/
│   │   ├── LeaveDashboard.jsx
│   │   ├── LeaveRequests.jsx
│   │   ├── LeaveCalendar.jsx
│   │   ├── LeaveBalances.jsx
│   │   ├── LeaveTypes.jsx
│   │   ├── LeaveEntitlements.jsx
│   │   └── LeaveApprovers.jsx
│   ├── my/
│   │   ├── MyLeave.jsx
│   │   └── ApplyLeave.jsx
├── components/
│   ├── leave/
│   │   ├── LeaveRequestForm.jsx
│   │   ├── LeaveBalanceCard.jsx
│   │   ├── LeaveCalendarGrid.jsx
│   │   ├── OverlapWarning.jsx
│   │   ├── LeaveTypeForm.jsx
│   │   └── BalanceAdjustmentModal.jsx
├── hooks/
│   ├── useLeave.js              (leave API queries)
│   └── useLeaveBalances.js      (balance calculations)
```

---

## Integration Points

### With Module 4 (Attendance)

1. **Shared `department_approvers` table:** Leave uses type='leave', OT uses type='overtime'
2. **Attendance sync:** Approved leave auto-creates `attendance_logs` with status='on_leave'
3. **Auto-absent job:** Checks leave_requests before marking absent
4. **OT Replacement Leave:** Deducts from `overtime_requests.replacement_hours_used`

### With Module 1 (Employee Directory)

1. **Employee relationship:** All leave records tied to employee_id
2. **Employment type:** Determines entitlement rules
3. **Join date:** Calculates service years for tiered entitlements
4. **Department:** Used for approval routing and calendar filtering

### Future: With Module 5 (Payroll)

1. **Unpaid leave:** Deducted from salary calculation
2. **Leave encashment:** Convert unused leave to payment on resignation
