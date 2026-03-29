# HR Phase 2: Attendance & Leave Management — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build Module 3 (Leave Management) and Module 4 (Attendance & Time Tracking) as an integrated Phase 2 for the HR system.

**Architecture:** Laravel API controllers + React SPA pages, following existing Phase 1 patterns. Shared infrastructure (department_approvers, holidays) built first. Attendance and Leave modules share the holiday calendar and approver system. Leave approval auto-syncs with attendance logs.

**Tech Stack:** Laravel 12, Pest tests, React 19, Shadcn/ui, TanStack Query, Recharts, PWA (Service Worker + Web Push)

**Design Docs:**
- [Module 3 - Leave Management](2026-03-27-hr-module3-leave-management-design.md)
- [Module 4 - Attendance](2026-03-27-hr-module4-attendance-design.md)

---

## Build Order Overview

The build follows this dependency chain:

```
Task 1-3:   Shared Infrastructure (holidays, department_approvers, work_schedules)
Task 4-6:   Attendance Backend (models, controllers, scheduled jobs)
Task 7-8:   Attendance Tests
Task 9-11:  Leave Backend (models, controllers, scheduled jobs)
Task 12-13: Leave Tests
Task 14:    API Routes (all at once)
Task 15-16: React API Client + Shared Components
Task 17-20: Attendance React Pages
Task 21-24: Leave React Pages
Task 25-26: Employee Self-Service Pages
Task 27-28: PWA Setup
Task 29:    Seeder
Task 30:    Final Integration Test
```

---

## Task 1: Migration — Shared Tables (holidays, department_approvers)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_holidays_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_department_approvers_table.php`

**Step 1: Create holidays migration**

```bash
php artisan make:migration create_holidays_table --no-interaction
```

```php
Schema::create('holidays', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->date('date');
    $table->enum('type', ['national', 'state']);
    $table->json('states')->nullable();
    $table->integer('year')->index();
    $table->boolean('is_recurring')->default(false);
    $table->timestamps();

    $table->unique(['date', 'name']);
});
```

**Step 2: Create department_approvers migration**

```bash
php artisan make:migration create_department_approvers_table --no-interaction
```

```php
Schema::create('department_approvers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
    $table->foreignId('approver_employee_id')->constrained('employees')->cascadeOnDelete();
    $table->enum('approval_type', ['overtime', 'leave', 'claims']);
    $table->timestamps();

    $table->index(['department_id', 'approval_type']);
});
```

**Step 3: Run migrations**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add holidays and department_approvers migrations"
```

---

## Task 2: Migration — Attendance Tables

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_work_schedules_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_employee_schedules_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_attendance_logs_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_overtime_requests_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_attendance_penalties_table.php`

**Step 1: Create work_schedules migration**

```bash
php artisan make:migration create_work_schedules_table --no-interaction
```

```php
Schema::create('work_schedules', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('type', ['fixed', 'flexible', 'shift']);
    $table->time('start_time')->nullable();
    $table->time('end_time')->nullable();
    $table->integer('break_duration_minutes')->default(60);
    $table->decimal('min_hours_per_day', 4, 1)->default(8.0);
    $table->integer('grace_period_minutes')->default(10);
    $table->json('working_days')->default('[1,2,3,4,5]');
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

**Step 2: Create employee_schedules migration**

```bash
php artisan make:migration create_employee_schedules_table --no-interaction
```

```php
Schema::create('employee_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('work_schedule_id')->constrained('work_schedules')->cascadeOnDelete();
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->time('custom_start_time')->nullable();
    $table->time('custom_end_time')->nullable();
    $table->timestamps();

    $table->index(['employee_id', 'effective_from']);
});
```

**Step 3: Create attendance_logs migration**

```bash
php artisan make:migration create_attendance_logs_table --no-interaction
```

```php
Schema::create('attendance_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->date('date');
    $table->datetime('clock_in')->nullable();
    $table->datetime('clock_out')->nullable();
    $table->string('clock_in_photo')->nullable();
    $table->string('clock_out_photo')->nullable();
    $table->string('clock_in_ip')->nullable();
    $table->string('clock_out_ip')->nullable();
    $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'wfh']);
    $table->integer('late_minutes')->default(0);
    $table->integer('early_leave_minutes')->default(0);
    $table->integer('total_work_minutes')->default(0);
    $table->boolean('is_overtime')->default(false);
    $table->text('remarks')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['employee_id', 'date']);
    $table->index(['date', 'status']);
});
```

**Step 4: Create overtime_requests migration**

```bash
php artisan make:migration create_overtime_requests_table --no-interaction
```

```php
Schema::create('overtime_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->date('requested_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->decimal('estimated_hours', 4, 1);
    $table->decimal('actual_hours', 4, 1)->nullable();
    $table->text('reason');
    $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled']);
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->datetime('approved_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->decimal('replacement_hours_earned', 5, 1)->nullable();
    $table->decimal('replacement_hours_used', 5, 1)->default(0);
    $table->timestamps();

    $table->index(['employee_id', 'status']);
    $table->index('requested_date');
});
```

**Step 5: Create attendance_penalties migration**

```bash
php artisan make:migration create_attendance_penalties_table --no-interaction
```

```php
Schema::create('attendance_penalties', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('attendance_log_id')->constrained('attendance_logs')->cascadeOnDelete();
    $table->enum('penalty_type', ['late_arrival', 'early_departure', 'absent_without_leave']);
    $table->integer('penalty_minutes')->default(0);
    $table->integer('month');
    $table->integer('year');
    $table->text('notes')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['employee_id', 'year', 'month']);
});
```

**Step 6: Run migrations**

```bash
php artisan migrate
```

**Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add attendance module migrations (work_schedules, attendance_logs, overtime, penalties)"
```

---

