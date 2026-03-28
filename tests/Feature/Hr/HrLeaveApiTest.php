<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLeaveAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createLeaveEmployeeWithUser(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'gender' => 'male',
        'employment_type' => 'full_time',
        'join_date' => '2024-01-01',
    ]);

    return compact('department', 'position', 'user', 'employee');
}

/*
|--------------------------------------------------------------------------
| 1. Leave Types Tests
|--------------------------------------------------------------------------
*/

test('can list leave types', function () {
    $admin = createLeaveAdminUser();
    LeaveType::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/types');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can create a custom leave type', function () {
    $admin = createLeaveAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/leave/types', [
        'name' => 'Birthday Leave',
        'code' => 'BL',
        'is_paid' => true,
        'is_attachment_required' => false,
        'color' => '#FF5733',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Birthday Leave')
        ->assertJsonPath('message', 'Leave type created successfully.');

    $this->assertDatabaseHas('leave_types', ['name' => 'Birthday Leave', 'code' => 'BL']);
});

test('can update a leave type', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->create([
        'name' => 'Old Name',
        'code' => 'ON',
        'color' => '#000000',
        'is_paid' => true,
        'is_attachment_required' => false,
    ]);

    $response = $this->actingAs($admin)->putJson("/api/hr/leave/types/{$leaveType->id}", [
        'name' => 'Updated Name',
        'code' => 'UN',
        'is_paid' => false,
        'is_attachment_required' => true,
        'color' => '#FFFFFF',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('message', 'Leave type updated successfully.');
});

test('cannot delete system leave types', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->system()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/leave/types/{$leaveType->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'System leave types cannot be deleted.');

    $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id]);
});

test('can delete custom leave types', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->create(['is_system' => false]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/leave/types/{$leaveType->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave type deleted successfully.');

    $this->assertDatabaseMissing('leave_types', ['id' => $leaveType->id]);
});

/*
|--------------------------------------------------------------------------
| 2. Leave Entitlements Tests
|--------------------------------------------------------------------------
*/

test('can list entitlements grouped by leave type', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->create();
    LeaveEntitlement::factory()->count(2)->create(['leave_type_id' => $leaveType->id]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/entitlements');

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

test('can create entitlement rule', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/hr/leave/entitlements', [
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 0,
        'max_service_months' => 24,
        'days_per_year' => 14,
        'is_prorated' => true,
        'carry_forward_max' => 5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Leave entitlement created successfully.');

    $this->assertDatabaseHas('leave_entitlements', [
        'leave_type_id' => $leaveType->id,
        'days_per_year' => 14,
    ]);
});

test('can update entitlement rule', function () {
    $admin = createLeaveAdminUser();
    $leaveType = LeaveType::factory()->create();
    $entitlement = LeaveEntitlement::factory()->create(['leave_type_id' => $leaveType->id]);

    $response = $this->actingAs($admin)->putJson("/api/hr/leave/entitlements/{$entitlement->id}", [
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 0,
        'days_per_year' => 16,
        'is_prorated' => false,
        'carry_forward_max' => 3,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave entitlement updated successfully.');
});

test('can delete entitlement rule', function () {
    $admin = createLeaveAdminUser();
    $entitlement = LeaveEntitlement::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/leave/entitlements/{$entitlement->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave entitlement deleted successfully.');

    $this->assertDatabaseMissing('leave_entitlements', ['id' => $entitlement->id]);
});

/*
|--------------------------------------------------------------------------
| 3. Leave Balances Tests
|--------------------------------------------------------------------------
*/

test('can list balances for a year', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/balances?year='.now()->year);

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

test('can initialize balances for employees', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 0,
        'days_per_year' => 14,
    ]);

    $response = $this->actingAs($admin)->postJson('/api/hr/leave/balances/initialize', [
        'year' => now()->year,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('leave_balances', [
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
    ]);
});

test('can manually adjust balance', function () {
    $admin = createLeaveAdminUser();
    $balance = LeaveBalance::factory()->create([
        'available_days' => 10.0,
    ]);

    $response = $this->actingAs($admin)->postJson("/api/hr/leave/balances/{$balance->id}/adjust", [
        'adjustment_days' => 2,
        'reason' => 'Extra days awarded for good attendance',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave balance adjusted successfully.');

    $this->assertDatabaseHas('leave_balances', [
        'id' => $balance->id,
        'available_days' => 12.0,
    ]);
});

test('can view single employee balance detail', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/hr/leave/balances/{$data['employee']->id}?year=".now()->year);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

/*
|--------------------------------------------------------------------------
| 4. Apply for Leave Tests
|--------------------------------------------------------------------------
*/

test('employee can apply for leave', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'entitled_days' => 14,
        'available_days' => 14,
        'pending_days' => 0,
        'used_days' => 0,
    ]);

    $startDate = now()->addDays(7)->startOfWeek()->format('Y-m-d');
    $endDate = now()->addDays(7)->startOfWeek()->addDay()->format('Y-m-d');

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'reason' => 'Family vacation planned',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Leave request submitted successfully.');

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $balance->refresh();
    expect((float) $balance->pending_days)->toBeGreaterThan(0);
    expect((float) $balance->available_days)->toBeLessThan(14);
});

test('cannot apply without sufficient balance', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'entitled_days' => 1,
        'available_days' => 0.5,
        'pending_days' => 0,
        'used_days' => 0.5,
    ]);

    $monday = now()->addDays(14)->startOfWeek();

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $monday->format('Y-m-d'),
        'end_date' => $monday->copy()->addDays(4)->format('Y-m-d'),
        'reason' => 'Long trip planned for family',
    ]);

    $response->assertUnprocessable();
});

