<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('department can be created with factory', function () {
    $department = Department::factory()->create();

    expect($department)->toBeInstanceOf(Department::class)
        ->and($department->name)->not->toBeEmpty()
        ->and($department->code)->not->toBeEmpty();
});

test('department has parent and children relationships', function () {
    $parent = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id)
        ->and($parent->children)->toHaveCount(1);
});

test('department has positions and employees', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    Employee::factory()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    expect($department->positions)->toHaveCount(1)
        ->and($department->employees)->toHaveCount(1);
});

test('department ordered scope sorts by name', function () {
    Department::factory()->create(['name' => 'Zulu']);
    Department::factory()->create(['name' => 'Alpha']);

    $departments = Department::ordered()->get();

    expect($departments->first()->name)->toBe('Alpha');
});

test('position can be created with factory', function () {
    $position = Position::factory()->create();

    expect($position)->toBeInstanceOf(Position::class)
        ->and($position->title)->not->toBeEmpty()
        ->and($position->department)->toBeInstanceOf(Department::class);
});

test('position has employees relationship', function () {
    $position = Position::factory()->create();
    Employee::factory()->create([
        'department_id' => $position->department_id,
        'position_id' => $position->id,
    ]);

    expect($position->employees)->toHaveCount(1);
});

test('employee can be created with factory', function () {
    $employee = Employee::factory()->create();

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->employee_id)->toStartWith('BDE-')
        ->and($employee->user)->toBeInstanceOf(User::class)
        ->and($employee->department)->toBeInstanceOf(Department::class)
        ->and($employee->position)->toBeInstanceOf(Position::class);
});

test('employee generates sequential employee ids', function () {
    $first = Employee::generateEmployeeId();
    expect($first)->toBe('BDE-0001');

    Employee::factory()->create(['employee_id' => 'BDE-0001']);

    $second = Employee::generateEmployeeId();
    expect($second)->toBe('BDE-0002');
});

test('employee tenure attribute returns human-readable string', function () {
    $employee = Employee::factory()->create([
        'join_date' => now()->subYears(2)->subMonths(3),
    ]);

    expect($employee->tenure)->toContain('2 years')
        ->and($employee->tenure)->toContain('3 months');
});

test('employee status color attribute returns correct colors', function () {
    $active = Employee::factory()->create(['status' => 'active']);
    $probation = Employee::factory()->create(['status' => 'probation']);
    $resigned = Employee::factory()->create(['status' => 'resigned', 'resignation_date' => now(), 'last_working_date' => now()]);

    expect($active->status_color)->toBe('green')
        ->and($probation->status_color)->toBe('yellow')
        ->and($resigned->status_color)->toBe('red');
});

test('employee masked ic attribute masks last 4 digits', function () {
    $employee = Employee::factory()->create([
        'ic_number' => '901215141234',
    ]);

    expect($employee->masked_ic)->toBe('901215-14-****');
});

test('employee uses soft deletes', function () {
    $employee = Employee::factory()->create();
    $employee->delete();

    expect(Employee::count())->toBe(0)
        ->and(Employee::withTrashed()->count())->toBe(1);
});

test('employee has emergency contacts relationship', function () {
    $employee = Employee::factory()->create();
    EmployeeEmergencyContact::factory()->create(['employee_id' => $employee->id]);

    expect($employee->emergencyContacts)->toHaveCount(1);
});

test('employee has documents relationship', function () {
    $employee = Employee::factory()->create();
    EmployeeDocument::factory()->create(['employee_id' => $employee->id]);

    expect($employee->documents)->toHaveCount(1);
});

test('employee has histories relationship', function () {
    $employee = Employee::factory()->create();
    EmployeeHistory::factory()->create(['employee_id' => $employee->id]);

    expect($employee->histories)->toHaveCount(1);
});

test('employee emergency contact can be created with factory', function () {
    $contact = EmployeeEmergencyContact::factory()->create();

    expect($contact)->toBeInstanceOf(EmployeeEmergencyContact::class)
        ->and($contact->employee)->toBeInstanceOf(Employee::class)
        ->and($contact->name)->not->toBeEmpty()
        ->and($contact->phone)->not->toBeEmpty();
});

test('employee document can be created with factory', function () {
    $document = EmployeeDocument::factory()->create();

    expect($document)->toBeInstanceOf(EmployeeDocument::class)
        ->and($document->employee)->toBeInstanceOf(Employee::class)
        ->and($document->file_name)->not->toBeEmpty();
});

test('employee document formatted size returns human-readable size', function () {
    $docBytes = EmployeeDocument::factory()->create(['file_size' => 500]);
    $docKb = EmployeeDocument::factory()->create(['file_size' => 2048]);
    $docMb = EmployeeDocument::factory()->create(['file_size' => 2097152]);

    expect($docBytes->formatted_size)->toBe('500 B')
        ->and($docKb->formatted_size)->toBe('2.00 KB')
        ->and($docMb->formatted_size)->toBe('2.00 MB');
});

test('employee history can be created with factory', function () {
    $history = EmployeeHistory::factory()->create();

    expect($history)->toBeInstanceOf(EmployeeHistory::class)
        ->and($history->employee)->toBeInstanceOf(Employee::class)
        ->and($history->changedByUser)->toBeInstanceOf(User::class);
});

test('employee history has no updated_at column', function () {
    $history = EmployeeHistory::factory()->create();

    expect($history->updated_at)->toBeNull();
});

test('user has employee relationship', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    expect($user->employee)->toBeInstanceOf(Employee::class);
});

test('user isHrAdmin returns true for admin role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $teacher = User::factory()->create(['role' => 'teacher']);

    expect($admin->isHrAdmin())->toBeTrue()
        ->and($teacher->isHrAdmin())->toBeFalse();
});

test('employee encrypts ic_number and bank_account_number', function () {
    $employee = Employee::factory()->create([
        'ic_number' => '901215141234',
        'bank_account_number' => '1234567890123456',
    ]);

    // Fetch raw from DB to verify encryption
    $raw = \Illuminate\Support\Facades\DB::table('employees')
        ->where('id', $employee->id)
        ->first();

    expect($raw->ic_number)->not->toBe('901215141234')
        ->and($raw->bank_account_number)->not->toBe('1234567890123456');

    // But the model should decrypt them
    $employee->refresh();
    expect($employee->ic_number)->toBe('901215141234')
        ->and($employee->bank_account_number)->toBe('1234567890123456');
});
