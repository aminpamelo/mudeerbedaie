# HOD Approver Portal Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a "My Approvals" section to the employee HR portal, visible only to employees assigned as department approvers, showing only their scoped department's requests with limited employee data.

**Architecture:** New `HrMyApprovalController` scopes all queries by the logged-in employee's assigned departments from `department_approvers`. Frontend adds 4 new React pages under `/hr/my/approvals/*` using existing React Query + Dialog patterns. The `EmployeeAppLayout` sidebar conditionally shows the nav item based on a `summary` API call.

**Tech Stack:** Laravel 12, PHP 8.3, React 19, TanStack Query, React Router, Lucide icons, Tailwind CSS v4

---

## Task 1: Create test file with failing tests for the summary endpoint

**Files:**
- Create: `tests/Feature/Hr/HrMyApprovalTest.php`

**Step 1: Create the test file**

Run:
```bash
php artisan make:test --pest Hr/HrMyApprovalTest
```

**Step 2: Replace the generated file with these tests**

```php
<?php

declare(strict_types=1);

use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ───────────────────────────────────────────────────────────────

function makeApproverEmployee(): array
{
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $subordinateUser = User::factory()->create(['role' => 'employee']);
    $subordinate = Employee::factory()->create([
        'user_id' => $subordinateUser->id,
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $outsiderUser = User::factory()->create(['role' => 'employee']);
    $outsider = Employee::factory()->create([
        'user_id' => $outsiderUser->id,
        'department_id' => $otherDept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('dept', 'otherDept', 'user', 'employee', 'subordinate', 'outsider');
}

// ─── Summary Tests ─────────────────────────────────────────────────────────

test('unauthenticated user cannot access summary', function () {
    $this->getJson('/api/hr/my-approvals/summary')->assertUnauthorized();
});

test('employee not assigned as approver gets isApprover false', function () {
    ['user' => $user] = makeApproverEmployee();

    $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson(['isApprover' => false]);
});

test('employee assigned as ot approver gets isApprover true with pending count', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    OvertimeRequest::factory()->create(['employee_id' => $subordinate->id, 'status' => 'pending']);
    OvertimeRequest::factory()->create(['employee_id' => $subordinate->id, 'status' => 'approved']);

    $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson([
            'isApprover' => true,
            'overtime' => ['pending' => 1, 'isAssigned' => true],
            'leave' => ['pending' => 0, 'isAssigned' => false],
            'claims' => ['pending' => 0, 'isAssigned' => false],
        ]);
});

test('summary does not count requests from other departments', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'outsider' => $outsider] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    OvertimeRequest::factory()->create(['employee_id' => $outsider->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/summary')
        ->assertJson(['overtime' => ['pending' => 0]]);
});

// ─── Overtime List Tests ────────────────────────────────────────────────────

test('ot approver can list overtime requests for their department only', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate, 'outsider' => $outsider] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    $ownRequest = OvertimeRequest::factory()->create(['employee_id' => $subordinate->id, 'status' => 'pending']);
    $otherRequest = OvertimeRequest::factory()->create(['employee_id' => $outsider->id, 'status' => 'pending']);

    $response = $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/overtime')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownRequest->id)
        ->not->toContain($otherRequest->id);
});

test('non-ot-approver gets empty list for overtime', function () {
    ['user' => $user] = makeApproverEmployee();

    $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/overtime')
        ->assertSuccessful()
        ->assertJson(['data' => []]);
});

// ─── Overtime Approve/Reject Tests ─────────────────────────────────────────

test('ot approver can approve a pending request in their department', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    $request = OvertimeRequest::factory()->create(['employee_id' => $subordinate->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->patchJson("/api/hr/my-approvals/overtime/{$request->id}/approve")
        ->assertSuccessful();

    expect($request->fresh()->status)->toBe('approved');
});

test('ot approver cannot approve request from another department', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'outsider' => $outsider] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    $request = OvertimeRequest::factory()->create(['employee_id' => $outsider->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->patchJson("/api/hr/my-approvals/overtime/{$request->id}/approve")
        ->assertForbidden();
});

test('ot approver can reject with reason', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'overtime',
    ]);

    $request = OvertimeRequest::factory()->create(['employee_id' => $subordinate->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->patchJson("/api/hr/my-approvals/overtime/{$request->id}/reject", [
            'rejection_reason' => 'Not enough justification provided.',
        ])
        ->assertSuccessful();

    expect($request->fresh()->status)->toBe('rejected');
});

// ─── Leave List Tests ───────────────────────────────────────────────────────

test('leave approver can list leave requests for their department only', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate, 'outsider' => $outsider] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'leave',
    ]);

    $leaveType = LeaveType::factory()->create();
    $ownLeave = LeaveRequest::factory()->create(['employee_id' => $subordinate->id, 'leave_type_id' => $leaveType->id, 'status' => 'pending']);
    $otherLeave = LeaveRequest::factory()->create(['employee_id' => $outsider->id, 'leave_type_id' => $leaveType->id, 'status' => 'pending']);

    $response = $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/leave')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownLeave->id)
        ->not->toContain($otherLeave->id);
});

// ─── Claims List Tests ──────────────────────────────────────────────────────

test('claims approver can list claim requests for their department only', function () {
    ['user' => $user, 'employee' => $employee, 'dept' => $dept, 'subordinate' => $subordinate, 'outsider' => $outsider] = makeApproverEmployee();

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $employee->id,
        'approval_type' => 'claims',
    ]);

    $claimType = ClaimType::factory()->create();
    $ownClaim = ClaimRequest::factory()->create(['employee_id' => $subordinate->id, 'claim_type_id' => $claimType->id, 'status' => 'pending']);
    $otherClaim = ClaimRequest::factory()->create(['employee_id' => $outsider->id, 'claim_type_id' => $claimType->id, 'status' => 'pending']);

    $response = $this->actingAs($user)
        ->getJson('/api/hr/my-approvals/claims')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownClaim->id)
        ->not->toContain($otherClaim->id);
});
```

