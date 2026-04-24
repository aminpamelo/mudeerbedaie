<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
});

function canonicalTiersPayload(int $platformId, string $effectiveFrom = '2026-04-01'): array
{
    return [
        'platform_id' => $platformId,
        'effective_from' => $effectiveFrom,
        'tiers' => [
            ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => 30000, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
            ['tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => null, 'internal_percent' => 7, 'l1_percent' => 1, 'l2_percent' => 2],
        ],
    ];
}

it('stores a tier schedule with valid payload', function () {
    actingAs($this->pic)
        ->post(
            "/livehost/hosts/{$this->host->id}/platforms/{$this->platform->id}/tiers",
            canonicalTiersPayload($this->platform->id),
        )
        ->assertRedirect()
        ->assertSessionHas('success');

    $rows = LiveHostPlatformCommissionTier::query()
        ->where('user_id', $this->host->id)
        ->where('platform_id', $this->platform->id)
        ->where('is_active', true)
        ->orderBy('tier_number')
        ->get();

    expect($rows)->toHaveCount(3);
    expect((int) $rows[0]->tier_number)->toBe(1);
    expect((float) $rows[0]->min_gmv_myr)->toBe(0.0);
    expect((float) $rows[0]->max_gmv_myr)->toBe(30000.0);
    expect((int) $rows[2]->tier_number)->toBe(3);
    expect($rows[2]->max_gmv_myr)->toBeNull();
    expect($rows->every(fn ($r) => $r->effective_to === null))->toBeTrue();
    expect($rows->every(fn ($r) => (string) $r->effective_from->toDateString() === '2026-04-01'))->toBeTrue();
});

it('archives the existing active schedule when a new one is stored', function () {
    $oldRow = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 1,
        'min_gmv_myr' => 0,
        'max_gmv_myr' => null,
        'internal_percent' => 4,
        'l1_percent' => 1,
        'l2_percent' => 2,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    actingAs($this->pic)
        ->post(
            "/livehost/hosts/{$this->host->id}/platforms/{$this->platform->id}/tiers",
            canonicalTiersPayload($this->platform->id, '2026-04-01'),
        )
        ->assertRedirect()
        ->assertSessionHas('success');

    $old = $oldRow->fresh();

    expect($old->is_active)->toBeFalse();
    expect($old->effective_to)->not->toBeNull();
    expect($old->effective_to->toDateString())->toBe('2026-03-31');

    $active = LiveHostPlatformCommissionTier::query()
        ->where('user_id', $this->host->id)
        ->where('platform_id', $this->platform->id)
        ->where('is_active', true)
        ->get();

    expect($active)->toHaveCount(3);
});

it('rejects an invalid tier schedule payload with 422', function () {
    actingAs($this->pic)
        ->post(
            "/livehost/hosts/{$this->host->id}/platforms/{$this->platform->id}/tiers",
            [
                'platform_id' => $this->platform->id,
                'effective_from' => '2026-04-01',
                'tiers' => [
                    ['tier_number' => 1, 'min_gmv_myr' => 0, 'max_gmv_myr' => null, 'internal_percent' => 5, 'l1_percent' => 1, 'l2_percent' => 2],
                    ['tier_number' => 3, 'min_gmv_myr' => 30000, 'max_gmv_myr' => null, 'internal_percent' => 6, 'l1_percent' => 1, 'l2_percent' => 2],
                ],
            ],
        )
        ->assertSessionHasErrors('tiers');

    expect(LiveHostPlatformCommissionTier::query()->count())->toBe(0);
});

it('updates a tier row with valid fields', function () {
    $tier = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 1,
        'min_gmv_myr' => 0,
        'max_gmv_myr' => 30000,
        'internal_percent' => 5,
        'l1_percent' => 1,
        'l2_percent' => 2,
    ]);

    actingAs($this->pic)
        ->patch("/livehost/hosts/{$this->host->id}/tiers/{$tier->id}", [
            'min_gmv_myr' => 0,
            'max_gmv_myr' => 40000,
            'internal_percent' => 8,
            'l1_percent' => 1,
            'l2_percent' => 3,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $tier->refresh();
    expect((float) $tier->max_gmv_myr)->toBe(40000.0);
    expect((float) $tier->internal_percent)->toBe(8.0);
    expect((float) $tier->l2_percent)->toBe(3.0);
});

it('returns 404 when updating a tier that belongs to a different host', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    $tier = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $otherHost->id,
        'platform_id' => $this->platform->id,
    ]);

    actingAs($this->pic)
        ->patch("/livehost/hosts/{$this->host->id}/tiers/{$tier->id}", [
            'min_gmv_myr' => 0,
            'max_gmv_myr' => 30000,
            'internal_percent' => 5,
            'l1_percent' => 1,
            'l2_percent' => 2,
        ])
        ->assertNotFound();
});

it('archives the highest tier in the active schedule when destroyed', function () {
    $t1 = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 1,
        'min_gmv_myr' => 0,
        'max_gmv_myr' => 30000,
        'effective_from' => '2026-04-01',
        'is_active' => true,
    ]);
    $t2 = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 2,
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => null,
        'effective_from' => '2026-04-01',
        'is_active' => true,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$this->host->id}/tiers/{$t2->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($t2->fresh()->is_active)->toBeFalse();
    expect($t1->fresh()->is_active)->toBeTrue();
});

it('refuses to destroy a non-highest tier and flashes an error', function () {
    $t1 = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 1,
        'min_gmv_myr' => 0,
        'max_gmv_myr' => 30000,
        'effective_from' => '2026-04-01',
        'is_active' => true,
    ]);
    $t2 = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 2,
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => null,
        'effective_from' => '2026-04-01',
        'is_active' => true,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$this->host->id}/tiers/{$t1->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($t1->fresh()->is_active)->toBeTrue();
    expect($t2->fresh()->is_active)->toBeTrue();
});

it('returns 404 when destroying a tier that belongs to a different host', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    $tier = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $otherHost->id,
        'platform_id' => $this->platform->id,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$this->host->id}/tiers/{$tier->id}")
        ->assertNotFound();
});

it('forbids a live_host user from creating a tier schedule', function () {
    $regular = User::factory()->create(['role' => 'live_host']);

    actingAs($regular)
        ->post(
            "/livehost/hosts/{$this->host->id}/platforms/{$this->platform->id}/tiers",
            canonicalTiersPayload($this->platform->id),
        )
        ->assertForbidden();

    expect(LiveHostPlatformCommissionTier::query()->count())->toBe(0);
});

it('forbids a live_host user from updating a tier', function () {
    $regular = User::factory()->create(['role' => 'live_host']);
    $tier = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
    ]);

    actingAs($regular)
        ->patch("/livehost/hosts/{$this->host->id}/tiers/{$tier->id}", [
            'min_gmv_myr' => 0,
            'max_gmv_myr' => 30000,
            'internal_percent' => 5,
            'l1_percent' => 1,
            'l2_percent' => 2,
        ])
        ->assertForbidden();
});

it('forbids a live_host user from destroying a tier', function () {
    $regular = User::factory()->create(['role' => 'live_host']);
    $tier = LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->platform->id,
    ]);

    actingAs($regular)
        ->delete("/livehost/hosts/{$this->host->id}/tiers/{$tier->id}")
        ->assertForbidden();

    expect($tier->fresh()->is_active)->toBeTrue();
});
