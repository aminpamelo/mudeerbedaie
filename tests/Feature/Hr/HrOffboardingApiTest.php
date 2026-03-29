<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use App\Models\ExitInterview;
use App\Models\FinalSettlement;
use App\Models\Position;
use App\Models\ResignationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOffboardingAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createOffboardingSetup(): array
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
        'employment_type' => 'full_time',
        'join_date' => now()->subYear(),
    ]);

    return compact('department', 'position', 'admin', 'adminEmployee', 'employee');
}

test('unauthenticated users get 401 on offboarding endpoints', function () {
    $this->getJson('/api/hr/offboarding/resignations')->assertUnauthorized();
    $this->getJson('/api/hr/offboarding/checklists')->assertUnauthorized();
});

test('admin can submit resignation on behalf of employee', function () {
    $setup = createOffboardingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/offboarding/resignations', [
        'employee_id' => $setup['employee']->id,
        'submitted_date' => '2026-03-28',
        'reason' => 'Moving to a different city.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.notice_period_days', 30)
        ->assertJsonPath('data.status', 'pending');
});

test('admin can approve resignation and exit checklist is created', function () {
    $setup = createOffboardingSetup();
    $resignation = ResignationRequest::factory()->create([
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/resignations/{$resignation->id}/approve", [
        'notes' => 'Approved. Best wishes.',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');

    // Verify exit checklist was created with default items
    expect(ExitChecklist::where('employee_id', $setup['employee']->id)->exists())->toBeTrue();
    $checklist = ExitChecklist::where('employee_id', $setup['employee']->id)->first();
    expect($checklist->items()->count())->toBeGreaterThanOrEqual(14);
});

test('admin can update exit checklist item status', function () {
    $setup = createOffboardingSetup();
    $checklist = ExitChecklist::factory()->create([
        'employee_id' => $setup['employee']->id,
    ]);
    $item = ExitChecklistItem::factory()->create([
        'exit_checklist_id' => $checklist->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson(
        "/api/hr/offboarding/checklists/{$checklist->id}/items/{$item->id}",
        ['status' => 'completed']
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');
});

test('admin can create exit interview', function () {
    $setup = createOffboardingSetup();

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/offboarding/exit-interviews', [
        'employee_id' => $setup['employee']->id,
        'interview_date' => '2026-04-20',
        'reason_for_leaving' => 'better_opportunity',
        'overall_satisfaction' => 4,
        'would_recommend' => true,
        'feedback' => 'Great company, but looking for new challenges.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.reason_for_leaving', 'better_opportunity');
});

test('admin can get exit interview analytics', function () {
    $admin = createOffboardingAdmin();
    ExitInterview::factory()->count(5)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/offboarding/exit-interviews/analytics');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['total_interviews', 'reasons', 'average_satisfaction', 'recommendation_rate']]);
});

test('admin can get final settlement list', function () {
    $admin = createOffboardingAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/offboarding/settlements');

    $response->assertSuccessful();
});

test('admin can approve and mark settlement as paid', function () {
    $setup = createOffboardingSetup();
    $settlement = FinalSettlement::factory()->calculated()->create([
        'employee_id' => $setup['employee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/settlements/{$settlement->id}/approve");
    $response->assertSuccessful()->assertJsonPath('data.status', 'approved');

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/settlements/{$settlement->id}/paid");
    $response->assertSuccessful()->assertJsonPath('data.status', 'paid');
});

test('admin can complete offboarding and employee status changes to resigned', function () {
    $setup = createOffboardingSetup();
    $resignation = ResignationRequest::factory()->approved()->create([
        'employee_id' => $setup['employee']->id,
        'approved_by' => $setup['adminEmployee']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/offboarding/resignations/{$resignation->id}/complete");

    $response->assertSuccessful();
    expect($setup['employee']->fresh()->status)->toBe('resigned');
});

test('employee can submit own resignation', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => 'full_time',
        'join_date' => now()->subYear(),
    ]);

    $response = $this->actingAs($user)->postJson('/api/hr/me/resignation', [
        'reason' => 'Personal reasons.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.notice_period_days', 30);
});

test('employee can view own resignation status', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    ResignationRequest::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/resignation');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['status', 'notice_period_days']]);
});
