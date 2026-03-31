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

test('admin can view org chart with departments and employees', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $parentDept = Department::factory()->create(['parent_id' => null]);
    $childDept = Department::factory()->create(['parent_id' => $parentDept->id]);

    $position = Position::factory()->create(['department_id' => $parentDept->id]);
    $childPosition = Position::factory()->create(['department_id' => $childDept->id]);

    $headEmployee = Employee::factory()->create([
        'department_id' => $parentDept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $parentDept->update(['head_employee_id' => $headEmployee->id]);

    $employee = Employee::factory()->create([
        'department_id' => $parentDept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $childEmployee = Employee::factory()->create([
        'department_id' => $childDept->id,
        'position_id' => $childPosition->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'employees_count',
                    'head_employee',
                    'employees',
                    'children',
                ],
            ],
            'meta' => [
                'total_employees',
                'total_departments',
            ],
        ]);

    $data = $response->json();

    expect($data['meta']['total_employees'])->toBe(3);
    expect($data['meta']['total_departments'])->toBe(2);
    expect($data['data'])->toHaveCount(1);
    expect($data['data'][0]['head_employee']['id'])->toBe($headEmployee->id);
    expect($data['data'][0]['employees'])->toHaveCount(2);
    expect($data['data'][0]['children'])->toHaveCount(1);
});

test('org chart excludes terminated and resigned employees', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create(['parent_id' => null]);
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

    expect($data['data'][0]['employees'])->toHaveCount(1);
    expect($data['meta']['total_employees'])->toBe(1);
});

test('org chart includes employee position information', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $dept = Department::factory()->create(['parent_id' => null]);
    $position = Position::factory()->create([
        'department_id' => $dept->id,
        'title' => 'Software Engineer',
    ]);

    Employee::factory()->create([
        'department_id' => $dept->id,
        'position_id' => $position->id,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful();

    $employee = $response->json('data.0.employees.0');
    expect($employee['position']['title'])->toBe('Software Engineer');
});

test('admin user can access org chart endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Department::factory()->create(['parent_id' => null]);

    $this->actingAs($admin)
        ->getJson('/api/hr/org-chart')
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'meta']);
});
