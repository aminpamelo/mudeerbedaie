<?php

declare(strict_types=1);

use App\Models\Certification;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\Position;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTrainingAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createTrainingSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('department', 'position', 'admin', 'employee');
}

test('unauthenticated users get 401 on training endpoints', function () {
    $this->getJson('/api/hr/training/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/training/programs')->assertUnauthorized();
});

test('admin can get training dashboard stats', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['upcoming_trainings', 'completed_this_year', 'total_spend', 'certs_expiring_soon']]);
});

test('admin can create training program', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/training/programs', [
        'title' => 'Fire Safety Training',
        'type' => 'internal',
        'category' => 'mandatory',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'max_participants' => 30,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Fire Safety Training')
        ->assertJsonPath('data.status', 'planned');
});

test('admin can enroll employees in training', function () {
    $setup = createTrainingSetup();
    $program = TrainingProgram::factory()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->postJson("/api/hr/training/programs/{$program->id}/enroll", [
        'employee_ids' => [$setup['employee']->id],
    ]);

    $response->assertCreated();
    expect(TrainingEnrollment::where('training_program_id', $program->id)->count())->toBe(1);
});

test('admin can update enrollment attendance', function () {
    $setup = createTrainingSetup();
    $enrollment = TrainingEnrollment::factory()->create([
        'enrolled_by' => $setup['admin']->id,
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/training/enrollments/{$enrollment->id}", [
        'status' => 'attended',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'attended');
});

test('admin can add training cost', function () {
    $admin = createTrainingAdmin();
    $program = TrainingProgram::factory()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->postJson("/api/hr/training/programs/{$program->id}/costs", [
        'description' => 'Venue rental',
        'amount' => 1500.00,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', '1500.00');
});

test('admin can CRUD certification types', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/training/certifications', [
        'name' => 'ISO 9001 Auditor',
        'issuing_body' => 'SIRIM',
        'validity_months' => 36,
    ]);

    $response->assertCreated();
    $certId = $response->json('data.id');

    $response = $this->actingAs($admin)->getJson('/api/hr/training/certifications');
    $response->assertSuccessful();

    $response = $this->actingAs($admin)->putJson("/api/hr/training/certifications/{$certId}", [
        'validity_months' => 24,
    ]);
    $response->assertSuccessful();
});

test('admin can add employee certification', function () {
    $setup = createTrainingSetup();
    $cert = Certification::factory()->create();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/training/employee-certifications', [
        'employee_id' => $setup['employee']->id,
        'certification_id' => $cert->id,
        'issued_date' => '2026-01-15',
        'expiry_date' => '2028-01-15',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');
});

test('admin can get expiring certifications', function () {
    $admin = createTrainingAdmin();
    EmployeeCertification::factory()->expiringSoon()->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/employee-certifications/expiring?days=90');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('admin can set training budget', function () {
    $setup = createTrainingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/training/budgets', [
        'department_id' => $setup['department']->id,
        'year' => 2026,
        'allocated_amount' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.allocated_amount', '50000.00');
});

test('admin can get training reports', function () {
    $admin = createTrainingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/training/reports');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['year', 'total_cost', 'total_enrollments', 'attendance_rate']]);
});

test('only planned programs can be deleted', function () {
    $admin = createTrainingAdmin();
    $program = TrainingProgram::factory()->completed()->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/training/programs/{$program->id}");

    $response->assertUnprocessable();
});

test('employee can view own training and certifications', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    TrainingEnrollment::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/training');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['trainings', 'certifications']]);
});

test('employee can submit training feedback', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $enrollment = TrainingEnrollment::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'attended',
    ]);

    $response = $this->actingAs($user)->putJson("/api/hr/me/training/{$enrollment->id}/feedback", [
        'feedback' => 'Very useful training session!',
        'feedback_rating' => 5,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.feedback_rating', 5);
});
