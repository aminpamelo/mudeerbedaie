<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('flips overdue pending requests to expired and notifies original host', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);

    $overdue = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->subMinute(),
    ]);

    $stillFresh = SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('replacements:expire')->assertExitCode(0);

    expect($overdue->fresh()->status)->toBe('expired');
    expect($stillFresh->fresh()->status)->toBe('pending');

    Notification::assertSentTo($host, ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'expired' && $n->request->id === $overdue->id
    );
});

it('is idempotent — re-running does not double-fire notifications', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('replacements:expire')->assertExitCode(0);
    $this->artisan('replacements:expire')->assertExitCode(0);

    Notification::assertSentToTimes($host, ReplacementResolvedNotification::class, 1);
});
