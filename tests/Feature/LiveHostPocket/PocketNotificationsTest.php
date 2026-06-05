<?php

use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Notifications\LiveHost\RecapOverdueNotification;
use App\Notifications\LiveHost\ScheduleSlotChangedNotification;
use App\Notifications\LiveHost\SessionStartingSoonNotification;
use App\Notifications\ReplacementAssignedToYouNotification;
use App\Notifications\ReplacementResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

test('the session reminder command pushes once for an imminent session', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addMinutes(10),
        'reminder_15m_sent_at' => null,
    ]);

    $this->artisan('livehost:send-session-reminders')->assertSuccessful();

    Notification::assertSentTo($host, SessionStartingSoonNotification::class);
    expect($session->fresh()->reminder_15m_sent_at)->not->toBeNull();

    // Re-running must not re-notify the same session.
    $this->artisan('livehost:send-session-reminders')->assertSuccessful();
    Notification::assertSentToTimes($host, SessionStartingSoonNotification::class, 1);
});

test('the session reminder command ignores sessions outside the lead window', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addHours(3),
    ]);

    $this->artisan('livehost:send-session-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the recap reminder command nudges hosts with no uploaded proof', function () {
    Notification::fake();

    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'ended',
        'actual_end_at' => now()->subHours(3),
        'recap_reminder_sent_at' => null,
    ]);

    $this->artisan('livehost:send-recap-reminders')->assertSuccessful();

    Notification::assertSentTo($host, RecapOverdueNotification::class);
    expect($session->fresh()->recap_reminder_sent_at)->not->toBeNull();

    $this->artisan('livehost:send-recap-reminders')->assertSuccessful();
    Notification::assertSentToTimes($host, RecapOverdueNotification::class, 1);
});

test('host-facing replacement notifications include the web push channel', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $request = SessionReplacementRequest::factory()->create();

    expect((new ReplacementAssignedToYouNotification($request))->via($host))
        ->toContain(WebPushChannel::class);

    expect((new ReplacementResolvedNotification($request, ReplacementResolvedNotification::RESOLUTION_REJECTED))->via($host))
        ->toContain(WebPushChannel::class);
});

test('assigning a host to a future dated slot pushes a schedule notification', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create();
    $platformAccount = PlatformAccount::factory()->create();
    $timeSlot = LiveTimeSlot::factory()->create(['platform_account_id' => $platformAccount->id]);

    $this->actingAs($pic)->post('/livehost/session-slots', [
        'live_account_id' => $account->id,
        'platform_account_id' => $platformAccount->id,
        'time_slot_id' => $timeSlot->id,
        'live_host_id' => $host->id,
        'day_of_week' => 3,
        'schedule_date' => now()->addWeek()->format('Y-m-d'),
        'is_template' => false,
    ])->assertRedirect();

    Notification::assertSentTo($host, ScheduleSlotChangedNotification::class);
});

test('creating a template slot does not push a schedule notification', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create();
    $platformAccount = PlatformAccount::factory()->create();
    $timeSlot = LiveTimeSlot::factory()->create(['platform_account_id' => $platformAccount->id]);

    $this->actingAs($pic)->post('/livehost/session-slots', [
        'live_account_id' => $account->id,
        'platform_account_id' => $platformAccount->id,
        'time_slot_id' => $timeSlot->id,
        'live_host_id' => $host->id,
        'day_of_week' => 3,
        'schedule_date' => null,
        'is_template' => true,
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('changing a future slot time pushes an updated schedule notification', function () {
    Notification::fake();

    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create();
    $platformAccount = PlatformAccount::factory()->create();
    $slotA = LiveTimeSlot::factory()->create(['platform_account_id' => $platformAccount->id]);
    $slotB = LiveTimeSlot::factory()->create([
        'platform_account_id' => $platformAccount->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
    ]);

    $assignment = LiveScheduleAssignment::factory()->create([
        'live_account_id' => $account->id,
        'platform_account_id' => $platformAccount->id,
        'time_slot_id' => $slotA->id,
        'live_host_id' => $host->id,
        'day_of_week' => 3,
        'schedule_date' => now()->addWeek()->format('Y-m-d'),
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $this->actingAs($pic)->patch("/livehost/session-slots/{$assignment->id}", [
        'live_account_id' => $account->id,
        'platform_account_id' => $platformAccount->id,
        'time_slot_id' => $slotB->id,
        'live_host_id' => $host->id,
        'day_of_week' => 3,
        'schedule_date' => now()->addWeek()->format('Y-m-d'),
        'is_template' => false,
    ])->assertRedirect();

    Notification::assertSentTo($host, ScheduleSlotChangedNotification::class);
});
