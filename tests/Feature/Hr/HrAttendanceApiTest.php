<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createAttendanceAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createAttendanceNonAdminUser(): User
{
    return User::factory()->create(['role' => 'student']);
}

/**
 * @return array{user: User, employee: Employee, department: Department, position: Position, schedule: WorkSchedule, employeeSchedule: EmployeeSchedule}
 */
function createEmployeeWithSchedule(): array
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

    $schedule = WorkSchedule::factory()->create([
        'start_time' => '09:00',
        'end_time' => '18:00',
        'grace_period_minutes' => 10,
    ]);

    $employeeSchedule = EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'effective_from' => now()->subMonth(),
        'effective_to' => null,
    ]);

    return compact('user', 'employee', 'department', 'position', 'schedule', 'employeeSchedule');
}

/*
|--------------------------------------------------------------------------
| Authentication & Authorization Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users get 401 on attendance endpoints', function () {
    $this->getJson('/api/hr/attendance')->assertUnauthorized();
    $this->getJson('/api/hr/work-schedules')->assertUnauthorized();
    $this->getJson('/api/hr/my-attendance')->assertUnauthorized();
    $this->postJson('/api/hr/my-attendance/clock-in')->assertUnauthorized();
    $this->getJson('/api/hr/overtime')->assertUnauthorized();
    $this->getJson('/api/hr/holidays')->assertUnauthorized();
    $this->getJson('/api/hr/department-approvers')->assertUnauthorized();
    $this->getJson('/api/hr/attendance-penalties')->assertUnauthorized();
    $this->getJson('/api/hr/attendance-analytics/overview')->assertUnauthorized();
});

test('non-admin users get 403 on admin-only endpoints', function () {
    $user = createAttendanceNonAdminUser();

    $this->actingAs($user)
        ->getJson('/api/hr/work-schedules')
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/hr/work-schedules', [])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Work Schedule Tests
|--------------------------------------------------------------------------
*/

test('admin can list work schedules', function () {
    $admin = createAttendanceAdminUser();
    WorkSchedule::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/hr/work-schedules');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin can create a work schedule with all fields', function () {
    $admin = createAttendanceAdminUser();

    $payload = [
        'name' => 'Morning Shift',
        'type' => 'fixed',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'break_duration_minutes' => 60,
        'min_hours_per_day' => 8.0,
        'grace_period_minutes' => 15,
        'working_days' => [1, 2, 3, 4, 5],
        'is_default' => false,
    ];

    $response = $this->actingAs($admin)->postJson('/api/hr/work-schedules', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Morning Shift')
        ->assertJsonPath('message', 'Work schedule created successfully.');

    $this->assertDatabaseHas('work_schedules', ['name' => 'Morning Shift', 'type' => 'fixed']);
});

test('work schedule store validates required fields', function () {
    $admin = createAttendanceAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/work-schedules', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type', 'break_duration_minutes', 'grace_period_minutes', 'working_days']);
});

test('admin can update a work schedule', function () {
    $admin = createAttendanceAdminUser();
    $schedule = WorkSchedule::factory()->create();

    $response = $this->actingAs($admin)->putJson("/api/hr/work-schedules/{$schedule->id}", [
        'name' => 'Updated Schedule',
        'type' => 'fixed',
        'start_time' => '08:30',
        'end_time' => '17:30',
        'break_duration_minutes' => 60,
        'grace_period_minutes' => 10,
        'working_days' => [1, 2, 3, 4, 5],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Schedule');
});

test('admin can delete a work schedule without employees', function () {
    $admin = createAttendanceAdminUser();
    $schedule = WorkSchedule::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/work-schedules/{$schedule->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Work schedule deleted successfully.');

    $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
});

/*
|--------------------------------------------------------------------------
| Clock In/Out Tests
|--------------------------------------------------------------------------
*/

test('employee can clock in', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    Carbon::setTestNow(Carbon::today()->setTime(8, 55));

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-in', [
            'photo' => $photo,
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Clocked in successfully.')
        ->assertJsonPath('is_late', false)
        ->assertJsonPath('data.status', 'present');

    $this->assertDatabaseHas('attendance_logs', [
        'employee_id' => $data['employee']->id,
        'status' => 'present',
        'late_minutes' => 0,
    ]);

    Carbon::setTestNow();
});

test('employee who clocks in late gets marked as late with correct late_minutes', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    Carbon::setTestNow(Carbon::today()->setTime(9, 30));

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-in', [
            'photo' => $photo,
        ]);

    $response->assertCreated()
        ->assertJsonPath('is_late', true)
        ->assertJsonPath('data.status', 'late');

    $log = AttendanceLog::where('employee_id', $data['employee']->id)->first();
    expect($log->late_minutes)->toBeGreaterThan(0);
    expect($log->status)->toBe('late');

    $this->assertDatabaseHas('attendance_penalties', [
        'employee_id' => $data['employee']->id,
        'penalty_type' => 'late_arrival',
    ]);

    Carbon::setTestNow();
});

