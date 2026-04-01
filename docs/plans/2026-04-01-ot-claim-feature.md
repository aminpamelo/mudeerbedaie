# OT Claim Feature Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow employees to claim their accumulated OT replacement hours as Time Off In Lieu (TOIL), with a full approval workflow and automatic attendance record adjustment.

**Architecture:** New `overtime_claim_requests` table tracks claims independently from OT requests. Balance is computed globally (sum of earned hours minus sum of approved claim minutes). When a claim is approved, the attendance log for that date is updated to clear late minutes and link the claim.

**Tech Stack:** Laravel 12, Eloquent, React 19, @tanstack/react-query, existing `DepartmentApprover` system, `BaseHrNotification`

---

## Context: Key Existing Files

- Model: `app/Models/OvertimeRequest.php`
- Controller (employee): `app/Http/Controllers/Api/Hr/HrMyAttendanceController.php`
- Controller (approver): `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`
- Controller (HR admin): `app/Http/Controllers/Api/Hr/HrOvertimeController.php`
- Approver helper: `HrMyApprovalController::getDeptIds($employeeId, 'overtime')` — reuse this
- Notification base: `app/Notifications/Hr/BaseHrNotification.php`
- React page (employee): `resources/js/hr/pages/my/MyOvertime.jsx`
- React page (approver): `resources/js/hr/pages/my/MyApprovalsOvertime.jsx`
- React page (HR admin): `resources/js/hr/pages/attendance/OvertimeManagement.jsx`
- API helpers: `resources/js/hr/lib/api.js` — exports `fetchMyOvertime`, etc.
- Routes: `resources/js/hr/App.jsx`
- API routes: `routes/api.php` (OT routes around lines 547–599)

---

## Task 1: Migration — Create `overtime_claim_requests` Table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_overtime_claim_requests_table.php`

**Step 1: Generate the migration**

```bash
php artisan make:migration create_overtime_claim_requests_table --no-interaction
```

**Step 2: Write the migration**

Open the newly created file and replace the `up()` method:

```php
public function up(): void
{
    Schema::create('overtime_claim_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
        $table->date('claim_date');
        $table->time('start_time');
        $table->unsignedSmallInteger('duration_minutes'); // 30–230
        $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('approved_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->text('notes')->nullable();
        $table->foreignId('attendance_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
        $table->timestamps();

        $table->unique(['employee_id', 'claim_date']); // one claim per employee per day
        $table->index(['employee_id', 'status']);
    });
}

public function down(): void
{
    Schema::dropIfExists('overtime_claim_requests');
}
```

**Step 3: Run the migration**

```bash
php artisan migrate --no-interaction
```

Expected: `Migrating: ...create_overtime_claim_requests_table` → `Migrated`

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add overtime_claim_requests migration"
```

---

## Task 2: Migration — Add `ot_claim_id` to `attendance_logs`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_ot_claim_id_to_attendance_logs_table.php`

**Step 1: Generate the migration**