## Task 3: Migration — Leave Tables

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_leave_types_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_leave_entitlements_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_leave_balances_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_leave_requests_table.php`

**Step 1: Create leave_types migration**

```bash
php artisan make:migration create_leave_types_table --no-interaction
```

```php
Schema::create('leave_types', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 10)->unique();
    $table->text('description')->nullable();
    $table->boolean('is_paid')->default(true);
    $table->boolean('is_attachment_required')->default(false);
    $table->boolean('is_system')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('max_consecutive_days')->nullable();
    $table->enum('gender_restriction', ['male', 'female'])->nullable();
    $table->string('color', 7)->default('#3B82F6');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 2: Create leave_entitlements migration**

```bash
php artisan make:migration create_leave_entitlements_table --no-interaction
```

```php
Schema::create('leave_entitlements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
    $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern', 'all']);
    $table->integer('min_service_months')->default(0);
    $table->integer('max_service_months')->nullable();
    $table->decimal('days_per_year', 4, 1);
    $table->boolean('is_prorated')->default(false);
    $table->integer('carry_forward_max')->default(0);
    $table->timestamps();

    $table->index(['leave_type_id', 'employment_type']);
});
```

**Step 3: Create leave_balances migration**

```bash
php artisan make:migration create_leave_balances_table --no-interaction
```

```php
Schema::create('leave_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
    $table->integer('year');
    $table->decimal('entitled_days', 4, 1)->default(0);
    $table->decimal('carried_forward_days', 4, 1)->default(0);
    $table->decimal('used_days', 4, 1)->default(0);
    $table->decimal('pending_days', 4, 1)->default(0);
    $table->decimal('available_days', 4, 1)->default(0);
    $table->timestamps();

    $table->unique(['employee_id', 'leave_type_id', 'year']);
});
```

**Step 4: Create leave_requests migration**

```bash
php artisan make:migration create_leave_requests_table --no-interaction
```

```php
Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date');
    $table->decimal('total_days', 3, 1);
    $table->boolean('is_half_day')->default(false);
    $table->enum('half_day_period', ['morning', 'afternoon'])->nullable();
    $table->text('reason');
    $table->string('attachment_path')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled']);
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->datetime('approved_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->boolean('is_replacement_leave')->default(false);
    $table->decimal('replacement_hours_deducted', 5, 1)->nullable();
    $table->timestamps();

    $table->index(['employee_id', 'status']);
    $table->index(['start_date', 'end_date']);
});
```

**Step 5: Run migrations**

```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add leave module migrations (leave_types, entitlements, balances, requests)"
```

---

## Task 4: Models — Shared + Attendance

**Files:**
- Create: `app/Models/Holiday.php`
- Create: `app/Models/DepartmentApprover.php`
- Create: `app/Models/WorkSchedule.php`
- Create: `app/Models/EmployeeSchedule.php`
- Create: `app/Models/AttendanceLog.php`
- Create: `app/Models/OvertimeRequest.php`
- Create: `app/Models/AttendancePenalty.php`

**Step 1: Create models using artisan**

```bash
php artisan make:model Holiday --no-interaction
php artisan make:model DepartmentApprover --no-interaction
php artisan make:model WorkSchedule --no-interaction
php artisan make:model EmployeeSchedule --no-interaction
php artisan make:model AttendanceLog --no-interaction
php artisan make:model OvertimeRequest --no-interaction
php artisan make:model AttendancePenalty --no-interaction
```

**Step 2: Implement each model**

Follow existing Employee model pattern:
- Define `$fillable` with all columns
- Define `$casts` for json/date/boolean fields
- Define relationships (belongsTo, hasMany)
- Add query scopes where useful
- Add accessor methods for computed values

Key relationships:
- `Holiday`: standalone, query scopes for `year()`, `national()`, `forState()`
- `DepartmentApprover`: belongsTo `Department`, belongsTo `Employee` (approver)
- `WorkSchedule`: hasMany `EmployeeSchedule`, scope `default()`
- `EmployeeSchedule`: belongsTo `Employee`, belongsTo `WorkSchedule`
- `AttendanceLog`: belongsTo `Employee`, hasMany `AttendancePenalty`, scopes for `forDate()`, `forEmployee()`, `status()`
- `OvertimeRequest`: belongsTo `Employee`, belongsTo `User` (approved_by), scopes for `pending()`, `approved()`, `completed()`
- `AttendancePenalty`: belongsTo `Employee`, belongsTo `AttendanceLog`

**Step 3: Add relationships to Employee model**

Modify: `app/Models/Employee.php` — add these relationships:

```php
public function schedules(): HasMany
{
    return $this->hasMany(EmployeeSchedule::class);
}

public function currentSchedule(): HasOne
{
    return $this->hasOne(EmployeeSchedule::class)
        ->where('effective_from', '<=', now())
        ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
        ->latest('effective_from');
}

public function attendanceLogs(): HasMany
{
    return $this->hasMany(AttendanceLog::class);
}

public function overtimeRequests(): HasMany
{
    return $this->hasMany(OvertimeRequest::class);
}

public function attendancePenalties(): HasMany
{
    return $this->hasMany(AttendancePenalty::class);
}
```

**Step 4: Commit**

```bash
git add app/Models/
git commit -m "feat(hr): add attendance and shared models with relationships"
```

---

## Task 5: Models — Leave

**Files:**
- Create: `app/Models/LeaveType.php`
- Create: `app/Models/LeaveEntitlement.php`
- Create: `app/Models/LeaveBalance.php`
- Create: `app/Models/LeaveRequest.php`

**Step 1: Create models**

```bash
php artisan make:model LeaveType --no-interaction
php artisan make:model LeaveEntitlement --no-interaction
php artisan make:model LeaveBalance --no-interaction
php artisan make:model LeaveRequest --no-interaction
```

**Step 2: Implement each model**

Key relationships:
- `LeaveType`: hasMany `LeaveEntitlement`, hasMany `LeaveBalance`, hasMany `LeaveRequest`, scope `active()`, `system()`
- `LeaveEntitlement`: belongsTo `LeaveType`
- `LeaveBalance`: belongsTo `Employee`, belongsTo `LeaveType`, scope `forYear()`, `forEmployee()`
- `LeaveRequest`: belongsTo `Employee`, belongsTo `LeaveType`, belongsTo `User` (approved_by), scopes `pending()`, `approved()`, `forDateRange()`

