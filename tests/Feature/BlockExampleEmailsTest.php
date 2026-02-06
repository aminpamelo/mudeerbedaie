<?php

declare(strict_types=1);

use App\Jobs\SendClassNotificationEmailJob;
use App\Listeners\BlockExampleEmails;
use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

test('listener filters out @example.com from mixed recipients', function () {
    $email = new Email;
    $email->to(new Address('valid@gmail.com'), new Address('test@example.com'));
    $email->subject('Test');

    $event = new MessageSending($email);

    $listener = new BlockExampleEmails;
    $result = $listener->handle($event);

    // Should not cancel - valid recipient remains
    expect($result)->toBeNull();

    $toAddresses = array_map(fn (Address $a) => $a->getAddress(), $email->getTo());
    expect($toAddresses)->toBe(['valid@gmail.com']);
});

test('listener cancels email when all recipients are @example.com', function () {
    $email = new Email;
    $email->to(new Address('one@example.com'), new Address('two@example.com'));
    $email->subject('Test');

    $event = new MessageSending($email);

    $listener = new BlockExampleEmails;
    $result = $listener->handle($event);

    expect($result)->toBeFalse();
});

test('SendClassNotificationEmailJob marks log as skipped for @example.com', function () {
    Mail::fake();

    $class = ClassModel::factory()->create();

    $setting = ClassNotificationSetting::create([
        'class_id' => $class->id,
        'notification_type' => 'reminder_30min',
        'is_enabled' => true,
        'send_to_students' => true,
        'send_to_teacher' => false,
    ]);

    $scheduledNotification = ScheduledNotification::create([
        'class_id' => $class->id,
        'class_notification_setting_id' => $setting->id,
        'status' => 'processing',
        'scheduled_at' => now(),
        'total_sent' => 0,
        'total_failed' => 0,
    ]);

    $log = NotificationLog::create([
        'scheduled_notification_id' => $scheduledNotification->id,
        'recipient_type' => 'student',
        'recipient_id' => 1,
        'channel' => 'email',
        'destination' => 'student@example.com',
        'status' => 'pending',
    ]);

    $job = new SendClassNotificationEmailJob(
        notificationLogId: $log->id,
        recipientEmail: 'student@example.com',
        recipientName: 'Test Student',
        subject: 'Test Subject',
        htmlContent: '<p>Test content</p>',
    );

    $job->handle();

    Mail::assertNothingSent();

    $log->refresh();
    expect($log->status)->toBe('skipped')
        ->and($log->error_message)->toBe('Skipped @example.com address');
});

test('SendClassNotificationEmailJob sends email for valid addresses', function () {
    Mail::fake();

    $class = ClassModel::factory()->create();

    $setting = ClassNotificationSetting::create([
        'class_id' => $class->id,
        'notification_type' => 'reminder_60min',
        'is_enabled' => true,
        'send_to_students' => true,
        'send_to_teacher' => false,
    ]);

    $scheduledNotification = ScheduledNotification::create([
        'class_id' => $class->id,
        'class_notification_setting_id' => $setting->id,
        'status' => 'processing',
        'scheduled_at' => now(),
        'total_sent' => 0,
        'total_failed' => 0,
    ]);

    $log = NotificationLog::create([
        'scheduled_notification_id' => $scheduledNotification->id,
        'recipient_type' => 'student',
        'recipient_id' => 1,
        'channel' => 'email',
        'destination' => 'student@gmail.com',
        'status' => 'pending',
    ]);

    $job = new SendClassNotificationEmailJob(
        notificationLogId: $log->id,
        recipientEmail: 'student@gmail.com',
        recipientName: 'Test Student',
        subject: 'Test Subject',
        htmlContent: '<p>Test content</p>',
    );

    $job->handle();

    $log->refresh();
    expect($log->status)->toBe('sent');
});
