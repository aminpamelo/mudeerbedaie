<?php

declare(strict_types=1);

use App\Jobs\SendClassNotificationEmailJob;
use App\Jobs\SendClassNotificationJob;
use App\Models\ClassSession;
use App\Models\ScheduledNotification;
use App\Services\EmailTemplateCompiler;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Queue;

it('marks a due notification as processing and dispatches it once', function () {
    Queue::fake();

    $notification = ScheduledNotification::factory()->create([
        'session_id' => ClassSession::factory()->create(['status' => 'scheduled'])->id,
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('notifications:process')->assertSuccessful();

    expect($notification->fresh()->status)->toBe('processing');
    Queue::assertPushed(SendClassNotificationJob::class, 1);
});

it('does not re-dispatch the same notification on a second run', function () {
    Queue::fake();

    ScheduledNotification::factory()->create([
        'session_id' => ClassSession::factory()->create(['status' => 'scheduled'])->id,
        'status' => 'pending',
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('notifications:process')->assertSuccessful();
    $this->artisan('notifications:process')->assertSuccessful();

    // The runaway bug re-dispatched every run; the fix flips it out of
    // 'pending' at dispatch, so the second run finds nothing to send.
    Queue::assertPushed(SendClassNotificationJob::class, 1);
});

it('skips a notification that is already finalised (no duplicate fan-out)', function () {
    Queue::fake();

    $notification = ScheduledNotification::factory()->create([
        'session_id' => ClassSession::factory()->create(['status' => 'scheduled'])->id,
        'status' => 'sent',
    ]);

    (new SendClassNotificationJob($notification))->handle(
        app(NotificationService::class),
        app(EmailTemplateCompiler::class),
    );

    Queue::assertNotPushed(SendClassNotificationEmailJob::class);
    expect($notification->fresh()->status)->toBe('sent');
});