**Step 3: Run tests to confirm they all fail (routes don't exist yet)**

```bash
php artisan test --compact tests/Feature/Hr/HrMyApprovalTest.php
```
Expected: All tests fail with 404 or route not found errors.

---

## Task 2: Create the controller

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrMyApprovalController.php`

**Step 1: Create the controller**

```bash
php artisan make:controller Api/Hr/HrMyApprovalController --no-interaction
```

**Step 2: Replace the generated file with this implementation**

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrMyApprovalController extends Controller
{
    private function getDeptIds(int $employeeId, string $type): array
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('approval_type', $type)
            ->pluck('department_id')
            ->toArray();
    }

    private function getEmployee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $otDepts = $this->getDeptIds($employee->id, 'overtime');
        $leaveDepts = $this->getDeptIds($employee->id, 'leave');
        $claimDepts = $this->getDeptIds($employee->id, 'claims');
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        $otPending = empty($otDepts) ? 0
            : OvertimeRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $otDepts))
                ->where('status', 'pending')
                ->count();

        $leavePending = empty($leaveDepts) ? 0
            : LeaveRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $leaveDepts))
                ->where('status', 'pending')
                ->count();

        $claimPending = 0;
        $isClaimsAssigned = ! empty($claimDepts) || $isIndividualClaims;

        if ($isClaimsAssigned) {
            $claimQuery = ClaimRequest::where('status', 'pending');

            if (! empty($claimDepts) && $isIndividualClaims) {
                $claimQuery->where(function ($q) use ($claimDepts, $employee) {
                    $q->whereHas('employee', fn ($sq) => $sq->whereIn('department_id', $claimDepts))
                        ->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
                });
            } elseif (! empty($claimDepts)) {
                $claimQuery->whereHas('employee', fn ($q) => $q->whereIn('department_id', $claimDepts));
            } else {
                $claimQuery->whereHas('employee.claimApprovers', fn ($q) => $q->where('approver_id', $employee->id)->where('is_active', true));
            }

            $claimPending = $claimQuery->count();
        }

        $isApprover = ! empty($otDepts) || ! empty($leaveDepts) || $isClaimsAssigned;

        return response()->json([
            'isApprover' => $isApprover,
            'overtime' => ['pending' => $otPending, 'isAssigned' => ! empty($otDepts)],
            'leave' => ['pending' => $leavePending, 'isAssigned' => ! empty($leaveDepts)],
            'claims' => ['pending' => $claimPending, 'isAssigned' => $isClaimsAssigned],
        ]);
    }

    // ── Overtime ─────────────────────────────────────────────────────────────

    public function overtime(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = OvertimeRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (! in_array($overtimeRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest)
            );
        }

        return response()->json($overtimeRequest->fresh(['employee.position', 'employee.department']));
    }

    public function rejectOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (! in_array($overtimeRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $overtimeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest)
            );
        }

        return response()->json($overtimeRequest->fresh(['employee.position', 'employee.department']));
    }

    // ── Leave ─────────────────────────────────────────────────────────────────

    public function leave(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = LeaveRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
            'leaveType:id,name,color',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (! in_array($leaveRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        // Delegate to existing controller which has full transaction logic
        $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);

        return $controller->approve($request, $leaveRequest);
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (! in_array($leaveRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);

        return $controller->reject($request, $leaveRequest);
    }

    // ── Claims ────────────────────────────────────────────────────────────────

    public function claims(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        if (empty($deptIds) && ! $isIndividualClaims) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = ClaimRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
            'claimType:id,name',
        ]);

        if (! empty($deptIds) && $isIndividualClaims) {
            $query->where(function ($q) use ($deptIds, $employee) {
                $q->whereHas('employee', fn ($sq) => $sq->whereIn('department_id', $deptIds))
                    ->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
            });
        } elseif (! empty($deptIds)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));
        } else {
            $query->whereHas('employee.claimApprovers', fn ($q) => $q->where('approver_id', $employee->id)->where('is_active', true));
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveClaim(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $inDept = ! empty($deptIds) && in_array($claimRequest->employee->department_id, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);

        return $controller->approve($request, $claimRequest);
    }

    public function rejectClaim(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $inDept = ! empty($deptIds) && in_array($claimRequest->employee->department_id, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);

        return $controller->reject($request, $claimRequest);
    }
}
```

**Step 3: Commit the controller**

```bash
git add app/Http/Controllers/Api/Hr/HrMyApprovalController.php
git commit -m "feat(hr): add HrMyApprovalController for HOD-scoped approvals"
```

---

## Task 3: Register API routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Find where the HR API routes group ends**

Search for `my-approvals` to confirm it doesn't exist yet. Then look for a good insertion point in `routes/api.php` near the other `me/` routes.

**Step 2: Add the routes inside the existing `auth:sanctum, role:admin,employee` HR group**

Find the block near `Route::get('me/overtime', ...)` and add AFTER the last `me/` route block:

```php
// HOD Approvals (scoped to assigned departments)
Route::prefix('my-approvals')->group(function () {
    Route::get('summary', [HrMyApprovalController::class, 'summary']);

    Route::get('overtime', [HrMyApprovalController::class, 'overtime']);
    Route::patch('overtime/{overtimeRequest}/approve', [HrMyApprovalController::class, 'approveOvertime']);
    Route::patch('overtime/{overtimeRequest}/reject', [HrMyApprovalController::class, 'rejectOvertime']);

    Route::get('leave', [HrMyApprovalController::class, 'leave']);
    Route::patch('leave/{leaveRequest}/approve', [HrMyApprovalController::class, 'approveLeave']);
    Route::patch('leave/{leaveRequest}/reject', [HrMyApprovalController::class, 'rejectLeave']);

    Route::get('claims', [HrMyApprovalController::class, 'claims']);
    Route::patch('claims/{claimRequest}/approve', [HrMyApprovalController::class, 'approveClaim']);
    Route::patch('claims/{claimRequest}/reject', [HrMyApprovalController::class, 'rejectClaim']);
});
```

Add the import at the top of the routes file (with the other Hr controller imports):

```php
use App\Http\Controllers\Api\Hr\HrMyApprovalController;
```

**Step 3: Run the tests — they should pass now**

```bash
php artisan test --compact tests/Feature/Hr/HrMyApprovalTest.php
```
Expected: All tests PASS.

**Step 4: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 5: Commit**

```bash
git add routes/api.php
git commit -m "feat(hr): register HOD my-approvals API routes"
```

---

## Task 4: Frontend — MyApprovals.jsx (summary dashboard)

**Files:**
- Create: `resources/js/hr/pages/my/MyApprovals.jsx`

**Step 1: Create the file**

```jsx
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Timer, CalendarOff, Receipt, ShieldCheck } from 'lucide-react';
import api from '../../lib/api';

function fetchApprovalSummary() {
    return api.get('/my-approvals/summary').then((r) => r.data);
}

function StatCard({ icon: Icon, label, pending, isAssigned, onClick, color }) {
    return (
        <button
            onClick={onClick}
            disabled={!isAssigned}
            className={`flex flex-col gap-3 rounded-xl border p-5 text-left transition-all ${
                isAssigned
                    ? 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md cursor-pointer'
                    : 'border-slate-100 bg-slate-50 cursor-not-allowed opacity-60'
            }`}
        >
            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${color}`}>
                <Icon className="h-5 w-5 text-white" />
            </div>
            <div>
                <p className="text-sm text-slate-500">{label}</p>
                {isAssigned ? (
                    <p className="text-2xl font-bold text-slate-800">
                        {pending}
                        <span className="ml-1 text-sm font-normal text-slate-400">pending</span>
                    </p>
                ) : (
                    <p className="text-sm text-slate-400 mt-1">Not assigned</p>
                )}
            </div>
        </button>
    );
}

