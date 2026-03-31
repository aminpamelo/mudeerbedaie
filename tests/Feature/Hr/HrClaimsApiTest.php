<?php

declare(strict_types=1);

use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\ClaimTypeVehicleRate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createClaimsAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createClaimsEmployeeWithRecord(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('user', 'employee', 'department', 'position');
}

/*
|--------------------------------------------------------------------------
| Authentication & Authorization Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users get 401 on claims endpoints', function () {
    $this->getJson('/api/hr/claims/types')->assertUnauthorized();
    $this->getJson('/api/hr/claims/requests')->assertUnauthorized();
    $this->getJson('/api/hr/claims/approvers')->assertUnauthorized();
    $this->getJson('/api/hr/claims/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/me/claims')->assertUnauthorized();
});

test('non-admin users get 403 on admin-only claim type endpoints', function () {
    $data = createClaimsEmployeeWithRecord();

    $this->actingAs($data['user'])
        ->postJson('/api/hr/claims/types', [])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Claim Type Tests
|--------------------------------------------------------------------------
*/

test('admin can list claim types', function () {
    $admin = createClaimsAdminUser();
    ClaimType::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/claims/types');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can create a claim type', function () {
    $admin = createClaimsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/claims/types', [
        'name' => 'Medical Claim',
        'code' => 'MEDICAL',
        'monthly_limit' => 500,
        'yearly_limit' => 3000,
        'requires_receipt' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Medical Claim')
        ->assertJsonPath('message', 'Claim type created successfully.');

    $this->assertDatabaseHas('claim_types', ['name' => 'Medical Claim', 'code' => 'MEDICAL']);
});

test('claim type store validates required fields', function () {
    $admin = createClaimsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/claims/types', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code', 'requires_receipt']);
});

test('admin can update a claim type', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/claims/types/{$claimType->id}", [
        'name' => 'Updated Claim',
        'code' => 'UPDATED',
        'requires_receipt' => false,
        'is_active' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Claim')
        ->assertJsonPath('message', 'Claim type updated successfully.');
});

test('admin can delete a claim type without requests', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/claims/types/{$claimType->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim type deleted successfully.');

    $this->assertDatabaseMissing('claim_types', ['id' => $claimType->id]);
});

test('admin cannot delete claim type with existing requests', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    ClaimRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/claims/types/{$claimType->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete claim type that has existing claim requests.');
});

/*
|--------------------------------------------------------------------------
| Claim Approver Tests
|--------------------------------------------------------------------------
*/

