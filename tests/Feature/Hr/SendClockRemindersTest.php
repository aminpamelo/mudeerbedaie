<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Notifications\Hr\ClockInReminder;
use App\Notifications\Hr\ClockOutReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Cache::flush();
});

it('sends clock-out reminder once even when command runs multiple times in window', function () {
    $workSchedule = WorkSchedule::factory()->create([
        'start_time' => '09:00',
        'end_time' => '17:30',
    ]);

    $employee = Employee::factory()->create();

    EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $workSchedule->id,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    // Simulate first run at 17:15 (within the 15-min window before 17:30)
    Carbon::setTestNow(Carbon::today()->setTime(17, 15));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    // Simulate second run at 17:20
    Carbon::setTestNow(Carbon::today()->setTime(17, 20));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    // Should only be sent once despite two runs
    Notification::assertSentToTimes($employee->user, ClockOutReminder::class, 1);
});

it('sends clock-in reminder once even when command runs multiple times in window', function () {
    $workSchedule = WorkSchedule::factory()->create([
        'start_time' => '09:00',
        'end_time' => '18:00',
    ]);

    $employee = Employee::factory()->create();

    EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $workSchedule->id,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    // Simulate first run at 08:45 (within the 15-min window before 09:00)
    Carbon::setTestNow(Carbon::today()->setTime(8, 45));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    // Simulate second run at 08:50
    Carbon::setTestNow(Carbon::today()->setTime(8, 50));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    // Should only be sent once
    Notification::assertSentToTimes($employee->user, ClockInReminder::class, 1);
});

it('does not send clock-out reminder at exact shift end time', function () {
    $workSchedule = WorkSchedule::factory()->create([
        'start_time' => '09:00',
        'end_time' => '17:30',
    ]);

    $employee = Employee::factory()->create();

    EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $workSchedule->id,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    // At exactly 17:30 (shift end), should NOT send — window is [17:15, 17:30)
    Carbon::setTestNow(Carbon::today()->setTime(17, 30));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    Notification::assertNotSentTo($employee->user, ClockOutReminder::class);
});

it('does not send clock-out reminder if employee already clocked out', function () {
    $workSchedule = WorkSchedule::factory()->create([
        'start_time' => '09:00',
        'end_time' => '17:30',
    ]);

    $employee = Employee::factory()->create();

    EmployeeSchedule::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $workSchedule->id,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    // Employee already clocked out
    AttendanceLog::factory()->clockedOut()->create([
        'employee_id' => $employee->id,
        'date' => Carbon::today()->toDateString(),
    ]);

    Carbon::setTestNow(Carbon::today()->setTime(17, 20));
    $this->artisan('hr:send-clock-reminders')->assertSuccessful();

    Notification::assertNotSentTo($employee->user, ClockOutReminder::class);
});
