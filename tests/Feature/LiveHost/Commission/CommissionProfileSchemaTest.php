<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'base_salary_myr' => 2000.00,
    ]);
});
