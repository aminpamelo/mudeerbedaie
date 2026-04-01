<?php

declare(strict_types=1);

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