**Step 3: Add relationships to Employee model**

```php
public function leaveBalances(): HasMany
{
    return $this->hasMany(LeaveBalance::class);
}

public function leaveRequests(): HasMany
{
    return $this->hasMany(LeaveRequest::class);
}
```

**Step 4: Commit**

```bash
git add app/Models/
git commit -m "feat(hr): add leave models with relationships"
```

---

## Task 6: Factories

**Files:**
- Create: `database/factories/HolidayFactory.php`
- Create: `database/factories/DepartmentApproverFactory.php`
- Create: `database/factories/WorkScheduleFactory.php`
- Create: `database/factories/EmployeeScheduleFactory.php`
- Create: `database/factories/AttendanceLogFactory.php`
- Create: `database/factories/OvertimeRequestFactory.php`
- Create: `database/factories/AttendancePenaltyFactory.php`
- Create: `database/factories/LeaveTypeFactory.php`
- Create: `database/factories/LeaveEntitlementFactory.php`
- Create: `database/factories/LeaveBalanceFactory.php`
- Create: `database/factories/LeaveRequestFactory.php`

**Step 1: Create all factories**

```bash
php artisan make:factory HolidayFactory --no-interaction
php artisan make:factory DepartmentApproverFactory --no-interaction
php artisan make:factory WorkScheduleFactory --no-interaction
php artisan make:factory EmployeeScheduleFactory --no-interaction
php artisan make:factory AttendanceLogFactory --no-interaction
php artisan make:factory OvertimeRequestFactory --no-interaction
php artisan make:factory AttendancePenaltyFactory --no-interaction
php artisan make:factory LeaveTypeFactory --no-interaction
php artisan make:factory LeaveEntitlementFactory --no-interaction
php artisan make:factory LeaveBalanceFactory --no-interaction
php artisan make:factory LeaveRequestFactory --no-interaction
```

**Step 2: Implement factory definitions**

Follow existing `EmployeeFactory` pattern. Each factory should have:
- Realistic default values using Faker
- State methods for common scenarios (e.g., `AttendanceLogFactory::late()`, `OvertimeRequestFactory::approved()`, `LeaveRequestFactory::pending()`)
- Malaysian-specific data where relevant (holiday names, etc.)

**Step 3: Commit**

```bash
git add database/factories/
git commit -m "feat(hr): add factories for attendance and leave models"
```

---

## Task 7: Form Requests — Attendance

**Files:**
- Create: `app/Http/Requests/Hr/StoreWorkScheduleRequest.php`
- Create: `app/Http/Requests/Hr/UpdateWorkScheduleRequest.php`
- Create: `app/Http/Requests/Hr/ClockInRequest.php`
- Create: `app/Http/Requests/Hr/ClockOutRequest.php`
- Create: `app/Http/Requests/Hr/StoreOvertimeRequest.php`
- Create: `app/Http/Requests/Hr/ApproveOvertimeRequest.php`
- Create: `app/Http/Requests/Hr/StoreHolidayRequest.php`
- Create: `app/Http/Requests/Hr/StoreDepartmentApproverRequest.php`

**Step 1: Create form requests using artisan**

```bash
php artisan make:request Hr/StoreWorkScheduleRequest --no-interaction
php artisan make:request Hr/ClockInRequest --no-interaction
php artisan make:request Hr/ClockOutRequest --no-interaction
php artisan make:request Hr/StoreOvertimeRequest --no-interaction
php artisan make:request Hr/StoreHolidayRequest --no-interaction
php artisan make:request Hr/StoreDepartmentApproverRequest --no-interaction
```

**Step 2: Implement validation rules**

Follow existing `StoreEmployeeRequest` pattern. Key validations:

- `ClockInRequest`: photo (image, max:2048), is_wfh (boolean)
- `StoreWorkScheduleRequest`: name, type (in:fixed,flexible,shift), start_time/end_time (required_if:type,fixed), grace_period_minutes (integer, 0-60), working_days (array)
- `StoreOvertimeRequest`: requested_date (date, after_or_equal:today), start_time, end_time (after:start_time), estimated_hours (numeric, 0.5-12), reason (string, min:10)
- `StoreHolidayRequest`: name, date, type (in:national,state), states (required_if:type,state), year (integer), is_recurring (boolean)

**Step 3: Commit**

```bash
git add app/Http/Requests/Hr/
git commit -m "feat(hr): add form requests for attendance module"
```

---

## Task 8: Form Requests — Leave

**Files:**
- Create: `app/Http/Requests/Hr/StoreLeaveTypeRequest.php`
- Create: `app/Http/Requests/Hr/StoreLeaveEntitlementRequest.php`
- Create: `app/Http/Requests/Hr/ApplyLeaveRequest.php`

**Step 1: Create form requests**

```bash
php artisan make:request Hr/StoreLeaveTypeRequest --no-interaction
php artisan make:request Hr/StoreLeaveEntitlementRequest --no-interaction
php artisan make:request Hr/ApplyLeaveRequest --no-interaction
```

**Step 2: Implement validation rules**

- `ApplyLeaveRequest`: leave_type_id (exists), start_date (date, after_or_equal:today), end_date (after_or_equal:start_date), is_half_day (boolean), half_day_period (required_if:is_half_day,true), reason (string, min:5), attachment (file, max:5120, mimes:pdf,jpg,jpeg,png — required based on leave type)
- `StoreLeaveTypeRequest`: name, code (unique), color (regex hex), gender_restriction (nullable, in:male,female)
- `StoreLeaveEntitlementRequest`: leave_type_id (exists), employment_type (in:full_time,...,all), days_per_year (numeric), carry_forward_max (integer, min:0)

**Step 3: Commit**

