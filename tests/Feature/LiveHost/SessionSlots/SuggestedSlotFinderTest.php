<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\SuggestedSlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function suggestionWeek(): array
{
    $start = CarbonImmutable::parse('2026-07-05')->startOfWeek(CarbonImmutable::SUNDAY);

    return [$start, $start->endOfWeek(CarbonImmutable::SATURDAY)];
}

it('surfaces an unmatched TikTok live as a gap suggestion resolved to its creator account', function () {
    [$weekStart, $weekEnd] = suggestionWeek();
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create([
        'creator_user_id' => null,
        'nickname' => 'amarmirzabedaie',
        'normalized_handle' => 'amarmirzabedaie',
    ]);
    $record = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => null,
        'creator_handle' => 'amarmirzabedaie',
        'launched_time' => '2026-07-05 17:00:00',
        'ended_time' => '2026-07-05 18:12:00',
        'gmv_myr' => 812,
        'live_attributed_gmv_myr' => 640,
    ]);

    $suggestions = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, []);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0])->toMatchArray([
        'id' => $record->id,
        'isRegistered' => true,
        'liveAccountId' => $account->id,
        'matchType' => 'gap',
        'dayOfWeek' => 0,
        'scheduleDate' => '2026-07-05',
        'startTime' => '17:00',
        'endTime' => '18:12',
        'gmv' => 812.0,
        'liveAttributedGmv' => 640.0,
    ]);
});

it('excludes TikTok lives already linked to a recorded session', function () {
    [$weekStart, $weekEnd] = suggestionWeek();
    $shop = PlatformAccount::factory()->create();
    LiveAccount::factory()->create(['creator_user_id' => null, 'nickname' => 'amar', 'normalized_handle' => 'amar']);
    $record = ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_handle' => 'amar',
        'launched_time' => '2026-07-06 14:00:00',
    ]);
    LiveSession::factory()->create(['matched_actual_live_record_id' => $record->id]);

    $suggestions = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, []);

    expect($suggestions)->toBeEmpty();
});

it('flags a live whose creator is not a registered account (only when unlinked are included)', function () {
    [$weekStart, $weekEnd] = suggestionWeek();
    $shop = PlatformAccount::factory()->create();
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_platform_user_id' => null,
        'creator_handle' => 'mila_ckoot',
        'launched_time' => '2026-07-07 20:00:00',
    ]);

    // Hidden by default now (linked-only).
    expect(app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, []))->toBeEmpty();

    $suggestions = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, [], includeUnlinked: true);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0]['isRegistered'])->toBeFalse();
    expect($suggestions[0]['liveAccountId'])->toBeNull();
    expect($suggestions[0]['liveAccountLabel'])->toBe('mila_ckoot');
});

it('hides affiliate and unknown creator lives from the timetable by default', function () {
    [$weekStart, $weekEnd] = suggestionWeek();
    $shop = PlatformAccount::factory()->create();

    LiveAccount::factory()->affiliate()->create(['creator_user_id' => null, 'nickname' => 'kakdee_akuwanita', 'normalized_handle' => 'kakdee_akuwanita']);
    LiveAccount::factory()->unknown()->create(['creator_user_id' => null, 'nickname' => 'mama_qiesh', 'normalized_handle' => 'mama_qiesh']);
    LiveAccount::factory()->linked()->create(['creator_user_id' => null, 'nickname' => 'amarmirzabedaie', 'normalized_handle' => 'amarmirzabedaie']);

    foreach (['kakdee_akuwanita', 'mama_qiesh', 'amarmirzabedaie'] as $i => $handle) {
        ActualLiveRecord::factory()->apiSync()->create([
            'platform_account_id' => $shop->id,
            'creator_platform_user_id' => null,
            'creator_handle' => $handle,
            'launched_time' => '2026-07-0'.($i + 5).' 17:00:00',
        ]);
    }

    $default = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, []);
    expect($default)->toHaveCount(1);
    expect($default[0]['creatorHandle'])->toBe('amarmirzabedaie');

    $all = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, [], includeUnlinked: true);
    expect($all)->toHaveCount(3);
});

