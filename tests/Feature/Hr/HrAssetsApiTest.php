<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\BenefitType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAssetsAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createAssetsEmployeeWithRecord(): array
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

test('unauthenticated users get 401 on asset endpoints', function () {
    $this->getJson('/api/hr/assets/categories')->assertUnauthorized();
    $this->getJson('/api/hr/assets')->assertUnauthorized();
    $this->getJson('/api/hr/assets/assignments')->assertUnauthorized();
    $this->getJson('/api/hr/me/assets')->assertUnauthorized();
    $this->getJson('/api/hr/benefits/types')->assertUnauthorized();
    $this->getJson('/api/hr/benefits')->assertUnauthorized();
});

test('non-admin users get 403 on admin-only asset endpoints', function () {
    $data = createAssetsEmployeeWithRecord();

    $this->actingAs($data['user'])
        ->postJson('/api/hr/assets/categories', [])
        ->assertForbidden();

    $this->actingAs($data['user'])
        ->postJson('/api/hr/assets', [])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Asset Category Tests
|--------------------------------------------------------------------------
*/

test('admin can list asset categories', function () {
    $admin = createAssetsAdminUser();
    AssetCategory::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/assets/categories');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can create an asset category', function () {
    $admin = createAssetsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/assets/categories', [
        'name' => 'Laptop',
        'code' => 'LAPTOP',
        'description' => 'Company laptops',
        'requires_serial_number' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Laptop')
        ->assertJsonPath('message', 'Asset category created successfully.');

    $this->assertDatabaseHas('asset_categories', ['name' => 'Laptop', 'code' => 'LAPTOP']);
});

test('asset category store validates required fields', function () {
    $admin = createAssetsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/assets/categories', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code', 'requires_serial_number']);
});

test('admin can update an asset category', function () {
    $admin = createAssetsAdminUser();
    $category = AssetCategory::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/assets/categories/{$category->id}", [
        'name' => 'Updated Category',
        'code' => 'UPDATED',
        'requires_serial_number' => false,
        'is_active' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Category')
        ->assertJsonPath('message', 'Asset category updated successfully.');
});

test('admin can delete an empty asset category', function () {
    $admin = createAssetsAdminUser();
    $category = AssetCategory::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/assets/categories/{$category->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Asset category deleted successfully.');

    $this->assertDatabaseMissing('asset_categories', ['id' => $category->id]);
});

test('admin cannot delete asset category with existing assets', function () {
    $admin = createAssetsAdminUser();
    $category = AssetCategory::factory()->create();
    Asset::factory()->create(['asset_category_id' => $category->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/assets/categories/{$category->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete asset category that has existing assets.');
});

/*
|--------------------------------------------------------------------------
| Asset CRUD Tests
|--------------------------------------------------------------------------
*/

test('admin can list assets', function () {
    $admin = createAssetsAdminUser();
    Asset::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/assets');

    $response->assertSuccessful();
    expect($response->json('total'))->toBeGreaterThanOrEqual(3);
});

test('admin can create an asset with auto-generated asset tag', function () {
    $admin = createAssetsAdminUser();
    $category = AssetCategory::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/hr/assets', [
        'asset_category_id' => $category->id,
        'name' => 'Dell Laptop XPS 15',
        'brand' => 'Dell',
        'model' => 'XPS 15',
        'serial_number' => 'SN-123456789',
        'purchase_date' => '2025-01-15',
        'purchase_price' => 5500.00,
        'condition' => 'new',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Dell Laptop XPS 15')
        ->assertJsonPath('message', 'Asset created successfully.');

    $asset = Asset::where('name', 'Dell Laptop XPS 15')->first();
    expect($asset)->not->toBeNull();
    expect($asset->asset_tag)->toStartWith('AST-');
    expect($asset->status)->toBe('available');
});

test('asset store validates required fields', function () {
    $admin = createAssetsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/assets', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_category_id', 'name', 'condition']);
});

test('admin can view a single asset with assignment history', function () {
    $admin = createAssetsAdminUser();
    $asset = Asset::factory()->create();

    $response = $this->actingAs($admin)->getJson("/api/hr/assets/{$asset->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonStructure(['data' => ['id', 'asset_tag', 'name', 'status', 'assignments']]);
});

test('admin can update an asset', function () {
    $admin = createAssetsAdminUser();
    $category = AssetCategory::factory()->create();
    $asset = Asset::factory()->create(['asset_category_id' => $category->id]);

    $response = $this->actingAs($admin)->putJson("/api/hr/assets/{$asset->id}", [
        'asset_category_id' => $category->id,
        'name' => 'Updated Asset Name',
        'condition' => 'good',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Asset Name')
        ->assertJsonPath('message', 'Asset updated successfully.');
});

test('admin can dispose of an available asset', function () {
    $admin = createAssetsAdminUser();
    $asset = Asset::factory()->create(['status' => 'available']);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/assets/{$asset->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Asset marked as disposed.');

    $asset->refresh();
    expect($asset->status)->toBe('disposed');
});

test('admin cannot dispose of an assigned asset', function () {
    $admin = createAssetsAdminUser();
    $asset = Asset::factory()->assigned()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/assets/{$asset->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot dispose of an assigned asset. Return it first.');
});

/*
|--------------------------------------------------------------------------
| Asset Assignment Flow Tests
|--------------------------------------------------------------------------
*/

test('admin can assign an asset to an employee', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $adminEmployee = Employee::factory()->create(['user_id' => $admin->id]);
    $asset = Asset::factory()->create(['status' => 'available']);

    $response = $this->actingAs($admin)->postJson('/api/hr/assets/assignments', [
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
        'assigned_date' => now()->format('Y-m-d'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Asset assigned successfully.');

    $this->assertDatabaseHas('asset_assignments', [
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
        'status' => 'active',
    ]);

    $asset->refresh();
    expect($asset->status)->toBe('assigned');
});

test('admin cannot assign an already assigned asset', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $asset = Asset::factory()->assigned()->create();

    $response = $this->actingAs($admin)->postJson('/api/hr/assets/assignments', [
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
        'assigned_date' => now()->format('Y-m-d'),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Asset is not available for assignment.');
});

test('admin can list asset assignments', function () {
    $admin = createAssetsAdminUser();
    AssetAssignment::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/assets/assignments');

    $response->assertSuccessful();
    expect($response->json('total'))->toBeGreaterThanOrEqual(3);
});

test('admin can process asset return', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $asset = Asset::factory()->assigned()->create();

    $assignment = AssetAssignment::factory()->create([
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->putJson("/api/hr/assets/assignments/{$assignment->id}/return", [
        'returned_condition' => 'good',
        'return_notes' => 'Returned in good condition.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Asset returned successfully.');

    $assignment->refresh();
    expect($assignment->status)->toBe('returned');
    expect($assignment->returned_condition)->toBe('good');

    $asset->refresh();
    expect($asset->status)->toBe('available');
});

test('admin cannot return an already returned asset', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $asset = Asset::factory()->create(['status' => 'available']);

    $assignment = AssetAssignment::factory()->returned()->create([
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)->putJson("/api/hr/assets/assignments/{$assignment->id}/return", [
        'returned_condition' => 'good',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'This assignment is not active.');
});

/*
|--------------------------------------------------------------------------
| My Assets Tests
|--------------------------------------------------------------------------
*/

test('employee can view their assigned assets', function () {
    $data = createAssetsEmployeeWithRecord();
    $asset = Asset::factory()->assigned()->create();

    AssetAssignment::factory()->create([
        'asset_id' => $asset->id,
        'employee_id' => $data['employee']->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($data['user'])->getJson('/api/hr/me/assets');

    $response->assertSuccessful()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data');
});

test('user without employee record gets 404 on my assets', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user)->getJson('/api/hr/me/assets');

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Benefit Type Tests
|--------------------------------------------------------------------------
*/

test('admin can list benefit types', function () {
    $admin = createAssetsAdminUser();
    BenefitType::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/benefits/types');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can create a benefit type', function () {
    $admin = createAssetsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/benefits/types', [
        'name' => 'Medical Insurance',
        'code' => 'MEDINS',
        'description' => 'Company medical insurance coverage',
        'category' => 'insurance',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Medical Insurance')
        ->assertJsonPath('message', 'Benefit type created successfully.');

    $this->assertDatabaseHas('benefit_types', ['name' => 'Medical Insurance', 'code' => 'MEDINS']);
});

test('admin can update a benefit type', function () {
    $admin = createAssetsAdminUser();
    $benefitType = BenefitType::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/benefits/types/{$benefitType->id}", [
        'name' => 'Updated Benefit',
        'code' => 'UPDBFT',
        'category' => 'insurance',
        'is_active' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Benefit')
        ->assertJsonPath('message', 'Benefit type updated successfully.');
});

test('admin can delete a benefit type without employee benefits', function () {
    $admin = createAssetsAdminUser();
    $benefitType = BenefitType::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/benefits/types/{$benefitType->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Benefit type deleted successfully.');

    $this->assertDatabaseMissing('benefit_types', ['id' => $benefitType->id]);
});

test('admin cannot delete benefit type with existing employee benefits', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $benefitType = BenefitType::factory()->create();

    EmployeeBenefit::factory()->create([
        'employee_id' => $data['employee']->id,
        'benefit_type_id' => $benefitType->id,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/benefits/types/{$benefitType->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete benefit type that has existing employee benefits.');
});

/*
|--------------------------------------------------------------------------
| Employee Benefit Tests
|--------------------------------------------------------------------------
*/

test('admin can list employee benefits', function () {
    $admin = createAssetsAdminUser();
    EmployeeBenefit::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/benefits');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can create an employee benefit record', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $benefitType = BenefitType::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/hr/benefits', [
        'employee_id' => $data['employee']->id,
        'benefit_type_id' => $benefitType->id,
        'provider' => 'Great Eastern',
        'policy_number' => 'GE-1234567',
        'coverage_amount' => 50000.00,
        'employer_contribution' => 200.00,
        'employee_contribution' => 50.00,
        'start_date' => now()->subYear()->format('Y-m-d'),
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Employee benefit created successfully.');

    $this->assertDatabaseHas('employee_benefits', [
        'employee_id' => $data['employee']->id,
        'benefit_type_id' => $benefitType->id,
        'provider' => 'Great Eastern',
    ]);
});

test('admin can update an employee benefit', function () {
    $admin = createAssetsAdminUser();
    $data = createAssetsEmployeeWithRecord();
    $benefitType = BenefitType::factory()->create();

    $benefit = EmployeeBenefit::factory()->create([
        'employee_id' => $data['employee']->id,
        'benefit_type_id' => $benefitType->id,
    ]);

    $response = $this->actingAs($admin)->putJson("/api/hr/benefits/{$benefit->id}", [
        'employee_id' => $data['employee']->id,
        'benefit_type_id' => $benefitType->id,
        'start_date' => now()->subYear()->format('Y-m-d'),
        'coverage_amount' => 75000.00,
        'employer_contribution' => 300.00,
        'employee_contribution' => 80.00,
        'is_active' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Employee benefit updated successfully.');

    $benefit->refresh();
    expect((float) $benefit->coverage_amount)->toBe(75000.0);
});

test('admin can delete an employee benefit', function () {
    $admin = createAssetsAdminUser();
    $benefit = EmployeeBenefit::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/benefits/{$benefit->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Employee benefit deleted successfully.');

    $this->assertDatabaseMissing('employee_benefits', ['id' => $benefit->id]);
});

test('employee benefit store validates required fields', function () {
    $admin = createAssetsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/benefits', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'benefit_type_id', 'start_date']);
});
