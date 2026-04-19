<?php

use App\Models\LiveSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds commission-related columns to live host tables', function () {
    expect(Schema::hasColumn('live_host_platform_account', 'creator_handle'))->toBeTrue();
    expect(Schema::hasColumn('live_host_platform_account', 'creator_platform_user_id'))->toBeTrue();
    expect(Schema::hasColumn('live_host_platform_account', 'is_primary'))->toBeTrue();

    if (Schema::hasTable('live_session_slots')) {
        expect(Schema::hasColumn('live_session_slots', 'live_host_platform_account_id'))->toBeTrue();
    }

    expect(Schema::hasColumn('live_sessions', 'live_host_platform_account_id'))->toBeTrue();
    expect(Schema::hasColumn('live_sessions', 'gmv_amount'))->toBeTrue();
    expect(Schema::hasColumn('live_sessions', 'gmv_adjustment'))->toBeTrue();
    expect(Schema::hasColumn('live_sessions', 'gmv_source'))->toBeTrue();
    expect(Schema::hasColumn('live_sessions', 'gmv_locked_at'))->toBeTrue();
    expect(Schema::hasColumn('live_sessions', 'commission_snapshot_json'))->toBeTrue();
});

it('defaults gmv_adjustment to 0 and gmv_source to manual on new sessions', function () {
    $session = LiveSession::factory()->create();

    $fresh = $session->fresh();

    expect((float) $fresh->gmv_adjustment)->toBe(0.0);
    expect($fresh->gmv_source)->toBe('manual');
});

it('allows null gmv_amount, gmv_locked_at, commission_snapshot_json on live sessions', function () {
    $session = LiveSession::factory()->create();

    $fresh = $session->fresh();

    expect($fresh->gmv_amount)->toBeNull();
    expect($fresh->gmv_locked_at)->toBeNull();
    expect($fresh->commission_snapshot_json)->toBeNull();
    expect($fresh->live_host_platform_account_id)->toBeNull();
});
