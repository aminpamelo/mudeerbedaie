<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Notifications\Hr\LeaveRequestApproved;
use App\Notifications\Hr\LeaveRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

uses(RefreshDatabase::class);

function createPushTestUser(string $role = 'admin'): User
{
    return User::factory()->create(['role' => $role]);
}

function createPushTestEmployee(): array
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

// --- Push Subscription API Tests ---

it('can store a push subscription', function () {
    $user = createPushTestUser();

    $response = $this->actingAs($user)->postJson('/api/hr/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        'keys' => [
            'p256dh' => base64_encode('test-p256dh-key-value'),
            'auth' => base64_encode('test-auth-value'),
        ],
        'content_encoding' => 'aesgcm',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        'content_encoding' => 'aesgcm',
    ]);
});

it('stores push subscription with default content encoding when not provided', function () {
    $user = createPushTestUser();

    $response = $this->actingAs($user)->postJson('/api/hr/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-2',
        'keys' => [
            'p256dh' => base64_encode('test-p256dh-key-value'),
            'auth' => base64_encode('test-auth-value'),
        ],
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-2',
        'content_encoding' => 'aesgcm',
    ]);
});

it('can remove a push subscription', function () {
    $user = createPushTestUser();

    $user->updatePushSubscription(
        'https://fcm.googleapis.com/fcm/send/to-delete',
        'p256dh-key',
        'auth-key',
        'aesgcm'
    );

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
    ]);

    $response = $this->actingAs($user)->deleteJson('/api/hr/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseMissing('push_subscriptions', [
        'subscribable_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
    ]);
});

it('requires authentication for push subscription endpoints', function () {
    $this->postJson('/api/hr/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
    ])->assertUnauthorized();

    $this->deleteJson('/api/hr/push-subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
    ])->assertUnauthorized();
});

it('validates push subscription store request', function () {
    $user = createPushTestUser();

    $this->actingAs($user)->postJson('/api/hr/push-subscriptions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.auth', 'keys.p256dh']);
});

// --- Notification Channel Tests ---

it('includes WebPushChannel for notifications with push channel', function () {
    $leaveType = \App\Models\LeaveType::factory()->create();
    $data = createPushTestEmployee();
    $leaveRequest = \App\Models\LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $notification = new LeaveRequestSubmitted($leaveRequest);
    $channels = $notification->via($data['user']);

    expect($channels)->toContain(WebPushChannel::class);
    expect($channels)->toContain('database');
    expect($channels)->toContain('mail');
});

it('generates correct WebPushMessage payload', function () {
    $leaveType = \App\Models\LeaveType::factory()->create(['name' => 'Annual Leave']);
    $data = createPushTestEmployee();
    $leaveRequest = \App\Models\LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
        'total_days' => 2,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-02',
    ]);

    $notification = new LeaveRequestSubmitted($leaveRequest);
    $message = $notification->toWebPush($data['user'], $notification);

    expect($message)->toBeInstanceOf(WebPushMessage::class);

    $payload = $message->toArray();
    expect($payload['title'])->toBe('New Leave Request');
    expect($payload['body'])->toContain($data['employee']->full_name);
    expect($payload['body'])->toContain('Annual Leave');
    expect($payload['icon'])->toBe('/icons/hr-192.png');
    expect($payload['data'])->toBe(['url' => '/hr/leave/requests']);
});

it('sends push notification when leave request is submitted', function () {
    Notification::fake();

    $adminUser = createPushTestUser('admin');
    $data = createPushTestEmployee();
    $leaveType = \App\Models\LeaveType::factory()->create();

    \App\Models\LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'entitled_days' => 14,
        'available_days' => 14,
        'used_days' => 0,
        'pending_days' => 0,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/leave/requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
        'reason' => 'Personal matters',
    ]);

    $response->assertCreated();

    Notification::assertSentTo(
        $adminUser,
        LeaveRequestSubmitted::class,
        function ($notification, $channels) {
            return in_array(WebPushChannel::class, $channels);
        }
    );
});

it('sends push notification when leave request is approved', function () {
    Notification::fake();

    $adminUser = createPushTestUser('admin');
    $data = createPushTestEmployee();
    $leaveType = \App\Models\LeaveType::factory()->create();
    $leaveRequest = \App\Models\LeaveRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
        'total_days' => 1,
        'start_date' => now()->addDays(5)->format('Y-m-d'),
        'end_date' => now()->addDays(5)->format('Y-m-d'),
    ]);

    \App\Models\LeaveBalance::factory()->create([
        'employee_id' => $data['employee']->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
        'entitled_days' => 14,
        'available_days' => 13,
        'used_days' => 0,
        'pending_days' => 1,
    ]);

    $response = $this->actingAs($adminUser)->patchJson(
        "/api/hr/leave/requests/{$leaveRequest->id}/approve"
    );

    $response->assertSuccessful();

    Notification::assertSentTo(
        $data['user'],
        LeaveRequestApproved::class,
        function ($notification, $channels) {
            return in_array(WebPushChannel::class, $channels);
        }
    );
});

// --- VAPID Config Tests ---

it('has VAPID configuration properly set', function () {
    expect(config('webpush.vapid.subject'))->not->toBeNull();
    expect(config('webpush.vapid.public_key'))->not->toBeNull();
    expect(config('webpush.vapid.private_key'))->not->toBeNull();
});