```bash
php artisan make:migration add_ot_claim_id_to_attendance_logs_table --no-interaction
```

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::table('attendance_logs', function (Blueprint $table) {
        $table->foreignId('ot_claim_id')
            ->nullable()
            ->after('remarks')
            ->constrained('overtime_claim_requests')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('attendance_logs', function (Blueprint $table) {
        $table->dropForeignIdFor(\App\Models\OvertimeClaimRequest::class, 'ot_claim_id');
        $table->dropColumn('ot_claim_id');
    });
}
```

**Step 3: Run the migration**

```bash
php artisan migrate --no-interaction
```

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat(hr): add ot_claim_id to attendance_logs"
```

---

## Task 3: Model — `OvertimeClaimRequest`

**Files:**
- Create: `app/Models/OvertimeClaimRequest.php`

**Step 1: Generate the model**

```bash
php artisan make:model OvertimeClaimRequest --no-interaction
```

**Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeClaimRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'claim_date',
        'start_time',
        'duration_minutes',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'attendance_id',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class, 'attendance_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
```

**Step 3: Verify the model loads**

```bash
php artisan tinker --no-interaction --execute="echo App\Models\OvertimeClaimRequest::count();"
```

Expected: `0`

**Step 4: Commit**

```bash
git add app/Models/OvertimeClaimRequest.php
git commit -m "feat(hr): add OvertimeClaimRequest model"
```

---

## Task 4: Form Request — `StoreOvertimeClaimRequest`

**Files:**
- Create: `app/Http/Requests/Hr/StoreOvertimeClaimRequest.php`

**Step 1: Generate the form request**

```bash
php artisan make:request Hr/StoreOvertimeClaimRequest --no-interaction
```

**Step 2: Write the rules**

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'claim_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:230'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.min' => 'Minimum claim duration is 30 minutes.',
            'duration_minutes.max' => 'Maximum claim duration is 230 minutes.',
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Requests/Hr/StoreOvertimeClaimRequest.php
git commit -m "feat(hr): add StoreOvertimeClaimRequest form request"
```

---

## Task 5: Notifications — Claim Submitted & Decision

**Files:**
- Create: `app/Notifications/Hr/OvertimeClaimSubmitted.php`
- Create: `app/Notifications/Hr/OvertimeClaimDecision.php`

**Step 1: Create `OvertimeClaimSubmitted`**

```bash
php artisan make:notification Hr/OvertimeClaimSubmitted --no-interaction
```

Replace the file contents:

```php
<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeClaimRequest;

class OvertimeClaimSubmitted extends BaseHrNotification
{
    public function __construct(
        public OvertimeClaimRequest $claim
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'New OT Claim Request';
    }

    protected function body(): string
    {
        $name = $this->claim->employee->full_name;
        $date = $this->claim->claim_date->format('M j, Y');
        $mins = $this->claim->duration_minutes;

        return "{$name} submitted an OT claim for {$mins} minutes on {$date}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/approvals/overtime';
    }

    protected function icon(): string
    {
        return 'timer';
    }
}
```

**Step 2: Create `OvertimeClaimDecision`**

```bash
php artisan make:notification Hr/OvertimeClaimDecision --no-interaction
```

Replace the file contents:

```php
<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeClaimRequest;

class OvertimeClaimDecision extends BaseHrNotification
{
    public function __construct(
        public OvertimeClaimRequest $claim,
        public string $decision
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'OT Claim ' . ucfirst($this->decision);
    }

    protected function body(): string
    {
        $date = $this->claim->claim_date->format('M j, Y');

        return "Your OT claim for {$date} has been {$this->decision}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/overtime';
    }

    protected function icon(): string
    {
        return $this->decision === 'approved' ? 'check-circle' : 'x-circle';
    }
}
```

**Step 3: Commit**

```bash
git add app/Notifications/Hr/OvertimeClaimSubmitted.php app/Notifications/Hr/OvertimeClaimDecision.php
git commit -m "feat(hr): add OT claim notifications"
```

---

## Task 6: Controller — Employee OT Claim Methods + Balance Fix

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrMyAttendanceController.php`

Add these three methods at the end of the class (before the closing `}`). Also update the `overtimeBalance` method.

**Step 1: Add imports** at the top of `HrMyAttendanceController.php`:

```php
use App\Models\AttendanceLog;
use App\Models\OvertimeClaimRequest;
use App\Http\Requests\Hr\StoreOvertimeClaimRequest;
use App\Models\DepartmentApprover;
use App\Models\User;
```

(Check existing imports first — only add what's missing)

**Step 2: Update `overtimeBalance` method** — change `SUM(replacement_hours_used)` to compute from claims:

Find the existing `overtimeBalance` method (around line 397) and replace the query logic:

```php
public function overtimeBalance(Request $request): JsonResponse
{
    $employee = $request->user()->employee;

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $totalEarned = (float) OvertimeRequest::query()
        ->where('employee_id', $employee->id)
        ->where('status', 'completed')
        ->sum('replacement_hours_earned');

    $totalUsedMinutes = (int) OvertimeClaimRequest::query()
        ->where('employee_id', $employee->id)
        ->where('status', 'approved')
        ->sum('duration_minutes');

    $totalUsed = round($totalUsedMinutes / 60, 1);

    return response()->json([
        'data' => [
            'total_earned' => $totalEarned,
            'total_used' => $totalUsed,
            'available' => round($totalEarned - $totalUsed, 1),
        ],
    ]);
}
```

**Step 3: Add `myOvertimeClaims` method**

```php
/**
 * List my OT claim requests.
 */
public function myOvertimeClaims(Request $request): JsonResponse
{
    $employee = $request->user()->employee;

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $claims = OvertimeClaimRequest::query()
        ->where('employee_id', $employee->id)
        ->orderByDesc('claim_date')
        ->paginate(15);

    return response()->json($claims);
}
```

**Step 4: Add `submitOvertimeClaim` method**

```php
/**
 * Submit a new OT claim request.
 */
