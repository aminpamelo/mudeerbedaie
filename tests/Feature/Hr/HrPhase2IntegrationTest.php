<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * @return array{admin: User, user: User, employee: Employee, department: Department, position: Position, schedule: WorkSchedule, employeeSchedule: EmployeeSchedule}
 */
function createIntegrationEmployeeWithSchedule(array $employeeOverrides = [], array $scheduleOverrides = []): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(array_merge([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'gender' => 'male',
        'employment_type' => 'full_time',
        'join_date' => '2024-01-01',
    ], $employeeOverrides));

    $schedule = WorkSchedule::factory()->create(array_merge([
        'start_time' => '09:00',
        'end_time' => '18:00',
        'grace_period_minutes' => 10,
        'working_days' => [1, 2, 3, 4, 5],
    ], $scheduleOverrides));

    $employeeSchedule = EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'effective_from' => now()->subMonth(),
        'effective_to' => null,
    ]);

    return compact('admin', 'user', 'employee', 'department', 'position', 'schedule', 'employeeSchedule');
}

/*
|--------------------------------------------------------------------------
| 1. Leave -> Attendance Sync
|--------------------------------------------------------------------------
*/

test('approving leave creates attendance logs for working days only', function () {
    // Set test time to a known Monday
    $monday = Carbon::parse('next monday');
    Carbon::setTestNow($monday);

    $data = createIntegrationEmployeeWithSchedule();

    $leaveType = LeaveType::factory()->annual()->create();

    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 0,
        'days_per_year' => 14,
    ]);

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => $monday->year,
        'entitled_days' => 10.0,
        'used_days' => 0,
        'pending_days' => 0,
        'available_days' => 10.0,
    ]);

    // Apply for leave (Mon-Tue) via API
    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/me/leave/requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $monday->toDateString(),
            'end_date' => $monday->copy()->addDay()->toDateString(),
            'reason' => 'Family vacation planned',
        ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();
    expect($leaveRequest->status)->toBe('pending');
    expect((float) $leaveRequest->total_days)->toBe(2.0);

    // Verify pending_days increased
    $balance->refresh();
    expect((float) $balance->pending_days)->toBe(2.0);
    expect((float) $balance->available_days)->toBe(8.0);

    // Approve leave as admin
    $approveResponse = $this->actingAs($data['admin'])
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve");

    $approveResponse->assertSuccessful();

    // Assert leave_request status = approved
    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('approved');

    // Assert balance: used_days increased by 2, pending_days decreased by 2
    $balance->refresh();
    expect((float) $balance->used_days)->toBe(2.0);
    expect((float) $balance->pending_days)->toBe(0.0);

    // Assert attendance_logs created for Mon and Tue with status 'on_leave'
    $monLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $monday->toDateString())
        ->where('status', 'on_leave')
        ->count();
    expect($monLogs)->toBe(1);

    $tueLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $monday->copy()->addDay()->toDateString())
        ->where('status', 'on_leave')
        ->count();
    expect($tueLogs)->toBe(1);

    // Only 2 on_leave records should exist (no weekend logs)
    $onLeaveCount = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->count();
    expect($onLeaveCount)->toBe(2);

    Carbon::setTestNow();
});

test('leave spanning weekend does not create attendance logs for Sat/Sun', function () {
    // Set to a Thursday
    $thursday = Carbon::parse('next thursday');
    Carbon::setTestNow($thursday);

    $data = createIntegrationEmployeeWithSchedule();

    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => $thursday->year,
        'entitled_days' => 10.0,
        'used_days' => 0,
        'pending_days' => 0,
        'available_days' => 10.0,
    ]);

    // Apply for leave Thursday to next Monday (spans weekend)
    $nextMonday = $thursday->copy()->addDays(4);
    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/me/leave/requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $thursday->toDateString(),
            'end_date' => $nextMonday->toDateString(),
            'reason' => 'Extended weekend trip',
        ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();

    // Approve
    $this->actingAs($data['admin'])
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve")
        ->assertSuccessful();

    // Should have 3 on_leave logs: Thu, Fri, Mon (skipping Sat/Sun)
    $onLeaveCount = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->count();
    expect($onLeaveCount)->toBe(3);

    // No logs for Saturday or Sunday
    $saturday = $thursday->copy()->addDays(2);
    $sunday = $thursday->copy()->addDays(3);

    $satCount = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $saturday->toDateString())
        ->count();
    expect($satCount)->toBe(0);

    $sunCount = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $sunday->toDateString())
        ->count();
    expect($sunCount)->toBe(0);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 2. OT -> Replacement Leave Flow
|--------------------------------------------------------------------------
*/

