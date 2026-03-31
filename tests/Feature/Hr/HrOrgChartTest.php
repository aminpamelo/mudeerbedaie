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