public function submitOvertimeClaim(StoreOvertimeClaimRequest $request): JsonResponse
{
    $employee = $request->user()->employee;

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $validated = $request->validated();

    // Check sufficient balance
    $totalEarned = (float) OvertimeRequest::query()
        ->where('employee_id', $employee->id)
        ->where('status', 'completed')
        ->sum('replacement_hours_earned');

    $totalUsedMinutes = (int) OvertimeClaimRequest::query()
        ->where('employee_id', $employee->id)
        ->where('status', 'approved')
        ->sum('duration_minutes');

    $availableMinutes = ($totalEarned * 60) - $totalUsedMinutes;

    if ($validated['duration_minutes'] > $availableMinutes) {
        return response()->json([
            'message' => 'Insufficient OT balance. You have ' . round($availableMinutes / 60, 1) . 'h available.',
        ], 422);
    }

    $claim = OvertimeClaimRequest::create(array_merge($validated, [
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]));

    // Notify OT approvers for this department
    $approverEmployeeIds = DepartmentApprover::where('department_id', $employee->department_id)
        ->where('approval_type', 'overtime')
        ->pluck('approver_employee_id');

    $notifiedUserIds = [];
    foreach ($approverEmployeeIds as $approverEmployeeId) {
        $approverEmployee = \App\Models\Employee::find($approverEmployeeId);
        if ($approverEmployee?->user) {
            $approverEmployee->user->notify(new \App\Notifications\Hr\OvertimeClaimSubmitted($claim));
            $notifiedUserIds[] = $approverEmployee->user->id;
        }
    }

    // Also notify HR admins not already notified
    User::where('role', 'admin')->whereNotIn('id', $notifiedUserIds)->each(function ($admin) use ($claim) {
        $admin->notify(new \App\Notifications\Hr\OvertimeClaimSubmitted($claim));
    });

    return response()->json([
        'data' => $claim,
        'message' => 'OT claim request submitted successfully.',
    ], 201);
}
```

**Step 5: Add `cancelOvertimeClaim` method**

```php
/**
 * Cancel a pending OT claim request.
 */
public function cancelOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
{
    $employee = $request->user()->employee;

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    if ($overtimeClaimRequest->employee_id !== $employee->id) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if ($overtimeClaimRequest->status !== 'pending') {
        return response()->json(['message' => 'Only pending claims can be cancelled.'], 422);
    }

    $overtimeClaimRequest->update(['status' => 'cancelled']);

    return response()->json(['message' => 'OT claim cancelled.']);
}
```

**Step 6: Run Pint**

```bash
vendor/bin/pint app/Http/Controllers/Api/Hr/HrMyAttendanceController.php --dirty
```

**Step 7: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrMyAttendanceController.php
git commit -m "feat(hr): add employee OT claim endpoints and fix balance calculation"
```

---

## Task 7: Controller — Approver OT Claim Methods

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`

Add these methods inside the class. The `getDeptIds` helper already exists — reuse it.

**Step 1: Add import** at the top:

```php
use App\Models\OvertimeClaimRequest;
use App\Models\AttendanceLog;
```

**Step 2: Add `overtimeClaims` method** (list for approver):

```php
// ── Overtime Claims ───────────────────────────────────────────────────────

