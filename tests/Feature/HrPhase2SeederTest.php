<?php

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\WorkSchedule;
use Database\Seeders\HrPhase2Seeder;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(HrSeeder::class);
});

test('seeder creates all 8 Malaysian leave types', function () {
    expect(LeaveType::count())->toBe(8);

    $codes = LeaveType::pluck('code')->sort()->values()->toArray();
    expect($codes)->toBe(['AL', 'CL', 'HL', 'MC', 'ML', 'PL', 'RL', 'UL']);
});

test('seeder creates leave entitlement rules', function () {
    expect(LeaveEntitlement::count())->toBe(17);

    $alEntitlements = LeaveEntitlement::query()
        ->whereHas('leaveType', fn ($q) => $q->where('code', 'AL'))
        ->count();
    expect($alEntitlements)->toBe(6);
});

test('seeder creates two work schedules with one default', function () {
    expect(WorkSchedule::count())->toBe(2);
    expect(WorkSchedule::where('is_default', true)->count())->toBe(1);

    $defaultSchedule = WorkSchedule::where('is_default', true)->first();
    expect($defaultSchedule->name)->toBe('Office Hours');
    expect($defaultSchedule->type)->toBe('fixed');
});

test('seeder creates 2026 Malaysian holidays', function () {
    expect(Holiday::where('year', 2026)->count())->toBe(16);
    expect(Holiday::where('type', 'national')->count())->toBeGreaterThanOrEqual(14);
});

test('seeder assigns default schedule to all active employees', function () {
    $activeEmployees = Employee::whereIn('status', ['active', 'probation'])->count();
    expect(EmployeeSchedule::count())->toBe($activeEmployees);
});

test('seeder sets department approvers for overtime and leave', function () {
    $departmentsWithEmployees = Department::query()
        ->whereHas('employees', fn ($q) => $q->whereIn('status', ['active', 'probation']))
        ->count();

    expect(DepartmentApprover::where('approval_type', 'overtime')->count())->toBe($departmentsWithEmployees);
    expect(DepartmentApprover::where('approval_type', 'leave')->count())->toBe($departmentsWithEmployees);
});

test('seeder initializes leave balances for all active employees', function () {
    expect(LeaveBalance::where('year', 2026)->count())->toBeGreaterThan(0);

    $balance = LeaveBalance::where('year', 2026)->first();
    expect($balance->entitled_days)->toBeGreaterThanOrEqual(0);
    expect($balance->available_days)->toBeGreaterThanOrEqual(0);
});

test('seeder creates attendance logs for working days', function () {
    expect(AttendanceLog::count())->toBeGreaterThan(0);

    $statuses = AttendanceLog::query()->distinct()->pluck('status')->toArray();
    expect($statuses)->toContain('present');
});

test('seeder creates 10 overtime requests with mixed statuses', function () {
    expect(OvertimeRequest::count())->toBe(10);
    expect(OvertimeRequest::where('status', 'pending')->count())->toBe(3);
    expect(OvertimeRequest::where('status', 'approved')->count())->toBe(3);
    expect(OvertimeRequest::where('status', 'completed')->count())->toBe(2);
    expect(OvertimeRequest::where('status', 'rejected')->count())->toBe(2);
});

test('seeder creates 10 leave requests with mixed statuses', function () {
    expect(LeaveRequest::count())->toBe(10);
    expect(LeaveRequest::where('status', 'pending')->count())->toBe(3);
    expect(LeaveRequest::where('status', 'approved')->count())->toBe(4);
    expect(LeaveRequest::where('status', 'rejected')->count())->toBe(2);
    expect(LeaveRequest::where('status', 'cancelled')->count())->toBe(1);
});

test('seeder reference data is idempotent when run twice', function () {
    $this->seed(HrPhase2Seeder::class);

    expect(LeaveType::count())->toBe(8);
    expect(WorkSchedule::count())->toBe(2);
    expect(Holiday::where('year', 2026)->count())->toBe(16);
    expect(LeaveEntitlement::count())->toBe(17);
});
