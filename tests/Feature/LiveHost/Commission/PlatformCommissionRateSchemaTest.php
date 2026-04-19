<?php

use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('can insert a platform commission rate row with all expected columns', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    \DB::table('live_host_platform_commission_rates')->insert([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
        'effective_from' => now(),
        'effective_to' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    assertDatabaseHas('live_host_platform_commission_rates', [
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
    ]);
});

it('rejects duplicate rows for same user, platform, and effective_from', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();
    $timestamp = now();

    \DB::table('live_host_platform_commission_rates')->insert([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
        'effective_from' => $timestamp,
        'effective_to' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => \DB::table('live_host_platform_commission_rates')->insert([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 5.00,
        'effective_from' => $timestamp,
        'effective_to' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows multiple rows for the same user and platform with different effective_from', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    \DB::table('live_host_platform_commission_rates')->insert([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 3.00,
        'effective_from' => now()->subMonth(),
        'effective_to' => now()->subDay(),
        'is_active' => false,
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subDay(),
    ]);

    \DB::table('live_host_platform_commission_rates')->insert([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
        'effective_from' => now(),
        'effective_to' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    assertDatabaseCount('live_host_platform_commission_rates', 2);
    assertDatabaseHas('live_host_platform_commission_rates', [
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 3.00,
        'is_active' => false,
    ]);
    assertDatabaseHas('live_host_platform_commission_rates', [
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
        'is_active' => true,
    ]);
});
