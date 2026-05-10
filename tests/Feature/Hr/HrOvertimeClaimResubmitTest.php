<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOtClaimEmployee(): array
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

    // Give the employee enough OT balance (10h earned) to cover several claims.
    OvertimeRequest::factory()->completed()->create([
        'employee_id' => $employee->id,
        'replacement_hours_earned' => 10.0,
    ]);

    return compact('user', 'employee');
}

test('employee can resubmit OT claim for same date after cancelling the previous one', function () {
    $data = createOtClaimEmployee();
    $date = '2026-05-08';

    $first = $this->actingAs($data['user'])->postJson('/api/hr/me/overtime/claims', [
        'claim_date' => $date,
        'start_time' => '10:00',
        'duration_minutes' => 210,
        'notes' => 'First attempt',
    ]);
    $first->assertCreated();
    $firstClaimId = $first->json('data.id');

    $cancel = $this->actingAs($data['user'])
        ->deleteJson("/api/hr/me/overtime/claims/{$firstClaimId}");
    $cancel->assertSuccessful();

    expect(OvertimeClaimRequest::find($firstClaimId)?->status)->toBe('cancelled');

    $second = $this->actingAs($data['user'])->postJson('/api/hr/me/overtime/claims', [
        'claim_date' => $date,
        'start_time' => '11:00',
        'duration_minutes' => 180,
        'notes' => 'Resubmission after cancellation',
    ]);

    $second->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.duration_minutes', 180);

    expect(OvertimeClaimRequest::where('employee_id', $data['employee']->id)
        ->whereDate('claim_date', $date)
        ->whereIn('status', ['pending', 'approved'])
        ->count())->toBe(1);
});

test('employee cannot have two active OT claims for the same date', function () {
    $data = createOtClaimEmployee();
    $date = '2026-05-08';

    $this->actingAs($data['user'])->postJson('/api/hr/me/overtime/claims', [
        'claim_date' => $date,
        'start_time' => '10:00',
        'duration_minutes' => 60,
    ])->assertCreated();

    $second = $this->actingAs($data['user'])->postJson('/api/hr/me/overtime/claims', [
        'claim_date' => $date,
        'start_time' => '14:00',
        'duration_minutes' => 60,
    ]);

    $second->assertStatus(422)
        ->assertJsonPath('message', 'You already have an active OT claim for this date.');
});

test('employee can resubmit OT claim for same date after rejection', function () {
    $data = createOtClaimEmployee();
    $date = '2026-05-08';

    $existing = OvertimeClaimRequest::create([
        'employee_id' => $data['employee']->id,
        'claim_date' => $date,
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/overtime/claims', [
        'claim_date' => $date,
        'start_time' => '15:00',
        'duration_minutes' => 90,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(OvertimeClaimRequest::where('employee_id', $data['employee']->id)
        ->whereDate('claim_date', $date)
        ->where('status', 'rejected')
        ->where('id', $existing->id)
        ->exists())->toBeTrue();
});
