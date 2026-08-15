<?php

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Artisan;

it('flags a creator with multiple lives at the same launch time', function () {
    $shopA = PlatformAccount::factory()->create();
    $shopB = PlatformAccount::factory()->create();

    // Same creator, same launch minute, two shops — a pile-up.
    ActualLiveRecord::factory()->create([
        'source' => 'api_sync',
        'source_record_id' => 'live-1',
        'platform_account_id' => $shopA->id,
        'creator_handle' => 'testcreator',
        'launched_time' => '2026-07-26 06:30:00',
        'gmv_myr' => 100,
    ]);
    ActualLiveRecord::factory()->create([
        'source' => 'api_sync',
        'source_record_id' => 'live-2',
        'platform_account_id' => $shopB->id,
        'creator_handle' => 'testcreator',
        'launched_time' => '2026-07-26 06:31:00',
        'gmv_myr' => 200,
    ]);

    // A lone live for a different creator — must NOT be flagged.
    ActualLiveRecord::factory()->create([
        'source' => 'api_sync',
        'source_record_id' => 'live-3',
        'platform_account_id' => $shopA->id,
        'creator_handle' => 'solocreator',
        'launched_time' => '2026-07-26 10:00:00',
        'gmv_myr' => 50,
    ]);

    $code = Artisan::call('livehost:inspect-concurrent-lives', ['--date' => '2026-07-26']);
    $output = Artisan::output();

    expect($code)->toBe(0);
    expect($output)->toContain('testcreator');
    expect($output)->toContain('2 lives');
    expect($output)->toContain('pile-up group(s) found');
    expect($output)->not->toContain('solocreator');
});

it('reports nothing when no lives pile up', function () {
    $shop = PlatformAccount::factory()->create();
    ActualLiveRecord::factory()->create([
        'source' => 'api_sync',
        'platform_account_id' => $shop->id,
        'creator_handle' => 'lonely',
        'launched_time' => '2026-07-26 06:30:00',
    ]);

    Artisan::call('livehost:inspect-concurrent-lives', ['--date' => '2026-07-26']);

    expect(Artisan::output())->toContain('No creator has 2+ lives');
});