test('overtime completion earns replacement hours and can be used for replacement leave', function () {
    $nextMonday = Carbon::parse('next monday');
    Carbon::setTestNow($nextMonday);

    $data = createIntegrationEmployeeWithSchedule();

    // Create overtime request
    $otRequest = OvertimeRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'requested_date' => $nextMonday->copy()->subWeek()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '02:00',
        'estimated_hours' => 8.0,
        'reason' => 'Project deadline',
        'status' => 'pending',
    ]);

    // Approve overtime
    $this->actingAs($data['admin'])
        ->patchJson("/api/hr/overtime/{$otRequest->id}/approve")
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->status)->toBe('approved');

    // Complete overtime with actual_hours: 8
    $this->actingAs($data['admin'])
        ->patchJson("/api/hr/overtime/{$otRequest->id}/complete", [
            'actual_hours' => 8,
        ])
        ->assertSuccessful();

    $otRequest->refresh();
    expect($otRequest->status)->toBe('completed');
    expect((float) $otRequest->replacement_hours_earned)->toBe(8.0);

    // Create Replacement Leave type
    $rlType = LeaveType::factory()->create([
        'name' => 'Replacement Leave',
        'code' => 'RL',
        'is_system' => true,
        'is_active' => true,
    ]);

    // Create leave balance for RL
    $rlBalance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $rlType->id,
        'year' => $nextMonday->year,
        'entitled_days' => 1.0,
        'used_days' => 0,
        'pending_days' => 0,
        'available_days' => 1.0,
    ]);

    // Apply for replacement leave (1 day)
    $leaveResponse = $this->actingAs($data['user'])
        ->postJson('/api/hr/me/leave/requests', [
            'leave_type_id' => $rlType->id,
            'start_date' => $nextMonday->copy()->addWeek()->toDateString(),
            'end_date' => $nextMonday->copy()->addWeek()->toDateString(),
            'reason' => 'Using replacement leave from OT',
        ]);

    $leaveResponse->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)
        ->where('leave_type_id', $rlType->id)
        ->first();

    // Manually set replacement leave fields (normally set by frontend/apply logic)
    $leaveRequest->update([
        'is_replacement_leave' => true,
        'replacement_hours_deducted' => 8.0,
    ]);

    // Approve the replacement leave
    $this->actingAs($data['admin'])
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve")
        ->assertSuccessful();

    // Assert: overtime_request replacement_hours_used increased
    $otRequest->refresh();
    expect((float) $otRequest->replacement_hours_used)->toBe(8.0);

    // Assert: leave_request is_replacement_leave = true
    $leaveRequest->refresh();
    expect($leaveRequest->is_replacement_leave)->toBeTrue();
    expect($leaveRequest->status)->toBe('approved');

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 3. Clock In Late -> Auto Penalty
|--------------------------------------------------------------------------
*/