it('lists only unknown/unresolved creators as unclassified (linked and affiliate are settled)', function () {
    $shop = PlatformAccount::factory()->create();

    LiveAccount::factory()->linked()->create(['creator_user_id' => null, 'nickname' => 'amarmirzabedaie', 'normalized_handle' => 'amarmirzabedaie']);
    LiveAccount::factory()->affiliate()->create(['creator_user_id' => null, 'nickname' => 'kakdee_akuwanita', 'normalized_handle' => 'kakdee_akuwanita']);
    LiveAccount::factory()->unknown()->create(['creator_user_id' => null, 'nickname' => 'ustamarbedaie', 'normalized_handle' => 'ustamarbedaie']);

    $rows = [
        ['amarmirzabedaie', 500, now()->subDays(2)],   // linked — excluded
        ['kakdee_akuwanita', 10, now()->subDays(1)],   // affiliate — excluded (settled)
        ['ustamarbedaie', 20, now()->subDays(1)],      // unknown — appears
        ['ustamarbedaie', 30, now()->subDays(3)],      // unknown — same group
        ['bondaaran', 0, now()->subDays(1)],           // unresolved (no account) — appears
    ];
    foreach ($rows as [$handle, $gmv, $when]) {
        ActualLiveRecord::factory()->apiSync()->create([
            'platform_account_id' => $shop->id, 'creator_platform_user_id' => null,
            'creator_handle' => $handle, 'launched_time' => $when, 'live_attributed_gmv_myr' => $gmv,
        ]);
    }

    $unclassified = app(SuggestedSlotFinder::class)->unclassifiedCreators(
        CarbonImmutable::now()->subDays(14),
        CarbonImmutable::now(),
    );

    $handles = collect($unclassified)->pluck('creatorHandle')->all();
    expect($unclassified)->toHaveCount(2);
    expect($handles)->not->toContain('amarmirzabedaie');
    expect($handles)->not->toContain('kakdee_akuwanita');

    $unknown = collect($unclassified)->firstWhere('creatorHandle', 'ustamarbedaie');
    expect($unknown['liveCount'])->toBe(2);
    expect($unknown['attributedGmv'])->toBe(50.0);
    // Knows which TikTok Shop the creator went live on.
    expect($unknown['platformAccountId'])->toBe($shop->id);
    expect($unknown['shops'])->toHaveCount(1);
    expect($unknown['shops'][0]['id'])->toBe($shop->id);
});

it('tags a live sitting on an existing unverified slot as near_slot and picks the nearest time slot', function () {
    [$weekStart, $weekEnd] = suggestionWeek();
    $shop = PlatformAccount::factory()->create();
    $account = LiveAccount::factory()->create([
        'creator_user_id' => null,
        'nickname' => 'amar',
        'normalized_handle' => 'amar',
    ]);
    $timeSlot = LiveTimeSlot::factory()->create([
        'platform_account_id' => $shop->id,
        'day_of_week' => 0,
        'start_time' => '17:00:00',
        'end_time' => '19:00:00',
    ]);
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $shop->id,
        'live_account_id' => $account->id,
        'time_slot_id' => $timeSlot->id,
        'day_of_week' => 0,
        'is_template' => true,
        'schedule_date' => null,
    ]);
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_handle' => 'amar',
        'launched_time' => '2026-07-05 17:05:00',
        'ended_time' => '2026-07-05 18:30:00',
    ]);

    $timeSlotOptions = [[
        'id' => $timeSlot->id,
        'label' => '17:00–19:00',
        'dayOfWeek' => 0,
        'platformAccountId' => $shop->id,
        'startTime' => '17:00',
        'endTime' => '19:00',
    ]];

    $suggestions = app(SuggestedSlotFinder::class)->forWeek($weekStart, $weekEnd, null, null, $timeSlotOptions);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0]['matchType'])->toBe('near_slot');
    expect($suggestions[0]['suggestedTimeSlotId'])->toBe($timeSlot->id);
});

it('exposes suggestions on the calendar page and honours the toggle off', function () {
    $shop = PlatformAccount::factory()->create();
    LiveAccount::factory()->create(['creator_user_id' => null, 'nickname' => 'amar', 'normalized_handle' => 'amar']);
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $shop->id,
        'creator_handle' => 'amar',
        'launched_time' => '2026-07-08 15:00:00',
    ]);

    $admin = User::factory()->create(['role' => 'admin_livehost']);

    $this->actingAs($admin)
        ->get('/livehost/session-slots/calendar?week_of=2026-07-05')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('session-slots/Calendar', false)
            ->has('suggestions', 1)
            ->where('suggestions.0.creatorHandle', 'amar')
        );

    $this->actingAs($admin)
        ->get('/livehost/session-slots/calendar?week_of=2026-07-05&show_suggestions=0')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('suggestions', 0));
});
