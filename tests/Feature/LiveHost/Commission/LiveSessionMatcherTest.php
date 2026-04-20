<?php

declare(strict_types=1);

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use App\Models\TiktokReportImport;
use App\Models\User;
use App\Services\LiveHost\Tiktok\LiveSessionMatcher;
use Carbon\Carbon;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->uploader = User::factory()->create(['role' => 'admin_livehost']);
    $platform = Platform::firstOrCreate(
        ['slug' => 'tiktok-shop'],
        Platform::factory()->make(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop'])->toArray()
    );
    $this->platformAccount = PlatformAccount::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $platform->id,
    ]);
    $this->pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'creator_handle' => '@amar',
        'creator_platform_user_id' => '6526684195492729856',
        'is_primary' => true,
    ]);
    $this->import = TiktokReportImport::create([
        'report_type' => 'live_analysis',
        'file_path' => 'imports/live.xlsx',
        'uploaded_by' => $this->uploader->id,
        'uploaded_at' => now(),
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
    ]);
});

it('matches a LiveSession with same creator id and time within window', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $match = (new LiveSessionMatcher)->match($report);

    expect($match?->id)->toBe($session->id);
});

it('returns null when no session matches the creator id', function () {
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '9999999999999999999',
        'launched_time' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    expect((new LiveSessionMatcher)->match($report))->toBeNull();
});

it('returns null when session is outside the +/- 30min window', function () {
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => Carbon::parse('2026-04-18 20:00:00'),
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    expect((new LiveSessionMatcher)->match($report))->toBeNull();
});

it('returns null when the report has a null launched_time', function () {
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $report = new TiktokLiveReport([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => null,
    ]);

    expect((new LiveSessionMatcher)->match($report))->toBeNull();
});

it('picks the closest-time candidate when multiple exist', function () {
    $reportTime = Carbon::parse('2026-04-18 22:00:00');

    $sessionTwentyMin = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => $reportTime->copy()->addMinutes(20),
    ]);

    $sessionTenMin = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $this->platformAccount->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'status' => 'ended',
        'actual_start_at' => $reportTime->copy()->addMinutes(10),
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => $reportTime,
    ]);

    $match = (new LiveSessionMatcher)->match($report);

    expect($match?->id)->toBe($sessionTenMin->id)
        ->and($match?->id)->not->toBe($sessionTwentyMin->id);
});

it('matcher scopes to platform_account_id when provided — session from a different shop is NOT matched', function () {
    $reportTime = Carbon::parse('2026-04-18 22:14:00');

    // Shop A — the import's target shop. No session on this shop for the creator.
    $shopA = $this->platformAccount; // seeded in beforeEach; creator=6526684195492729856

    // Shop B — a different platform account, same creator id pivoted in.
    $shopB = PlatformAccount::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $shopA->platform_id,
    ]);
    $pivotB = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $shopB->id,
        'creator_handle' => '@amar-shop-b',
        'creator_platform_user_id' => '6526684195492729856',
        'is_primary' => false,
    ]);

    // The ONLY live session for this creator id is on Shop B.
    $sessionOnShopB = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'platform_account_id' => $shopB->id,
        'live_host_platform_account_id' => $pivotB->id,
        'status' => 'ended',
        'actual_start_at' => $reportTime,
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => $reportTime,
    ]);

    // Unscoped: matches the Shop B session — current (backward-compat) behavior.
    $unscoped = (new LiveSessionMatcher)->match($report);
    expect($unscoped?->id)->toBe($sessionOnShopB->id);

    // Scoped to Shop A: NO match, because the only candidate is on Shop B.
    $scoped = (new LiveSessionMatcher)->match($report, $shopA->id);
    expect($scoped)->toBeNull();

    // Scoped to Shop B: still matches.
    $scopedB = (new LiveSessionMatcher)->match($report, $shopB->id);
    expect($scopedB?->id)->toBe($sessionOnShopB->id);
});

it('does not match sessions belonging to a different creator id', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    $otherPlatformAccount = PlatformAccount::factory()->create([
        'user_id' => $otherHost->id,
        'platform_id' => $this->platformAccount->platform_id,
    ]);
    $otherPivot = LiveHostPlatformAccount::create([
        'user_id' => $otherHost->id,
        'platform_account_id' => $otherPlatformAccount->id,
        'creator_handle' => '@other',
        'creator_platform_user_id' => '1111111111111111111',
        'is_primary' => true,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $otherHost->id,
        'platform_account_id' => $otherPlatformAccount->id,
        'live_host_platform_account_id' => $otherPivot->id,
        'status' => 'ended',
        'actual_start_at' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    $report = TiktokLiveReport::create([
        'import_id' => $this->import->id,
        'tiktok_creator_id' => '6526684195492729856',
        'launched_time' => Carbon::parse('2026-04-18 22:14:00'),
    ]);

    expect((new LiveSessionMatcher)->match($report))->toBeNull();
});
