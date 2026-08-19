<?php

use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

function customTimePayload(array $overrides = []): array
{
    return array_merge([
        'platform_account_id' => PlatformAccount::factory()->create()->id,
        'live_account_id' => LiveAccount::factory()->create()->id,
        'live_host_id' => User::factory()->create(['role' => 'live_host'])->id,
        'day_of_week' => 2,
        'is_template' => true,
    ], $overrides);
}

it('resolves a bespoke start/end time into a hidden ad-hoc slot', function () {
    $preset = LiveTimeSlot::factory()->create(['start_time' => '06:30:00', 'end_time' => '08:30:00']);

    actingAs($this->pic)
        ->post('/livehost/session-slots', customTimePayload([
            'time_slot_id' => $preset->id,
            'start_time' => '06:30',
            'end_time' => '09:15', // nudged past the preset window
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $created = LiveScheduleAssignment::latest('id')->first();
    $slot = $created->timeSlot;

    expect($slot->id)->not->toBe($preset->id)
        ->and($slot->is_ad_hoc)->toBeTrue()
        ->and(substr((string) $slot->start_time, 0, 5))->toBe('06:30')
        ->and(substr((string) $slot->end_time, 0, 5))->toBe('09:15')
        ->and($slot->duration_minutes)->toBe(165);
});

it('keeps the chosen preset when the custom time is left unchanged (no ad-hoc row)', function () {
    $preset = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);

    actingAs($this->pic)
        ->post('/livehost/session-slots', customTimePayload([
            'time_slot_id' => $preset->id,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(LiveScheduleAssignment::latest('id')->first()->time_slot_id)->toBe($preset->id)
        ->and(LiveTimeSlot::where('is_ad_hoc', true)->count())->toBe(0);
});

it('reuses one ad-hoc slot for the same bespoke window across assignments', function () {
    actingAs($this->pic)->post('/livehost/session-slots', customTimePayload([
        'day_of_week' => 2,
        'start_time' => '06:15',
        'end_time' => '07:45',
    ]))->assertSessionHasNoErrors();

    actingAs($this->pic)->post('/livehost/session-slots', customTimePayload([
        'day_of_week' => 3,
        'start_time' => '06:15',
        'end_time' => '07:45',
    ]))->assertSessionHasNoErrors();

    expect(LiveTimeSlot::where('is_ad_hoc', true)->count())->toBe(1);

    $slotIds = LiveScheduleAssignment::query()->pluck('time_slot_id')->unique();
    expect($slotIds)->toHaveCount(1);
});

it('rejects a custom time whose end is not after the start', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', customTimePayload([
            'start_time' => '10:00',
            'end_time' => '09:00',
        ]))
        ->assertSessionHasErrors('end_time');

    expect(LiveTimeSlot::where('is_ad_hoc', true)->count())->toBe(0);
});

it('rejects a malformed custom time without creating a slot', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', customTimePayload([
            'start_time' => '25:99',
            'end_time' => '09:00',
        ]))
        ->assertSessionHasErrors('start_time');

    expect(LiveTimeSlot::where('is_ad_hoc', true)->count())->toBe(0);
});

it('repoints an assignment to an ad-hoc slot when edited to a custom time', function () {
    $preset = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
    $assignment = LiveScheduleAssignment::factory()->create([
        'time_slot_id' => $preset->id,
        'day_of_week' => 1,
        'is_template' => true,
    ]);

    actingAs($this->pic)
        ->put("/livehost/session-slots/{$assignment->id}", [
            'platform_account_id' => $assignment->platform_account_id,
            'live_account_id' => $assignment->live_account_id,
            'live_host_id' => User::factory()->create(['role' => 'live_host'])->id,
            'time_slot_id' => $preset->id,
            'start_time' => '09:30',
            'end_time' => '11:45',
            'day_of_week' => 1,
            'is_template' => true,
            'status' => 'confirmed',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $slot = $assignment->fresh()->timeSlot;
    expect($slot->id)->not->toBe($preset->id)
        ->and($slot->is_ad_hoc)->toBeTrue()
        ->and(substr((string) $slot->start_time, 0, 5))->toBe('09:30')
        ->and(substr((string) $slot->end_time, 0, 5))->toBe('11:45');
});

it('hides ad-hoc slots from the calendar time-slot scaffolds', function () {
    LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
    LiveTimeSlot::factory()->create(['is_ad_hoc' => true, 'start_time' => '06:15:00', 'end_time' => '07:45:00']);

    actingAs($this->pic)
        ->get('/livehost/session-slots/calendar')
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots', 1)->etc());
});

it('hides ad-hoc slots from the Time Slots management page', function () {
    LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
    LiveTimeSlot::factory()->create(['is_ad_hoc' => true, 'start_time' => '06:15:00', 'end_time' => '07:45:00']);

    actingAs($this->pic)
        ->get('/livehost/time-slots')
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots.data', 1)->etc());
});