```bash
git add app/Http/Requests/Hr/
git commit -m "feat(hr): add form requests for leave module"
```

---

## Task 9: Controllers — Attendance

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrAttendanceController.php`
- Create: `app/Http/Controllers/Api/Hr/HrWorkScheduleController.php`
- Create: `app/Http/Controllers/Api/Hr/HrOvertimeController.php`
- Create: `app/Http/Controllers/Api/Hr/HrHolidayController.php`
- Create: `app/Http/Controllers/Api/Hr/HrDepartmentApproverController.php`
- Create: `app/Http/Controllers/Api/Hr/HrAttendancePenaltyController.php`
- Create: `app/Http/Controllers/Api/Hr/HrAttendanceAnalyticsController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyAttendanceController.php`

**Step 1: Create controllers**

Follow `HrEmployeeController` pattern exactly:
- Namespace: `App\Http\Controllers\Api\Hr`
- Extends `Controller`
- Returns `JsonResponse`
- Uses `DB::transaction()` for writes
- Uses Form Requests for validation

**Step 2: Implement key controllers**

`HrAttendanceController`: index (list with filters), show, update (admin manual adjustment), today (today's attendance), export
`HrWorkScheduleController`: CRUD + employees (list assigned employees)
`HrOvertimeController`: index (filter by status/dept), show, approve, reject, complete
`HrHolidayController`: CRUD + bulkImport (Malaysian holidays)
`HrMyAttendanceController`: clockIn, clockOut, today, summary, myAttendance
- Clock in: validate selfie, check schedule, calculate late, create penalty if late
- Clock out: validate selfie, calculate total_work_minutes, check early departure

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add attendance controllers"
```

---

## Task 10: Controllers — Leave

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrLeaveRequestController.php`
- Create: `app/Http/Controllers/Api/Hr/HrLeaveTypeController.php`
- Create: `app/Http/Controllers/Api/Hr/HrLeaveEntitlementController.php`
- Create: `app/Http/Controllers/Api/Hr/HrLeaveBalanceController.php`
- Create: `app/Http/Controllers/Api/Hr/HrLeaveCalendarController.php`
- Create: `app/Http/Controllers/Api/Hr/HrLeaveDashboardController.php`
- Create: `app/Http/Controllers/Api/Hr/HrMyLeaveController.php`

**Step 1: Create and implement controllers**

Key logic in `HrLeaveRequestController::approve()`:
1. Update leave_request status
2. Update leave_balance: used_days += total_days, pending_days -= total_days
3. If replacement leave: deduct from overtime_requests.replacement_hours_used
4. Create attendance_logs with status 'on_leave' for each working day in range (skip weekends via work_schedule, skip holidays)

Key logic in `HrMyLeaveController::apply()`:
1. Validate balance sufficiency
2. Check gender restriction
3. Calculate working days (exclude weekends + holidays)
4. Create leave_request
5. Update leave_balance: pending_days += total_days

Key logic in `HrLeaveBalanceController::initialize()`:
1. For each active employee + each active leave type
2. Find matching entitlement rule (employment_type + service months)
3. Calculate entitled_days (pro-rate if mid-year joiner)
4. Calculate carry forward from previous year
5. Create leave_balance records

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/Hr/
git commit -m "feat(hr): add leave controllers"
```

---

## Task 11: Scheduled Commands

**Files:**
- Create: `app/Console/Commands/HrMarkAbsent.php`
- Create: `app/Console/Commands/HrPenaltySummary.php`
- Create: `app/Console/Commands/HrInitializeLeaveBalances.php`
- Modify: `routes/console.php` (add schedules)

**Step 1: Create commands**

```bash
php artisan make:command HrMarkAbsent --no-interaction
php artisan make:command HrPenaltySummary --no-interaction
php artisan make:command HrInitializeLeaveBalances --no-interaction
```

**Step 2: Implement HrMarkAbsent**

- Signature: `hr:mark-absent`
- Runs daily at 11:59 PM
- For each active employee with a work schedule for today (check working_days):
  - Skip if today is a holiday
  - Skip if employee has approved leave for today (check leave_requests)
  - If no attendance_log exists: create with status 'absent', create penalty

**Step 3: Implement HrPenaltySummary**

- Signature: `hr:penalty-summary`
- Runs 1st of each month at 8:00 AM
- Calculate previous month's penalties per employee
- Flag employees with 3+ late arrivals

**Step 4: Implement HrInitializeLeaveBalances**

- Signature: `hr:initialize-leave-balances {--year=}`
- Can be run manually or scheduled Jan 1st
- For each employee: match entitlement rules, calculate carry forward, create balance records

**Step 5: Register schedules in routes/console.php**

```php
Schedule::command('hr:mark-absent')->dailyAt('23:59');
Schedule::command('hr:penalty-summary')->monthlyOn(1, '08:00');
Schedule::command('hr:initialize-leave-balances')->yearlyOn(1, 1, '00:01');
```

**Step 6: Commit**

```bash
git add app/Console/Commands/ routes/console.php
git commit -m "feat(hr): add scheduled commands (mark-absent, penalty-summary, leave-balances)"
```

---

## Task 12: API Routes

**Files:**
- Modify: `routes/api.php` — add routes inside existing HR group

**Step 1: Add all Phase 2 routes**

Add inside the existing `Route::middleware(['auth:sanctum', 'role:admin,employee'])->prefix('hr')->group()`:

