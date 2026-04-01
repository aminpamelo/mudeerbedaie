<?php

declare(strict_types=1);

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\ExitPermissionNotifier;
use App\Models\OfficeExitPermission;
use App\Models\User;
use App\Notifications\Hr\ExitPermissionApproved;
use App\Notifications\Hr\ExitPermissionRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->department = Department::factory()->create();

    $this->employeeUser = User::factory()->create(['role' => 'employee']);
    $this->employee = Employee::factory()->create([
        'user_id' => $this->employeeUser->id,
        'department_id' => $this->department->id,
        'status' => 'active',
    ]);

    $this->approverUser = User::factory()->create(['role' => 'employee']);
    $this->approverEmployee = Employee::factory()->create([
        'user_id' => $this->approverUser->id,
        'department_id' => $this->department->id,
        'status' => 'active',
    ]);

    DepartmentApprover::create([
        'department_id' => $this->department->id,
        'approver_employee_id' => $this->approverEmployee->id,
        'approval_type' => 'exit_permission',
    ]);
});

// --- Permission number generation ---

it('generates sequential permission numbers', function (): void {
    $p1 = OfficeExitPermission::factory()->create(['employee_id' => $this->employee->id]);
    $p2 = OfficeExitPermission::factory()->create(['employee_id' => $this->employee->id]);

    expect($p1->permission_number)->toStartWith('OEP-');
    expect($p2->permission_number)->not->toBe($p1->permission_number);
});

// --- Employee self-service ---

it('employee can submit an exit permission request', function (): void {
    $response = $this->actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '14:00',
            'return_time' => '16:00',
            'errand_type' => 'personal',
            'purpose' => 'Personal medical appointment downtown.',
            'addressed_to' => 'Manager HR',
        ]);

    $response->assertCreated();
    expect(OfficeExitPermission::count())->toBe(1);
    expect(OfficeExitPermission::first()->permission_number)->toStartWith('OEP-');
    expect(OfficeExitPermission::first()->status)->toBe('pending');
});

it('employee cannot submit with return_time before exit_time', function (): void {
    $this->actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '16:00',
            'return_time' => '14:00',
            'errand_type' => 'company',
            'purpose' => 'Company errand to government office.',
            'addressed_to' => 'Manager',
        ])
        ->assertUnprocessable();
});

it('employee cannot submit with exit_date in the past', function (): void {
    $this->actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->subDay()->toDateString(),
            'exit_time' => '14:00',
            'return_time' => '16:00',
            'errand_type' => 'company',
            'purpose' => 'Company errand to government office.',
            'addressed_to' => 'Manager',
        ])
        ->assertUnprocessable();
});

it('employee cannot submit with purpose too short', function (): void {
    $this->actingAs($this->employeeUser)
        ->postJson('/api/hr/my/exit-permissions', [
            'exit_date' => now()->addDay()->toDateString(),
            'exit_time' => '14:00',
            'return_time' => '16:00',
            'errand_type' => 'company',
            'purpose' => 'Short',
            'addressed_to' => 'Manager',
        ])
        ->assertUnprocessable();
});

it('employee can list their own requests', function (): void {
    OfficeExitPermission::factory()->count(3)->create(['employee_id' => $this->employee->id]);

    $this->actingAs($this->employeeUser)
        ->getJson('/api/hr/my/exit-permissions')
        ->assertOk()
        ->assertJsonCount(3, 'data.data');
});

it('employee cannot see other employees requests', function (): void {
    $otherEmployee = Employee::factory()->create(['status' => 'active']);
    OfficeExitPermission::factory()->create(['employee_id' => $otherEmployee->id]);

    $response = $this->actingAs($this->employeeUser)
        ->getJson('/api/hr/my/exit-permissions')
        ->assertOk();

    expect($response->json('data.data'))->toHaveCount(0);
});

it('employee can cancel a pending request', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->employeeUser)
        ->deleteJson("/api/hr/my/exit-permissions/{$permission->id}")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('cancelled');
});

it('employee cannot cancel an approved request', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->actingAs($this->employeeUser)
        ->deleteJson("/api/hr/my/exit-permissions/{$permission->id}")
        ->assertUnprocessable();
});

it('employee cannot cancel another employees request', function (): void {
    $otherEmployeeUser = User::factory()->create(['role' => 'employee']);
    $otherEmployee = Employee::factory()->create([
        'user_id' => $otherEmployeeUser->id,
        'status' => 'active',
    ]);
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $otherEmployee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->employeeUser)
        ->deleteJson("/api/hr/my/exit-permissions/{$permission->id}")
        ->assertNotFound();
});

