<?php

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;

it('casts launched_time to carbon and raw_json to array', function () {
    $record = ActualLiveRecord::factory()->create([
        'raw_json' => ['foo' => 'bar'],
    ]);

    expect($record->launched_time)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($record->raw_json)->toBe(['foo' => 'bar']);
});

it('belongs to a platform account', function () {
    $account = PlatformAccount::factory()->create();
    $record = ActualLiveRecord::factory()->create(['platform_account_id' => $account->id]);

    expect($record->platformAccount->id)->toBe($account->id);
});
