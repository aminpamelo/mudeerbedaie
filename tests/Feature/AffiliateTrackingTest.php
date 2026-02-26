<?php

use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createFunnelWithStep(): Funnel
{
    $funnel = Funnel::factory()->affiliateEnabled()->published()->create();

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Landing',
        'slug' => 'landing',
        'type' => 'landing',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    FunnelStepContent::create([
        'funnel_step_id' => $step->id,
        'content' => ['root' => ['type' => 'Root', 'props' => []]],
        'is_published' => true,
        'published_at' => now(),
    ]);

    return $funnel;
}

test('affiliate ref code is stored via path-based URL', function () {
    $funnel = createFunnelWithStep();
    $affiliate = FunnelAffiliate::factory()->create();

    $affiliate->funnels()->attach($funnel->id, [
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $response = $this->get("/f/{$funnel->slug}/ref/{$affiliate->ref_code}");

    $response->assertSuccessful();
    $response->assertCookie("affiliate_ref_{$funnel->id}");
});

test('affiliate ref code is stored via query param', function () {
    $funnel = createFunnelWithStep();
    $affiliate = FunnelAffiliate::factory()->create();

    $affiliate->funnels()->attach($funnel->id, [
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $response = $this->get("/f/{$funnel->slug}?ref={$affiliate->ref_code}");

    $response->assertSuccessful();
    $response->assertCookie("affiliate_ref_{$funnel->id}");
});

test('affiliate can join a published funnel with affiliates enabled', function () {
    $affiliate = FunnelAffiliate::factory()->create();
    $funnel = Funnel::factory()->affiliateEnabled()->published()->create();

    $this->postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    $response = $this->postJson("/api/v1/affiliate/funnels/{$funnel->id}/join");

    $response->assertSuccessful();

    expect($affiliate->funnels()->where('funnel_id', $funnel->id)->exists())->toBeTrue();
});

test('affiliate can discover published funnels with affiliates enabled', function () {
    $affiliate = FunnelAffiliate::factory()->create();
    Funnel::factory()->affiliateEnabled()->published()->count(3)->create();
    Funnel::factory()->draft()->count(2)->create();

    $this->postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    $response = $this->getJson('/api/v1/affiliate/funnels/discover');

    $response->assertSuccessful();
    expect(count($response->json('funnels')))->toBe(3);
});

test('affiliate dashboard returns stats summary', function () {
    $affiliate = FunnelAffiliate::factory()->create();

    $this->postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    $response = $this->getJson('/api/v1/affiliate/dashboard');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'stats' => [
            'total_approved',
            'total_pending',
            'total_paid',
            'total_clicks',
            'total_conversions',
        ],
    ]);
});