```php
// ========== Attendance Module ==========

// Work Schedules
Route::apiResource('schedules', HrWorkScheduleController::class)->names('api.hr.schedules');
Route::get('schedules/{schedule}/employees', [HrWorkScheduleController::class, 'employees'])->name('api.hr.schedules.employees');

// Employee Schedule Assignments
Route::get('employee-schedules', [HrEmployeeScheduleController::class, 'index'])->name('api.hr.employee-schedules.index');
Route::post('employee-schedules', [HrEmployeeScheduleController::class, 'store'])->name('api.hr.employee-schedules.store');
Route::put('employee-schedules/{employeeSchedule}', [HrEmployeeScheduleController::class, 'update'])->name('api.hr.employee-schedules.update');
Route::delete('employee-schedules/{employeeSchedule}', [HrEmployeeScheduleController::class, 'destroy'])->name('api.hr.employee-schedules.destroy');

// Attendance Logs (Admin)
Route::get('attendance', [HrAttendanceController::class, 'index'])->name('api.hr.attendance.index');
Route::get('attendance/today', [HrAttendanceController::class, 'today'])->name('api.hr.attendance.today');
Route::get('attendance/export', [HrAttendanceController::class, 'export'])->name('api.hr.attendance.export');
Route::get('attendance/{attendanceLog}', [HrAttendanceController::class, 'show'])->name('api.hr.attendance.show');
Route::put('attendance/{attendanceLog}', [HrAttendanceController::class, 'update'])->name('api.hr.attendance.update');

// Overtime (Admin)
Route::get('overtime', [HrOvertimeController::class, 'index'])->name('api.hr.overtime.index');
Route::get('overtime/{overtimeRequest}', [HrOvertimeController::class, 'show'])->name('api.hr.overtime.show');
Route::patch('overtime/{overtimeRequest}/approve', [HrOvertimeController::class, 'approve'])->name('api.hr.overtime.approve');
Route::patch('overtime/{overtimeRequest}/reject', [HrOvertimeController::class, 'reject'])->name('api.hr.overtime.reject');
Route::patch('overtime/{overtimeRequest}/complete', [HrOvertimeController::class, 'complete'])->name('api.hr.overtime.complete');

// Holidays
Route::apiResource('holidays', HrHolidayController::class)->names('api.hr.holidays');
Route::post('holidays/bulk-import', [HrHolidayController::class, 'bulkImport'])->name('api.hr.holidays.bulk-import');

// Department Approvers
Route::apiResource('department-approvers', HrDepartmentApproverController::class)->names('api.hr.department-approvers');

// Penalties
Route::get('penalties', [HrAttendancePenaltyController::class, 'index'])->name('api.hr.penalties.index');
Route::get('penalties/flagged', [HrAttendancePenaltyController::class, 'flagged'])->name('api.hr.penalties.flagged');
Route::get('penalties/summary', [HrAttendancePenaltyController::class, 'summary'])->name('api.hr.penalties.summary');

// Attendance Analytics
Route::get('attendance/analytics/overview', [HrAttendanceAnalyticsController::class, 'overview'])->name('api.hr.attendance.analytics.overview');
Route::get('attendance/analytics/trends', [HrAttendanceAnalyticsController::class, 'trends'])->name('api.hr.attendance.analytics.trends');
Route::get('attendance/analytics/department', [HrAttendanceAnalyticsController::class, 'department'])->name('api.hr.attendance.analytics.department');
Route::get('attendance/analytics/punctuality', [HrAttendanceAnalyticsController::class, 'punctuality'])->name('api.hr.attendance.analytics.punctuality');
Route::get('attendance/analytics/overtime', [HrAttendanceAnalyticsController::class, 'overtime'])->name('api.hr.attendance.analytics.overtime');

// Employee Self-Service — Attendance
Route::get('me/attendance', [HrMyAttendanceController::class, 'index'])->name('api.hr.me.attendance');
Route::post('me/attendance/clock-in', [HrMyAttendanceController::class, 'clockIn'])->name('api.hr.me.attendance.clock-in');
Route::post('me/attendance/clock-out', [HrMyAttendanceController::class, 'clockOut'])->name('api.hr.me.attendance.clock-out');
Route::get('me/attendance/today', [HrMyAttendanceController::class, 'today'])->name('api.hr.me.attendance.today');
Route::get('me/attendance/summary', [HrMyAttendanceController::class, 'summary'])->name('api.hr.me.attendance.summary');
Route::get('me/overtime', [HrMyAttendanceController::class, 'myOvertime'])->name('api.hr.me.overtime.index');
Route::post('me/overtime', [HrMyAttendanceController::class, 'submitOvertime'])->name('api.hr.me.overtime.store');
Route::get('me/overtime/balance', [HrMyAttendanceController::class, 'overtimeBalance'])->name('api.hr.me.overtime.balance');
Route::delete('me/overtime/{overtimeRequest}', [HrMyAttendanceController::class, 'cancelOvertime'])->name('api.hr.me.overtime.cancel');

// ========== Leave Module ==========

// Leave Types
Route::apiResource('leave/types', HrLeaveTypeController::class)->names('api.hr.leave.types');

// Leave Entitlements
Route::get('leave/entitlements', [HrLeaveEntitlementController::class, 'index'])->name('api.hr.leave.entitlements.index');
Route::post('leave/entitlements', [HrLeaveEntitlementController::class, 'store'])->name('api.hr.leave.entitlements.store');
Route::put('leave/entitlements/{leaveEntitlement}', [HrLeaveEntitlementController::class, 'update'])->name('api.hr.leave.entitlements.update');
Route::delete('leave/entitlements/{leaveEntitlement}', [HrLeaveEntitlementController::class, 'destroy'])->name('api.hr.leave.entitlements.destroy');
Route::post('leave/entitlements/recalculate', [HrLeaveEntitlementController::class, 'recalculate'])->name('api.hr.leave.entitlements.recalculate');

// Leave Balances
Route::get('leave/balances', [HrLeaveBalanceController::class, 'index'])->name('api.hr.leave.balances.index');
Route::get('leave/balances/{employeeId}', [HrLeaveBalanceController::class, 'show'])->name('api.hr.leave.balances.show');
Route::post('leave/balances/initialize', [HrLeaveBalanceController::class, 'initialize'])->name('api.hr.leave.balances.initialize');
Route::put('leave/balances/{leaveBalance}/adjust', [HrLeaveBalanceController::class, 'adjust'])->name('api.hr.leave.balances.adjust');
Route::get('leave/balances/export', [HrLeaveBalanceController::class, 'export'])->name('api.hr.leave.balances.export');

// Leave Requests (Admin)
Route::get('leave/requests', [HrLeaveRequestController::class, 'index'])->name('api.hr.leave.requests.index');
Route::get('leave/requests/export', [HrLeaveRequestController::class, 'export'])->name('api.hr.leave.requests.export');
Route::get('leave/requests/{leaveRequest}', [HrLeaveRequestController::class, 'show'])->name('api.hr.leave.requests.show');
Route::patch('leave/requests/{leaveRequest}/approve', [HrLeaveRequestController::class, 'approve'])->name('api.hr.leave.requests.approve');
Route::patch('leave/requests/{leaveRequest}/reject', [HrLeaveRequestController::class, 'reject'])->name('api.hr.leave.requests.reject');

// Leave Calendar
Route::get('leave/calendar', [HrLeaveCalendarController::class, 'index'])->name('api.hr.leave.calendar');
Route::get('leave/calendar/overlaps', [HrLeaveCalendarController::class, 'overlaps'])->name('api.hr.leave.calendar.overlaps');

// Leave Dashboard
Route::get('leave/dashboard/stats', [HrLeaveDashboardController::class, 'stats'])->name('api.hr.leave.dashboard.stats');
Route::get('leave/dashboard/pending', [HrLeaveDashboardController::class, 'pending'])->name('api.hr.leave.dashboard.pending');
Route::get('leave/dashboard/distribution', [HrLeaveDashboardController::class, 'distribution'])->name('api.hr.leave.dashboard.distribution');

// Employee Self-Service — Leave
Route::get('me/leave/balances', [HrMyLeaveController::class, 'balances'])->name('api.hr.me.leave.balances');
Route::get('me/leave/requests', [HrMyLeaveController::class, 'requests'])->name('api.hr.me.leave.requests');
Route::post('me/leave/requests', [HrMyLeaveController::class, 'apply'])->name('api.hr.me.leave.apply');
Route::delete('me/leave/requests/{leaveRequest}', [HrMyLeaveController::class, 'cancel'])->name('api.hr.me.leave.cancel');
Route::get('me/leave/calculate-days', [HrMyLeaveController::class, 'calculateDays'])->name('api.hr.me.leave.calculate-days');
```

