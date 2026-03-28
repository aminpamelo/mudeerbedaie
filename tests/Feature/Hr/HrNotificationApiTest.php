<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Notifications\Hr\LeaveRequestApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createNotifAdminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createNotifEmployeeWithUser(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $user = User::factory()->create(['role' => 'employee']);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'status' => 'active',
        'gender' => 'male',
        'employment_type' => 'full_time',
        'join_date' => '2024-01-01',
    ]);

    return compact('department', 'position', 'user', 'employee');
}

/*
|--------------------------------------------------------------------------
| Notification Listing Tests
|--------------------------------------------------------------------------
*/

test('can list HR notifications for authenticated user', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    // Send notification directly (synchronously) to avoid queue
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                ['id', 'type', 'data', 'read_at', 'created_at'],
            ],
        ]);
});

test('notifications list is paginated', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);
});

test('notifications list returns empty when no notifications', function () {
    $data = createNotifEmployeeWithUser();

    $response = $this->actingAs($data['user'])->getJson('/api/hr/notifications');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

/*
|--------------------------------------------------------------------------
| Unread Count Tests
|--------------------------------------------------------------------------
*/

test('returns zero unread count when no notifications', function () {
    $data = createNotifEmployeeWithUser();

    $response = $this->actingAs($data['user'])->getJson('/api/hr/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJson(['count' => 0]);
});

test('returns correct unread count after receiving notification', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJson(['count' => 1]);
});

test('returns correct unread count with multiple notifications', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJson(['count' => 3]);
});

/*
|--------------------------------------------------------------------------
| Mark as Read Tests
|--------------------------------------------------------------------------
*/

test('can mark a notification as read', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    $notification = $user->notifications()->first();

    $response = $this->actingAs($user)
        ->patchJson("/api/hr/notifications/{$notification->id}/read");

    $response->assertSuccessful();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('unread count decreases after marking notification as read', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->patchJson("/api/hr/notifications/{$notification->id}/read")
        ->assertSuccessful();

    $response = $this->actingAs($user)->getJson('/api/hr/notifications/unread-count');
    $response->assertSuccessful()
        ->assertJson(['count' => 1]);
});

/*
|--------------------------------------------------------------------------
| Mark All as Read Tests
|--------------------------------------------------------------------------
*/

test('can mark all notifications as read', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)
        ->postJson('/api/hr/notifications/mark-all-read');

    $response->assertSuccessful();

    $unreadCount = $user->unreadNotifications()
        ->where('type', 'like', 'App\\Notifications\\Hr\\%')
        ->count();
    expect($unreadCount)->toBe(0);
});

test('mark all read only affects unread notifications', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));
    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    // Mark first one as read manually
    $first = $user->notifications()->first();
    $first->markAsRead();

    // Mark all as read
    $this->actingAs($user)
        ->postJson('/api/hr/notifications/mark-all-read')
        ->assertSuccessful();

    // All should be read now
    $totalNotifications = $user->notifications()->count();
    $readNotifications = $user->readNotifications()->count();
    expect($readNotifications)->toBe($totalNotifications);
});

/*
|--------------------------------------------------------------------------
| Leave Approval Notification Integration Test
|--------------------------------------------------------------------------
*/

test('sends notification when leave request is approved via API', function () {
    Notification::fake();

    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create();
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
        'total_days' => 1,
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'pending_days' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/hr/leave/requests/{$leaveRequest->id}/approve");

    $response->assertSuccessful();

    Notification::assertSentTo($user, LeaveRequestApproved::class);
});

/*
|--------------------------------------------------------------------------
| Authentication Tests
|--------------------------------------------------------------------------
*/

test('requires authentication for listing notifications', function () {
    $this->getJson('/api/hr/notifications')
        ->assertUnauthorized();
});

test('requires authentication for unread count', function () {
    $this->getJson('/api/hr/notifications/unread-count')
        ->assertUnauthorized();
});

test('requires authentication for marking notification as read', function () {
    $this->patchJson('/api/hr/notifications/fake-id/read')
        ->assertUnauthorized();
});

test('requires authentication for marking all as read', function () {
    $this->postJson('/api/hr/notifications/mark-all-read')
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Notification Data Structure Tests
|--------------------------------------------------------------------------
*/

test('notification contains correct data structure for leave approval', function () {
    $admin = createNotifAdminUser();
    $data = createNotifEmployeeWithUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $leaveType = LeaveType::factory()->create(['name' => 'Annual Leave']);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'approved',
    ]);

    Notification::sendNow($user, new LeaveRequestApproved($leaveRequest, $admin));

    $response = $this->actingAs($user)->getJson('/api/hr/notifications');

    $response->assertSuccessful();

    $notificationData = $response->json('data.0.data');
    expect($notificationData)->toHaveKeys(['title', 'body', 'url', 'icon'])
        ->and($notificationData['title'])->toBe('Leave Request Approved')
        ->and($notificationData['icon'])->toBe('check-circle')
        ->and($notificationData['url'])->toBe('/hr/my/leave');
});
