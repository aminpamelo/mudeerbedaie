<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createNonAdminUser(): User
{
    return User::factory()->create(['role' => 'student']);
}

function createEmployeeWithRelations(): array
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

    return compact('department', 'position', 'user', 'employee');
}

/*
|--------------------------------------------------------------------------
| Authentication & Authorization Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users get 401 on hr endpoints', function () {
    $this->getJson('/api/hr/dashboard/stats')->assertUnauthorized();
    $this->getJson('/api/hr/employees')->assertUnauthorized();
    $this->getJson('/api/hr/departments')->assertUnauthorized();
    $this->getJson('/api/hr/positions')->assertUnauthorized();
});

test('non-admin users get 403 on hr endpoints', function () {
    $user = createNonAdminUser();

    $this->actingAs($user)
        ->getJson('/api/hr/dashboard/stats')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/hr/employees')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Dashboard Tests
|--------------------------------------------------------------------------
*/

test('dashboard stats returns correct counts', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    Employee::factory()->count(3)->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);
    Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'probation',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonPath('data.total_employees', 4)
        ->assertJsonPath('data.active_employees', 3)
        ->assertJsonPath('data.on_probation', 1)
        ->assertJsonPath('data.departments_count', 1);
});

test('dashboard recent activity returns history entries', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    EmployeeHistory::factory()->count(3)->create([
        'employee_id' => $data['employee']->id,
        'changed_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/dashboard/recent-activity');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('dashboard headcount by department returns grouped counts', function () {
    $admin = createAdminUser();
    $dept1 = Department::factory()->create(['name' => 'Engineering']);
    $dept2 = Department::factory()->create(['name' => 'Marketing']);
    $pos1 = Position::factory()->create(['department_id' => $dept1->id]);
    $pos2 = Position::factory()->create(['department_id' => $dept2->id]);

    Employee::factory()->count(3)->create([
        'department_id' => $dept1->id,
        'position_id' => $pos1->id,
        'status' => 'active',
    ]);
    Employee::factory()->count(2)->create([
        'department_id' => $dept2->id,
        'position_id' => $pos2->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/dashboard/headcount-by-department');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| Employee CRUD Tests
|--------------------------------------------------------------------------
*/

test('employee index returns paginated list', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    Employee::factory()->count(5)->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/employees');

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

test('employee index supports search by name', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    Employee::factory()->create([
        'full_name' => 'Ahmad Amin',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    Employee::factory()->create([
        'full_name' => 'Sarah Lee',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/employees?search=Ahmad');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('employee index supports department filter', function () {
    $admin = createAdminUser();
    $dept1 = Department::factory()->create();
    $dept2 = Department::factory()->create();
    $pos1 = Position::factory()->create(['department_id' => $dept1->id]);
    $pos2 = Position::factory()->create(['department_id' => $dept2->id]);

    Employee::factory()->count(3)->create(['department_id' => $dept1->id, 'position_id' => $pos1->id]);
    Employee::factory()->count(2)->create(['department_id' => $dept2->id, 'position_id' => $pos2->id]);

    $response = $this->actingAs($admin)->getJson("/api/hr/employees?department_id={$dept1->id}");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('employee index supports status filter', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    Employee::factory()->count(2)->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);
    Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'probation',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/employees?status=active');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('employee store creates employee with user account', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    $payload = [
        'full_name' => 'Test Employee',
        'ic_number' => '901215-14-5678',
        'date_of_birth' => '1990-12-15',
        'gender' => 'male',
        'religion' => 'islam',
        'race' => 'malay',
        'marital_status' => 'single',
        'phone' => '0123456789',
        'personal_email' => 'test.employee@example.com',
        'address_line_1' => '123 Jalan Test',
        'city' => 'Shah Alam',
        'state' => 'Selangor',
        'postcode' => '40000',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => 'full_time',
        'join_date' => '2026-03-01',
        'bank_name' => 'Maybank',
        'bank_account_number' => '1234567890',
    ];

    $response = $this->actingAs($admin)->postJson('/api/hr/employees', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.full_name', 'Test Employee')
        ->assertJsonPath('message', 'Employee created successfully.');

    $this->assertDatabaseHas('employees', [
        'full_name' => 'Test Employee',
        'department_id' => $department->id,
    ]);

    // Verify user was created
    $this->assertDatabaseHas('users', [
        'email' => 'test.employee@example.com',
        'role' => 'employee',
    ]);

    // Verify history entry was created
    $employee = Employee::where('full_name', 'Test Employee')->first();
    $this->assertDatabaseHas('employee_histories', [
        'employee_id' => $employee->id,
        'change_type' => 'general_update',
        'remarks' => 'Employee record created',
    ]);
});

test('employee store validates required fields', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/employees', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'ic_number', 'date_of_birth', 'gender', 'phone', 'personal_email']);
});

test('employee store validates ic number format', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    $response = $this->actingAs($admin)->postJson('/api/hr/employees', [
        'full_name' => 'Test',
        'ic_number' => 'invalid-format',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
        'religion' => 'islam',
        'race' => 'malay',
        'marital_status' => 'single',
        'phone' => '012345',
        'personal_email' => 'test@example.com',
        'address_line_1' => 'Test',
        'city' => 'KL',
        'state' => 'KL',
        'postcode' => '50000',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => 'full_time',
        'join_date' => '2026-01-01',
        'bank_name' => 'Maybank',
        'bank_account_number' => '123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['ic_number']);
});

test('employee show returns employee with all relationships', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    EmployeeEmergencyContact::factory()->create(['employee_id' => $data['employee']->id]);
    EmployeeHistory::factory()->create([
        'employee_id' => $data['employee']->id,
        'changed_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/employees/{$data['employee']->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.full_name', $data['employee']->full_name)
        ->assertJsonStructure([
            'data' => [
                'id', 'full_name', 'department', 'position',
                'emergency_contacts', 'documents', 'histories',
            ],
        ]);
});

test('employee update tracks field changes in history', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();
    $newDepartment = Department::factory()->create();

    $response = $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$data['employee']->id}", [
            'department_id' => $newDepartment->id,
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('employee_histories', [
        'employee_id' => $data['employee']->id,
        'change_type' => 'department_transfer',
        'field_name' => 'department_id',
        'new_value' => (string) $newDepartment->id,
    ]);
});

test('employee update status creates history entry', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->patchJson("/api/hr/employees/{$data['employee']->id}/status", [
            'status' => 'resigned',
            'effective_date' => '2026-03-26',
            'remarks' => 'Voluntary resignation',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('employees', [
        'id' => $data['employee']->id,
        'status' => 'resigned',
    ]);

    $this->assertDatabaseHas('employee_histories', [
        'employee_id' => $data['employee']->id,
        'change_type' => 'status_change',
        'field_name' => 'status',
        'old_value' => 'active',
        'new_value' => 'resigned',
        'remarks' => 'Voluntary resignation',
    ]);
});

test('employee delete soft deletes the employee', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->deleteJson("/api/hr/employees/{$data['employee']->id}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('employees', ['id' => $data['employee']->id]);
});

test('employee next id returns next available id', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin)->getJson('/api/hr/employees/next-id');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['next_id']]);
});

test('employee export returns csv', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)->get('/api/hr/employees/export');

    $response->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

/*
|--------------------------------------------------------------------------
| Department CRUD Tests
|--------------------------------------------------------------------------
*/

test('department index returns list with employee counts', function () {
    $admin = createAdminUser();
    Department::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/departments');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('department index supports search', function () {
    $admin = createAdminUser();
    Department::factory()->create(['name' => 'Engineering', 'code' => 'ENG']);
    Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);

    $response = $this->actingAs($admin)->getJson('/api/hr/departments?search=Eng');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('department store creates department', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/departments', [
        'name' => 'Human Resources',
        'code' => 'HR',
        'description' => 'HR Department',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Human Resources');

    $this->assertDatabaseHas('departments', ['name' => 'Human Resources', 'code' => 'HR']);
});

test('department store validates unique code', function () {
    $admin = createAdminUser();
    Department::factory()->create(['code' => 'HR']);

    $response = $this->actingAs($admin)->postJson('/api/hr/departments', [
        'name' => 'HR Dept',
        'code' => 'HR',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('department show returns department with positions', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    Position::factory()->count(2)->create(['department_id' => $department->id]);

    $response = $this->actingAs($admin)->getJson("/api/hr/departments/{$department->id}");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.positions');
});

test('department update modifies department', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/departments/{$department->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('department destroy fails when employees are assigned', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->deleteJson("/api/hr/departments/{$data['department']->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete department with assigned employees.');
});

test('department destroy succeeds when no employees are assigned', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/departments/{$department->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});

test('department tree returns hierarchical structure', function () {
    $admin = createAdminUser();
    $parent = Department::factory()->create(['parent_id' => null]);
    Department::factory()->create(['parent_id' => $parent->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/departments/tree');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.children');
});

test('department employees returns employees list', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/departments/{$data['department']->id}/employees");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

/*
|--------------------------------------------------------------------------
| Position CRUD Tests
|--------------------------------------------------------------------------
*/

test('position index returns list with department and employee count', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();
    Position::factory()->count(3)->create(['department_id' => $department->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/positions');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('position index supports department filter', function () {
    $admin = createAdminUser();
    $dept1 = Department::factory()->create();
    $dept2 = Department::factory()->create();
    Position::factory()->count(2)->create(['department_id' => $dept1->id]);
    Position::factory()->create(['department_id' => $dept2->id]);

    $response = $this->actingAs($admin)->getJson("/api/hr/positions?department_id={$dept1->id}");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('position store creates position', function () {
    $admin = createAdminUser();
    $department = Department::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/hr/positions', [
        'title' => 'Software Engineer',
        'department_id' => $department->id,
        'level' => 3,
        'description' => 'Senior software engineer role',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Software Engineer');

    $this->assertDatabaseHas('positions', ['title' => 'Software Engineer']);
});

test('position update modifies position', function () {
    $admin = createAdminUser();
    $position = Position::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/positions/{$position->id}", [
        'title' => 'Updated Title',
        'level' => 5,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Updated Title');
});

test('position destroy fails when employees are assigned', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->deleteJson("/api/hr/positions/{$data['position']->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete position with assigned employees.');
});

test('position destroy succeeds when no employees are assigned', function () {
    $admin = createAdminUser();
    $position = Position::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/positions/{$position->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});

/*
|--------------------------------------------------------------------------
| Employee Document Tests
|--------------------------------------------------------------------------
*/

test('employee documents index returns list', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();
    EmployeeDocument::factory()->count(2)->create(['employee_id' => $data['employee']->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/employees/{$data['employee']->id}/documents");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('employee document upload stores file', function () {
    Storage::fake('public');
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$data['employee']->id}/documents", [
            'file' => $file,
            'document_type' => 'offer_letter',
            'notes' => 'Employment offer',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.document_type', 'offer_letter');

    $this->assertDatabaseHas('employee_documents', [
        'employee_id' => $data['employee']->id,
        'document_type' => 'offer_letter',
    ]);
});

test('employee document upload validates file size', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $file = UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf');

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$data['employee']->id}/documents", [
            'file' => $file,
            'document_type' => 'contract',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('employee document delete removes file and record', function () {
    Storage::fake('public');
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $filePath = 'employee-documents/test.pdf';
    Storage::disk('public')->put($filePath, 'test content');

    $document = EmployeeDocument::factory()->create([
        'employee_id' => $data['employee']->id,
        'file_path' => $filePath,
    ]);

    $response = $this->actingAs($admin)
        ->deleteJson("/api/hr/employees/{$data['employee']->id}/documents/{$document->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('employee_documents', ['id' => $document->id]);
    Storage::disk('public')->assertMissing($filePath);
});

/*
|--------------------------------------------------------------------------
| Emergency Contact Tests
|--------------------------------------------------------------------------
*/

test('emergency contacts index returns list', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();
    EmployeeEmergencyContact::factory()->count(2)->create(['employee_id' => $data['employee']->id]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/employees/{$data['employee']->id}/emergency-contacts");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('emergency contact store creates contact', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$data['employee']->id}/emergency-contacts", [
            'name' => 'Jane Doe',
            'relationship' => 'spouse',
            'phone' => '0198765432',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe');

    $this->assertDatabaseHas('employee_emergency_contacts', [
        'employee_id' => $data['employee']->id,
        'name' => 'Jane Doe',
    ]);
});

test('emergency contact store validates required fields', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$data['employee']->id}/emergency-contacts", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'relationship', 'phone']);
});

test('emergency contact update modifies contact', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();
    $contact = EmployeeEmergencyContact::factory()->create(['employee_id' => $data['employee']->id]);

    $response = $this->actingAs($admin)
        ->putJson("/api/hr/emergency-contacts/{$contact->id}", [
            'name' => 'Updated Name',
            'phone' => '0111111111',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('emergency contact delete removes contact', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();
    $contact = EmployeeEmergencyContact::factory()->create(['employee_id' => $data['employee']->id]);

    $response = $this->actingAs($admin)
        ->deleteJson("/api/hr/emergency-contacts/{$contact->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('employee_emergency_contacts', ['id' => $contact->id]);
});

/*
|--------------------------------------------------------------------------
| Employee History Tests
|--------------------------------------------------------------------------
*/

test('employee history index returns entries ordered by latest', function () {
    $admin = createAdminUser();
    $data = createEmployeeWithRelations();

    EmployeeHistory::factory()->count(5)->create([
        'employee_id' => $data['employee']->id,
        'changed_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/employees/{$data['employee']->id}/history");

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data');
});
