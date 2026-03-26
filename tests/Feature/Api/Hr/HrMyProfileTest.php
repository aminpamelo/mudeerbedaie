<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createEmployeeUser(): array
{
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    return [$user, $employee];
}

// === Show Profile ===

test('show returns employee profile with relationships', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.hr.me'));

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $employee->id)
        ->assertJsonStructure([
            'data' => ['id', 'full_name', 'department', 'position', 'emergency_contacts', 'documents', 'histories'],
        ]);
});

test('show returns 404 when user has no employee record', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.hr.me'));

    $response->assertNotFound();
});

test('show requires authentication', function () {
    $response = $this->getJson(route('api.hr.me'));

    $response->assertUnauthorized();
});

// === Update Profile ===

test('update modifies allowed fields and logs history', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(route('api.hr.me.update'), [
            'phone' => '0123456789',
            'city' => 'Kuala Lumpur',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Profile updated successfully.');

    $employee->refresh();
    expect($employee->phone)->toBe('0123456789')
        ->and($employee->city)->toBe('Kuala Lumpur');

    expect(EmployeeHistory::where('employee_id', $employee->id)->count())->toBeGreaterThanOrEqual(1);
});

test('update validates fields', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(route('api.hr.me.update'), [
            'personal_email' => 'not-an-email',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['personal_email']);
});

// === Documents ===

test('documents returns employee documents', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.hr.me.documents'));

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

test('upload document stores file and creates record', function () {
    Storage::fake('public');
    [$user, $employee] = createEmployeeUser();

    $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('api.hr.me.documents.store'), [
            'file' => $file,
            'document_type' => 'ic_front',
            'title' => 'My IC Front',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.document_type', 'ic_front');

    expect($employee->documents()->count())->toBe(1);
});

test('upload document validates required fields', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('api.hr.me.documents.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['file', 'document_type', 'title']);
});

// === Emergency Contacts ===

test('emergency contacts returns employee contacts', function () {
    [$user, $employee] = createEmployeeUser();
    EmployeeEmergencyContact::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.hr.me.emergency-contacts'));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('store emergency contact creates record', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('api.hr.me.emergency-contacts.store'), [
            'name' => 'John Doe',
            'relationship' => 'Spouse',
            'phone' => '0198765432',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'John Doe');

    expect($employee->emergencyContacts()->count())->toBe(1);
});

test('store emergency contact validates required fields', function () {
    [$user, $employee] = createEmployeeUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('api.hr.me.emergency-contacts.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'relationship', 'phone']);
});

test('update emergency contact modifies record', function () {
    [$user, $employee] = createEmployeeUser();
    $contact = EmployeeEmergencyContact::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(route('api.hr.me.emergency-contacts.update', $contact->id), [
            'name' => 'Jane Updated',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Jane Updated');
});

test('update emergency contact cannot modify other employee contacts', function () {
    [$user, $employee] = createEmployeeUser();
    [$otherUser, $otherEmployee] = createEmployeeUser();
    $otherContact = EmployeeEmergencyContact::factory()->create(['employee_id' => $otherEmployee->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson(route('api.hr.me.emergency-contacts.update', $otherContact->id), [
            'name' => 'Hacked',
        ]);

    $response->assertNotFound();
});

test('delete emergency contact removes record', function () {
    [$user, $employee] = createEmployeeUser();
    $contact = EmployeeEmergencyContact::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('api.hr.me.emergency-contacts.destroy', $contact->id));

    $response->assertSuccessful();
    expect($employee->emergencyContacts()->count())->toBe(0);
});

test('delete emergency contact cannot delete other employee contacts', function () {
    [$user, $employee] = createEmployeeUser();
    [$otherUser, $otherEmployee] = createEmployeeUser();
    $otherContact = EmployeeEmergencyContact::factory()->create(['employee_id' => $otherEmployee->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('api.hr.me.emergency-contacts.destroy', $otherContact->id));

    $response->assertNotFound();
});
