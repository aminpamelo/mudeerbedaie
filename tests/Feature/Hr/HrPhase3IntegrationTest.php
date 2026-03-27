<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\HrPayslip;
use App\Models\Position;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{admin: User, adminEmployee: Employee, user: User, employee: Employee}
 */
function createPhase3Employee(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    // Admin also needs an employee record for asset assignment (assigned_by FK)
    $adminEmployee = Employee::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'employment_type' => 'full_time',
    ]);

    return compact('admin', 'adminEmployee', 'user', 'employee');
}

/*
|--------------------------------------------------------------------------
| 1. Payroll Calculation Cycle (End-to-End)
|--------------------------------------------------------------------------
*/

test('complete payroll workflow: create run, calculate, submit review, approve, finalize generates payslip', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $employee = $data['employee'];

    // Seed salary components
    app(\Database\Seeders\HrPhase3Seeder::class)->run();

    $basicComponent = SalaryComponent::where('code', 'BASIC')->first();
    expect($basicComponent)->not->toBeNull();

    // Assign basic salary to employee
    EmployeeSalary::create([
        'employee_id' => $employee->id,
        'salary_component_id' => $basicComponent->id,
        'amount' => 5000.00,
        'effective_from' => now()->subYear()->toDateString(),
        'effective_to' => null,
    ]);

    // Create tax profile (use firstOrCreate since seeder may have created one)
    EmployeeTaxProfile::firstOrCreate(
        ['employee_id' => $employee->id],
        [
            'marital_status' => 'single',
            'num_children' => 0,
            'disabled_individual' => false,
            'disabled_spouse' => false,
            'is_pcb_manual' => false,
        ]
    );

    // Step 1: Create a payroll run
    $response = $this->actingAs($admin)
        ->postJson('/api/hr/payroll/runs', [
            'month' => 3,
            'year' => 2026,
        ]);

    $response->assertCreated();
    $runId = $response->json('data.id');
    expect($runId)->not->toBeNull();

    $this->assertDatabaseHas('payroll_runs', [
        'id' => $runId,
        'month' => 3,
        'year' => 2026,
        'status' => 'draft',
    ]);

    // Step 2: Calculate payroll
    $this->actingAs($admin)
        ->postJson("/api/hr/payroll/runs/{$runId}/calculate")
        ->assertSuccessful();

    // Verify payroll items were created for this employee
    $this->assertDatabaseHas('payroll_items', [
        'payroll_run_id' => $runId,
        'employee_id' => $employee->id,
        'component_code' => 'BASIC',
    ]);

    // Step 3: Submit for review
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$runId}/submit-review")
        ->assertSuccessful();

    $this->assertDatabaseHas('payroll_runs', [
        'id' => $runId,
        'status' => 'review',
    ]);

    // Step 4: Approve
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$runId}/approve")
        ->assertSuccessful();

    $this->assertDatabaseHas('payroll_runs', [
        'id' => $runId,
        'status' => 'approved',
    ]);

    // Step 5: Finalize (generates payslips)
    $this->actingAs($admin)
        ->patchJson("/api/hr/payroll/runs/{$runId}/finalize")
        ->assertSuccessful();

    $this->assertDatabaseHas('payroll_runs', [
        'id' => $runId,
        'status' => 'finalized',
    ]);

    // Verify payslip was created in hr_payslips table
    $this->assertDatabaseHas('hr_payslips', [
        'payroll_run_id' => $runId,
        'employee_id' => $employee->id,
        'month' => 3,
        'year' => 2026,
    ]);

    $payslip = HrPayslip::where('payroll_run_id', $runId)
        ->where('employee_id', $employee->id)
        ->first();

    expect($payslip)->not->toBeNull();
    expect((float) $payslip->gross_salary)->toBeGreaterThan(0);
    expect((float) $payslip->net_salary)->toBeGreaterThan(0);
    expect((float) $payslip->net_salary)->toBeLessThan((float) $payslip->gross_salary);
});

