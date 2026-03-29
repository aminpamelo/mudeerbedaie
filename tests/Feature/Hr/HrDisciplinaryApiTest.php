<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryInquiry;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDisciplinaryAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createDisciplinarySetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);
    $adminEmployee = Employee::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    return compact('department', 'position', 'admin', 'adminEmployee', 'employee');
}

test('unauthenticated users get 401 on disciplinary endpoints', function () {
    $this->getJson('/api/hr/disciplinary/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/disciplinary/actions')->assertUnauthorized();
});

test('admin can get disciplinary dashboard stats', function () {
    $admin = createDisciplinaryAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/disciplinary/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['active_cases', 'warnings_this_month', 'pending_responses', 'cases_by_type']]);
});

test('admin can create disciplinary action', function () {
    $setup = createDisciplinarySetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/disciplinary/actions', [
        'employee_id' => $setup['employee']->id,
        'type' => 'verbal_warning',
        'reason' => 'Late to work three times this month.',
        'incident_date' => '2026-03-20',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'verbal_warning')
        ->assertJsonStructure(['data' => ['reference_number']]);
});

test('admin can issue disciplinary action', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/actions/{$action->id}/issue");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'issued');
});

test('show cause action sets pending_response status on issue', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
        'type' => 'show_cause',
        'response_required' => true,
        'response_deadline' => now()->addDays(7),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/actions/{$action->id}/issue");

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'pending_response');
});

test('admin can schedule domestic inquiry', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/disciplinary/inquiries', [
        'disciplinary_action_id' => $action->id,
        'hearing_date' => '2026-04-15',
        'hearing_time' => '10:00',
        'location' => 'HR Meeting Room',
        'panel_members' => [$setup['adminEmployee']->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'scheduled');
});

test('admin can complete inquiry with decision', function () {
    $setup = createDisciplinarySetup();
    $action = DisciplinaryAction::factory()->create([
        'employee_id' => $setup['employee']->id,
        'issued_by' => $setup['adminEmployee']->id,
    ]);
    $inquiry = DisciplinaryInquiry::factory()->create([
        'disciplinary_action_id' => $action->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/disciplinary/inquiries/{$inquiry->id}/complete", [
        'decision' => 'guilty',
        'findings' => 'Employee found guilty of repeated misconduct.',
        'penalty' => '2-week suspension',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.decision', 'guilty');
});

test('employee can view own disciplinary records', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $issuer = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    DisciplinaryAction::factory()->issued()->create([
        'employee_id' => $employee->id,
        'issued_by' => $issuer->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/disciplinary');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('employee can respond to show cause', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $issuer = Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $action = DisciplinaryAction::factory()->showCause()->create([
        'employee_id' => $employee->id,
        'issued_by' => $issuer->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/hr/me/disciplinary/{$action->id}/respond", [
        'employee_response' => 'I was stuck in traffic due to an accident on the highway.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'responded');
});
