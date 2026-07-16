<?php

use App\Models\LiveHostCommissionTierTemplate;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

function templatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Standard 5-Tier',
        'description' => 'Default ladder for new hosts',
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 30000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => null, 'internal_percent' => 7, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ], $overrides);
}

it('renders the manager page with existing templates', function () {
    LiveHostCommissionTierTemplate::create([
        'name' => 'Standard 5-Tier',
        'tiers' => templatePayload()['tiers'],
        'created_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->get('/livehost/commission-templates')
        ->assertOk();
});

it('creates a template with a valid ladder', function () {
    actingAs($this->pic)
        ->post('/livehost/commission-templates', templatePayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $template = LiveHostCommissionTierTemplate::first();
    expect($template)->not->toBeNull();
    expect($template->name)->toBe('Standard 5-Tier');
    expect($template->tiers)->toHaveCount(3);
    expect($template->created_by)->toBe($this->pic->id);
    // Open-ended top tier persists as null, values are canonicalised.
    expect($template->tiers[2]['max_gmv_myr'])->toBeNull();
    expect((float) $template->tiers[0]['internal_percent'])->toBe(5.0);
});

it('rejects a template with a non-contiguous ladder', function () {
    actingAs($this->pic)
        ->post('/livehost/commission-templates', templatePayload([
            'tiers' => [
                ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 30000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
                ['tier_number' => 2, 'min_gmv_myr' => 40000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
            ],
        ]))
        ->assertSessionHasErrors('tiers');

    expect(LiveHostCommissionTierTemplate::count())->toBe(0);
});

it('requires a name', function () {
    actingAs($this->pic)
        ->post('/livehost/commission-templates', templatePayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('updates an existing template', function () {
    $template = LiveHostCommissionTierTemplate::create([
        'name' => 'Old',
        'tiers' => templatePayload()['tiers'],
        'created_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->put("/livehost/commission-templates/{$template->id}", templatePayload(['name' => 'Renamed']))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($template->fresh()->name)->toBe('Renamed');
});

it('deletes a template', function () {
    $template = LiveHostCommissionTierTemplate::create([
        'name' => 'Doomed',
        'tiers' => templatePayload()['tiers'],
        'created_by' => $this->pic->id,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/commission-templates/{$template->id}")
        ->assertRedirect();

    expect(LiveHostCommissionTierTemplate::count())->toBe(0);
});

it('forbids a live_host from managing templates', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/livehost/commission-templates', templatePayload())
        ->assertForbidden();
});

it('applies a template ladder to a host via the schedule endpoint', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $tiers = templatePayload()['tiers'];

    // Mirrors what the host page posts when a template is chosen: the ladder
    // goes to the per-platform schedule endpoint (platform in the URL only).
    actingAs($this->pic)
        ->post("/livehost/hosts/{$host->id}/platforms/{$platform->id}/tiers", [
            'effective_from' => '2026-04-01',
            'tiers' => $tiers,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(LiveHostPlatformCommissionTier::where('user_id', $host->id)->where('platform_id', $platform->id)->count())->toBe(3);
});