public function overtimeClaims(Request $request): JsonResponse
{
    $employee = $this->getEmployee($request);

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $deptIds = $this->getDeptIds($employee->id, 'overtime');

    if (empty($deptIds)) {
        return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
    }

    $query = OvertimeClaimRequest::with([
        'employee:id,full_name,position_id,department_id',
        'employee.position:id,name',
        'employee.department:id,name',
    ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

    if ($request->filled('status') && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    return response()->json($query->orderByDesc('created_at')->paginate(20));
}
```

**Step 3: Add `approveOvertimeClaim` method**:

```php
public function approveOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
{
    $employee = $this->getEmployee($request);

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $deptIds = $this->getDeptIds($employee->id, 'overtime');

    if (! in_array($overtimeClaimRequest->employee->department_id, $deptIds)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if ($overtimeClaimRequest->status !== 'pending') {
        return response()->json(['message' => 'Only pending claims can be approved.'], 422);
    }

    $overtimeClaimRequest->update([
        'status' => 'approved',
        'approved_by' => $request->user()->id,
        'approved_at' => now(),
    ]);

    // Link attendance record if it exists for that date
    $attendanceLog = AttendanceLog::where('employee_id', $overtimeClaimRequest->employee_id)
        ->where('date', $overtimeClaimRequest->claim_date)
        ->first();

    if ($attendanceLog) {
        $newLateMinutes = max(0, $attendanceLog->late_minutes - $overtimeClaimRequest->duration_minutes);
        $newStatus = ($newLateMinutes === 0 && $attendanceLog->status === 'late') ? 'present' : $attendanceLog->status;

        $attendanceLog->update([
            'ot_claim_id' => $overtimeClaimRequest->id,
            'late_minutes' => $newLateMinutes,
            'status' => $newStatus,
        ]);

        $overtimeClaimRequest->update(['attendance_id' => $attendanceLog->id]);
    }

    // Notify employee
    if ($overtimeClaimRequest->employee->user) {
        $overtimeClaimRequest->employee->user->notify(
            new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'approved')
        );
    }

    return response()->json($overtimeClaimRequest->fresh([
        'employee:id,full_name,position_id,department_id',
        'employee.position:id,name',
        'employee.department:id,name',
    ]));
}
```

**Step 4: Add `rejectOvertimeClaim` method**:

```php
public function rejectOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
{
    $employee = $this->getEmployee($request);

    if (! $employee) {
        return response()->json(['message' => 'Employee record not found.'], 404);
    }

    $deptIds = $this->getDeptIds($employee->id, 'overtime');

    if (! in_array($overtimeClaimRequest->employee->department_id, $deptIds)) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    if ($overtimeClaimRequest->status !== 'pending') {
        return response()->json(['message' => 'Only pending claims can be rejected.'], 422);
    }

    $validated = $request->validate([
        'rejection_reason' => ['required', 'string', 'min:5'],
    ]);

    $overtimeClaimRequest->update([
        'status' => 'rejected',
        'rejection_reason' => $validated['rejection_reason'],
    ]);

    if ($overtimeClaimRequest->employee->user) {
        $overtimeClaimRequest->employee->user->notify(
            new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'rejected')
        );
    }

    return response()->json($overtimeClaimRequest->fresh([
        'employee:id,full_name,position_id,department_id',
        'employee.position:id,name',
        'employee.department:id,name',
    ]));
}
```

**Step 5: Run Pint**

```bash
vendor/bin/pint app/Http/Controllers/Api/Hr/HrMyApprovalController.php --dirty
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrMyApprovalController.php
git commit -m "feat(hr): add approver OT claim endpoints"
```

---

## Task 8: Controller — HR Admin OT Claims View

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrOvertimeController.php`

**Step 1: Add import**:

```php
use App\Models\OvertimeClaimRequest;
```

**Step 2: Add `claims` method** at the end of the class:

```php
/**
 * List all OT claim requests (HR admin view).
 */
public function claims(Request $request): JsonResponse
{
    $query = OvertimeClaimRequest::query()
        ->with(['employee.department']);

    if ($status = $request->get('status')) {
        $query->where('status', $status);
    }

    if ($departmentId = $request->get('department_id')) {
        $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
    }

    if ($dateFrom = $request->get('date_from')) {
        $query->whereDate('claim_date', '>=', $dateFrom);
    }

    if ($dateTo = $request->get('date_to')) {
        $query->whereDate('claim_date', '<=', $dateTo);
    }

    return response()->json($query->orderByDesc('claim_date')->paginate(15));
}
```

**Step 3: Run Pint**

```bash
vendor/bin/pint app/Http/Controllers/Api/Hr/HrOvertimeController.php --dirty
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrOvertimeController.php
git commit -m "feat(hr): add HR admin OT claims list endpoint"
```

---

## Task 9: Routes — Register New API Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Find the existing "me/overtime" routes** (around lines 547–550):

```
Route::get('me/overtime', ...
Route::post('me/overtime', ...
Route::get('me/overtime/balance', ...
Route::delete('me/overtime/{overtimeRequest}', ...
```

**Step 2: Add claim routes directly after them**:

```php
// OT Claims (employee)
Route::get('me/overtime/claims', [HrMyAttendanceController::class, 'myOvertimeClaims'])->name('api.hr.my-attendance.overtime-claims.index');
Route::post('me/overtime/claims', [HrMyAttendanceController::class, 'submitOvertimeClaim'])->name('api.hr.my-attendance.overtime-claims.store');
Route::delete('me/overtime/claims/{overtimeClaimRequest}', [HrMyAttendanceController::class, 'cancelOvertimeClaim'])->name('api.hr.my-attendance.overtime-claims.cancel');
```

**Step 3: Find the "my-approvals overtime" routes** (around lines 556–558):

```
Route::get('overtime', [HrMyApprovalController::class, 'overtime']);
Route::patch('overtime/{overtimeRequest}/approve', ...
Route::patch('overtime/{overtimeRequest}/reject', ...
```

**Step 4: Add claim approval routes directly after them**:

```php
// OT Claim approvals
Route::get('overtime-claims', [HrMyApprovalController::class, 'overtimeClaims']);
Route::patch('overtime-claims/{overtimeClaimRequest}/approve', [HrMyApprovalController::class, 'approveOvertimeClaim']);
Route::patch('overtime-claims/{overtimeClaimRequest}/reject', [HrMyApprovalController::class, 'rejectOvertimeClaim']);
```

**Step 5: Find the HR admin overtime routes** (around lines 595–599):

**Step 6: Add HR admin claims route after them**:

```php
Route::get('overtime/claims', [HrOvertimeController::class, 'claims'])->name('api.hr.overtime-claims.index');
```

**Step 7: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): register OT claim API routes"
```

---

## Task 10: React API Helpers

**Files:**
- Modify: `resources/js/hr/lib/api.js`

**Step 1: Find the existing OT API functions** (around line 206–209):

```js
export const fetchMyOvertime = ...
export const submitMyOvertime = ...
export const fetchMyOvertimeBalance = ...
export const cancelMyOvertime = ...
```

**Step 2: Add claim API functions directly after them**:

```js
export const fetchMyOvertimeClaims = (params) => api.get('/me/overtime/claims', { params }).then(r => r.data);
export const submitMyOvertimeClaim = (data) => api.post('/me/overtime/claims', data).then(r => r.data);
export const cancelMyOvertimeClaim = (id) => api.delete(`/me/overtime/claims/${id}`).then(r => r.data);
```

**Step 3: Commit**

```bash
git add resources/js/hr/lib/api.js
git commit -m "feat(hr): add OT claim API helper functions"
```

---

## Task 11: React — Employee `MyOvertimeClaims` Tab

**Files:**
- Modify: `resources/js/hr/pages/my/MyOvertime.jsx`

The goal is to add a tab switcher ("OT Requests" | "My Claims") to `MyOvertime.jsx`, and show a claims list + "New Claim" modal in the Claims tab.

**Step 1: Add new imports** at the top of the file:

```js
import { fetchMyOvertimeClaims, submitMyOvertimeClaim, cancelMyOvertimeClaim } from '../../lib/api';
```

**Step 2: Add tab state** inside the `MyOvertime` component, alongside the existing `showForm` state:

```js
const [activeTab, setActiveTab] = useState('requests'); // 'requests' | 'claims'
const [showClaimForm, setShowClaimForm] = useState(false);
const [claimForm, setClaimForm] = useState({ claim_date: '', start_time: '', duration_minutes: '', notes: '' });
const [claimFormError, setClaimFormError] = useState(null);
```

**Step 3: Add claim data queries** after the existing balance query:

```js
const { data: claimsData, isLoading: claimsLoading } = useQuery({
    queryKey: ['my-overtime-claims'],
    queryFn: () => fetchMyOvertimeClaims(),
});
const claims = claimsData?.data ?? [];

const submitClaimMut = useMutation({
    mutationFn: submitMyOvertimeClaim,
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['my-overtime-claims'] });
        queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
        setShowClaimForm(false);
        setClaimForm({ claim_date: '', start_time: '', duration_minutes: '', notes: '' });
        setClaimFormError(null);
    },
    onError: (err) => {
        setClaimFormError(err?.response?.data?.message || 'Failed to submit claim');
    },
});

