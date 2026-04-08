<?php

use App\Models\ApprovalLog;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\Hr\TierApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TierApprovalService;
});

test('getMaxTier returns the highest tier for a department and approval type', function () {
    $department = \App\Models\Department::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);
    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 3,
    ]);
    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 2,
    ]);

    expect($this->service->getMaxTier($department->id, 'overtime'))->toBe(3);
});

test('getMaxTier returns 1 when no approvers configured', function () {
    expect($this->service->getMaxTier(999, 'overtime'))->toBe(1);
});

test('isApproverForTier returns true when employee is assigned', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approver_employee_id' => $approver->id,
        'approval_type' => 'leave',
        'tier' => 2,
    ]);

    expect($this->service->isApproverForTier($approver->id, $department->id, 'leave', 2))->toBeTrue();
});

test('isApproverForTier returns false when employee is not assigned', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    expect($this->service->isApproverForTier($approver->id, $department->id, 'leave', 1))->toBeFalse();
});

test('getApproverTiers returns all tiers for an approver', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approver_employee_id' => $approver->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);
    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approver_employee_id' => $approver->id,
        'approval_type' => 'overtime',
        'tier' => 3,
    ]);

    $tiers = $this->service->getApproverTiers($approver->id, $department->id, 'overtime');

    expect($tiers)->toContain(1)->toContain(3)->toHaveCount(2);
});

test('approve advances tier when not at max', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);
    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 2,
    ]);

    $request = OvertimeRequest::factory()->create([
        'current_approval_tier' => 1,
    ]);

    $result = $this->service->approve($request, $approver, 'overtime', $department->id, 'Looks good');

    expect($result)->toBe(['advanced' => true, 'fully_approved' => false]);
    expect($request->fresh()->current_approval_tier)->toBe(2);
    expect(ApprovalLog::where('approvable_id', $request->id)->where('action', 'approved')->exists())->toBeTrue();
});

test('approve returns fully approved when at max tier', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);

    $request = OvertimeRequest::factory()->create([
        'current_approval_tier' => 1,
    ]);

    $result = $this->service->approve($request, $approver, 'overtime', $department->id);

    expect($result)->toBe(['advanced' => false, 'fully_approved' => true]);
    expect($request->fresh()->current_approval_tier)->toBe(1);
});

test('approve creates an approval log entry', function () {
    $department = \App\Models\Department::factory()->create();
    $approver = Employee::factory()->create();

    DepartmentApprover::factory()->create([
        'department_id' => $department->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);

    $request = OvertimeRequest::factory()->create([
        'current_approval_tier' => 1,
    ]);

    $this->service->approve($request, $approver, 'overtime', $department->id, 'Approved with note');

    $log = ApprovalLog::where('approvable_id', $request->id)->first();

    expect($log)
        ->approvable_type->toBe(OvertimeRequest::class)
        ->tier->toBe(1)
        ->approver_id->toBe($approver->id)
        ->action->toBe('approved')
        ->notes->toBe('Approved with note');
});

test('reject creates a rejection log entry', function () {
    $approver = Employee::factory()->create();

    $request = OvertimeRequest::factory()->create([
        'current_approval_tier' => 1,
    ]);

    $this->service->reject($request, $approver, 'Not justified');

    $log = ApprovalLog::where('approvable_id', $request->id)->first();

    expect($log)
        ->action->toBe('rejected')
        ->notes->toBe('Not justified')
        ->tier->toBe(1)
        ->approver_id->toBe($approver->id);
});
