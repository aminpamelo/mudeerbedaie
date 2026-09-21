<?php

declare(strict_types=1);

use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\LiveTimeSlotOverride;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Reproduces the customer report: editing a slot override (its date range or one
 * slot's time) must NOT wipe every scheduled session on the override — only the
 * slots genuinely removed/retimed should lose their sessions.
 */
function makeOverrideWithScheduledSessions(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create();

    $override = LiveTimeSlotOverride::create([
        'live_account_id' => $account->id,
        'effective_from' => '2026-09-13',
        'effective_until' => '2026-09-19',
        'label' => 'Special week',
        'created_by' => $admin->id,
    ]);

    // Two slots on the override (Mon 08:00-11:00 and Mon 15:00-17:00).
    $slotA = LiveTimeSlot::factory()->create([
        'override_id' => $override->id,
        'day_of_week' => 1,
        'start_time' => '08:00:00',
        'end_time' => '11:00:00',
    ]);
    $slotB = LiveTimeSlot::factory()->create([
        'override_id' => $override->id,
        'day_of_week' => 1,
        'start_time' => '15:00:00',
        'end_time' => '17:00:00',
    ]);

    // A dated assignment on each slot -> materialises a scheduled LiveSession.
    $assignmentA = LiveScheduleAssignment::factory()->forDate('2026-09-14')->create([
        'platform_account_id' => $shop->id,
        'live_account_id' => $account->id,
        'time_slot_id' => $slotA->id,
        'day_of_week' => 1,
    ]);
    $assignmentB = LiveScheduleAssignment::factory()->forDate('2026-09-14')->create([
        'platform_account_id' => $shop->id,
        'live_account_id' => $account->id,
        'time_slot_id' => $slotB->id,
        'day_of_week' => 1,
    ]);

    return compact('admin', 'account', 'override', 'slotA', 'slotB', 'assignmentA', 'assignmentB');
}

it('preserves all slots and sessions when only the date range changes', function () {
    ['admin' => $admin, 'account' => $account, 'override' => $override, 'slotA' => $slotA, 'slotB' => $slotB] = makeOverrideWithScheduledSessions();

    expect(LiveSession::count())->toBe(2);

    // Edit: same slots, only the date window shifts.
    $this->actingAs($admin)
        ->putJson(route('livehost.slot-overrides.update', $override), [
            'live_account_id' => $account->id,
            'effective_from' => '2026-09-13',
            'effective_until' => '2026-09-26', // window extended, slots unchanged
            'label' => 'Special week',
            'slots' => [
                ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '11:00'],
                ['day_of_week' => 1, 'start_time' => '15:00', 'end_time' => '17:00'],
            ],
        ])
        ->assertSuccessful();

    // Slot ids preserved (no delete-and-recreate) -> assignments + sessions survive.
    expect(LiveTimeSlot::whereIn('id', [$slotA->id, $slotB->id])->count())->toBe(2)
        ->and(LiveScheduleAssignment::count())->toBe(2)
        ->and(LiveSession::count())->toBe(2);
});

it('drops only the retimed slot session, keeping untouched slots', function () {
    ['admin' => $admin, 'account' => $account, 'override' => $override, 'slotA' => $slotA, 'slotB' => $slotB, 'assignmentA' => $assignmentA, 'assignmentB' => $assignmentB] = makeOverrideWithScheduledSessions();

    expect(LiveSession::count())->toBe(2);

    // Edit: slot A retimed (08:00 -> 09:00), slot B untouched.
    $this->actingAs($admin)
        ->putJson(route('livehost.slot-overrides.update', $override), [
            'live_account_id' => $account->id,
            'effective_from' => '2026-09-13',
            'effective_until' => '2026-09-19',
            'label' => 'Special week',
            'slots' => [
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '11:00'],
                ['day_of_week' => 1, 'start_time' => '15:00', 'end_time' => '17:00'],
            ],
        ])
        ->assertSuccessful();

    // Slot B survives untouched; slot A replaced -> only its assignment is gone.
    // The assignment cascade-nulls its live_session link (nullOnDelete), so exactly
    // one session remains attached to a live calendar schedule.
    expect(LiveTimeSlot::find($slotB->id))->not->toBeNull()
        ->and(LiveTimeSlot::find($slotA->id))->toBeNull()
        ->and(LiveScheduleAssignment::find($assignmentB->id))->not->toBeNull()
        ->and(LiveScheduleAssignment::find($assignmentA->id))->toBeNull()
        ->and(LiveSession::whereNotNull('live_schedule_assignment_id')->count())->toBe(1);
});
