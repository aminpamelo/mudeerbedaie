<?php

declare(strict_types=1);

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Notifications\ReplacementRequestedNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('notifies admins (admin + admin_livehost) when a host submits a request', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);
    $host = User::factory()->create(['role' => 'live_host']);

    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '06:30:00', 'end_time' => '08:30:00',
    ]);
    $assignment = LiveScheduleAssignment::factory()->create([
        'live_host_id' => $host->id,
        'time_slot_id' => $slot->id,
        'platform_account_id' => PlatformAccount::factory(),
        'day_of_week' => now()->addDay()->dayOfWeek,
    ]);

    $this->actingAs($host)->post(route('live-host.replacement-requests.store'), [
        'live_schedule_assignment_id' => $assignment->id,
        'scope' => 'one_date',
        'target_date' => now()->addDay()->toDateString(),
        'reason_category' => 'sick',
    ]);

    Notification::assertSentTo([$admin, $pic], ReplacementRequestedNotification::class);
    Notification::assertNotSentTo($assistant, ReplacementRequestedNotification::class);
    Notification::assertNotSentTo($host, ReplacementRequestedNotification::class);
});

it('notifies the replacement host when PIC assigns them', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $candidate = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.assign', $req), [
        'replacement_host_id' => $candidate->id,
    ]);

    Notification::assertSentTo($candidate, \App\Notifications\ReplacementAssignedToYouNotification::class);
    Notification::assertNotSentTo($host, \App\Notifications\ReplacementAssignedToYouNotification::class);
});

it('notifies original host when PIC assigns', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $candidate = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.assign', $req), [
        'replacement_host_id' => $candidate->id,
    ]);

    Notification::assertSentTo($host, \App\Notifications\ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'assigned'
    );
});

it('notifies original host when PIC rejects', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $assignment = LiveScheduleAssignment::factory()->create(['live_host_id' => $host->id]);
    $req = \App\Models\SessionReplacementRequest::factory()->pending()->create([
        'live_schedule_assignment_id' => $assignment->id,
        'original_host_id' => $host->id,
    ]);

    $this->actingAs($pic)->post(route('livehost.replacements.reject', $req), [
        'rejection_reason' => 'Tidak boleh diganti.',
    ]);

    Notification::assertSentTo($host, \App\Notifications\ReplacementResolvedNotification::class,
        fn ($n) => $n->resolution === 'rejected'
    );
});
