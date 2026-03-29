# Module 4: Attendance & Time Tracking — Detailed Design

**Date:** 2026-03-27
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 2 (Day-to-Day Operations)

---

## Key Decisions

- **Work Schedules:** Configurable per employee/group (fixed, flexible, shift types)
- **Clock Method:** Web-based with selfie capture (camera photo on clock in/out)
- **PWA:** Full PWA with install prompt, push notifications, offline clock-in support
- **Late Policy:** Auto-flag with configurable grace period + penalty tracking (count + minutes)
- **Overtime:** Request-based with approval workflow. OT is NOT paid — earns replacement hours (compensatory time-off)
- **WFH:** Supported as an attendance type, no selfie required
- **Approvers:** Configurable PIC per department (for OT, extensible to leave/claims)
- **Holidays:** Configurable calendar with national + state-level Malaysian holidays
- **Analytics:** Detailed — trends, department comparison, punctuality scores, penalty reports

---

## Data Models

### `work_schedules` — Schedule templates

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Office Hours", "Flexible", "Morning Shift" |
| type | enum | fixed, flexible, shift |
| start_time | time | e.g., 09:00 (nullable for flexible) |
| end_time | time | e.g., 18:00 (nullable for flexible) |
| break_duration_minutes | int | e.g., 60 (lunch break) |
| min_hours_per_day | decimal(4,1) | e.g., 8.0 (for flexible schedules) |
| grace_period_minutes | int | e.g., 10 (late grace period) |
| working_days | json | e.g., [1,2,3,4,5] (Mon=1, Sun=7) |
| is_default | boolean | Default schedule for new employees |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_schedules` — Schedule assignment per employee

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| work_schedule_id | FK → work_schedules | |
| effective_from | date | When this schedule starts |
| effective_to | date | nullable (null = ongoing) |
| custom_start_time | time | nullable, override schedule default |
| custom_end_time | time | nullable, override schedule default |
| created_at | timestamp | |
| updated_at | timestamp | |

### `attendance_logs` — Daily clock in/out records

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| date | date | Attendance date |
| clock_in | datetime | nullable |
| clock_out | datetime | nullable |
| clock_in_photo | string | Selfie file path (nullable) |
| clock_out_photo | string | Selfie file path (nullable) |
| clock_in_ip | string | nullable |
| clock_out_ip | string | nullable |
| status | enum | present, absent, late, half_day, on_leave, holiday, wfh |
| late_minutes | int | default 0 |
| early_leave_minutes | int | default 0 |
| total_work_minutes | int | Calculated on clock out, default 0 |
| is_overtime | boolean | default false |
| remarks | text | nullable |
| approved_by | FK → users | nullable (for manual adjustments) |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `employee_id + date`

### `overtime_requests` — OT request & approval

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| requested_date | date | Date of OT |
| start_time | time | Planned OT start |
| end_time | time | Planned OT end |
| estimated_hours | decimal(4,1) | |
| actual_hours | decimal(4,1) | nullable, filled after OT completed |
| reason | text | Why OT is needed |
| status | enum | pending, approved, rejected, completed, cancelled |
| approved_by | FK → users | nullable |
| approved_at | datetime | nullable |
| rejection_reason | text | nullable |
| replacement_hours_earned | decimal(5,1) | Hours earned as time-off (nullable) |
| replacement_hours_used | decimal(5,1) | Hours already claimed (default 0) |
| created_at | timestamp | |
| updated_at | timestamp | |

### `holidays` — Public holiday calendar

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Hari Raya Aidilfitri" |
| date | date | |
| type | enum | national, state |
| states | json | nullable. e.g., ["selangor","kl"] for state holidays. Null = all states |
| year | int | For easy year-based queries |
| is_recurring | boolean | Same date every year? |
| created_at | timestamp | |
| updated_at | timestamp | |

### `department_approvers` — Configurable approvers per department

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| department_id | FK → departments | |
| approver_employee_id | FK → employees | The PIC |
| approval_type | enum | overtime, leave, claims |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `department_id + approval_type` (one approver per type per department)

### `attendance_penalties` — Late/absence penalty tracking

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| attendance_log_id | FK → attendance_logs | |
| penalty_type | enum | late_arrival, early_departure, absent_without_leave |
| penalty_minutes | int | |
| month | int | 1-12 |
| year | int | |
| notes | text | nullable |
| created_at | timestamp | |

---

## Screens

### Admin Pages (8)

#### 1. Attendance Dashboard (`/hr/attendance`)

**Layout:** Stats cards + charts + today's live log

**Stats Cards (today):**
- Present count (green)
- Late count (yellow)
- Absent count (red)
- WFH count (blue)
- On Leave count (purple)
- Holiday indicator

**Charts:**
- Attendance rate gauge (last 30 days)
- Late arrivals trend (line chart, last 30 days)
- Department attendance comparison (bar chart)

**Today's Log:**
- Real-time table: Selfie thumbnail | Name | Department | Clock In | Clock Out | Status
- Auto-refresh every 60 seconds
- Quick filter: All / Present / Late / Absent

#### 2. Attendance Records (`/hr/attendance/records`)

- Full paginated table of all attendance logs
- Filters: Date range picker, Department, Employee search, Status
- Columns: Date | Employee (with photo) | Department | Clock In | Clock Out | Total Hours | Status | Late Min | Actions
- Click row → modal with detail: selfie photos (in/out), IP, remarks, schedule info
- Admin manual adjustment: Edit clock in/out time, change status, add remarks
- Export CSV button

#### 3. Work Schedules (`/hr/attendance/schedules`)

- Schedule list table: Name | Type | Hours | Break | Grace Period | Working Days | Employees Count | Default | Actions
- "+ Create Schedule" button → modal form
- Edit/Delete actions
- "Set as Default" action
- Click schedule → see assigned employees

#### 4. Schedule Assignments (`/hr/attendance/assignments`)

- Table: Employee | Department | Current Schedule | Custom Hours | Effective From | Actions
- Filter by department, schedule
- Bulk assign: Select employees → choose schedule → set effective date
- Individual edit: custom start/end time override

#### 5. Overtime Management (`/hr/attendance/overtime`)

- Tabs: All | Pending | Approved | Completed | Rejected
- Table: Employee | Date | Planned Hours | Actual Hours | Reason | Status | Replacement Hours | Actions
- Pending tab: Approve/Reject buttons with optional reason
- Completed: Shows actual hours + replacement hours earned
- Replacement Hours Summary: Total earned vs used per employee

#### 6. Holiday Calendar (`/hr/attendance/holidays`)

- Toggle: Calendar view (month grid) / List view (table)
- Calendar view: Days with holidays highlighted, hover for details
- List view: Date | Holiday Name | Type (National/State) | States | Recurring | Actions
- "+ Add Holiday" button → modal form
- "Import Holidays" button → bulk import Malaysian holidays for selected year
- Year selector dropdown

#### 7. Attendance Analytics (`/hr/attendance/analytics`)

- Date range filter + Department filter
- **Attendance Rate by Department** — horizontal bar chart
- **Monthly Attendance Trend** — line chart (12 months)
- **Top Late Employees** — table: Employee | Late Count | Total Late Minutes | Avg Late Minutes
- **Absence Pattern** — heatmap (day of week × week number)
- **Punctuality Ranking** — employee list sorted by on-time %
- **OT Summary** — department OT hours, replacement hours balance
- **Penalty Report** — employees flagged (3+ lates/month threshold)

#### 8. Department Approvers (`/hr/attendance/approvers`)

- Table: Department | OT Approver | Leave Approver | Claims Approver | Actions
- Edit: Select employee as approver per type per department
- Extensible for future modules (leave, claims)

### Employee Self-Service Pages (2)

#### 9. My Attendance (`/hr/my/attendance`)

- Current month calendar with color-coded days (green=present, yellow=late, red=absent, blue=wfh, purple=leave, gray=holiday)
- Monthly summary: Present, Late, Absent, WFH, On Leave counts
- My attendance log table (current month)
- My penalty summary: Total lates, total late minutes this month
- Link to "Clock In/Out" page

#### 10. My Overtime (`/hr/my/overtime`)

- My OT requests list with status
- "+ New OT Request" button → form (date, time, hours, reason)
- My replacement hours balance: Earned | Used | Available
- Request detail: Shows approval status, actual hours, replacement hours

### Shared Page

#### Clock In/Out Page (`/hr/clock`)

- **Mobile-first design** (PWA primary use case)
- Greeting: "Good Morning, {name}" + current date/time
- Camera preview (live video feed)
- Big clock in button (green) / clock out button (red)
- WFH toggle switch (skips selfie when ON)
- Today's record: Clock in time, Clock out time, Total hours
- This week summary: Mon-Fri with status icons
- Current schedule display: "Your schedule: 9:00 AM - 6:00 PM"
- Status indicator: "Not clocked in" / "Clocked in at 9:05 AM" / "Completed"

---

## Workflows

### Clock In Flow

```
Employee opens clock-in page (web/PWA)
  → Camera activates (getUserMedia API)
  → Employee taps "Clock In"
  → System captures selfie from camera feed
  → If WFH toggle is ON:
    → Skip selfie, mark status as "wfh"
  → API call: POST /api/hr/me/attendance/clock-in
    → Payload: { photo: base64/file, is_wfh: bool }
  → Backend:
    1. Check if already clocked in today → reject if yes
    2. Check if today is a holiday → warn but allow
    3. Get employee's current schedule
    4. Save attendance_log: clock_in, photo, IP
    5. Calculate status:
       - clock_in <= schedule.start_time + grace_period → "present"
       - clock_in > schedule.start_time + grace_period → "late"
       - Calculate late_minutes
    6. If late → create AttendancePenalty record
    7. Return confirmation
  → Show: "Clocked in at 9:05 AM ✓" (or "Late by 5 minutes ⚠")