**Step 2: Add use statements for all new controllers at top of api.php**

**Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): add Phase 2 API routes (attendance + leave)"
```

---

## Task 13: Tests — Attendance

**Files:**
- Create: `tests/Feature/Hr/HrAttendanceApiTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Hr/HrAttendanceApiTest --pest --no-interaction
```

**Step 2: Write tests covering:**

1. **Auth/Authorization:** Unauthenticated 401, non-admin 403 on admin endpoints
2. **Work Schedules:** CRUD operations, default schedule, validation
3. **Clock In/Out:** Successful clock in, late detection, duplicate clock-in rejection, clock out calculation
4. **Overtime:** Submit request, approve/reject, complete with replacement hours
5. **Holidays:** CRUD, bulk import
6. **Department Approvers:** CRUD, multiple per department
7. **Penalties:** Auto-creation on late, flagged employees query
8. **Analytics:** Stats endpoint returns correct counts

Follow existing `HrApiTest.php` patterns: `createAdminUser()`, `createEmployeeWithRelations()`, `RefreshDatabase`.

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrAttendanceApiTest.php
```

**Step 4: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add attendance API tests"
```

---

## Task 14: Tests — Leave

**Files:**
- Create: `tests/Feature/Hr/HrLeaveApiTest.php`

**Step 1: Create test file**

```bash
php artisan make:test Hr/HrLeaveApiTest --pest --no-interaction
```

**Step 2: Write tests covering:**

1. **Leave Types:** CRUD, system types can't be deleted
2. **Leave Entitlements:** CRUD, recalculate
3. **Leave Balances:** Initialize, manual adjust, balance calculation
4. **Apply for Leave:** Successful application, balance check, attachment required for MC, gender restriction
5. **Approve/Reject:** Status update, balance update, attendance_logs sync on approve
6. **Cancel:** Pending cancel, approved future cancel, restore balance
7. **Calendar:** Returns correct data, overlap detection
8. **Replacement Leave:** Deducts from OT balance

**Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrLeaveApiTest.php
```

**Step 4: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add leave API tests"
```

---

## Task 15: React — API Client Extensions

**Files:**
- Modify: `resources/js/hr/lib/api.js`

**Step 1: Add all attendance and leave API functions**

Append to existing api.js following the same pattern:

```javascript
// ========== Work Schedules ==========
export const fetchSchedules = (params) => api.get('/schedules', { params }).then(r => r.data);
export const createSchedule = (data) => api.post('/schedules', data).then(r => r.data);
export const updateSchedule = (id, data) => api.put(`/schedules/${id}`, data).then(r => r.data);
export const deleteSchedule = (id) => api.delete(`/schedules/${id}`).then(r => r.data);

// ========== Attendance ==========
export const fetchAttendance = (params) => api.get('/attendance', { params }).then(r => r.data);
export const fetchTodayAttendance = () => api.get('/attendance/today').then(r => r.data);
// ... etc for all endpoints

// ========== My Attendance (Self-Service) ==========
export const clockIn = (data) => api.post('/me/attendance/clock-in', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);
export const clockOut = (data) => api.post('/me/attendance/clock-out', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
}).then(r => r.data);

// ========== Leave ==========
// ... all leave endpoints

