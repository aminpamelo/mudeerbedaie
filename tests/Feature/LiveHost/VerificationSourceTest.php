<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->platform = PlatformAccount::factory()->create();
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id, 'source_record_id' => '111',
        'launched_time' => '2026-06-12 09:00:00',
    ]);
    ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->platform->id, 'source' => 'csv_import', 'source_record_id' => null,
        'launched_time' => '2026-06-12 14:00:00',
    ]);
});

it('excludes csv_import BY DEFAULT (verify_source unset = api only)', function () {
    $rows = ActualLiveRecord::query()->verificationSource()->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->source)->toBe('api_sync');
});

it('still excludes csv_import when verify_source is explicitly api', function () {
    app(SettingsService::class)->set('livehost.verify_source', 'api', 'string', 'livehost');

    $rows = ActualLiveRecord::query()->verificationSource()->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->source)->toBe('api_sync');
});

it('includes both again when verify_source is set back to all', function () {
    app(SettingsService::class)->set('livehost.verify_source', 'all', 'string', 'livehost');

    expect(ActualLiveRecord::query()->verificationSource()->count())->toBe(2);
});