test('payroll run cannot be calculated twice without returning to draft', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $employee = $data['employee'];

    app(\Database\Seeders\HrPhase3Seeder::class)->run();

    $basicComponent = SalaryComponent::where('code', 'BASIC')->first();
    EmployeeSalary::create([
        'employee_id' => $employee->id,
        'salary_component_id' => $basicComponent->id,
        'amount' => 4000.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    EmployeeTaxProfile::firstOrCreate(
        ['employee_id' => $employee->id],
        [
            'marital_status' => 'single',
            'num_children' => 0,
            'is_pcb_manual' => false,
        ]
    );

    // Create and calculate
    $createResponse = $this->actingAs($admin)
        ->postJson('/api/hr/payroll/runs', ['month' => 1, 'year' => 2026]);

    $runId = $createResponse->json('data.id');

    $this->actingAs($admin)
        ->postJson("/api/hr/payroll/runs/{$runId}/calculate")
        ->assertSuccessful();

    // Submit review and approve
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/submit-review")->assertSuccessful();
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/approve")->assertSuccessful();

    // Cannot finalize twice
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/finalize")->assertSuccessful();

    $finalizeAgain = $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/finalize");
    $finalizeAgain->assertStatus(422);
});

test('employee payslip appears in self-service my payslips after finalization', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $user = $data['user'];
    $employee = $data['employee'];

    app(\Database\Seeders\HrPhase3Seeder::class)->run();

    $basicComponent = SalaryComponent::where('code', 'BASIC')->first();
    EmployeeSalary::create([
        'employee_id' => $employee->id,
        'salary_component_id' => $basicComponent->id,
        'amount' => 3500.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    EmployeeTaxProfile::firstOrCreate(
        ['employee_id' => $employee->id],
        [
            'marital_status' => 'single',
            'num_children' => 0,
            'is_pcb_manual' => false,
        ]
    );

    // Create, calculate, review, approve, finalize
    $createResponse = $this->actingAs($admin)
        ->postJson('/api/hr/payroll/runs', ['month' => 2, 'year' => 2026]);

    $runId = $createResponse->json('data.id');

    $this->actingAs($admin)->postJson("/api/hr/payroll/runs/{$runId}/calculate")->assertSuccessful();
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/submit-review")->assertSuccessful();
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/approve")->assertSuccessful();
    $this->actingAs($admin)->patchJson("/api/hr/payroll/runs/{$runId}/finalize")->assertSuccessful();

    // Employee can access their payslip via self-service endpoint
    $myPayslipsResponse = $this->actingAs($user)
        ->getJson('/api/hr/me/payslips');

    $myPayslipsResponse->assertSuccessful();

    $payslips = $myPayslipsResponse->json('data');
    expect(count($payslips))->toBeGreaterThan(0);

    $matchingPayslip = collect($payslips)->firstWhere('month', 2);
    expect($matchingPayslip)->not->toBeNull();
    expect($matchingPayslip['year'])->toBe(2026);
});

/*
|--------------------------------------------------------------------------
| 2. Claim Submission + Approval Workflow
|--------------------------------------------------------------------------
*/

test('complete claim workflow: draft, submit, approve, mark paid', function () {
    Storage::fake('public');

    $data = createPhase3Employee();
    $admin = $data['admin'];
    $user = $data['user'];
    $employee = $data['employee'];

    $claimType = ClaimType::create([
        'name' => 'Medical',
        'code' => 'MED',
        'description' => 'Medical expenses',
        'monthly_limit' => 500.00,
        'yearly_limit' => 3000.00,
        'requires_receipt' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Step 1: Employee creates a draft claim
    $receipt = \Illuminate\Http\UploadedFile::fake()->image('receipt.jpg');

    $storeResponse = $this->actingAs($user)
        ->postJson('/api/hr/me/claims', [
            'claim_type_id' => $claimType->id,
            'amount' => 150.00,
            'claim_date' => now()->format('Y-m-d'),
            'description' => 'Doctor visit for flu',
            'receipt' => $receipt,
        ]);

    $storeResponse->assertCreated();
    $claimId = $storeResponse->json('data.id');
    expect($claimId)->not->toBeNull();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'employee_id' => $employee->id,
        'status' => 'draft',
        'amount' => 150.00,
    ]);

    // Step 2: Employee submits for approval
    $submitResponse = $this->actingAs($user)
        ->postJson("/api/hr/me/claims/{$claimId}/submit");

    $submitResponse->assertSuccessful();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'status' => 'pending',
    ]);

    // Step 3: Admin approves the claim
    $approveResponse = $this->actingAs($admin)
        ->postJson("/api/hr/claims/requests/{$claimId}/approve", [
            'approved_amount' => 150.00,
        ]);

    $approveResponse->assertSuccessful();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'status' => 'approved',
        'approved_amount' => 150.00,
    ]);

    // Step 4: Admin marks as paid
    $markPaidResponse = $this->actingAs($admin)
        ->postJson("/api/hr/claims/requests/{$claimId}/mark-paid", [
            'paid_reference' => 'TXN-2026-001',
        ]);

    $markPaidResponse->assertSuccessful();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'status' => 'paid',
    ]);

    $finalClaim = ClaimRequest::find($claimId);
    expect($finalClaim->status)->toBe('paid');
    expect($finalClaim->paid_reference)->toBe('TXN-2026-001');
    expect($finalClaim->paid_at)->not->toBeNull();
});