test('cannot clock in twice on same day', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    Carbon::setTestNow(Carbon::today()->setTime(8, 55));

    AttendanceLog::factory()->create([
        'employee_id' => $data['employee']->id,
        'date' => Carbon::today(),
        'clock_in' => now(),
        'status' => 'present',
    ]);

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-in', [
            'photo' => $photo,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'You have already clocked in today.');

    Carbon::setTestNow();
});

test('employee can clock out and total work minutes is calculated', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    $clockInTime = Carbon::today()->setTime(9, 0);

    AttendanceLog::factory()->create([
        'employee_id' => $data['employee']->id,
        'date' => Carbon::today(),
        'clock_in' => $clockInTime,
        'status' => 'present',
    ]);

    Carbon::setTestNow(Carbon::today()->setTime(18, 0));

    $photo = UploadedFile::fake()->image('clockout.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-out', [
            'photo' => $photo,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Clocked out successfully.');

    expect($response->json('total_work_minutes'))->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('cannot clock out without clocking in first', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    $photo = UploadedFile::fake()->image('clockout.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-out', [
            'photo' => $photo,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'You have not clocked in today.');
});

test('wfh clock in skips photo requirement', function () {
    $data = createEmployeeWithSchedule();

    Carbon::setTestNow(Carbon::today()->setTime(9, 0));

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-in', [
            'is_wfh' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'wfh');

    $this->assertDatabaseHas('attendance_logs', [
        'employee_id' => $data['employee']->id,
        'status' => 'wfh',
    ]);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Overtime Tests
|--------------------------------------------------------------------------
*/

test('employee can submit overtime request', function () {
    $data = createEmployeeWithSchedule();

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/overtime', [
            'requested_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'estimated_hours' => 2.0,
            'reason' => 'Need to complete the quarterly report deadline.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Overtime request submitted successfully.')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('overtime_requests', [
        'employee_id' => $data['employee']->id,
        'status' => 'pending',
    ]);
});

test('admin can list overtime requests', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    OvertimeRequest::factory()->count(3)->create([
        'employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/overtime');

    $response->assertSuccessful()
        ->assertJsonPath('total', 3);
});

test('admin can approve overtime request', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/overtime/{$otRequest->id}/approve");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Overtime request approved successfully.');

    $otRequest->refresh();
    expect($otRequest->status)->toBe('approved');
    expect($otRequest->approved_by)->toBe($admin->id);
    expect($otRequest->approved_at)->not->toBeNull();
});

test('admin can reject overtime request with reason', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/overtime/{$otRequest->id}/reject", [
            'rejection_reason' => 'Budget constraints this quarter.',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Overtime request rejected.');

    $otRequest->refresh();
    expect($otRequest->status)->toBe('rejected');
    expect($otRequest->rejection_reason)->toBe('Budget constraints this quarter.');
});

test('admin can complete overtime with actual hours', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $otRequest = OvertimeRequest::factory()->approved()->create([
        'employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/api/hr/overtime/{$otRequest->id}/complete", [
            'actual_hours' => 3.0,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Overtime request completed successfully.');

    $otRequest->refresh();
    expect($otRequest->status)->toBe('completed');
    expect((float) $otRequest->actual_hours)->toBe(3.0);
    expect((float) $otRequest->replacement_hours_earned)->toBe(3.0);
});

/*
|--------------------------------------------------------------------------
| Holiday Tests
|--------------------------------------------------------------------------
*/

test('admin can create a holiday', function () {
    $admin = createAttendanceAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/holidays', [
        'name' => 'Hari Raya Aidilfitri',
        'date' => '2026-03-30',
        'type' => 'national',
        'year' => 2026,
        'is_recurring' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Hari Raya Aidilfitri');

    $this->assertDatabaseHas('holidays', ['name' => 'Hari Raya Aidilfitri']);
});

test('admin can update a holiday', function () {
    $admin = createAttendanceAdminUser();
    $holiday = Holiday::factory()->create(['name' => 'Old Name', 'type' => 'national', 'year' => 2026]);

    $response = $this->actingAs($admin)->putJson("/api/hr/holidays/{$holiday->id}", [
        'name' => 'Updated Holiday',
        'date' => '2026-06-01',
        'type' => 'national',
        'year' => 2026,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Holiday');
});

test('admin can delete a holiday', function () {
    $admin = createAttendanceAdminUser();
    $holiday = Holiday::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/hr/holidays/{$holiday->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Holiday deleted successfully.');

    $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
});

test('admin can bulk import Malaysian holidays', function () {
    $admin = createAttendanceAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/holidays/bulk-import', [
        'year' => 2026,
    ]);

    $response->assertCreated();

    $count = Holiday::where('year', 2026)->count();
    expect($count)->toBeGreaterThanOrEqual(10);
});

test('holiday store validates required fields', function () {
    $admin = createAttendanceAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/holidays', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'date', 'type', 'year']);
});

/*
|--------------------------------------------------------------------------
| Department Approver Tests
|--------------------------------------------------------------------------
*/

test('admin can create an approver for a department', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $response = $this->actingAs($admin)->postJson('/api/hr/department-approvers', [
        'department_id' => $data['department']->id,
        'approver_employee_id' => $data['employee']->id,
        'approval_type' => 'overtime',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Department approver created successfully.');

    $this->assertDatabaseHas('department_approvers', [
        'department_id' => $data['department']->id,
        'approver_employee_id' => $data['employee']->id,
        'approval_type' => 'overtime',
    ]);
});

test('admin can list approvers grouped by department', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    DepartmentApprover::factory()->create([
        'department_id' => $data['department']->id,
        'approver_employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/department-approvers');

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

test('admin can delete an approver', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $approver = DepartmentApprover::factory()->create([
        'department_id' => $data['department']->id,
        'approver_employee_id' => $data['employee']->id,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/department-approvers/{$approver->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Department approver deleted successfully.');

    $this->assertDatabaseMissing('department_approvers', ['id' => $approver->id]);
});

/*
|--------------------------------------------------------------------------
| Penalty Tests
|--------------------------------------------------------------------------
*/

test('late clock in creates penalty record automatically', function () {
    Storage::fake('public');
    $data = createEmployeeWithSchedule();

    Carbon::setTestNow(Carbon::today()->setTime(9, 30));

    $photo = UploadedFile::fake()->image('selfie.jpg');

    $this->actingAs($data['user'])
        ->postJson('/api/hr/my-attendance/clock-in', [
            'photo' => $photo,
        ]);

    $this->assertDatabaseHas('attendance_penalties', [
        'employee_id' => $data['employee']->id,
        'penalty_type' => 'late_arrival',
        'month' => Carbon::today()->month,
        'year' => Carbon::today()->year,
    ]);

    $penalty = AttendancePenalty::where('employee_id', $data['employee']->id)->first();
    expect($penalty->penalty_minutes)->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('flagged endpoint returns employees with 3+ lates', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    $currentMonth = now()->month;
    $currentYear = now()->year;

    for ($i = 0; $i < 4; $i++) {
        $log = AttendanceLog::factory()->late()->create([
            'employee_id' => $data['employee']->id,
            'date' => Carbon::today()->subDays($i),
        ]);

        AttendancePenalty::factory()->create([
            'employee_id' => $data['employee']->id,
            'attendance_log_id' => $log->id,
            'penalty_type' => 'late_arrival',
            'penalty_minutes' => 20,
            'month' => $currentMonth,
            'year' => $currentYear,
        ]);
    }

    $response = $this->actingAs($admin)->getJson('/api/hr/attendance-penalties/flagged');

    $response->assertSuccessful();

    $flagged = collect($response->json('data'));
    $employeeFlag = $flagged->firstWhere('employee_id', $data['employee']->id);
    expect($employeeFlag)->not->toBeNull();
    expect($employeeFlag['late_count'])->toBeGreaterThanOrEqual(3);
});

/*
|--------------------------------------------------------------------------
| Analytics Tests
|--------------------------------------------------------------------------
*/

test('attendance overview returns correct today stats', function () {
    $admin = createAttendanceAdminUser();
    $data = createEmployeeWithSchedule();

    AttendanceLog::factory()->create([
        'employee_id' => $data['employee']->id,
        'date' => Carbon::today(),
        'status' => 'present',
    ]);

    $dept2 = Department::factory()->create();
    $pos2 = Position::factory()->create(['department_id' => $dept2->id]);
    $emp2 = Employee::factory()->create([
        'department_id' => $dept2->id,
        'position_id' => $pos2->id,
        'status' => 'active',
    ]);

    AttendanceLog::factory()->late()->create([
        'employee_id' => $emp2->id,
        'date' => Carbon::today(),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/hr/attendance-analytics/overview');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'today',
                'total_active_employees',
                'thirty_day_attendance_rate',
            ],
        ]);
});

test('attendance trends returns 12 months of data', function () {
    $admin = createAttendanceAdminUser();

    $response = $this->actingAs($admin)->getJson('/api/hr/attendance-analytics/trends');

    $response->assertSuccessful()
        ->assertJsonCount(12, 'data');

    $firstMonth = $response->json('data.0');
    expect($firstMonth)->toHaveKeys(['month', 'label', 'present', 'late', 'absent', 'on_leave']);
});