// --- Admin approval ---

it('admin can approve a pending exit permission', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('approved');
    expect($permission->fresh()->approved_by)->toBe($this->admin->id);
    Notification::assertSentTo($this->employeeUser, ExitPermissionApproved::class);
});

it('admin cannot approve an already approved permission', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertUnprocessable();
});

it('admin can reject with a reason', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/reject", [
            'rejection_reason' => 'Busy period, please reschedule.',
        ])
        ->assertOk();

    expect($permission->fresh()->status)->toBe('rejected');
    expect($permission->fresh()->rejection_reason)->toBe('Busy period, please reschedule.');
    Notification::assertSentTo($this->employeeUser, ExitPermissionRejected::class);
});

it('admin cannot reject without a reason', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/reject", [])
        ->assertUnprocessable();
});

it('admin cannot reject with reason shorter than 5 chars', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/reject", [
            'rejection_reason' => 'No',
        ])
        ->assertUnprocessable();
});

// --- Attendance note ---

it('attendance log gets a note when permission is approved', function (): void {
    Notification::fake();
    $exitDate = now()->addDay()->toDateString();

    AttendanceLog::create([
        'employee_id' => $this->employee->id,
        'date' => $exitDate,
        'status' => 'present',
        'clock_in' => $exitDate.' 08:00:00',
    ]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'exit_date' => $exitDate,
        'exit_time' => '14:00:00',
        'return_time' => '16:00:00',
        'errand_type' => 'company',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->attendance_note_created)->toBeTrue();

    $log = AttendanceLog::where('employee_id', $this->employee->id)
        ->whereDate('date', $exitDate)
        ->first();
    expect($log->remarks)->toContain('Exit:');
});

it('attendance_note_created is true even when no attendance log exists for the date', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'exit_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->attendance_note_created)->toBeTrue();
});

// --- CC Notifiers ---

it('cc notifiers receive email when permission is approved', function (): void {
    Notification::fake();

    $notifierUser = User::factory()->create(['role' => 'employee']);
    $notifierEmployee = Employee::factory()->create([
        'user_id' => $notifierUser->id,
        'department_id' => $this->department->id,
        'status' => 'active',
    ]);

    ExitPermissionNotifier::create([
        'department_id' => $this->department->id,
        'employee_id' => $notifierEmployee->id,
    ]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/hr/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    Notification::assertSentTo($notifierUser, ExitPermissionApproved::class);
    expect($permission->fresh()->cc_notified_at)->not->toBeNull();
});

// --- PDF ---

it('pdf endpoint returns 403 for non-approved permissions', function (): void {
    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->get("/api/hr/exit-permissions/{$permission->id}/pdf")
        ->assertForbidden();
});

// --- HOD Approval ---

it('assigned hod can approve exit permission in their department', function (): void {
    Notification::fake();

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->approverUser)
        ->patchJson("/api/hr/my-approvals/exit-permissions/{$permission->id}/approve")
        ->assertOk();

    expect($permission->fresh()->status)->toBe('approved');
});

it('non-assigned user cannot approve exit permission', function (): void {
    $otherUser = User::factory()->create(['role' => 'employee']);
    Employee::factory()->create([
        'user_id' => $otherUser->id,
        'status' => 'active',
    ]);

    $permission = OfficeExitPermission::factory()->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($otherUser)
        ->patchJson("/api/hr/my-approvals/exit-permissions/{$permission->id}/approve")
        ->assertForbidden();
});

it('hod can see pending permissions in their department', function (): void {
    OfficeExitPermission::factory()->count(2)->create([
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->approverUser)
        ->getJson('/api/hr/my-approvals/exit-permissions?status=pending')
        ->assertOk()
        ->assertJsonCount(2, 'data.data');
});

// --- Notifiers management ---

it('admin can add a notifier', function (): void {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/hr/exit-permission-notifiers', [
            'department_id' => $this->department->id,
            'employee_id' => $this->employee->id,
        ]);

    $response->assertCreated();
    expect(ExitPermissionNotifier::count())->toBe(1);
});

it('admin can remove a notifier', function (): void {
    $notifier = ExitPermissionNotifier::create([
        'department_id' => $this->department->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/hr/exit-permission-notifiers/{$notifier->id}")
        ->assertOk();

    expect(ExitPermissionNotifier::count())->toBe(0);
});
