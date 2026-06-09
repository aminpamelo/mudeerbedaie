<?php

use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the sessions list exposes the creator account name alongside the shop', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create([
        'display_name' => 'BeDaie Ustaz Amar',
        'nickname' => 'amarmirzabedaie',
    ]);
    $shop = PlatformAccount::factory()->create(['name' => 'Tiktok Shop Bedaie']);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'live_account_id' => $account->id,
        'platform_account_id' => $shop->id,
        'status' => 'ended',
    ]);

    $this->actingAs($host)
        ->get('/live-host/sessions?filter=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->where('sessions.data.0.creatorAccount', 'BeDaie Ustaz Amar')
            ->where('sessions.data.0.platformAccount', 'Tiktok Shop Bedaie')
        );
});

test('the creator account falls back to nickname when there is no display name', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create([
        'display_name' => null,
        'nickname' => 'amarmirzabedaie',
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'live_account_id' => $account->id,
        'status' => 'ended',
    ]);

    $this->actingAs($host)
        ->get('/live-host/sessions?filter=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->where('sessions.data.0.creatorAccount', 'amarmirzabedaie')
        );
});

test('the creator account is null when no creator account is linked', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'live_account_id' => null,
        'status' => 'ended',
    ]);

    $this->actingAs($host)
        ->get('/live-host/sessions?filter=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->where('sessions.data.0.creatorAccount', null)
            ->has('sessions.data.0.platformAccount')
        );
});

test('the weekly schedule slot cards expose the creator account name', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = LiveAccount::factory()->create([
        'display_name' => 'BeDaie Ustaz Amar',
        'nickname' => 'amarmirzabedaie',
    ]);
    $shop = PlatformAccount::factory()->create(['name' => 'Tiktok Shop Bedaie']);
    $timeSlot = LiveTimeSlot::factory()->create(['platform_account_id' => $shop->id]);

    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $host->id,
        'live_account_id' => $account->id,
        'platform_account_id' => $shop->id,
        'time_slot_id' => $timeSlot->id,
        'is_template' => true,
        'schedule_date' => null,
        'day_of_week' => 3,
        'status' => 'scheduled',
    ]);

    $this->actingAs($host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p
            ->where('days.3.schedules.0.creatorAccount', 'BeDaie Ustaz Amar')
            ->where('days.3.schedules.0.platformAccount', 'Tiktok Shop Bedaie')
        );
});