test('admin can create a claim approver', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    $data2 = createClaimsEmployeeWithRecord();

    $response = $this->actingAs($admin)->postJson('/api/hr/claims/approvers', [
        'employee_id' => $data['employee']->id,
        'approver_id' => $data2['employee']->id,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Claim approver assigned successfully.');

    $this->assertDatabaseHas('claim_approvers', [
        'employee_id' => $data['employee']->id,
        'approver_id' => $data2['employee']->id,
    ]);
});

test('admin can list claim approvers', function () {
    $admin = createClaimsAdminUser();
    ClaimApprover::factory()->count(2)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/claims/approvers');

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

test('admin can delete a claim approver', function () {
    $admin = createClaimsAdminUser();
    $approver = ClaimApprover::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/claims/approvers/{$approver->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim approver removed successfully.');

    $this->assertDatabaseMissing('claim_approvers', ['id' => $approver->id]);
});

/*
|--------------------------------------------------------------------------
| Claim Submission Flow Tests
|--------------------------------------------------------------------------
*/

test('employee can submit a claim (creates as draft)', function () {
    Storage::fake('public');
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create(['requires_receipt' => false]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 150.00,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Doctor visit for fever',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Claim request created successfully.')
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('claim_requests', [
        'employee_id' => $data['employee']->id,
        'status' => 'draft',
    ]);
});

test('employee can submit claim with receipt file', function () {
    Storage::fake('public');
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create(['requires_receipt' => true]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 200.00,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Medical receipt attached',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ]);

    $response->assertCreated();

    $claim = ClaimRequest::where('employee_id', $data['employee']->id)->first();
    expect($claim->receipt_path)->not->toBeNull();
});

test('employee can submit a draft claim for approval', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    $claim = ClaimRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($data['user'])->postJson("/api/hr/me/claims/{$claim->id}/submit");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim request submitted for approval.');

    $claim->refresh();
    expect($claim->status)->toBe('pending');
    expect($claim->submitted_at)->not->toBeNull();
});

test('employee can delete a draft claim', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    $claim = ClaimRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($data['user'])->deleteJson("/api/hr/me/claims/{$claim->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim request deleted successfully.');

    $this->assertDatabaseMissing('claim_requests', ['id' => $claim->id]);
});

test('employee cannot delete a submitted claim', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    $claim = ClaimRequest::factory()->pending()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($data['user'])->deleteJson("/api/hr/me/claims/{$claim->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only draft claims can be deleted.');
});

test('employee cannot access another employee claim', function () {
    $data1 = createClaimsEmployeeWithRecord();
    $data2 = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    $claim = ClaimRequest::factory()->create([
        'employee_id' => $data1['employee']->id,
        'claim_type_id' => $claimType->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($data2['user'])->getJson("/api/hr/me/claims/{$claim->id}");

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Admin Approval Flow Tests
|--------------------------------------------------------------------------
*/

test('admin can list all claim requests', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    ClaimRequest::factory()->pending()->count(3)->create([
        'employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/claims/requests');

    $response->assertSuccessful();
    expect($response->json('total'))->toBeGreaterThanOrEqual(3);
});

test('admin can approve a pending claim with custom amount', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();
    Employee::factory()->create(['user_id' => $admin->id]);

    $claimType = ClaimType::factory()->create();
    $claim = ClaimRequest::factory()->pending()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
        'amount' => 200.00,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/approve", [
        'approved_amount' => 150.00,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim request approved successfully.');

    $claim->refresh();
    expect($claim->status)->toBe('approved');
    expect((float) $claim->approved_amount)->toBe(150.0);
    expect($claim->approved_at)->not->toBeNull();
});

test('admin can reject a pending claim with reason', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    $claimType = ClaimType::factory()->create();
    $claim = ClaimRequest::factory()->pending()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/reject", [
        'rejected_reason' => 'Receipt is not valid or legible.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim request rejected.');

    $claim->refresh();
    expect($claim->status)->toBe('rejected');
    expect($claim->rejected_reason)->toBe('Receipt is not valid or legible.');
});

test('admin cannot approve a non-pending claim', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    $claimType = ClaimType::factory()->create();
    $claim = ClaimRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/approve", [
        'approved_amount' => 100.00,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only pending requests can be approved.');
});

test('admin can mark an approved claim as paid', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    $claimType = ClaimType::factory()->create();
    $claim = ClaimRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/mark-paid", [
        'paid_reference' => 'TRF-20260327-001',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Claim marked as paid.');

    $claim->refresh();
    expect($claim->status)->toBe('paid');
    expect($claim->paid_at)->not->toBeNull();
    expect($claim->paid_reference)->toBe('TRF-20260327-001');
});

test('admin cannot mark a non-approved claim as paid', function () {
    $admin = createClaimsAdminUser();
    $data = createClaimsEmployeeWithRecord();

    $claimType = ClaimType::factory()->create();
    $claim = ClaimRequest::factory()->pending()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/requests/{$claim->id}/mark-paid");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Only approved claims can be marked as paid.');
});

/*
|--------------------------------------------------------------------------
| Monthly Limit Warning Tests
|--------------------------------------------------------------------------
*/

test('claim warns when monthly limit is exceeded', function () {
    Storage::fake('public');
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create(['monthly_limit' => 500.00]);

    ClaimRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
        'amount' => 400.00,
        'status' => 'approved',
        'claim_date' => now(),
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'amount' => 200.00,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Over monthly limit claim',
    ]);

    $response->assertCreated();
    expect($response->json('warning'))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| My Claims List Tests
|--------------------------------------------------------------------------
*/

test('employee can list their own claims', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create();

    ClaimRequest::factory()->count(3)->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($data['user'])->getJson('/api/hr/me/claims');

    $response->assertSuccessful();
    expect($response->json('total'))->toBe(3);
});

test('user without employee record gets 404 on my claims', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user)->getJson('/api/hr/me/claims');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Mileage Claim Type Tests
|--------------------------------------------------------------------------
*/

test('admin can create a mileage claim type', function () {
    $admin = createClaimsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/claims/types', [
        'name' => 'Petrol Mileage',
        'code' => 'MILEAGE',
        'monthly_limit' => 500,
        'requires_receipt' => false,
        'is_active' => true,
        'is_mileage_type' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Petrol Mileage')
        ->assertJsonPath('data.is_mileage_type', true);

    $this->assertDatabaseHas('claim_types', ['code' => 'MILEAGE', 'is_mileage_type' => true]);
});

/*
|--------------------------------------------------------------------------
| Vehicle Rate Tests
|--------------------------------------------------------------------------
*/

test('admin can list vehicle rates for a claim type', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    ClaimTypeVehicleRate::factory()->count(2)->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->getJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('admin can create a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", [
        'name' => 'Car',
        'rate_per_km' => 0.60,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Car')
        ->assertJsonPath('data.rate_per_km', '0.60')
        ->assertJsonPath('message', 'Vehicle rate created successfully.');

    $this->assertDatabaseHas('claim_type_vehicle_rates', [
        'claim_type_id' => $claimType->id,
        'name' => 'Car',
    ]);
});

test('admin can update a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);
    $rate = ClaimTypeVehicleRate::factory()->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->putJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates/{$rate->id}", [
        'name' => 'Updated Vehicle',
        'rate_per_km' => 0.75,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Vehicle')
        ->assertJsonPath('message', 'Vehicle rate updated successfully.');
});

test('admin can delete a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);
    $rate = ClaimTypeVehicleRate::factory()->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates/{$rate->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Vehicle rate deleted successfully.');

    $this->assertDatabaseMissing('claim_type_vehicle_rates', ['id' => $rate->id]);
});

test('non-admin cannot create vehicle rates', function () {
    $user = User::factory()->create(['role' => 'student']);
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($user)->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", [
        'name' => 'Car',
        'rate_per_km' => 0.60,
    ]);

    $response->assertForbidden();
});

test('vehicle rate validates required fields', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'rate_per_km']);
});
