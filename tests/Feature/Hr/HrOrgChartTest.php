<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access org chart', function () {
    $this->getJson('/api/hr/org-chart')->assertUnauthorized();
});

test('non-admin/employee users cannot access org chart', function () {
    $user = User::factory()->create(['role' => 'student']);

    $this->actingAs($user)
        ->getJson('/api/hr/org-chart')
        ->assertForbidden();
});

test('admin can view org chart with employee hierarchy', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create();
    $ceoPosition = Position::factory()->create(['department_id' => $dept->id, 'title' => 'CEO', 'level' => 1]);
    $mgrPosition = Position::factory()->create(['department_id' => $dept->id, 'title' => 'Manager', 'level' => 2]);

    $ceo = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $ceoPosition->id,
        'status' => 'active',
        'reports_to' => null,
    ]);

    $manager = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $mgrPosition->id,
        'status' => 'active',
        'reports_to' => $ceo->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'full_name',
                    'profile_photo_url',
                    'position',
                    'department',
                    'children',
                ],
            ],
            'meta' => [
                'total_employees',
                'linked_employees',
                'unlinked_employees',
            ],
        ]);

    $data = $response->json();

    expect($data['meta']['total_employees'])->toBe(2);
    expect($data['meta']['linked_employees'])->toBe(1);
    expect($data['meta']['unlinked_employees'])->toBe(1);
    // CEO is root, manager is child
    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0]['id'])->toBe($ceo->id);
    expect($data['data'][0]['children'])->toHaveCount(1);
    expect($data['data'][0]['children'][0]['id'])->toBe($manager->id);
});

test('org chart excludes terminated and resigned employees', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'terminated',
    ]);

    Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'resigned',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful();

    $data = $response->json();

    expect($data['meta']['total_employees'])->toBe(1);
    expect($data['data'])->toHaveCount(1);
});

test('org chart includes position and department info', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create([
        'department_id' => $dept->id,
        'title' => 'Software Engineer',
        'level' => 3,
    ]);

    Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful();

    $employee = $response->json('data.0');
    expect($employee['position']['title'])->toBe('Software Engineer');
    expect($employee['position']['level'])->toBe(3);
    expect($employee['department']['name'])->toBe('Engineering');
});

// ========== Assign Manager Tests ==========

test('admin can assign a manager to an employee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    $manager = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
        'reports_to' => null,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/employees/{$employee->id}/manager", [
            'reports_to' => $manager->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Manager assigned successfully.');

    expect($employee->fresh()->reports_to)->toBe($manager->id);
});

test('admin can remove a manager assignment', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    $manager = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
        'reports_to' => $manager->id,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/employees/{$employee->id}/manager", [
            'reports_to' => null,
        ])
        ->assertSuccessful();

    expect($employee->fresh()->reports_to)->toBeNull();
});

test('employee cannot report to themselves', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/employees/{$employee->id}/manager", [
            'reports_to' => $employee->id,
        ])
        ->assertStatus(422);
});

test('assign manager prevents circular references', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $dept = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $dept->id]);

    $employeeA = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
        'reports_to' => null,
    ]);

    $employeeB = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
        'reports_to' => $employeeA->id,
    ]);

    // Try to make A report to B (would create cycle: A -> B -> A)
    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/employees/{$employeeA->id}/manager", [
            'reports_to' => $employeeB->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This assignment would create a circular reporting chain.');
});

test('org chart sorts children by position level', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create();
    $directorPos = Position::factory()->create(['department_id' => $dept->id, 'level' => 1]);
    $mgrPos = Position::factory()->create(['department_id' => $dept->id, 'level' => 2]);
    $workerPos = Position::factory()->create(['department_id' => $dept->id, 'level' => 3]);

    $director = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $directorPos->id,
        'status' => 'active',
        'reports_to' => null,
    ]);

    $worker = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $workerPos->id,
        'status' => 'active',
        'reports_to' => $director->id,
        'full_name' => 'Zara Worker',
    ]);

    $manager = Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $mgrPos->id,
        'status' => 'active',
        'reports_to' => $director->id,
        'full_name' => 'Amy Manager',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful();

    $children = $response->json('data.0.children');
    // Manager (level 2) should come before Worker (level 3)
    expect($children[0]['id'])->toBe($manager->id);
    expect($children[1]['id'])->toBe($worker->id);
});

// ========== Department Org Chart Tests ==========

test('admin can view department org chart with hierarchy', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $parentDept = Department::factory()->create(['name' => 'Top Management', 'parent_id' => null]);
    $childDept = Department::factory()->create(['name' => 'Engineering', 'parent_id' => $parentDept->id]);
    $position = Position::factory()->create(['department_id' => $childDept->id]);

    Employee::factory()->create([
        'department_id' => $childDept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart/departments')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'code',
                    'parent_id',
                    'employee_count',
                    'employees',
                    'children',
                ],
            ],
            'meta' => [
                'total_departments',
                'in_hierarchy',
                'root_level',
            ],
        ]);

    $data = $response->json();
    expect($data['meta']['total_departments'])->toBe(2);
    expect($data['meta']['in_hierarchy'])->toBe(1);
    expect($data['meta']['root_level'])->toBe(1);

    // Parent is root, child is nested
    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0]['id'])->toBe($parentDept->id);
    expect($data['data'][0]['children'])->toHaveCount(1);
    expect($data['data'][0]['children'][0]['id'])->toBe($childDept->id);
    expect($data['data'][0]['children'][0]['employees'])->toHaveCount(1);
});

test('admin can assign a parent department', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $parent = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => null]);

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/departments/{$child->id}/parent", [
            'parent_id' => $parent->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Parent department assigned successfully.');

    expect($child->fresh()->parent_id)->toBe($parent->id);
});

test('admin can remove parent department assignment', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $parent = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/departments/{$child->id}/parent", [
            'parent_id' => null,
        ])
        ->assertSuccessful();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('department cannot be its own parent', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create();

    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/departments/{$dept->id}/parent", [
            'parent_id' => $dept->id,
        ])
        ->assertStatus(422);
});

test('assign parent prevents circular department chain', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $deptA = Department::factory()->create(['parent_id' => null]);
    $deptB = Department::factory()->create(['parent_id' => $deptA->id]);

    // Try to make A a child of B (would create cycle: A -> B -> A)
    $this->actingAs($admin)
        ->patchJson("/api/hr/org-chart/departments/{$deptA->id}/parent", [
            'parent_id' => $deptB->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This assignment would create a circular department chain.');
});
