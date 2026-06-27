<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeAdjustment;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use App\Services\Hr\OvertimeBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, employee: Employee}
 */
function makeOtEmployee(): array
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

    return compact('user', 'employee');
}

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('available balance = earned minus approved claims', function () {
    ['user' => $user, 'employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'requested_date' => now(),
        'replacement_hours_earned' => 10.0,
    ]);

    OvertimeClaimRequest::create([
        'employee_id' => $employee->id,
        'claim_date' => now()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/overtime/balance');

    $response->assertSuccessful()
        ->assertJsonPath('data.total_earned', 10)
        ->assertJsonPath('data.total_used', 1.5)
        ->assertJsonPath('data.available', 8.5)
        ->assertJsonPath('data.available_minutes', 510);
});

test('admin minus adjustment reduces the employee available balance', function () {
    ['user' => $user, 'employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'requested_date' => now(),
        'replacement_hours_earned' => 10.0,
    ]);

    // Before adjustment: 600 min available.
    $this->actingAs($user)->getJson('/api/hr/me/overtime/balance')
        ->assertJsonPath('data.available_minutes', 600);

    // Admin deducts 30 minutes.
    $this->actingAs(adminUser())->postJson('/api/hr/overtime/adjustments', [
        'employee_id' => $employee->id,
        'minutes' => -30,
        'reason' => 'Correction for miscounted hours',
    ])->assertCreated()
        ->assertJsonPath('data.minutes', -30);

    // After adjustment: the employee now sees 570 min available.
    $this->actingAs($user)->getJson('/api/hr/me/overtime/balance')
        ->assertJsonPath('data.available_minutes', 570)
        ->assertJsonPath('data.available', 9.5);
});

test('admin by-employee replacement balance equals the employee available', function () {
    ['user' => $user, 'employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'requested_date' => now(),
        'replacement_hours_earned' => 10.0,
    ]);
    OvertimeClaimRequest::create([
        'employee_id' => $employee->id,
        'claim_date' => now()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'status' => 'approved',
    ]);
    OvertimeAdjustment::create([
        'employee_id' => $employee->id,
        'minutes' => -30,
        'reason' => 'Manual deduction',
        'effective_date' => now()->toDateString(),
    ]);

    // Employee view: 600 - 90 - 30 = 480 min = 8.0h
    $employeeAvailable = (float) $this->actingAs($user)->getJson('/api/hr/me/overtime/balance')
        ->json('data.available');

    expect($employeeAvailable)->toBe(8.0);

    $rows = $this->actingAs(adminUser())
        ->getJson('/api/hr/overtime/by-employee?period=all')
        ->assertSuccessful()
        ->json('data');

    $row = collect($rows)->firstWhere('employee_id', $employee->id);

    expect($row)->not->toBeNull();
    expect((float) $row['replacement_balance'])->toBe(8.0);
    expect((float) $row['replacement_balance'])->toBe($employeeAvailable);
});

test('replacement leave usage is subtracted from the balance', function () {
    ['user' => $user, 'employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'requested_date' => now(),
        'replacement_hours_earned' => 10.0,
        'replacement_hours_used' => 2.0, // consumed by a replacement leave
    ]);

    $this->actingAs($user)->getJson('/api/hr/me/overtime/balance')
        ->assertJsonPath('data.total_used', 2)
        ->assertJsonPath('data.available_minutes', 480);
});

test('balance service combines every channel in minutes', function () {
    ['employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'replacement_hours_earned' => 5.0,
        'replacement_hours_used' => 1.0,
    ]);
    OvertimeClaimRequest::create([
        'employee_id' => $employee->id,
        'claim_date' => now()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 45,
        'status' => 'approved',
    ]);
    OvertimeAdjustment::create([
        'employee_id' => $employee->id,
        'minutes' => 15,
        'reason' => 'Bonus',
        'effective_date' => now()->toDateString(),
    ]);

    $balance = app(OvertimeBalanceService::class)->forEmployee($employee->id);

    // earned 300 + adj 15 - claim 45 - leave 60 = 210
    expect($balance)->toMatchArray([
        'earned_minutes' => 300,
        'used_minutes' => 105,
        'adjustment_minutes' => 15,
        'available_minutes' => 210,
    ]);
});

test('admin adjustment requires non-zero minutes within range', function () {
    ['employee' => $employee] = makeOtEmployee();
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/hr/overtime/adjustments', [
        'employee_id' => $employee->id,
        'minutes' => 0,
        'reason' => 'No-op should fail',
    ])->assertStatus(422)->assertJsonValidationErrors('minutes');

    $this->actingAs($admin)->postJson('/api/hr/overtime/adjustments', [
        'employee_id' => $employee->id,
        'minutes' => 5000,
        'reason' => 'Too large',
    ])->assertStatus(422)->assertJsonValidationErrors('minutes');

    $this->actingAs($admin)->postJson('/api/hr/overtime/adjustments', [
        'employee_id' => $employee->id,
        'minutes' => 45,
        'reason' => 'Valid adjustment',
    ])->assertCreated();

    expect(OvertimeAdjustment::where('employee_id', $employee->id)->where('minutes', 45)->exists())->toBeTrue();
});

test('overview headline replacement balance reflects all channels', function () {
    ['employee' => $employee] = makeOtEmployee();

    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'requested_date' => now(),
        'replacement_hours_earned' => 10.0,
    ]);
    OvertimeClaimRequest::create([
        'employee_id' => $employee->id,
        'claim_date' => now()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 120,
        'status' => 'approved',
    ]);

    $stats = $this->actingAs(adminUser())
        ->getJson('/api/hr/overtime/overview?period=this_month')
        ->assertSuccessful()
        ->json('data.stats');

    // earned 10h - used 2h = 8h, regardless of the period window.
    expect((float) $stats['replacement_earned'])->toBe(10.0);
    expect((float) $stats['replacement_used'])->toBe(2.0);
    expect((float) $stats['replacement_balance'])->toBe(8.0);
});