test('clocking in late creates attendance penalty with correct late minutes', function () {
    $today = Carbon::parse('next monday');
    Carbon::setTestNow($today->copy()->setTime(9, 25));

    $data = createIntegrationEmployeeWithSchedule([], [
        'start_time' => '09:00',
        'grace_period_minutes' => 10,
    ]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $photo = \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg');

    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/me/attendance/clock-in', [
            'photo' => $photo,
        ]);

    $response->assertCreated();

    // Assert: attendance_log status = 'late'
    $log = AttendanceLog::where('employee_id', $data['employee']->id)->first();
    expect($log->status)->toBe('late');
    expect($log->late_minutes)->toBeGreaterThanOrEqual(15);

    // Assert: attendance_penalty record created with type 'late_arrival'
    $this->assertDatabaseHas('attendance_penalties', [
        'employee_id' => $data['employee']->id,
        'attendance_log_id' => $log->id,
        'penalty_type' => 'late_arrival',
    ]);

    $penalty = AttendancePenalty::where('employee_id', $data['employee']->id)->first();
    expect($penalty->penalty_minutes)->toBeGreaterThanOrEqual(15);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 4. Holiday -> Mark Absent Skips
|--------------------------------------------------------------------------
*/

test('hr:mark-absent skips employees on holidays and marks absent otherwise', function () {
    $today = Carbon::parse('next monday');
    Carbon::setTestNow($today);

    $data = createIntegrationEmployeeWithSchedule();

    // Create a holiday for today
    $holiday = Holiday::factory()->create([
        'name' => 'Test Holiday',
        'date' => $today->toDateString(),
        'type' => 'national',
        'year' => $today->year,
    ]);

    // Run mark-absent command
    Artisan::call('hr:mark-absent');

    // Assert: NO attendance_log created (holiday skip)
    $holidayLogCount = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $today->toDateString())
        ->count();
    expect($holidayLogCount)->toBe(0);

    // Remove the holiday
    $holiday->delete();

    // Run command again
    Artisan::call('hr:mark-absent');

    // Assert: absent record created since it is a working day and no clock-in
    $absentLog = AttendanceLog::where('employee_id', $data['employee']->id)
        ->whereDate('date', $today->toDateString())
        ->where('status', 'absent')
        ->first();
    expect($absentLog)->not->toBeNull();

    // Verify penalty was also created
    expect(AttendancePenalty::where('employee_id', $data['employee']->id)
        ->where('penalty_type', 'absent_without_leave')
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 5. Leave Balance Initialization
|--------------------------------------------------------------------------
*/

test('leave balance initialization assigns correct days based on employment type and tenure', function () {
    $year = 2026;
    Carbon::setTestNow(Carbon::create($year, 3, 27));

    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    // Employee 1: full_time, joined 2 years ago (24 months)
    $user1 = User::factory()->create(['role' => 'employee']);
    $emp1 = Employee::factory()->create([
        'user_id' => $user1->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'employment_type' => 'full_time',
        'join_date' => Carbon::create($year, 1, 1)->subMonths(24),
    ]);

    // Employee 2: full_time, joined 5 years ago (60 months)
    $user2 = User::factory()->create(['role' => 'employee']);
    $emp2 = Employee::factory()->create([
        'user_id' => $user2->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'employment_type' => 'full_time',
        'join_date' => Carbon::create($year, 1, 1)->subMonths(60),
    ]);

    // Employee 3: part_time
    $user3 = User::factory()->create(['role' => 'employee']);
    $emp3 = Employee::factory()->create([
        'user_id' => $user3->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'employment_type' => 'part_time',
        'join_date' => Carbon::create($year, 1, 1)->subMonths(12),
    ]);

    // Create annual leave type
    $leaveType = LeaveType::factory()->annual()->create();

    // Create entitlement rules
    // 0-23 months full_time: 8 days
    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 0,
        'max_service_months' => 23,
        'days_per_year' => 8,
        'is_prorated' => false,
        'carry_forward_max' => 0,
    ]);

    // 24-59 months full_time: 12 days
    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 24,
        'max_service_months' => 59,
        'days_per_year' => 12,
        'is_prorated' => false,
        'carry_forward_max' => 0,
    ]);

    // 60+ months full_time: 16 days
    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'full_time',
        'min_service_months' => 60,
        'max_service_months' => null,
        'days_per_year' => 16,
        'is_prorated' => false,
        'carry_forward_max' => 0,
    ]);

    // part_time: 4 days
    LeaveEntitlement::factory()->create([
        'leave_type_id' => $leaveType->id,
        'employment_type' => 'part_time',
        'min_service_months' => 0,
        'max_service_months' => null,
        'days_per_year' => 4,
        'is_prorated' => false,
        'carry_forward_max' => 0,
    ]);

    // Run the command
    Artisan::call('hr:initialize-leave-balances', ['--year' => $year]);

    // Assert: Employee 1 (2yr full_time) gets 12 days
    $this->assertDatabaseHas('leave_balances', [
        'employee_id' => $emp1->id,
        'leave_type_id' => $leaveType->id,
        'year' => $year,
        'entitled_days' => 12.0,
    ]);

    // Assert: Employee 2 (5yr full_time) gets 16 days
    $this->assertDatabaseHas('leave_balances', [
        'employee_id' => $emp2->id,
        'leave_type_id' => $leaveType->id,
        'year' => $year,
        'entitled_days' => 16.0,
    ]);

    // Assert: Employee 3 (part_time) gets 4 days
    $this->assertDatabaseHas('leave_balances', [
        'employee_id' => $emp3->id,
        'leave_type_id' => $leaveType->id,
        'year' => $year,
        'entitled_days' => 4.0,
    ]);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 6. Cancel Approved Leave Restores Everything
|--------------------------------------------------------------------------
*/

test('cancelling approved leave restores balance and deletes attendance logs', function () {
    // Use a future Monday so the cancel check (start_date > today) passes
    $futureMonday = Carbon::parse('next monday')->addWeeks(2);
    Carbon::setTestNow($futureMonday->copy()->subWeek());

    $data = createIntegrationEmployeeWithSchedule();

    $leaveType = LeaveType::factory()->annual()->create();

    $balance = LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => $futureMonday->year,
        'entitled_days' => 10.0,
        'used_days' => 0,
        'pending_days' => 0,
        'available_days' => 10.0,
    ]);

    // Apply for 2 days leave (Mon-Tue)
    $response = $this->actingAs($data['user'])
        ->postJson('/api/hr/me/leave/requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $futureMonday->toDateString(),
            'end_date' => $futureMonday->copy()->addDay()->toDateString(),
            'reason' => 'Holiday plans',
        ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::where('employee_id', $data['employee']->id)->first();

    // Approve the leave
    $this->actingAs($data['admin'])
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve")
        ->assertSuccessful();

    // Verify post-approval state
    $balance->refresh();
    expect((float) $balance->used_days)->toBe(2.0);
    expect((float) $balance->available_days)->toBe(8.0);

    $attendanceLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->count();
    expect($attendanceLogs)->toBe(2);

    // Cancel the leave (future dates, so cancellation allowed)
    $cancelResponse = $this->actingAs($data['user'])
        ->deleteJson("/api/hr/me/leave/requests/{$leaveRequest->id}");

    $cancelResponse->assertSuccessful();

    // Assert: leave request status is cancelled
    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('cancelled');

    // Assert: balance restored
    $balance->refresh();
    expect((float) $balance->used_days)->toBe(0.0);
    expect((float) $balance->available_days)->toBe(10.0);

    // Assert: attendance_logs deleted
    $remainingLogs = AttendanceLog::where('employee_id', $data['employee']->id)
        ->where('status', 'on_leave')
        ->count();
    expect($remainingLogs)->toBe(0);

    Carbon::setTestNow();
});