```

### Clock Out Flow

```
Employee taps "Clock Out"
  → Camera activates → capture selfie
  → API call: POST /api/hr/me/attendance/clock-out
    → Payload: { photo: base64/file }
  → Backend:
    1. Find today's attendance_log → reject if not clocked in
    2. Save: clock_out, photo, IP
    3. Calculate total_work_minutes
    4. Check early departure:
       - clock_out < schedule.end_time → early_leave_minutes
       - If early → create AttendancePenalty record
    5. Return summary
  → Show: "Clocked out at 6:10 PM — Total: 8h 5m ✓"
```

### Auto-Absent Marking (Scheduled Job)

```
Laravel scheduled command: daily at 11:59 PM
  → Get all active employees with schedules for today (check working_days)
  → For each employee:
    - Skip if today is a holiday (check holidays table + employee's state)
    - Skip if employee has approved leave for today (future Leave module integration)
    - If no attendance_log exists for today:
      → Create attendance_log with status: "absent"
      → Create AttendancePenalty: absent_without_leave
  → Log results for HR review
```

### OT Request & Approval Flow

```
Employee submits OT request:
  → POST /api/hr/me/overtime
  → Payload: { requested_date, start_time, end_time, estimated_hours, reason }
  → Backend:
    1. Validate: date is in future, no duplicate request
    2. Find department approver from department_approvers (type: overtime)
    3. Create overtime_request with status: "pending"
    4. Send push notification to PIC
  → Employee sees: "OT request submitted, pending approval"

PIC reviews request:
  → PATCH /api/hr/overtime/{id}/approve  OR  /reject
  → Approve:
    1. Update status: "approved"
    2. Set approved_by, approved_at
    3. Notify employee (push notification)
  → Reject:
    1. Update status: "rejected"
    2. Set rejection_reason
    3. Notify employee

After OT is completed:
  → HR/PIC marks as completed: PATCH /api/hr/overtime/{id}/complete
  → Backend:
    1. Look up attendance_log for that date
    2. Calculate actual_hours from clock in/out times (beyond schedule)
    3. Set replacement_hours_earned = actual_hours
    4. Update status: "completed"
    5. Notify employee of replacement hours earned

Replacement Hours Usage:
  → When Leave module is built:
    - Employee can request "Replacement Leave" using earned hours
    - System deducts from replacement_hours_used
    - Balance = earned - used
```

### Penalty Tracking Flow

```
Automatic (on each clock in/out):
  → Late arrival: Create penalty record (late_arrival, X minutes)
  → Early departure: Create penalty record (early_departure, X minutes)
  → Absent: Create penalty record (absent_without_leave)

Monthly Summary (scheduled job, 1st of each month):
  → For each employee, calculate previous month:
    - Total late count
    - Total late minutes
    - Total early departures
    - Total absences without leave
  → Flag employees exceeding threshold (configurable, default: 3+ lates)
  → Create notification for HR Admin with flagged employees list

HR Review:
  → GET /api/hr/penalties/flagged
  → Shows flagged employees with details
  → HR can add notes, take action, or dismiss
```

---

## API Endpoints

### Attendance Logs (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/attendance` | List logs (filter: date range, department, employee, status) |
| GET | `/api/hr/attendance/today` | Today's attendance for all employees |
| GET | `/api/hr/attendance/{id}` | Single attendance detail with photos |
| PUT | `/api/hr/attendance/{id}` | Admin manual adjustment (edit times, status) |
| GET | `/api/hr/attendance/export` | Export CSV |

### Employee Self-Service — Attendance

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/me/attendance` | My attendance records (filter: month/year) |
| POST | `/api/hr/me/attendance/clock-in` | Clock in with selfie |
| POST | `/api/hr/me/attendance/clock-out` | Clock out with selfie |
| GET | `/api/hr/me/attendance/today` | My today's status |
| GET | `/api/hr/me/attendance/summary` | My monthly summary stats |

### Work Schedules

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/schedules` | List all schedules |
| POST | `/api/hr/schedules` | Create schedule |
| GET | `/api/hr/schedules/{id}` | Schedule detail |
| PUT | `/api/hr/schedules/{id}` | Update schedule |
| DELETE | `/api/hr/schedules/{id}` | Delete schedule |
| GET | `/api/hr/schedules/{id}/employees` | Employees on this schedule |

### Employee Schedule Assignments

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/employee-schedules` | List all assignments |
| POST | `/api/hr/employee-schedules` | Assign schedule (supports bulk) |
| PUT | `/api/hr/employee-schedules/{id}` | Update assignment |
| DELETE | `/api/hr/employee-schedules/{id}` | Remove assignment |

### Overtime (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/overtime` | List all OT requests (filter: status, department, date) |
| GET | `/api/hr/overtime/{id}` | OT request detail |
| PATCH | `/api/hr/overtime/{id}/approve` | Approve OT request |
| PATCH | `/api/hr/overtime/{id}/reject` | Reject OT request |
| PATCH | `/api/hr/overtime/{id}/complete` | Mark OT completed + calculate replacement hours |

### Employee Self-Service — Overtime

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/me/overtime` | My OT requests |
| POST | `/api/hr/me/overtime` | Submit OT request |
| GET | `/api/hr/me/overtime/balance` | My replacement hours balance |
| DELETE | `/api/hr/me/overtime/{id}` | Cancel pending OT request |

### Holidays

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/holidays` | List holidays (filter: year, type) |
| POST | `/api/hr/holidays` | Create holiday |
| PUT | `/api/hr/holidays/{id}` | Update holiday |
| DELETE | `/api/hr/holidays/{id}` | Delete holiday |
| POST | `/api/hr/holidays/bulk-import` | Bulk import Malaysian holidays for year |

### Department Approvers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/department-approvers` | List all approvers |
| POST | `/api/hr/department-approvers` | Set approver |
| PUT | `/api/hr/department-approvers/{id}` | Update approver |
| DELETE | `/api/hr/department-approvers/{id}` | Remove approver |

### Penalties

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/penalties` | List penalties (filter: employee, month, year, type) |
| GET | `/api/hr/penalties/flagged` | Employees exceeding penalty threshold |
| GET | `/api/hr/penalties/summary` | Monthly penalty summary by department |

### Analytics

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/attendance/analytics/overview` | Attendance rate, today's stats |
| GET | `/api/hr/attendance/analytics/trends` | Monthly attendance trend (12 months) |
| GET | `/api/hr/attendance/analytics/department` | Department comparison |
| GET | `/api/hr/attendance/analytics/punctuality` | Employee punctuality ranking |
| GET | `/api/hr/attendance/analytics/penalties` | Penalty summary report |
| GET | `/api/hr/attendance/analytics/overtime` | OT summary + replacement hours |
| GET | `/api/hr/attendance/analytics/absence-pattern` | Absence heatmap data |

---

## Scheduled Jobs (Laravel Commands)

| Command | Schedule | Description |
|---------|----------|-------------|
| `hr:mark-absent` | Daily 11:59 PM | Auto-mark absent for employees who didn't clock in |
| `hr:penalty-summary` | Monthly 1st, 8:00 AM | Generate monthly penalty report + flag employees |
| `hr:clock-in-reminder` | Daily (configurable) | Push notification reminder to clock in |

---

## PWA Configuration

### Service Worker
- Cache static assets (JS, CSS, images, fonts)
- Cache clock-in page for offline access
- Background sync: Queue clock-in/out requests when offline, sync when online

### Web App Manifest
```json
{
  "name": "Mudeer HR",
  "short_name": "HR",
  "start_url": "/hr/clock",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#1e40af",
  "icons": [
    { "src": "/icons/hr-192.png", "sizes": "192x192" },
    { "src": "/icons/hr-512.png", "sizes": "512x512" }
  ]
}
```

### Install Prompt
- Show "Add to Home Screen" banner on first mobile visit
- Custom install button in the clock-in page header

### Push Notifications
- **Clock-in reminder:** Configurable time (e.g., 8:50 AM on working days)
- **OT request updates:** Approved/rejected notifications
- **Penalty warnings:** When threshold exceeded
- Uses Web Push API + Laravel notification channels

### Camera API
- `navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })` for front camera
- Canvas capture for selfie snapshot
- Compress image before upload (max 500KB)
- Fallback: File input for devices without camera API support

### Offline Clock-In
- Clock-in page cached by service worker
- If offline: save clock-in data to IndexedDB
- When online: Background Sync API sends queued clock-ins
- Show "Offline mode" indicator to user

---

## Validation Rules

### Clock In
- Must be an active employee
- Must not have already clocked in today
- Photo: required (unless WFH), image, max 2MB
- is_wfh: boolean

### Clock Out
- Must have clocked in today
- Must not have already clocked out
- Photo: required (unless WFH), image, max 2MB

### Work Schedule
- name: required, string, max:255
- type: required, in:fixed,flexible,shift
- start_time: required_if:type,fixed,shift, date_format:H:i
- end_time: required_if:type,fixed,shift, date_format:H:i, after:start_time
- break_duration_minutes: required, integer, min:0, max:120
- min_hours_per_day: required_if:type,flexible, numeric, min:1, max:24
- grace_period_minutes: required, integer, min:0, max:60
- working_days: required, array, min:1 (values 1-7)

### OT Request
- requested_date: required, date, after_or_equal:today
- start_time: required, date_format:H:i
- end_time: required, date_format:H:i, after:start_time
- estimated_hours: required, numeric, min:0.5, max:12
- reason: required, string, min:10, max:500

### Holiday
- name: required, string, max:255
- date: required, date
- type: required, in:national,state
- states: required_if:type,state, array
- year: required, integer, min:2020, max:2050
- is_recurring: boolean

### Department Approver
- department_id: required, exists:departments,id
- approver_employee_id: required, exists:employees,id
- approval_type: required, in:overtime,leave,claims

---

## Malaysian Holiday Reference (2026)

For bulk import feature:

| Date | Holiday | Type |
|------|---------|------|
| 1 Jan | New Year's Day | National |
| 1 Feb | Federal Territory Day | State (KL, Putrajaya, Labuan) |
| 29 Jan | Thaipusam | National |
| 1 Feb | Israk & Mikraj | National |
| 1 May | Labour Day | National |
| 7 Jun | Agong's Birthday | National |
| 20 Mar | Nuzul Al-Quran | National |
| 31 Mar - 1 Apr | Hari Raya Aidilfitri | National |
| 7 Jun | Hari Raya Haji | National |
| 28 Jun | Awal Muharram | National |
| 31 Aug | Merdeka Day | National |
| 16 Sep | Malaysia Day | National |
| 6 Sep | Maulidur Rasul | National |
| 27 Oct | Deepavali | National |
| 25 Dec | Christmas Day | National |

*(Dates are approximate — Islamic holidays follow lunar calendar)*

---

## React File Structure

```
resources/js/hr/
├── pages/
│   ├── attendance/
│   │   ├── AttendanceDashboard.jsx
│   │   ├── AttendanceRecords.jsx
│   │   ├── WorkSchedules.jsx
│   │   ├── ScheduleAssignments.jsx
│   │   ├── OvertimeManagement.jsx
│   │   ├── HolidayCalendar.jsx
│   │   ├── AttendanceAnalytics.jsx
│   │   └── DepartmentApprovers.jsx
│   ├── my/
│   │   ├── MyAttendance.jsx
│   │   └── MyOvertime.jsx
│   └── ClockInOut.jsx          (shared clock page)
├── components/
│   ├── attendance/
│   │   ├── CameraCapture.jsx   (selfie camera component)
│   │   ├── ClockButton.jsx     (big clock in/out button)
│   │   ├── WeekSummary.jsx     (weekly attendance strip)
│   │   ├── AttendanceCalendar.jsx (monthly calendar view)
│   │   ├── OvertimeRequestForm.jsx
│   │   └── HolidayForm.jsx
│   └── charts/
│       ├── AttendanceGauge.jsx
│       ├── LateTrend.jsx
│       ├── DepartmentChart.jsx
│       └── AbsenceHeatmap.jsx
├── hooks/
│   ├── useCamera.js            (camera access hook)
│   ├── useAttendance.js        (attendance API queries)
│   └── useOvertime.js          (overtime API queries)
└── sw.js                       (service worker)
```
