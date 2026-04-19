<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->tiktok = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
        'is_active' => true,
    ]);
});

it('includes a commission_plan string for each host row', function () {
    $withPlan = User::factory()->create(['role' => 'live_host', 'name' => 'AAA With Plan']);
    $withoutPlan = User::factory()->create(['role' => 'live_host', 'name' => 'BBB No Plan']);

    LiveHostCommissionProfile::factory()->for($withPlan)->create([
        'base_salary_myr' => 2000,
        'per_live_rate_myr' => 30,
        'override_rate_l1_percent' => 10,
        'override_rate_l2_percent' => 5,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);
    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $withPlan->id,
        'platform_id' => $this->tiktok->id,
        'commission_rate_percent' => 4,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/hosts')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($withPlan, $withoutPlan) {
            $page->component('hosts/Index', false)->has('hosts.data');

            $rows = collect($page->toArray()['props']['hosts']['data']);

            $planRow = $rows->firstWhere('id', $withPlan->id);
            $noPlanRow = $rows->firstWhere('id', $withoutPlan->id);

            expect($planRow)->not->toBeNull();
            expect($planRow['commission_plan'])->toBeString();
            expect($planRow['commission_plan'])->toContain('RM');
            expect($planRow['commission_plan'])->toContain('2,000');
            expect($planRow['commission_plan'])->toContain('4%');
            expect($planRow['commission_plan'])->toContain('30');

            expect($noPlanRow)->not->toBeNull();
            expect($noPlanRow['commission_plan'])->toBe('—');
        });
});

it('filter has_upline=has_upline returns only hosts whose active profile has an upline_user_id', function () {
    $upline = User::factory()->create(['role' => 'live_host', 'name' => 'Upline Host']);
    $withUpline = User::factory()->create(['role' => 'live_host', 'name' => 'Has Upline']);
    $withoutUpline = User::factory()->create(['role' => 'live_host', 'name' => 'No Upline']);
    $noPlan = User::factory()->create(['role' => 'live_host', 'name' => 'No Plan']);

    LiveHostCommissionProfile::factory()->for($upline)->create([
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);
    LiveHostCommissionProfile::factory()->for($withUpline)->create([
        'upline_user_id' => $upline->id,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);
    LiveHostCommissionProfile::factory()->for($withoutUpline)->create([
        'upline_user_id' => null,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/hosts?has_upline=has_upline')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($withUpline, $withoutUpline, $noPlan, $upline) {
            $ids = collect($page->toArray()['props']['hosts']['data'])->pluck('id')->all();

            expect($ids)->toContain($withUpline->id)
                ->not->toContain($withoutUpline->id)
                ->not->toContain($noPlan->id)
                ->not->toContain($upline->id);
        });
});

it('filter has_upline=no_plan returns only hosts without an active profile', function () {
    $withPlan = User::factory()->create(['role' => 'live_host', 'name' => 'Has Plan']);
    $noPlan = User::factory()->create(['role' => 'live_host', 'name' => 'No Plan']);

    LiveHostCommissionProfile::factory()->for($withPlan)->create([
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/hosts?has_upline=no_plan')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($withPlan, $noPlan) {
            $ids = collect($page->toArray()['props']['hosts']['data'])->pluck('id')->all();

            expect($ids)->toContain($noPlan->id)
                ->not->toContain($withPlan->id);
        });
});

it('filter has_upline=is_upline_only returns only hosts who are someone else\'s upline', function () {
    $uplineHost = User::factory()->create(['role' => 'live_host', 'name' => 'Upline Host']);
    $downline = User::factory()->create(['role' => 'live_host', 'name' => 'Downline Host']);
    $unrelated = User::factory()->create(['role' => 'live_host', 'name' => 'Unrelated']);

    LiveHostCommissionProfile::factory()->for($uplineHost)->create([
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);
    LiveHostCommissionProfile::factory()->for($downline)->create([
        'upline_user_id' => $uplineHost->id,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);
    LiveHostCommissionProfile::factory()->for($unrelated)->create([
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/hosts?has_upline=is_upline_only')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($uplineHost, $downline, $unrelated) {
            $ids = collect($page->toArray()['props']['hosts']['data'])->pluck('id')->all();

            expect($ids)->toContain($uplineHost->id)
                ->not->toContain($downline->id)
                ->not->toContain($unrelated->id);
        });
});
