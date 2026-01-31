<?php

declare(strict_types=1);

use App\Jobs\SendClassNotificationEmailJob;
use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->class = ClassModel::factory()->create();

    $this->setting = ClassNotificationSetting::create([
        'class_id' => $this->class->id,
        'notification_type' => 'reminder_30min',
        'is_enabled' => true,
        'send_to_students' => true,
        'send_to_teacher' => false,
    ]);

    $this->scheduledNotification = ScheduledNotification::create([
        'class_id' => $this->class->id,
        'class_notification_setting_id' => $this->setting->id,
        'status' => 'processing',
        'scheduled_at' => now(),
        'total_sent' => 0,
        'total_failed' => 0,
    ]);
});

test('it sends email and marks log as sent', function () {
    $log = NotificationLog::create([
        'scheduled_notification_id' => $this->scheduledNotification->id,
        'recipient_type' => 'student',
        'recipient_id' => 1,
        'channel' => 'email',
        'destination' => 'test@gmail.com',
        'status' => 'pending',
    ]);

    $job = new SendClassNotificationEmailJob(
        notificationLogId: $log->id,
        recipientEmail: 'test@gmail.com',
        recipientName: 'Test Student',
        subject: 'Test Subject',
        htmlContent: '<p>Test content</p>',
    );

    $job->handle();

    $log->refresh();
    expect($log->status)->toBe('sent')
        ->and($log->sent_at)->not->toBeNull();

    $this->scheduledNotification->refresh();
    expect($this->scheduledNotification->total_sent)->toBe(1);
});

test('it increments total_failed on failure', function () {
    $log = NotificationLog::create([
        'scheduled_notification_id' => $this->scheduledNotification->id,
        'recipient_type' => 'student',
        'recipient_id' => 1,
        'channel' => 'email',
        'destination' => 'test@gmail.com',
        'status' => 'pending',
    ]);

    $job = new SendClassNotificationEmailJob(
        notificationLogId: $log->id,
        recipientEmail: 'test@gmail.com',
        recipientName: 'Test Student',
        subject: 'Test Subject',
        htmlContent: '<p>Test content</p>',
    );

    $job->failed(new \RuntimeException('SMTP error'));

    $log->refresh();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toBe('SMTP error');

    $this->scheduledNotification->refresh();
    expect($this->scheduledNotification->total_failed)->toBe(1);
});

test('it handles missing notification log gracefully', function () {
    $job = new SendClassNotificationEmailJob(
        notificationLogId: 99999,
        recipientEmail: 'test@gmail.com',
        recipientName: 'Test Student',
        subject: 'Test Subject',
        htmlContent: '<p>Test content</p>',
    );

    $job->handle();

    Mail::assertNothingSent();
});