test('claim workflow: admin can reject a pending claim', function () {
    Storage::fake('public');

    $data = createPhase3Employee();
    $admin = $data['admin'];
    $user = $data['user'];
    $employee = $data['employee'];

    $claimType = ClaimType::create([
        'name' => 'Transport',
        'code' => 'TRANS',
        'description' => 'Transport expenses',
        'monthly_limit' => 300.00,
        'yearly_limit' => 2000.00,
        'requires_receipt' => false,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    // Create and submit a claim
    $storeResponse = $this->actingAs($user)
        ->postJson('/api/hr/me/claims', [
            'claim_type_id' => $claimType->id,
            'amount' => 50.00,
            'claim_date' => now()->format('Y-m-d'),
            'description' => 'Grab to client office',
        ]);

    $claimId = $storeResponse->json('data.id');

    $this->actingAs($user)->postJson("/api/hr/me/claims/{$claimId}/submit")->assertSuccessful();

    // Admin rejects
    $rejectResponse = $this->actingAs($admin)
        ->postJson("/api/hr/claims/requests/{$claimId}/reject", [
            'rejected_reason' => 'Receipt required for transport claims above RM30.',
        ]);

    $rejectResponse->assertSuccessful();

    $this->assertDatabaseHas('claim_requests', [
        'id' => $claimId,
        'status' => 'rejected',
    ]);

    $claim = ClaimRequest::find($claimId);
    expect($claim->status)->toBe('rejected');
    expect($claim->rejected_reason)->toContain('Receipt required');
});

test('employee cannot submit a claim that is already pending', function () {
    Storage::fake('public');

    $data = createPhase3Employee();
    $user = $data['user'];

    $claimType = ClaimType::create([
        'name' => 'Parking',
        'code' => 'PARK',
        'monthly_limit' => 150.00,
        'yearly_limit' => 1000.00,
        'requires_receipt' => true,
        'is_active' => true,
        'sort_order' => 3,
    ]);

    // Create and submit
    $storeResponse = $this->actingAs($user)
        ->postJson('/api/hr/me/claims', [
            'claim_type_id' => $claimType->id,
            'amount' => 20.00,
            'claim_date' => now()->format('Y-m-d'),
            'description' => 'Parking at client site',
        ]);

    $claimId = $storeResponse->json('data.id');
    $this->actingAs($user)->postJson("/api/hr/me/claims/{$claimId}/submit")->assertSuccessful();

    // Try to submit again (should fail)
    $reSubmitResponse = $this->actingAs($user)
        ->postJson("/api/hr/me/claims/{$claimId}/submit");

    $reSubmitResponse->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| 3. Asset Assignment and Return Flow
|--------------------------------------------------------------------------
*/

test('complete asset flow: create, assign to employee, return asset', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $employee = $data['employee'];

    $category = AssetCategory::create([
        'name' => 'Laptop',
        'code' => 'LAPTOP',
        'description' => 'Laptop computers',
        'requires_serial_number' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Step 1: Create an asset
    $createAssetResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets', [
            'asset_category_id' => $category->id,
            'name' => 'MacBook Pro 14"',
            'brand' => 'Apple',
            'condition' => 'new',
        ]);

    $createAssetResponse->assertCreated();
    $assetId = $createAssetResponse->json('data.id');
    expect($assetId)->not->toBeNull();

    $this->assertDatabaseHas('assets', [
        'id' => $assetId,
        'status' => 'available',
        'condition' => 'new',
    ]);

    // Step 2: Assign asset to employee
    $assignResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets/assignments', [
            'asset_id' => $assetId,
            'employee_id' => $employee->id,
            'assigned_date' => now()->format('Y-m-d'),
            'notes' => 'Issued for remote work',
        ]);

    $assignResponse->assertCreated();
    $assignmentId = $assignResponse->json('data.id');
    expect($assignmentId)->not->toBeNull();

    // Verify asset status changed to assigned
    $this->assertDatabaseHas('assets', [
        'id' => $assetId,
        'status' => 'assigned',
    ]);

    $this->assertDatabaseHas('asset_assignments', [
        'id' => $assignmentId,
        'asset_id' => $assetId,
        'employee_id' => $employee->id,
        'status' => 'active',
    ]);

    // Step 3: Return asset
    $returnResponse = $this->actingAs($admin)
        ->putJson("/api/hr/assets/assignments/{$assignmentId}/return", [
            'returned_condition' => 'good',
            'return_notes' => 'No damage found',
        ]);

    $returnResponse->assertSuccessful();

    // Verify asset is available again
    $this->assertDatabaseHas('assets', [
        'id' => $assetId,
        'status' => 'available',
    ]);

    // Verify assignment is marked as returned
    $this->assertDatabaseHas('asset_assignments', [
        'id' => $assignmentId,
        'status' => 'returned',
    ]);

    $assignment = AssetAssignment::find($assignmentId);
    expect($assignment->status)->toBe('returned');
    expect($assignment->returned_condition)->toBe('good');
    expect($assignment->return_notes)->toBe('No damage found');
    expect($assignment->returned_date)->not->toBeNull();
});

test('asset cannot be assigned if already assigned', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $employee = $data['employee'];

    $category = AssetCategory::create([
        'name' => 'Access Card',
        'code' => 'ACCESS_CARD',
        'requires_serial_number' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Create asset
    $createAssetResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets', [
            'asset_category_id' => $category->id,
            'name' => 'Office Access Card',
            'brand' => 'Genetec',
            'condition' => 'good',
        ]);

    $assetId = $createAssetResponse->json('data.id');

    // Assign to employee 1
    $this->actingAs($admin)
        ->postJson('/api/hr/assets/assignments', [
            'asset_id' => $assetId,
            'employee_id' => $employee->id,
            'assigned_date' => now()->format('Y-m-d'),
        ])
        ->assertCreated();

    // Create employee 2
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee2 = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    // Try to assign the same asset to employee 2 (should fail)
    $secondAssignResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets/assignments', [
            'asset_id' => $assetId,
            'employee_id' => $employee2->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);

    $secondAssignResponse->assertStatus(422);
});