export default function MyApprovals() {
    const navigate = useNavigate();
    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-summary'],
        queryFn: fetchApprovalSummary,
    });

    if (isLoading) {
        return (
            <div className="flex h-48 items-center justify-center">
                <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-3xl p-4 lg:p-6">
            <div className="mb-6 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600">
                    <ShieldCheck className="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 className="text-xl font-bold text-slate-800">My Approvals</h1>
                    <p className="text-sm text-slate-500">Review and action requests from your team</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    icon={Timer}
                    label="Overtime"
                    pending={data?.overtime?.pending ?? 0}
                    isAssigned={data?.overtime?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/overtime')}
                    color="bg-orange-500"
                />
                <StatCard
                    icon={CalendarOff}
                    label="Leave"
                    pending={data?.leave?.pending ?? 0}
                    isAssigned={data?.leave?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/leave')}
                    color="bg-blue-500"
                />
                <StatCard
                    icon={Receipt}
                    label="Claims"
                    pending={data?.claims?.pending ?? 0}
                    isAssigned={data?.claims?.isAssigned ?? false}
                    onClick={() => navigate('/my/approvals/claims')}
                    color="bg-green-500"
                />
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/hr/pages/my/MyApprovals.jsx
git commit -m "feat(hr): add MyApprovals summary dashboard page"
```

---

## Task 5: Frontend — MyApprovalsOvertime.jsx

**Files:**
- Create: `resources/js/hr/pages/my/MyApprovalsOvertime.jsx`

**Step 1: Create the file**

```jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../ui/dialog';
import api from '../../lib/api';

const TABS = ['all', 'pending', 'approved', 'rejected', 'completed'];

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    completed: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-slate-100 text-slate-600',
};

function fetchOvertimeApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/overtime${params}`).then((r) => r.data);
}

export default function MyApprovalsOvertime() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [rejectDialog, setRejectDialog] = useState(null); // { id, name }
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-overtime', tab],
        queryFn: () => fetchOvertimeApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: (id) => api.patch(`/my-approvals/overtime/${id}/approve`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['my-approvals-overtime'] }),
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/overtime/${id}/reject`, { rejection_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-overtime'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setRejectDialog(null);
            setRejectReason('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const requests = data?.data ?? [];

    return (
        <div className="mx-auto max-w-4xl p-4 lg:p-6">
            <div className="mb-5 flex items-center gap-3">
                <button onClick={() => navigate('/my/approvals')} className="text-slate-400 hover:text-slate-600">
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="text-xl font-bold text-slate-800">Overtime Approvals</h1>
            </div>

            {/* Tabs */}
            <div className="mb-4 flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`flex-shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                            tab === t ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 py-12 text-center text-slate-400">
                    No {tab === 'all' ? '' : tab} overtime requests
                </div>
            ) : (
                <div className="space-y-3">
                    {requests.map((req) => (
                        <div key={req.id} className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">{req.employee?.name}</p>
                                    <p className="text-sm text-slate-500">
                                        {req.employee?.position?.name} · {req.employee?.department?.name}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {req.requested_date} · {req.estimated_hours}h estimated
                                    </p>
                                    {req.reason && (
                                        <p className="mt-1 text-sm text-slate-500 line-clamp-2">{req.reason}</p>
                                    )}
                                    {req.rejection_reason && (
                                        <p className="mt-1 text-sm text-red-500">Reason: {req.rejection_reason}</p>
                                    )}
                                </div>
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${STATUS_COLORS[req.status] ?? 'bg-slate-100 text-slate-600'}`}
                                >
                                    {req.status}
                                </span>
                            </div>

                            {req.status === 'pending' && (
                                <div className="mt-3 flex gap-2">
                                    <button
                                        onClick={() => approveMut.mutate(req.id)}
                                        disabled={approveMut.isPending}
                                        className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                    >
                                        <Check className="h-4 w-4" /> Approve
                                    </button>
                                    <button
                                        onClick={() => {
                                            setRejectDialog({ id: req.id, name: req.employee?.name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                        className="flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <X className="h-4 w-4" /> Reject
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Reject Dialog */}
            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Overtime Request</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Rejecting request from <strong>{rejectDialog?.name}</strong>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-2 w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })}
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {rejectMut.isPending ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/hr/pages/my/MyApprovalsOvertime.jsx
git commit -m "feat(hr): add MyApprovalsOvertime page for HOD"
```

---

## Task 6: Frontend — MyApprovalsLeave.jsx

**Files:**
- Create: `resources/js/hr/pages/my/MyApprovalsLeave.jsx`

**Step 1: Create the file**

Follow the same pattern as `MyApprovalsOvertime.jsx` — same structure, different API path and fields:

```jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../ui/dialog';
import api from '../../lib/api';

const TABS = ['all', 'pending', 'approved', 'rejected', 'cancelled'];

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-600',
};

function fetchLeaveApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/leave${params}`).then((r) => r.data);
}

export default function MyApprovalsLeave() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-leave', tab],
        queryFn: () => fetchLeaveApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: (id) => api.patch(`/my-approvals/leave/${id}/approve`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-leave'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
        },
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/leave/${id}/reject`, { rejection_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-leave'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setRejectDialog(null);
            setRejectReason('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const requests = data?.data ?? [];

    return (
        <div className="mx-auto max-w-4xl p-4 lg:p-6">
            <div className="mb-5 flex items-center gap-3">
                <button onClick={() => navigate('/my/approvals')} className="text-slate-400 hover:text-slate-600">
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="text-xl font-bold text-slate-800">Leave Approvals</h1>
            </div>

            <div className="mb-4 flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`flex-shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                            tab === t ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 py-12 text-center text-slate-400">
                    No {tab === 'all' ? '' : tab} leave requests
                </div>
            ) : (
                <div className="space-y-3">
                    {requests.map((req) => (
                        <div key={req.id} className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">{req.employee?.name}</p>
                                    <p className="text-sm text-slate-500">
                                        {req.employee?.position?.name} · {req.employee?.department?.name}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {req.leave_type?.name} · {req.start_date} – {req.end_date}
                                        {' '}({req.total_days} day{req.total_days !== 1 ? 's' : ''})
                                    </p>
                                    {req.reason && (
                                        <p className="mt-1 text-sm text-slate-500 line-clamp-2">{req.reason}</p>
                                    )}
                                    {req.rejection_reason && (
                                        <p className="mt-1 text-sm text-red-500">Reason: {req.rejection_reason}</p>
                                    )}
                                </div>
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${STATUS_COLORS[req.status] ?? 'bg-slate-100 text-slate-600'}`}
                                >
                                    {req.status}
                                </span>
                            </div>

                            {req.status === 'pending' && (
                                <div className="mt-3 flex gap-2">
                                    <button
                                        onClick={() => approveMut.mutate(req.id)}
                                        disabled={approveMut.isPending}
                                        className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                    >
                                        <Check className="h-4 w-4" /> Approve
                                    </button>
                                    <button
                                        onClick={() => {
                                            setRejectDialog({ id: req.id, name: req.employee?.name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                        className="flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <X className="h-4 w-4" /> Reject
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Leave Request</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Rejecting request from <strong>{rejectDialog?.name}</strong>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-2 w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })}
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {rejectMut.isPending ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/hr/pages/my/MyApprovalsLeave.jsx
git commit -m "feat(hr): add MyApprovalsLeave page for HOD"
```

---

## Task 7: Frontend — MyApprovalsClaims.jsx

**Files:**
- Create: `resources/js/hr/pages/my/MyApprovalsClaims.jsx`

**Step 1: Create the file**

Same pattern as Leave, but Claims approval requires entering an `approved_amount`:

```jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, X, AlertCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../../ui/dialog';
import api from '../../lib/api';

const TABS = ['all', 'pending', 'approved', 'rejected'];

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    paid: 'bg-blue-100 text-blue-800',
    draft: 'bg-slate-100 text-slate-600',
};

function fetchClaimsApprovals(status) {
    const params = status !== 'all' ? `?status=${status}` : '';
    return api.get(`/my-approvals/claims${params}`).then((r) => r.data);
}

export default function MyApprovalsClaims() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [tab, setTab] = useState('pending');
    const [approveDialog, setApproveDialog] = useState(null); // { id, name, amount }
    const [approvedAmount, setApprovedAmount] = useState('');
    const [rejectDialog, setRejectDialog] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [actionError, setActionError] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['my-approvals-claims', tab],
        queryFn: () => fetchClaimsApprovals(tab),
    });

    const approveMut = useMutation({
        mutationFn: ({ id, amount }) =>
            api.patch(`/my-approvals/claims/${id}/approve`, { approved_amount: amount }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-claims'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setApproveDialog(null);
            setApprovedAmount('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to approve.'),
    });

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }) =>
            api.patch(`/my-approvals/claims/${id}/reject`, { rejected_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['my-approvals-claims'] });
            qc.invalidateQueries({ queryKey: ['my-approvals-summary'] });
            setRejectDialog(null);
            setRejectReason('');
        },
        onError: (err) => setActionError(err.response?.data?.message ?? 'Failed to reject.'),
    });

    const requests = data?.data ?? [];

    return (
        <div className="mx-auto max-w-4xl p-4 lg:p-6">
            <div className="mb-5 flex items-center gap-3">
                <button onClick={() => navigate('/my/approvals')} className="text-slate-400 hover:text-slate-600">
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <h1 className="text-xl font-bold text-slate-800">Claims Approvals</h1>
            </div>

            <div className="mb-4 flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`flex-shrink-0 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                            tab === t ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
                </div>
            ) : requests.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 py-12 text-center text-slate-400">
                    No {tab === 'all' ? '' : tab} claim requests
                </div>
            ) : (
                <div className="space-y-3">
                    {requests.map((req) => (
                        <div key={req.id} className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">{req.employee?.name}</p>
                                    <p className="text-sm text-slate-500">
                                        {req.employee?.position?.name} · {req.employee?.department?.name}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {req.claim_type?.name} · RM {Number(req.amount).toFixed(2)}
                                        {req.approved_amount && ` → approved RM ${Number(req.approved_amount).toFixed(2)}`}
                                    </p>
                                    <p className="text-sm text-slate-500">{req.claim_date}</p>
                                    {req.description && (
                                        <p className="mt-1 text-sm text-slate-500 line-clamp-2">{req.description}</p>
                                    )}
                                    {req.rejected_reason && (
                                        <p className="mt-1 text-sm text-red-500">Reason: {req.rejected_reason}</p>
                                    )}
                                </div>
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${STATUS_COLORS[req.status] ?? 'bg-slate-100 text-slate-600'}`}
                                >
                                    {req.status}
                                </span>
                            </div>

                            {req.status === 'pending' && (
                                <div className="mt-3 flex gap-2">
                                    <button
                                        onClick={() => {
                                            setApproveDialog({ id: req.id, name: req.employee?.name, amount: req.amount });
                                            setApprovedAmount(String(req.amount));
                                            setActionError('');
                                        }}
                                        className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                                    >
                                        <Check className="h-4 w-4" /> Approve
                                    </button>
                                    <button
                                        onClick={() => {
                                            setRejectDialog({ id: req.id, name: req.employee?.name });
                                            setRejectReason('');
                                            setActionError('');
                                        }}
                                        className="flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <X className="h-4 w-4" /> Reject
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* Approve Dialog */}
            <Dialog open={!!approveDialog} onOpenChange={() => setApproveDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Claim</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Approving claim from <strong>{approveDialog?.name}</strong>. Requested: RM {Number(approveDialog?.amount ?? 0).toFixed(2)}
                    </p>
                    <div className="mt-2">
                        <label className="mb-1 block text-sm font-medium text-slate-700">Approved Amount (RM)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            className="w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            value={approvedAmount}
                            onChange={(e) => setApprovedAmount(e.target.value)}
                        />
                    </div>
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setApproveDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => approveMut.mutate({ id: approveDialog.id, amount: parseFloat(approvedAmount) })}
                            disabled={!approvedAmount || parseFloat(approvedAmount) <= 0 || approveMut.isPending}
                            className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {approveMut.isPending ? 'Approving...' : 'Confirm Approve'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={!!rejectDialog} onOpenChange={() => setRejectDialog(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Claim</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-slate-500">
                        Rejecting claim from <strong>{rejectDialog?.name}</strong>. Please provide a reason.
                    </p>
                    <textarea
                        className="mt-2 w-full rounded-lg border border-slate-200 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        rows={3}
                        placeholder="Reason for rejection (min 5 characters)"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                    />
                    {actionError && (
                        <p className="flex items-center gap-1 text-sm text-red-500">
                            <AlertCircle className="h-4 w-4" /> {actionError}
                        </p>
                    )}
                    <DialogFooter>
                        <button
                            onClick={() => setRejectDialog(null)}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={() => rejectMut.mutate({ id: rejectDialog.id, reason: rejectReason })}
                            disabled={rejectReason.length < 5 || rejectMut.isPending}
                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {rejectMut.isPending ? 'Rejecting...' : 'Confirm Reject'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/hr/pages/my/MyApprovalsClaims.jsx
git commit -m "feat(hr): add MyApprovalsClaims page for HOD"
```

---

## Task 8: Wire up routes in App.jsx

**Files:**
- Modify: `resources/js/hr/App.jsx`

**Step 1: Add imports at the top (with other My page imports, around line 124)**

```jsx
import MyApprovals from './pages/my/MyApprovals';
import MyApprovalsOvertime from './pages/my/MyApprovalsOvertime';
import MyApprovalsLeave from './pages/my/MyApprovalsLeave';
import MyApprovalsClaims from './pages/my/MyApprovalsClaims';
```

**Step 2: Add routes inside `EmployeeRoutes()` (after the existing my/* routes, before `notifications`)**

```jsx
<Route path="my/approvals" element={<MyApprovals />} />
<Route path="my/approvals/overtime" element={<MyApprovalsOvertime />} />
<Route path="my/approvals/leave" element={<MyApprovalsLeave />} />
<Route path="my/approvals/claims" element={<MyApprovalsClaims />} />
```

**Step 3: Commit**

```bash
git add resources/js/hr/App.jsx
git commit -m "feat(hr): add my/approvals routes to App.jsx"
```

---

## Task 9: Add conditional nav items in EmployeeAppLayout.jsx

**Files:**
- Modify: `resources/js/hr/layouts/EmployeeAppLayout.jsx`

**Step 1: Add imports at the top**

```jsx
import { useQuery } from '@tanstack/react-query';
import { ShieldCheck } from 'lucide-react';
import api from '../lib/api';
```

**Step 2: Add a hook to fetch approver status**

Inside the main layout component (or as a hook at the top of the file), add:

```jsx
function useApprovalSummary() {
    return useQuery({
        queryKey: ['my-approvals-summary'],
        queryFn: () => api.get('/my-approvals/summary').then((r) => r.data),
        staleTime: 1000 * 60 * 2, // 2 min
    });
}
```

**Step 3: Use the hook in the component that renders the sidebar**

In the component that renders `sidebarNav` and `moreMenuItems` (the main layout function or `SidebarNav` component), add:

```jsx
const { data: approvalData } = useApprovalSummary();
const isApprover = approvalData?.isApprover ?? false;
const totalPending = (approvalData?.overtime?.pending ?? 0)
    + (approvalData?.leave?.pending ?? 0)
    + (approvalData?.claims?.pending ?? 0);
```

**Step 4: Add the nav item to `sidebarNav` conditionally**

Replace the static `sidebarNav` array with a computed version:

```jsx
const sidebarNavItems = [
    ...sidebarNav,
    ...(isApprover
        ? [{ name: `My Approvals${totalPending > 0 ? ` (${totalPending})` : ''}`, to: '/my/approvals', icon: ShieldCheck }]
        : []),
];
```

Use `sidebarNavItems` wherever `sidebarNav` is currently mapped.

**Step 5: Add to `moreMenuItems` conditionally**

Similarly for mobile:

```jsx
const moreItems = [
    ...moreMenuItems,
    ...(isApprover
        ? [{ name: 'My Approvals', to: '/my/approvals', icon: ShieldCheck, description: 'Review and approve team requests' }]
        : []),
];
```

Use `moreItems` wherever `moreMenuItems` is currently mapped.

**Step 6: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 7: Commit**

```bash
git add resources/js/hr/layouts/EmployeeAppLayout.jsx
git commit -m "feat(hr): show My Approvals nav item for assigned approvers"
```

---

## Task 10: Build assets and verify

**Step 1: Build frontend assets**

```bash
npm run build
```

**Step 2: Run the full test suite for the new feature**

```bash
php artisan test --compact tests/Feature/Hr/HrMyApprovalTest.php
```
Expected: All tests PASS.

**Step 3: Manual smoke test**
1. Log in as an employee who is assigned as an OT approver in the Department Approvers page
2. Navigate to `/hr` — should see "My Approvals" in the sidebar
3. Click it — should see the summary dashboard with OT card showing pending count
4. Click Overtime — should see only requests from the assigned department
5. Log in as an employee NOT assigned as approver — should NOT see "My Approvals" in sidebar

**Step 4: Final commit if any cleanup needed**

```bash
vendor/bin/pint --dirty
git add -p
git commit -m "feat(hr): HOD approver portal complete"
```