// ========== My Leave (Self-Service) ==========
// ... all my leave endpoints
```

**Step 2: Commit**

```bash
git add resources/js/hr/lib/api.js
git commit -m "feat(hr): add attendance and leave API client functions"
```

---

## Task 16: React — Shared Components

**Files:**
- Create: `resources/js/hr/components/attendance/CameraCapture.jsx`
- Create: `resources/js/hr/components/attendance/ClockButton.jsx`
- Create: `resources/js/hr/components/attendance/WeekSummary.jsx`
- Create: `resources/js/hr/components/charts/AttendanceGauge.jsx`
- Create: `resources/js/hr/components/charts/LateTrend.jsx`
- Create: `resources/js/hr/components/charts/DepartmentChart.jsx`
- Create: `resources/js/hr/components/leave/LeaveBalanceCard.jsx`
- Create: `resources/js/hr/components/leave/LeaveCalendarGrid.jsx`
- Create: `resources/js/hr/components/leave/OverlapWarning.jsx`

**Step 1: Build CameraCapture component**

Key: Uses `navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })` for front camera. Canvas capture for snapshot. Compress before upload. Fallback file input.

**Step 2: Build chart components using Recharts**

Follow existing dashboard patterns. Install Recharts if not present: `npm install recharts`

**Step 3: Commit**

```bash
git add resources/js/hr/components/
git commit -m "feat(hr): add shared React components (camera, charts, leave cards)"
```

---

## Task 17: React — Attendance Admin Pages

**Files:**
- Create: `resources/js/hr/pages/attendance/AttendanceDashboard.jsx`
- Create: `resources/js/hr/pages/attendance/AttendanceRecords.jsx`
- Create: `resources/js/hr/pages/attendance/WorkSchedules.jsx`
- Create: `resources/js/hr/pages/attendance/ScheduleAssignments.jsx`
- Create: `resources/js/hr/pages/attendance/OvertimeManagement.jsx`
- Create: `resources/js/hr/pages/attendance/HolidayCalendar.jsx`
- Create: `resources/js/hr/pages/attendance/AttendanceAnalytics.jsx`
- Create: `resources/js/hr/pages/attendance/DepartmentApprovers.jsx`

**Step 1: Build each page following EmployeeList.jsx pattern**

Each page should:
- Use `useQuery` for data fetching
- Use `useMutation` for CRUD operations
- Include loading skeletons
- Include empty states
- Include filter/search functionality
- Use DataTable component for tables
- Use Shadcn/ui components (Dialog, Button, Input, Select, etc.)

**Step 2: Commit after each page or batch**

---

## Task 18: React — Leave Admin Pages

**Files:**
- Create: `resources/js/hr/pages/leave/LeaveDashboard.jsx`
- Create: `resources/js/hr/pages/leave/LeaveRequests.jsx`
- Create: `resources/js/hr/pages/leave/LeaveCalendar.jsx`
- Create: `resources/js/hr/pages/leave/LeaveBalances.jsx`
- Create: `resources/js/hr/pages/leave/LeaveTypes.jsx`
- Create: `resources/js/hr/pages/leave/LeaveEntitlements.jsx`
- Create: `resources/js/hr/pages/leave/LeaveApprovers.jsx`

**Step 1: Build each page following same patterns as Task 17**

**Step 2: Commit**

---

## Task 19: React — Employee Self-Service Pages

**Files:**
- Create: `resources/js/hr/pages/ClockInOut.jsx`
- Create: `resources/js/hr/pages/my/MyAttendance.jsx`
- Create: `resources/js/hr/pages/my/MyOvertime.jsx`
- Create: `resources/js/hr/pages/my/MyLeave.jsx`
- Create: `resources/js/hr/pages/my/ApplyLeave.jsx`

**Step 1: Build ClockInOut page (mobile-first)**

Key features:
- Camera preview with CameraCapture component
- Big clock in/out button
- WFH toggle
- Today's record display
- Week summary strip
- Current schedule display

**Step 2: Build self-service pages**

**Step 3: Commit**

---

## Task 20: React — Update App Router

**Files:**
- Modify: `resources/js/hr/App.jsx`
- Modify: `resources/js/hr/layouts/HrLayout.jsx` (add nav items)

**Step 1: Add new routes to App.jsx**

Add inside AdminRoutes:
```jsx
// Attendance
<Route path="attendance" element={<AttendanceDashboard />} />
<Route path="attendance/records" element={<AttendanceRecords />} />
<Route path="attendance/schedules" element={<WorkSchedules />} />
<Route path="attendance/assignments" element={<ScheduleAssignments />} />
<Route path="attendance/overtime" element={<OvertimeManagement />} />
<Route path="attendance/holidays" element={<HolidayCalendar />} />
<Route path="attendance/analytics" element={<AttendanceAnalytics />} />
<Route path="attendance/approvers" element={<DepartmentApprovers />} />

