<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-04-25 20:00:00');
    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->slot = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
});

afterEach(fn () => Carbon::setTestNow());

function apiLive(): ActualLiveRecord
{
    return ActualLiveRecord::factory()->create([
        'platform_account_id' => test()->platform->id,
        'source' => 'api_sync',
        'source_record_id' => (string) fake()->numerify('################'),
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 09:15:37',
        'ended_time' => '2026-04-20 10:30:00',
        'duration_seconds' => 4463,
        'live_attributed_gmv_myr' => 7487.33,
        'viewers' => 17992,
    ]);
}

function csvTwin(): ActualLiveRecord
{
    return ActualLiveRecord::factory()->create([
        'platform_account_id' => test()->platform->id,
        'source' => 'csv_import',
        'source_record_id' => null,
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 09:15:00', // 37s before the api twin
        'ended_time' => null,
        'duration_seconds' => 4400,
        'live_attributed_gmv_myr' => 4.00,
        'viewers' => 58,
    ]);
}

it('deletes an unlinked csv record that has an api twin', function () {
    $api = apiLive();
    $csv = csvTwin();

    $this->artisan('livehost:dedupe-live-sources --apply')->assertSuccessful();

    expect(ActualLiveRecord::find($csv->id))->toBeNull();      // csv dupe removed
    expect(ActualLiveRecord::find($api->id))->not->toBeNull(); // api kept
});

it('keeps a csv record that has NO api twin', function () {
    $lonelyCsv = ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->platform->id, 'source' => 'csv_import', 'source_record_id' => null,
        'launched_time' => '2026-04-20 14:00:00', 'live_attributed_gmv_myr' => 100, 'viewers' => 10,
    ]);

    $this->artisan('livehost:dedupe-live-sources --apply')->assertSuccessful();

    expect(ActualLiveRecord::find($lonelyCsv->id))->not->toBeNull();
});

it('does NOT treat a different creator on the same shop as a twin', function () {
    // api_sync for creator B, same shop, same minute — but a DIFFERENT creator.
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->platform->id, 'source' => 'api_sync',
        'source_record_id' => (string) fake()->numerify('################'),
        'creator_handle' => 'creatorB', 'launched_time' => '2026-04-20 09:15:37',
        'live_attributed_gmv_myr' => 5000, 'viewers' => 9000,
    ]);
    // csv for creator A — must be kept (no twin of ITS OWN creator).
    $csvA = ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->platform->id, 'source' => 'csv_import', 'source_record_id' => null,
        'creator_handle' => 'creatorA', 'launched_time' => '2026-04-20 09:15:00',
        'live_attributed_gmv_myr' => 100, 'viewers' => 50,
    ]);

    $this->artisan('livehost:dedupe-live-sources --apply')->assertSuccessful();

    expect(ActualLiveRecord::find($csvA->id))->not->toBeNull();
});

it('unlinks a csv twin from its session before deleting, keeping the api record', function () {
    $assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id, 'live_account_id' => $this->account->id,
        'live_host_id' => $this->host->id, 'time_slot_id' => $this->slot->id,
        'is_template' => false, 'schedule_date' => '2026-04-20', 'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();
    $api = apiLive();
    $csv = csvTwin();

    // Session double-links BOTH twins (the double-count bug).
    foreach ([$api->id => 7487.33, $csv->id => 4.00] as $id => $gmv) {
        $session->actualLiveRecords()->attach($id, ['is_primary' => $id === $api->id, 'live_attributed_gmv_myr' => $gmv, 'linked_at' => now()]);
    }
    $session->update(['verification_status' => 'verified', 'matched_actual_live_record_id' => $api->id, 'gmv_amount' => 7491.33, 'status' => 'ended', 'actual_end_at' => '2026-04-20 10:30:00']);

    $this->artisan('livehost:dedupe-live-sources --apply')->assertSuccessful();

    $session->refresh();
    expect(ActualLiveRecord::find($csv->id))->toBeNull();
    expect($session->actualLiveRecords()->pluck('actual_live_records.id')->all())->toBe([$api->id]);
    expect((float) $session->gmv_amount)->toBe(7487.33); // recomputed from the api record only, no double-count
});
