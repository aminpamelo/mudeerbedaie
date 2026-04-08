<?php

declare(strict_types=1);

use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Helpers ---

function makeTieredSetup(): array
{
    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    // Tier 1 approver
    $tier1User = User::factory()->create(['role' => 'employee']);
    $tier1Employee = Employee::factory()->create([
        'user_id' => $tier1User->id,
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    // Tier 2 approver
    $tier2User = User::factory()->create(['role' => 'employee']);
    $tier2Employee = Employee::factory()->create([
        'user_id' => $tier2User->id,
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    // Regular employee (submitter)
    $submitterUser = User::factory()->create(['role' => 'employee']);
    $submitter = Employee::factory()->create([
        'user_id' => $submitterUser->id,
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    // Set up 2-tier approval for overtime
    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $tier1Employee->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);
    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $tier2Employee->id,
        'approval_type' => 'overtime',
        'tier' => 2,
    ]);

    return compact('dept', 'position', 'tier1User', 'tier1Employee', 'tier2User', 'tier2Employee', 'submitterUser', 'submitter');
}

// --- 1. Department approver store with tiers ---

test('department approver store creates records with correct tier values', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $emp1 = Employee::factory()->create();
    $emp2 = Employee::factory()->create();
    $emp3 = Employee::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/hr/department-approvers', [
            'department_id' => $dept->id,
            'ot_approvers' => [
                ['tier' => 1, 'employee_ids' => [$emp1->id]],
                ['tier' => 2, 'employee_ids' => [$emp2->id, $emp3->id]],
            ],
        ]);

    $response->assertStatus(201);

    expect(DepartmentApprover::where('department_id', $dept->id)->count())->toBe(3);

    expect(DepartmentApprover::where('department_id', $dept->id)
        ->where('approval_type', 'overtime')
        ->where('tier', 1)
        ->where('approver_employee_id', $emp1->id)
        ->exists())->toBeTrue();

    expect(DepartmentApprover::where('department_id', $dept->id)
        ->where('approval_type', 'overtime')
        ->where('tier', 2)
        ->count())->toBe(2);
});

// --- 2. Department approver update with tiers ---

test('department approver update replaces old records with new tier configuration', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $emp1 = Employee::factory()->create();
    $emp2 = Employee::factory()->create();
    $emp3 = Employee::factory()->create();

    // Create initial config
    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $emp1->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);

    // Update with new tier config
    $response = $this->actingAs($admin)
        ->putJson("/api/hr/department-approvers/{$dept->id}", [
            'department_id' => $dept->id,
            'ot_approvers' => [
                ['tier' => 1, 'employee_ids' => [$emp2->id]],
                ['tier' => 2, 'employee_ids' => [$emp3->id]],
            ],
        ]);

    $response->assertSuccessful();

    // Old record should be gone
    expect(DepartmentApprover::where('approver_employee_id', $emp1->id)->exists())->toBeFalse();

    // New records should exist
    expect(DepartmentApprover::where('department_id', $dept->id)->count())->toBe(2);
    expect(DepartmentApprover::where('approver_employee_id', $emp2->id)->where('tier', 1)->exists())->toBeTrue();
    expect(DepartmentApprover::where('approver_employee_id', $emp3->id)->where('tier', 2)->exists())->toBeTrue();
});

// --- 3. Department approver index returns tier-grouped data ---

test('department approver index returns tier-grouped data by type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $emp1 = Employee::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
    $emp2 = Employee::factory()->create(['department_id' => $dept->id, 'status' => 'active']);

    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $emp1->id,
        'approval_type' => 'overtime',
        'tier' => 1,
    ]);
    DepartmentApprover::create([
        'department_id' => $dept->id,
        'approver_employee_id' => $emp2->id,
        'approval_type' => 'overtime',
        'tier' => 2,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/department-approvers')
        ->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);

    $deptData = $data[0];
    expect($deptData['department_id'])->toBe($dept->id);

    // ot_approvers should be grouped by tier
    expect($deptData['ot_approvers'])->toHaveKey('1');
    expect($deptData['ot_approvers'])->toHaveKey('2');
});

// --- 4. Tier 1 approve advances to Tier 2 ---

test('tier 1 approval advances request to tier 2 and status stays pending', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $response = $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->current_approval_tier)->toBe(2);
    expect($otRequest->status)->toBe('pending');
});

// --- 5. Final tier approve fully approves ---