// Leave
<Route path="leave" element={<LeaveDashboard />} />
<Route path="leave/requests" element={<LeaveRequests />} />
<Route path="leave/calendar" element={<LeaveCalendar />} />
<Route path="leave/balances" element={<LeaveBalances />} />
<Route path="leave/types" element={<LeaveTypes />} />
<Route path="leave/entitlements" element={<LeaveEntitlements />} />
<Route path="leave/approvers" element={<LeaveApprovers />} />
```

Add inside EmployeeRoutes:
```jsx
<Route path="clock" element={<ClockInOut />} />
<Route path="my/attendance" element={<MyAttendance />} />
<Route path="my/overtime" element={<MyOvertime />} />
<Route path="my/leave" element={<MyLeave />} />
<Route path="my/leave/apply" element={<ApplyLeave />} />
```

**Step 2: Add navigation items to HrLayout sidebar**

Add "Attendance" and "Leave" sections with sub-items matching the routes.

**Step 3: Commit**

```bash
git add resources/js/hr/
git commit -m "feat(hr): add Phase 2 routes and navigation to React app"
```

---

## Task 21: PWA Setup

**Files:**
- Create: `resources/js/hr/sw.js` (service worker)
- Create: `public/manifest.json` (web app manifest)
- Create: `public/icons/hr-192.png`, `public/icons/hr-512.png`
- Modify: `resources/js/hr/main.jsx` (register service worker)
- Modify: `resources/views/hr.blade.php` (add manifest link + meta tags)

**Step 1: Create Web App Manifest**

```json
{
    "name": "Mudeer HR",
    "short_name": "HR",
    "start_url": "/hr/clock",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#1e40af",
    "icons": [
        { "src": "/icons/hr-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/icons/hr-512.png", "sizes": "512x512", "type": "image/png" }
    ]
}
```

**Step 2: Create Service Worker**

- Cache static assets (JS, CSS, fonts)
- Cache clock-in page for offline
- Background sync for offline clock-in/out
- Push notification handler

**Step 3: Register in main.jsx**

```javascript
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}
```

**Step 4: Add to Blade template**

```html
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1e40af">
<meta name="apple-mobile-web-app-capable" content="yes">
```

**Step 5: Commit**

```bash
git add resources/js/hr/sw.js public/manifest.json public/icons/ resources/views/hr.blade.php resources/js/hr/main.jsx
git commit -m "feat(hr): add PWA setup (manifest, service worker, offline support)"
```

---

## Task 22: Seeder — Phase 2 Data

**Files:**
- Create: `database/seeders/HrPhase2Seeder.php`
- Modify: `database/seeders/HrSeeder.php` (call Phase 2 seeder)

**Step 1: Create seeder**

```bash
php artisan make:seeder HrPhase2Seeder --no-interaction
```

**Step 2: Implement seeder**

Should create:
1. Default Malaysian leave types (AL, MC, HL, ML, PL, CL, RL, UL)
2. Default leave entitlement rules (tiered by service years)
3. Default work schedule ("Office Hours" 9-6 Mon-Fri)
4. Malaysian holidays for 2026
5. Sample department approvers
6. Assign default schedule to all existing employees
7. Initialize leave balances for current year
8. Sample attendance logs (past 30 days)
9. Sample OT requests (mix of statuses)
10. Sample leave requests (mix of statuses)

**Step 3: Run seeder**

```bash
php artisan db:seed --class=HrPhase2Seeder
```

**Step 4: Commit**

```bash
git add database/seeders/
git commit -m "feat(hr): add Phase 2 seeder (leave types, holidays, schedules, sample data)"
```

---

## Task 23: Final Integration Test

**Files:**
- Create: `tests/Feature/Hr/HrPhase2IntegrationTest.php`

**Step 1: Write integration tests for cross-module workflows**

1. **Leave → Attendance sync:** Apply leave, approve, verify attendance_logs created
2. **OT → Replacement Leave:** Complete OT, apply replacement leave, verify hours deducted
3. **Clock in late → Penalty:** Clock in late, verify penalty record and late_minutes
4. **Holiday → Absent skip:** Set holiday, run mark-absent command, verify no absent for that day
5. **Balance initialization:** Create employee, run initialize command, verify balances match entitlement rules

**Step 2: Run all HR tests**

```bash
php artisan test --compact tests/Feature/Hr/
```

**Step 3: Commit**

```bash
git add tests/Feature/Hr/
git commit -m "test(hr): add Phase 2 integration tests"
```

---

## Task 24: Code Quality & Final Build

**Step 1: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

**Step 3: Build frontend**

```bash
npm run build
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(hr): complete Phase 2 - Attendance & Leave Management"
```

---

## File Summary

### New Files Created (~60 files)

**Migrations (11):**
- `create_holidays_table`
- `create_department_approvers_table`
- `create_work_schedules_table`
- `create_employee_schedules_table`
- `create_attendance_logs_table`
- `create_overtime_requests_table`
- `create_attendance_penalties_table`
- `create_leave_types_table`
- `create_leave_entitlements_table`
- `create_leave_balances_table`
- `create_leave_requests_table`

**Models (11):**
- `Holiday`, `DepartmentApprover`, `WorkSchedule`, `EmployeeSchedule`, `AttendanceLog`, `OvertimeRequest`, `AttendancePenalty`
- `LeaveType`, `LeaveEntitlement`, `LeaveBalance`, `LeaveRequest`

**Controllers (15):**
- `HrAttendanceController`, `HrWorkScheduleController`, `HrEmployeeScheduleController`, `HrOvertimeController`, `HrHolidayController`, `HrDepartmentApproverController`, `HrAttendancePenaltyController`, `HrAttendanceAnalyticsController`, `HrMyAttendanceController`
- `HrLeaveRequestController`, `HrLeaveTypeController`, `HrLeaveEntitlementController`, `HrLeaveBalanceController`, `HrLeaveCalendarController`, `HrLeaveDashboardController`, `HrMyLeaveController`

**Form Requests (8):**
- Attendance: `StoreWorkScheduleRequest`, `ClockInRequest`, `ClockOutRequest`, `StoreOvertimeRequest`, `StoreHolidayRequest`, `StoreDepartmentApproverRequest`
- Leave: `StoreLeaveTypeRequest`, `StoreLeaveEntitlementRequest`, `ApplyLeaveRequest`

**Commands (3):**
- `HrMarkAbsent`, `HrPenaltySummary`, `HrInitializeLeaveBalances`

**Factories (11):**
- One per model

**Tests (3):**
- `HrAttendanceApiTest`, `HrLeaveApiTest`, `HrPhase2IntegrationTest`

**Seeders (1):**
- `HrPhase2Seeder`

**React Pages (16):**
- Attendance admin: 8 pages
- Leave admin: 7 pages
- Shared: ClockInOut
- Self-service: MyAttendance, MyOvertime, MyLeave, ApplyLeave

**React Components (~12):**
- Camera, clock button, charts, leave cards, calendar grid, etc.

**PWA (3):**
- `sw.js`, `manifest.json`, icons

### Modified Files

- `app/Models/Employee.php` — add attendance/leave relationships
- `routes/api.php` — add Phase 2 routes
- `routes/console.php` — add scheduled commands
- `resources/js/hr/lib/api.js` — add API functions
- `resources/js/hr/App.jsx` — add routes
- `resources/js/hr/layouts/HrLayout.jsx` — add nav items
- `resources/js/hr/main.jsx` — register service worker
- `resources/views/hr.blade.php` — add PWA meta tags
