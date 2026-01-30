<?php

use App\Models\Funnel;
use App\Models\FunnelAffiliate;
use App\Models\FunnelProduct;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createEmbedFunnel(): Funnel
{
    $funnel = Funnel::factory()->affiliateEnabled()->published()->create([
        'embed_enabled' => true,
        'embed_key' => Str::random(32),
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'name' => 'Test Product',
        'funnel_price' => 100.00,
    ]);

    return $funnel;
}

test('embed checkout tracks affiliate via ref query param', function () {
    $funnel = createEmbedFunnel();
    $affiliate = FunnelAffiliate::factory()->create();

    $affiliate->funnels()->attach($funnel->id, [
        'status' => 'approved',
        'joined_at' => now(),
    ]);

    $response = $this->get("/embed/{$funnel->embed_key}?ref={$affiliate->ref_code}");

    $response->assertSuccessful();

    $session = FunnelSession::where('funnel_id', $funnel->id)->latest()->first();
    expect($session)->not->toBeNull();
    expect($session->affiliate_id)->toBe($affiliate->id);
});

test('embed checkout creates session without affiliate when no ref', function () {
    $funnel = createEmbedFunnel();

    $response = $this->get("/embed/{$funnel->embed_key}");

    $response->assertSuccessful();

    $session = FunnelSession::where('funnel_id', $funnel->id)->latest()->first();
    expect($session)->not->toBeNull();
    expect($session->affiliate_id)->toBeNull();
});

test('embed checkout ignores invalid ref code', function () {
    $funnel = createEmbedFunnel();

    $response = $this->get("/embed/{$funnel->embed_key}?ref=INVALID123");

    $response->assertSuccessful();

    $session = FunnelSession::where('funnel_id', $funnel->id)->latest()->first();
    expect($session)->not->toBeNull();
    expect($session->affiliate_id)->toBeNull();
});

test('embed checkout ignores ref code from unapproved affiliate', function () {
    $funnel = createEmbedFunnel();
    $affiliate = FunnelAffiliate::factory()->create();

    // Affiliate not joined to funnel
    $response = $this->get("/embed/{$funnel->embed_key}?ref={$affiliate->ref_code}");

    $response->assertSuccessful();

    $session = FunnelSession::where('funnel_id', $funnel->id)->latest()->first();
    expect($session)->not->toBeNull();
    expect($session->affiliate_id)->toBeNull();
});
