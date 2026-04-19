<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('can insert a commission profile row with all expected columns', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $upline = User::factory()->create(['role' => 'live_host']);

    \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 2000.00,
        'per_live_rate_myr' => 30.00,
        'upline_user_id' => $upline->id,
        'override_rate_l1_percent' => 10.00,
        'override_rate_l2_percent' => 5.00,
        'effective_from' => now(),
        'effective_to' => null,
        'is_active' => true,
        'notes' => 'initial profile',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    assertDatabaseHas('live_host_commission_profiles', [
        'user_id' => $host->id,
        'upline_user_id' => $upline->id,
        'base_salary_myr' => 2000.00,
    ]);
});

it('allows multiple profile rows for the same user with different effective_from', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 1500.00,
        'per_live_rate_myr' => 20.00,
        'upline_user_id' => null,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => now()->subMonth(),
        'effective_to' => now()->subDay(),
        'is_active' => false,
        'notes' => 'old profile',
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subDay(),
    ]);

    \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 2500.00,
        'per_live_rate_myr' => 35.00,
        'upline_user_id' => null,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => now(),
        'effective_to' => null,
        'is_active' => true,
        'notes' => 'current profile',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    assertDatabaseCount('live_host_commission_profiles', 2);
    assertDatabaseHas('live_host_commission_profiles', [
        'user_id' => $host->id,
        'base_salary_myr' => 1500.00,
        'is_active' => false,
    ]);
    assertDatabaseHas('live_host_commission_profiles', [
        'user_id' => $host->id,
        'base_salary_myr' => 2500.00,
        'is_active' => true,
    ]);
});

it('rejects duplicate profile rows for same user and effective_from', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $timestamp = now();

    \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 1000.00,
        'per_live_rate_myr' => 10.00,
        'upline_user_id' => null,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => $timestamp,
        'effective_to' => null,
        'is_active' => true,
        'notes' => 'first row',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => \DB::table('live_host_commission_profiles')->insert([
        'user_id' => $host->id,
        'base_salary_myr' => 2000.00,
        'per_live_rate_myr' => 20.00,
        'upline_user_id' => null,
        'override_rate_l1_percent' => 0,
        'override_rate_l2_percent' => 0,
        'effective_from' => $timestamp,
        'effective_to' => null,
        'is_active' => true,
        'notes' => 'duplicate row',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