test('returned asset can be re-assigned to another employee', function () {
    $data = createPhase3Employee();
    $admin = $data['admin'];
    $employee = $data['employee'];

    $category = AssetCategory::create([
        'name' => 'Headset',
        'code' => 'HEADSET',
        'requires_serial_number' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Create asset
    $createAssetResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets', [
            'asset_category_id' => $category->id,
            'name' => 'Office Headset',
            'brand' => 'Logitech',
            'condition' => 'good',
        ]);

    $assetId = $createAssetResponse->json('data.id');

    // Assign to employee 1
    $assignResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets/assignments', [
            'asset_id' => $assetId,
            'employee_id' => $employee->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);

    $assignmentId = $assignResponse->json('data.id');

    // Return asset
    $this->actingAs($admin)
        ->putJson("/api/hr/assets/assignments/{$assignmentId}/return", [
            'returned_condition' => 'good',
        ])
        ->assertSuccessful();

    // Create employee 2
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee2 = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    // Re-assign the returned asset to employee 2
    $reassignResponse = $this->actingAs($admin)
        ->postJson('/api/hr/assets/assignments', [
            'asset_id' => $assetId,
            'employee_id' => $employee2->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);

    $reassignResponse->assertCreated();

    $this->assertDatabaseHas('assets', [
        'id' => $assetId,
        'status' => 'assigned',
    ]);
});

/*
|--------------------------------------------------------------------------
| 4. Phase 3 Seeder is Idempotent
|--------------------------------------------------------------------------
*/

test('HrPhase3Seeder is idempotent and can be run multiple times safely', function () {
    // Run seeder twice
    app(\Database\Seeders\HrPhase3Seeder::class)->run();
    app(\Database\Seeders\HrPhase3Seeder::class)->run();

    // Should have exactly one BASIC salary component (not duplicated)
    $basicCount = SalaryComponent::where('code', 'BASIC')->count();
    expect($basicCount)->toBe(1);

    // Should have exactly one Medical claim type
    $medCount = ClaimType::where('code', 'MED')->count();
    expect($medCount)->toBe(1);

    // Should have exactly one Laptop asset category
    $laptopCatCount = AssetCategory::where('code', 'LAPTOP')->count();
    expect($laptopCatCount)->toBe(1);
});