const cancelClaimMut = useMutation({
    mutationFn: cancelMyOvertimeClaim,
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['my-overtime-claims'] });
        queryClient.invalidateQueries({ queryKey: ['my-overtime-balance'] });
    },
});
```

**Step 4: Add the claim duration presets constant** (near the existing `DURATION_PRESETS`):

```js
const CLAIM_DURATION_PRESETS = [30, 60, 90, 120, 150, 180, 210, 230];
```

**Step 5: Replace the return JSX** — wrap existing content in a tab structure. The tab bar goes above the balance cards:

```jsx
return (
    <div className="space-y-4">
        {/* Header */}
        <div className="flex items-center justify-between">
            <div>
                <h1 className="text-xl font-bold text-zinc-900">My Overtime</h1>
                <p className="text-sm text-zinc-500 mt-0.5">Manage your overtime requests</p>
            </div>
            {activeTab === 'requests' ? (
                <Button size="sm" onClick={() => { resetForm(); setShowForm(true); }}>
                    <Plus className="h-4 w-4 mr-1" /> New OT Request
                </Button>
            ) : (
                <Button size="sm" onClick={() => setShowClaimForm(true)}>
                    <Plus className="h-4 w-4 mr-1" /> New Claim
                </Button>
            )}
        </div>

        {/* Tab switcher */}
        <div className="flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-50 w-fit">
            <button
                onClick={() => setActiveTab('requests')}
                className={cn(
                    'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                    activeTab === 'requests'
                        ? 'bg-white text-zinc-900 shadow-sm'
                        : 'text-zinc-500 hover:text-zinc-700'
                )}
            >
                OT Requests
            </button>
            <button
                onClick={() => setActiveTab('claims')}
                className={cn(
                    'px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
                    activeTab === 'claims'
                        ? 'bg-white text-zinc-900 shadow-sm'
                        : 'text-zinc-500 hover:text-zinc-700'
                )}
            >
                My Claims
            </button>
        </div>

        {/* Balance cards — always visible */}
        <div className="grid grid-cols-3 gap-2">
            {/* ...existing balance cards unchanged... */}
        </div>

        {/* Tab content */}
        {activeTab === 'requests' ? (
            <Card>
                {/* ...existing OT Requests card unchanged... */}
            </Card>
        ) : (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-sm">OT Claims</CardTitle>
                </CardHeader>
                <CardContent>
                    {claimsLoading ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-zinc-400" />
                        </div>
                    ) : claims.length === 0 ? (
                        <div className="py-8 text-center">
                            <Clock className="h-8 w-8 text-zinc-300 mx-auto mb-2" />
                            <p className="text-sm text-zinc-500">No claim requests yet</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {claims.map((claim) => {
                                const cfg = STATUS_CONFIG[claim.status] || STATUS_CONFIG.pending;
                                return (
                                    <div
                                        key={claim.id}
                                        className="flex items-center justify-between rounded-lg border border-zinc-100 p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-medium text-zinc-900">
                                                    {formatDate(claim.claim_date)}
                                                </p>
                                                <Badge variant={cfg.variant} className="text-[10px]">
                                                    {cfg.label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-zinc-500 mt-0.5">
                                                {formatTime(claim.start_time)} • {formatDuration(claim.duration_minutes)}
                                            </p>
                                            {claim.notes && (
                                                <p className="text-xs text-zinc-500 mt-0.5 truncate">{claim.notes}</p>
                                            )}
                                            {claim.rejection_reason && (
                                                <p className="text-xs text-red-500 mt-0.5">{claim.rejection_reason}</p>
                                            )}
                                        </div>
                                        {claim.status === 'pending' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0 text-red-500 hover:text-red-700 shrink-0 ml-2"
                                                onClick={() => {
                                                    if (window.confirm('Cancel this OT claim?')) {
                                                        cancelClaimMut.mutate(claim.id);
                                                    }
                                                }}
                                                disabled={cancelClaimMut.isPending}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        )}

        {/* Existing OT Request Dialog — unchanged */}
        {/* ... */}

        {/* New Claim Dialog */}
        <Dialog open={showClaimForm} onOpenChange={setShowClaimForm}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New OT Claim</DialogTitle>
                    <DialogDescription>
                        Claim your OT hours as time off. Available: <span className="font-semibold">{balance.available ?? 0}h</span>
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={(e) => {
                    e.preventDefault();
                    setClaimFormError(null);
                    const mins = parseInt(claimForm.duration_minutes);
                    const availableMins = Math.round((balance.available ?? 0) * 60);
                    if (!mins || mins < 30) { setClaimFormError('Minimum claim is 30 minutes.'); return; }
                    if (mins > 230) { setClaimFormError('Maximum claim is 230 minutes.'); return; }
                    if (mins > availableMins) { setClaimFormError(`Not enough balance. You have ${availableMins} minutes available.`); return; }
                    submitClaimMut.mutate(claimForm);
                }} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label className="text-xs">Date *</Label>
                            <Input
                                type="date"
                                value={claimForm.claim_date}
                                onChange={(e) => setClaimForm({ ...claimForm, claim_date: e.target.value })}
                                className="mt-1"
                                required
                            />
                        </div>
                        <div>
                            <Label className="text-xs">Start Time *</Label>
                            <Input
                                type="time"
                                value={claimForm.start_time}
                                onChange={(e) => setClaimForm({ ...claimForm, start_time: e.target.value })}
                                className="mt-1"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <Label className="text-xs">Duration *</Label>
                        <div className="mt-1 space-y-2">
                            <div className="flex flex-wrap gap-1.5">
                                {CLAIM_DURATION_PRESETS.map((mins) => (
                                    <button
                                        key={mins}
                                        type="button"
                                        onClick={() => setClaimForm({ ...claimForm, duration_minutes: String(mins) })}
                                        className={cn(
                                            'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                                            claimForm.duration_minutes === String(mins)
                                                ? 'bg-zinc-900 text-white border-zinc-900'
                                                : 'bg-white text-zinc-600 border-zinc-200 hover:border-zinc-400'
                                        )}
                                    >
                                        {formatDuration(mins)}
                                    </button>
                                ))}
                            </div>
                            <Input
                                type="number"
                                min="30"
                                max="230"
                                step="15"
                                value={claimForm.duration_minutes}
                                onChange={(e) => setClaimForm({ ...claimForm, duration_minutes: e.target.value })}
                                placeholder="Custom minutes (30–230)"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <Label className="text-xs">Notes (optional)</Label>
                        <Textarea
                            value={claimForm.notes}
                            onChange={(e) => setClaimForm({ ...claimForm, notes: e.target.value })}
                            className="mt-1"
                            rows={2}
                            placeholder="Optional note..."
                        />
                    </div>
                    {claimFormError && (
                        <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3">
                            <AlertCircle className="h-4 w-4 text-red-500 shrink-0" />
                            <p className="text-sm text-red-700">{claimFormError}</p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setShowClaimForm(false)}>Cancel</Button>
                        <Button type="submit" disabled={submitClaimMut.isPending}>
                            {submitClaimMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-1" />}
                            Submit Claim
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
);
```

> **Note:** When actually implementing, keep the existing OT Requests JSX block intact inside the `activeTab === 'requests'` branch. Don't duplicate or remove it.

**Step 6: Run the dev build to verify no JS errors**

```bash
npm run build 2>&1 | tail -20
```

Expected: no errors.

**Step 7: Commit**

```bash
git add resources/js/hr/pages/my/MyOvertime.jsx
git commit -m "feat(hr): add OT claim tab to MyOvertime page"
```

---

## Task 12: React — Approver OT Claims Tab in `MyApprovalsOvertime.jsx`

**Files:**
- Modify: `resources/js/hr/pages/my/MyApprovalsOvertime.jsx`

The goal is to add a "OT Claims" section. Add a top-level type switcher ("OT Requests" | "OT Claims"), and reuse the same card/tab pattern already in the file.

**Step 1: Add imports**:

```js
import { Clock } from 'lucide-react';
```

(Only if not already imported)

**Step 2: Add state for type switcher**:

```js
const [type, setType] = useState('requests'); // 'requests' | 'claims'
```

**Step 3: Add claims query** (after the existing OT query):

```js
const { data: claimsData, isLoading: claimsLoading } = useQuery({
    queryKey: ['my-approvals-overtime-claims', tab],
    queryFn: () => {
        const params = tab !== 'all' ? `?status=${tab}` : '';
        return api.get(`/my-approvals/overtime-claims${params}`).then((r) => r.data);
    },
    enabled: type === 'claims',
});

const claimApproveMut = useMutation({
    mutationFn: (id) => api.patch(`/my-approvals/overtime-claims/${id}/approve`),
    onSuccess: () => {
        qc.invalidateQueries({ queryKey: ['my-approvals-overtime-claims'] });
        qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
        setApproveDialog(null);
    },
});

const claimRejectMut = useMutation({
    mutationFn: ({ id, reason }) =>
        api.patch(`/my-approvals/overtime-claims/${id}/reject`, { rejection_reason: reason }),
    onSuccess: () => {
        qc.invalidateQueries({ queryKey: ['my-approvals-overtime-claims'] });
        qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
        setRejectDialog(null);
        setRejectReason('');
        setActionError('');
    },
    onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
});
```

**Step 4: Add type switcher UI** — add it directly below the back button/header, above the tab strip:

```jsx
{/* Type switcher */}
<div className="flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-50 w-fit mb-3">
    <button
        onClick={() => setType('requests')}
        className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
            type === 'requests' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-600'
        }`}
    >
        OT Requests
    </button>
    <button
        onClick={() => setType('claims')}
        className={`px-3 py-1 rounded-md text-xs font-medium transition-colors ${
            type === 'claims' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-600'
        }`}
    >
        OT Claims
    </button>
</div>
```

**Step 5: Conditionally render cards** — in the content area, render claims cards when `type === 'claims'`. Claim cards show: employee name/dept, claim date, start time, duration (formatted), notes, rejection reason, and Approve/Reject actions for pending status. Reuse the existing `OTCard` component structure.

Create a `ClaimCard` component (similar to the existing `OTCard`) with these fields:
- `claim_date` instead of `requested_date`
- `duration_minutes` displayed as `formatDuration(claim.duration_minutes)`
- `start_time`
- `notes` instead of `reason`

```jsx
function ClaimCard({ claim, onApprove, onReject }) {
    const cfg = STATUS_CONFIG[claim.status] ?? STATUS_CONFIG.cancelled;
    const name = claim.employee?.full_name ?? 'Unknown';

    function formatDuration(minutes) {
        if (!minutes) return '-';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        if (h === 0) return `${m}min`;
        if (m === 0) return `${h}h`;
        return `${h}h ${m}min`;
    }

    return (
        <div className={`rounded-2xl bg-white border border-zinc-100 border-l-4 ${cfg.border} shadow-sm overflow-hidden`}>
            <div className="p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${avatarColor(name)}`}>
                            {getInitials(name)}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-zinc-900 truncate">{name}</p>
                            <div className="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                {claim.employee?.department?.name && (
                                    <span className="flex items-center gap-0.5 text-xs text-zinc-400">
                                        <Building2 className="h-3 w-3" />
                                        {claim.employee.department.name}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <span className={`shrink-0 inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize ${cfg.color}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                        {claim.status}
                    </span>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-2">
                    <div className="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                        <Calendar className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                        <span className="text-xs text-zinc-700 font-medium">{formatDate(claim.claim_date)}</span>
                    </div>
                    <div className="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                        <Clock className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                        <span className="text-xs text-zinc-700 font-medium">{formatDuration(claim.duration_minutes)}</span>
                    </div>
                    {claim.start_time && (
                        <div className="col-span-2 flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                            <Timer className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                            <span className="text-xs text-zinc-700 font-medium">From {claim.start_time}</span>
                        </div>
                    )}
                </div>
                {claim.notes && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-zinc-50 px-3 py-2">
                        <FileText className="h-3.5 w-3.5 text-zinc-400 shrink-0 mt-0.5" />
                        <p className="text-xs text-zinc-500 line-clamp-2">{claim.notes}</p>
                    </div>
                )}
                {claim.rejection_reason && (
                    <div className="mt-2.5 flex items-start gap-2 rounded-xl bg-red-50 px-3 py-2">
                        <AlertCircle className="h-3.5 w-3.5 text-red-400 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-600">{claim.rejection_reason}</p>
                    </div>
                )}
                {claim.status === 'pending' && (
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <button
                            onClick={() => onApprove(claim)}
                            className="flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-all hover:bg-emerald-700 active:scale-95"
                        >
                            <Check className="h-4 w-4" /> Approve
                        </button>
                        <button
                            onClick={() => onReject(claim)}
                            className="flex items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-600 transition-all hover:bg-red-100 active:scale-95"
                        >
                            <X className="h-4 w-4" /> Reject
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
```

In the content area, show `ClaimCard` items when `type === 'claims'`, using `claimApproveMut` and `claimRejectMut` for the approve/reject dialogs (reuse the existing dialogs, but wire them to the claim mutations).

**Step 6: Build check**

```bash
npm run build 2>&1 | tail -20
```

**Step 7: Commit**

```bash
git add resources/js/hr/pages/my/MyApprovalsOvertime.jsx
git commit -m "feat(hr): add OT claims tab to MyApprovalsOvertime page"
```

---

## Task 13: React — HR Admin OT Claims Tab in `OvertimeManagement.jsx`

**Files:**
- Modify: `resources/js/hr/pages/attendance/OvertimeManagement.jsx`

**Step 1: Add claims query** to the existing queries:

```js
const { data: claimsData } = useQuery({
    queryKey: ['hr-overtime-claims', activeTab, filters],
    queryFn: () => api.get('/hr/overtime/claims', {
        params: {
            status: activeTab !== 'all' ? activeTab : undefined,
            ...filters,
        }
    }).then(r => r.data),
    enabled: mainView === 'claims',
});
```

**Step 2: Add state** for main view switcher:

```js
const [mainView, setMainView] = useState('overtime'); // 'overtime' | 'claims'
```

**Step 3: Add view switcher UI** at the top of the page header:

```jsx
<div className="flex rounded-lg border border-zinc-200 p-0.5 bg-zinc-50 w-fit">
    <button
        onClick={() => setMainView('overtime')}
        className={cn('px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
            mainView === 'overtime' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700'
        )}
    >
        OT Requests
    </button>
    <button
        onClick={() => setMainView('claims')}
        className={cn('px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
            mainView === 'claims' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700'
        )}
    >
        OT Claims
    </button>
</div>
```

**Step 4: Add claims table** — when `mainView === 'claims'`, render a table with columns: Employee, Date, Start Time, Duration, Status, Notes. No action buttons (HR is view-only). Reuse the existing table styling.

```jsx
{mainView === 'claims' && (
    <table className="w-full text-sm">
        <thead>
            <tr className="border-b border-zinc-100">
                <th className="pb-3 text-left font-medium text-zinc-500 text-xs uppercase">Employee</th>
                <th className="pb-3 text-left font-medium text-zinc-500 text-xs uppercase">Date</th>
                <th className="pb-3 text-left font-medium text-zinc-500 text-xs uppercase">Start</th>
                <th className="pb-3 text-left font-medium text-zinc-500 text-xs uppercase">Duration</th>
                <th className="pb-3 text-left font-medium text-zinc-500 text-xs uppercase">Status</th>
            </tr>
        </thead>
        <tbody className="divide-y divide-zinc-50">
            {(claimsData?.data ?? []).map((claim) => (
                <tr key={claim.id} className="hover:bg-zinc-50">
                    <td className="py-3">
                        <div>
                            <p className="font-medium text-zinc-900">{claim.employee?.full_name}</p>
                            <p className="text-xs text-zinc-400">{claim.employee?.department?.name}</p>
                        </div>
                    </td>
                    <td className="py-3 text-zinc-700">{formatDate(claim.claim_date)}</td>
                    <td className="py-3 text-zinc-700">{claim.start_time}</td>
                    <td className="py-3 text-zinc-700">{formatDuration(claim.duration_minutes)}</td>
                    <td className="py-3">
                        <StatusBadge status={claim.status} />
                    </td>
                </tr>
            ))}
        </tbody>
    </table>
)}
```

> Reuse whatever `formatDate`, `formatDuration`, `StatusBadge` helpers already exist in this file.

**Step 5: Build check**

```bash
npm run build 2>&1 | tail -20
```

**Step 6: Commit**

```bash
git add resources/js/hr/pages/attendance/OvertimeManagement.jsx
git commit -m "feat(hr): add OT claims view to OvertimeManagement page"
```

---

## Task 14: Final Verification

**Step 1: Run Pint on all changed PHP files**

```bash
vendor/bin/pint --dirty
```

**Step 2: Run tests**

```bash
php artisan test --compact
```

Expected: all passing.

**Step 3: Quick smoke test via tinker**

```bash
php artisan tinker --no-interaction --execute="
    \$claim = new App\Models\OvertimeClaimRequest;
    echo 'Model OK: ' . class_basename(\$claim) . PHP_EOL;
    echo 'Table: ' . \$claim->getTable() . PHP_EOL;
    echo 'Columns: ' . implode(', ', array_keys(\App\Models\OvertimeClaimRequest::first()?->getAttributes() ?? ['(empty)' => 1])) . PHP_EOL;
"
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(hr): complete OT claim feature implementation"
```
