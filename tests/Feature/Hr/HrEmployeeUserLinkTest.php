<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a different user account to an employee via update', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $currentUser = User::factory()->create(['role' => 'employee']);
    $newUser = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $currentUser->id]);

    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", ['user_id' => $newUser->id])
        ->assertOk();

    expect($employee->fresh()->user_id)->toBe($newUser->id);
});

it('rejects linking a user already linked to another employee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sharedUser = User::factory()->create(['role' => 'employee']);
    Employee::factory()->create(['user_id' => $sharedUser->id]);
    $ownUser = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $ownUser->id]);

    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", ['user_id' => $sharedUser->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    expect($employee->fresh()->user_id)->toBe($ownUser->id);
});

it('allows keeping the same linked user (uniqueness ignores self)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", [
            'user_id' => $user->id,
            'full_name' => 'Updated Name',
        ])
        ->assertOk();

    $employee->refresh();
    expect($employee->user_id)->toBe($user->id)
        ->and($employee->full_name)->toBe('Updated Name');
});

it('rejects a non-existent user id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = Employee::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", ['user_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

it('returns the linked user in the employee show payload', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'employee', 'name' => 'Nurul Najiha']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($admin)
        ->getJson("/api/hr/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', 'Nurul Najiha');
});

it('reassigns a user account from another employee to this one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sharedUser = User::factory()->create(['role' => 'employee']);
    $otherEmployee = Employee::factory()->create(['user_id' => $sharedUser->id]);
    $employee = Employee::factory()->create(['user_id' => null]);

    $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => $sharedUser->id])
        ->assertOk()
        ->assertJsonPath('data.user.id', $sharedUser->id)
        ->assertJsonPath('reassigned_from.0.id', $otherEmployee->id);

    expect($employee->fresh()->user_id)->toBe($sharedUser->id)
        ->and($otherEmployee->fresh()->user_id)->toBeNull();
});

it('lets the employee save normally after a reassignment', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sharedUser = User::factory()->create(['role' => 'employee']);
    Employee::factory()->create(['user_id' => $sharedUser->id]);
    $employee = Employee::factory()->create(['user_id' => $sharedUser->id]);

    // The conflict blocks a normal save first.
    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", ['user_id' => $sharedUser->id])
        ->assertStatus(422);

    // Reassigning resolves it, then the save goes through.
    $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => $sharedUser->id])
        ->assertOk();

    $this->actingAs($admin)
        ->putJson("/api/hr/employees/{$employee->id}", ['user_id' => $sharedUser->id, 'full_name' => 'Saved Name'])
        ->assertOk();

    expect($employee->fresh()->full_name)->toBe('Saved Name');
});

it('records history on both employees when reassigning', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sharedUser = User::factory()->create(['role' => 'employee']);
    $otherEmployee = Employee::factory()->create(['user_id' => $sharedUser->id]);
    $employee = Employee::factory()->create(['user_id' => null]);

    $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => $sharedUser->id])
        ->assertOk();

    expect(EmployeeHistory::where('employee_id', $employee->id)->where('field_name', 'user_id')->exists())->toBeTrue()
        ->and(EmployeeHistory::where('employee_id', $otherEmployee->id)->where('field_name', 'user_id')->exists())->toBeTrue();
});

it('links a user with no prior employee (reassigned_from empty)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => null]);

    $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => $user->id])
        ->assertOk()
        ->assertJsonPath('reassigned_from', []);

    expect($employee->fresh()->user_id)->toBe($user->id);
});

it('forbids a non-admin employee from reassigning', function () {
    $employeeUser = User::factory()->create(['role' => 'employee']);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create(['user_id' => null]);

    $this->actingAs($employeeUser)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => $user->id])
        ->assertForbidden();

    expect($employee->fresh()->user_id)->toBeNull();
});

it('rejects reassigning a non-existent user id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = Employee::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/hr/employees/{$employee->id}/reassign-user", ['user_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});
