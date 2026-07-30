<?php

declare(strict_types=1);

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

function makeNotificationWithLogs(int $sent, int $failed): ClassModel
{
    $class = ClassModel::factory()->create();

    $setting = ClassNotificationSetting::factory()->create([
        'class_id' => $class->id,
        'email_enabled' => true,
    ]);

    $notification = ScheduledNotification::factory()->create([
        'class_id' => $class->id,
        'class_notification_setting_id' => $setting->id,
        'session_id' => null,
        'scheduled_session_date' => now()->toDateString(),
        'scheduled_session_time' => '18:00:00',
        'status' => 'sent',
        'total_sent' => $sent,
        'total_failed' => $failed,
        'total_recipients' => $sent + $failed,
    ]);

    NotificationLog::factory()->count($sent)->sent()->create([
        'scheduled_notification_id' => $notification->id,
        'channel' => 'email',
    ]);
    NotificationLog::factory()->count($failed)->failed()->create([
        'scheduled_notification_id' => $notification->id,
        'channel' => 'email',
    ]);

    return $class;
}

it('renders the notification history tab with SQL-computed counts', function () {
    $class = makeNotificationWithLogs(sent: 3, failed: 2);

    Livewire::test('admin.class-notification-settings', ['class' => $class])
        ->assertOk()
        ->assertSee('3/5'); // sent / total for the email channel
});

it('does not load full log rows into memory (counts survive log bloat)', function () {
    // 250 logs on one notification would previously all be hydrated; the query
    // now caps the eager load and counts in SQL, so the summary stays correct.
    $class = makeNotificationWithLogs(sent: 200, failed: 50);

    Livewire::test('admin.class-notification-settings', ['class' => $class])
        ->assertOk()
        ->assertSee('200/250');
});
