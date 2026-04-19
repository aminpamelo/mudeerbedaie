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
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->tiktok = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
        'is_active' => true,
    ]);
});

it('exposes commissionProfile, commissionProfiles (history), platformCommissionRates, and platforms to the PIC host Show page', function () {
    // Old inactive profile (history)
    LiveHostCommissionProfile::factory()->for($this->host)->create([
        'base_salary_myr' => 1500,
        'per_live_rate_myr' => 20,
        'override_rate_l1_percent' => 10,
        'override_rate_l2_percent' => 5,
        'is_active' => false,
        'effective_from' => now()->subMonth(),
        'effective_to' => now()->subDay(),
    ]);

    // Active profile
    $active = LiveHostCommissionProfile::factory()->for($this->host)->create([
        'base_salary_myr' => 2000,
        'per_live_rate_myr' => 30,
        'override_rate_l1_percent' => 10,
        'override_rate_l2_percent' => 5,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->tiktok->id,
        'commission_rate_percent' => 4,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Show', false)
            ->has('commissionProfile')
            ->where('commissionProfile.base_salary_myr', fn ($v) => (float) $v === 2000.0)
            ->where('commissionProfile.per_live_rate_myr', fn ($v) => (float) $v === 30.0)
            ->has('commissionProfiles', 2)
            ->has('platformCommissionRates', 1)
            ->where('platformCommissionRates.0.commission_rate_percent', fn ($v) => (float) $v === 4.0)
            ->has('platforms')
            ->has('uplineCandidates')
        );
});

it('exposes null commissionProfile and empty collections when host has no profile yet', function () {
    actingAs($this->pic)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Show', false)
            ->where('commissionProfile', null)
            ->has('commissionProfiles', 0)
            ->has('platformCommissionRates', 0)
            ->has('platforms')
            ->has('uplineCandidates')
        );
});

it('excludes the current host from the upline candidates list', function () {
    $otherHost = User::factory()->create(['role' => 'live_host', 'name' => 'Other Host']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($otherHost) {
            $page
                ->component('hosts/Show', false)
                ->has('uplineCandidates');

            $candidates = collect($page->toArray()['props']['uplineCandidates'] ?? []);
            expect($candidates->pluck('id')->all())
                ->toContain($otherHost->id)
                ->not->toContain($this->host->id);
        });
});

it('live_host role is forbidden from viewing the PIC host Show page', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertForbidden();
});