test('gender restriction check for maternity leave', function () {
    $data = createLeaveEmployeeWithUser();
    $maternityType = LeaveType::factory()->create([
        'name' => 'Maternity Leave',
        'code' => 'MAT',
        'gender_restriction' => 'female',
        'color' => '#FF69B4',
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $maternityType->id,
        'year' => now()->year,
        'available_days' => 60,
    ]);

    $startDate = now()->addDays(7)->startOfWeek()->format('Y-m-d');

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $maternityType->id,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'reason' => 'Maternity leave request needed',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'This leave type is not available for your gender.');
});

test('attachment required for MC type stores request without attachment', function () {
    $data = createLeaveEmployeeWithUser();
    $mcType = LeaveType::factory()->medical()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $mcType->id,
        'year' => now()->year,
        'available_days' => 14,
    ]);

    $startDate = now()->addDays(7)->startOfWeek()->format('Y-m-d');

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $mcType->id,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'reason' => 'Feeling unwell and visited doctor',
    ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();
    expect($leaveRequest->attachment_path)->toBeNull();
});

test('half day leave calculates as 0.5 days', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 14,
        'pending_days' => 0,
    ]);

    $startDate = now()->addDays(7)->startOfWeek()->format('Y-m-d');

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'is_half_day' => true,
        'half_day_period' => 'morning',
        'reason' => 'Morning appointment with dentist',
    ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();
    expect((float) $leaveRequest->total_days)->toBe(0.5);

    $balance = LeaveBalance::where('employee_id', $data['employee']->id)->first();
    expect((float) $balance->pending_days)->toBe(0.5);
    expect((float) $balance->available_days)->toBe(13.5);
});

test('working days calculation excludes weekends and holidays', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 14,
        'pending_days' => 0,
    ]);

    $monday = now()->addDays(14)->startOfWeek();

    Holiday::factory()->create([
        'date' => $monday->copy()->addDays(2),
        'year' => $monday->year,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $monday->format('Y-m-d'),
        'end_date' => $monday->copy()->addDays(4)->format('Y-m-d'),
        'reason' => 'Week off for family event',
    ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();
    expect((float) $leaveRequest->total_days)->toBe(4.0);
});

/*
|--------------------------------------------------------------------------
| 5. Approve/Reject Tests
|--------------------------------------------------------------------------
*/

test('admin can approve leave request', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'entitled_days' => 14,
        'available_days' => 12,
        'pending_days' => 2,
        'used_days' => 0,
    ]);

    $startDate = now()->addDays(7)->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $startDate->copy()->addDay(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave request approved successfully.');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('approved');
    expect($leaveRequest->approved_by)->toBe($admin->id);

    $balance->refresh();
    expect((float) $balance->used_days)->toBe(2.0);
    expect((float) $balance->pending_days)->toBe(0.0);
});

test('approved leave creates attendance logs for working days', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $workSchedule = WorkSchedule::factory()->create([
        'working_days' => [1, 2, 3, 4, 5],
    ]);
    EmployeeSchedule::factory()->create([
        'employee_id' => $data['employee']->id,
        'work_schedule_id' => $workSchedule->id,
        'effective_from' => now()->subMonth(),
        'effective_to' => null,
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 14,
        'pending_days' => 2,
    ]);

    $monday = now()->addDays(14)->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $monday,
        'end_date' => $monday->copy()->addDay(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve");

    $attendanceLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->get();

    expect($attendanceLogs->count())->toBe(2);
});