test('final tier approval changes status to completed', function () {
    $setup = makeTieredSetup();

    // Request already at tier 2 (the max tier)
    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 2,
    ]);

    $response = $this->actingAs($setup['tier2User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->status)->toBe('completed');
});

// --- 6. Immediate rejection at any tier ---

test('rejection at tier 1 immediately sets status to rejected', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/reject", [
            'rejection_reason' => 'Not enough justification for this overtime request.',
        ])
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->status)->toBe('rejected');
});

test('rejection at tier 2 immediately sets status to rejected', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 2,
    ]);

    $this->actingAs($setup['tier2User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/reject", [
            'rejection_reason' => 'Budget constraints prevent this overtime approval.',
        ])
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->status)->toBe('rejected');
});

// --- 7. Unauthorized tier check ---

test('tier 2 approver cannot approve a request at tier 1', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier2User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertForbidden();
});

test('tier 1 approver cannot approve a request at tier 2', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 2,
    ]);

    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertForbidden();
});

// --- 8. Approval logs created ---

test('approval log is created after tier 1 approval', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    $log = ApprovalLog::where('approvable_id', $otRequest->id)
        ->where('approvable_type', OvertimeRequest::class)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->tier)->toBe(1);
    expect($log->approver_id)->toBe($setup['tier1Employee']->id);
    expect($log->action)->toBe('approved');
});

test('rejection log is created after rejection', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/reject", [
            'rejection_reason' => 'This request lacks sufficient details.',
        ])
        ->assertSuccessful();

    $log = ApprovalLog::where('approvable_id', $otRequest->id)
        ->where('approvable_type', OvertimeRequest::class)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->tier)->toBe(1);
    expect($log->approver_id)->toBe($setup['tier1Employee']->id);
    expect($log->action)->toBe('rejected');
    expect($log->notes)->toBe('This request lacks sufficient details.');
});

test('full two-tier approval creates two log entries', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    // Tier 1 approve
    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    // Tier 2 approve
    $this->actingAs($setup['tier2User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    $logs = ApprovalLog::where('approvable_id', $otRequest->id)
        ->where('approvable_type', OvertimeRequest::class)
        ->orderBy('tier')
        ->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0]->tier)->toBe(1);
    expect($logs[0]->approver_id)->toBe($setup['tier1Employee']->id);
    expect($logs[1]->tier)->toBe(2);
    expect($logs[1]->approver_id)->toBe($setup['tier2Employee']->id);

    // Final status should be completed
    expect($otRequest->fresh()->status)->toBe('completed');
});

// --- 9. My approvals summary tier-aware ---

test('tier 1 approver sees requests at tier 1 in summary count', function () {
    $setup = makeTieredSetup();

    OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier1User'])
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson([
            'isApprover' => true,
            'overtime' => ['pending' => 1, 'isAssigned' => true],
        ]);
});

test('tier 2 approver does not see requests at tier 1 in summary count', function () {
    $setup = makeTieredSetup();

    OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $this->actingAs($setup['tier2User'])
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson([
            'isApprover' => true,
            'overtime' => ['pending' => 0, 'isAssigned' => true],
        ]);
});

test('after tier 1 approves, tier 2 approver sees the request in summary', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    // Tier 1 approves
    $this->actingAs($setup['tier1User'])
        ->patchJson("/api/hr/my-approvals/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    // Now tier 2 should see it
    $this->actingAs($setup['tier2User'])
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson([
            'overtime' => ['pending' => 1, 'isAssigned' => true],
        ]);

    // Tier 1 should no longer see it
    $this->actingAs($setup['tier1User'])
        ->getJson('/api/hr/my-approvals/summary')
        ->assertSuccessful()
        ->assertJson([
            'overtime' => ['pending' => 0, 'isAssigned' => true],
        ]);
});

test('tier 1 approver sees requests at tier 1 in overtime list', function () {
    $setup = makeTieredSetup();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $response = $this->actingAs($setup['tier1User'])
        ->getJson('/api/hr/my-approvals/overtime')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($otRequest->id);
});

test('tier 2 approver does not see requests at tier 1 in overtime list', function () {
    $setup = makeTieredSetup();

    OvertimeRequest::factory()->create([
        'employee_id' => $setup['submitter']->id,
        'status' => 'pending',
        'current_approval_tier' => 1,
    ]);

    $response = $this->actingAs($setup['tier2User'])
        ->getJson('/api/hr/my-approvals/overtime')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toBeEmpty();
});