test('admin can reject leave request', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 12,
        'pending_days' => 2,
        'used_days' => 0,
    ]);

    $startDate = now()->addDays(7)->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $startDate->copy()->addDay(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/reject", [
        'rejection_reason' => 'Team capacity is low during this period',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave request rejected.');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('rejected');
    expect($leaveRequest->rejection_reason)->toBe('Team capacity is low during this period');

    $balance->refresh();
    expect((float) $balance->pending_days)->toBe(0.0);
    expect((float) $balance->available_days)->toBe(14.0);
});

test('reject requires rejection reason', function () {
    $admin = createLeaveAdminUser();
    $leaveRequest = LeaveRequest::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($admin)->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/reject", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);
});

/*
|--------------------------------------------------------------------------
| 6. Cancel Leave Tests
|--------------------------------------------------------------------------
*/

test('can cancel pending leave and balance is restored', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 12,
        'pending_days' => 2,
    ]);

    $startDate = now()->addDays(7)->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $startDate->copy()->addDay(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($data['user'])->deleteJson("/api/hr/me/leave/requests/{$leaveRequest->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Leave request cancelled successfully.');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('cancelled');

    $balance->refresh();
    expect((float) $balance->pending_days)->toBe(0.0);
    expect((float) $balance->available_days)->toBe(14.0);
});

test('can cancel future approved leave and balance is restored', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'available_days' => 12,
        'pending_days' => 0,
        'used_days' => 2,
    ]);

    $futureDate = now()->addDays(14)->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $futureDate,
        'end_date' => $futureDate->copy()->addDay(),
        'total_days' => 2,
    ]);

    AttendanceLog::factory()->create([
        'employee_id' => $data['employee']->id,
        'date' => $futureDate,
        'status' => 'on_leave',
    ]);
    AttendanceLog::factory()->create([
        'employee_id' => $data['employee']->id,
        'date' => $futureDate->copy()->addDay(),
        'status' => 'on_leave',
    ]);

    $response = $this->actingAs($data['user'])->deleteJson("/api/hr/me/leave/requests/{$leaveRequest->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Approved leave cancelled successfully.');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('cancelled');

    $balance->refresh();
    expect((float) $balance->used_days)->toBe(0.0);
    expect((float) $balance->available_days)->toBe(14.0);

    $attendanceLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->count();
    expect($attendanceLogs)->toBe(0);
});

test('cannot cancel approved leave with past dates', function () {
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->annual()->create();

    $pastDate = now()->subDays(3);
    $leaveRequest = LeaveRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $pastDate,
        'end_date' => $pastDate->copy()->addDay(),
        'total_days' => 2,
    ]);

    $response = $this->actingAs($data['user'])->deleteJson("/api/hr/me/leave/requests/{$leaveRequest->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot cancel approved leave that has already started or passed.');
});

/*
|--------------------------------------------------------------------------
| 7. Leave Calendar Tests
|--------------------------------------------------------------------------
*/

test('leave calendar returns approved leaves for given month', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->create();

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfMonth()->addDays(5),
        'end_date' => now()->startOfMonth()->addDays(6),
        'total_days' => 2,
    ]);

    LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfMonth()->addDays(10),
        'end_date' => now()->startOfMonth()->addDays(10),
        'total_days' => 1,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/calendar?month='.now()->month.'&year='.now()->year);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('leave calendar overlaps endpoint returns correct count', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->create();

    $startDate = now()->addDays(7)->startOfWeek();
    LeaveRequest::factory()->approved()->count(3)->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $startDate->copy()->addDays(2),
        'total_days' => 3,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/calendar/overlaps?'.http_build_query([
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $startDate->copy()->addDays(2)->format('Y-m-d'),
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('data.overlap_count', 3);
});

/*
|--------------------------------------------------------------------------
| 8. Leave Dashboard Tests
|--------------------------------------------------------------------------
*/

test('leave dashboard stats returns correct counts', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType = LeaveType::factory()->create();

    LeaveRequest::factory()->count(2)->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'pending_count',
                'approved_this_month',
                'on_leave_today',
                'upcoming_leaves',
            ],
        ])
        ->assertJsonPath('data.pending_count', 2);
});

test('leave dashboard distribution returns per-type counts', function () {
    $admin = createLeaveAdminUser();
    $data = createLeaveEmployeeWithUser();
    $leaveType1 = LeaveType::factory()->annual()->create();
    $leaveType2 = LeaveType::factory()->medical()->create();

    LeaveRequest::factory()->approved()->count(3)->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType1->id,
        'start_date' => now()->startOfYear()->addDays(10),
        'end_date' => now()->startOfYear()->addDays(11),
    ]);

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType2->id,
        'start_date' => now()->startOfYear()->addDays(20),
        'end_date' => now()->startOfYear()->addDays(20),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/leave/dashboard/distribution');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
