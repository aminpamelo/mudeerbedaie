<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelStudioServer;
use App\Mcp\Tools\AddFunnelProductTool;
use App\Mcp\Tools\AddFunnelStepTool;
use App\Mcp\Tools\ConfigureAffiliatesTool;
use App\Mcp\Tools\ConfigurePaymentTool;
use App\Mcp\Tools\ConfigureTrackingTool;
use App\Mcp\Tools\CreateFunnelAutomationTool;
use App\Mcp\Tools\DeleteFunnelStepTool;
use App\Mcp\Tools\FunnelOrdersTool;
use App\Mcp\Tools\GetFunnelEmbedTool;
use App\Mcp\Tools\ToggleFunnelAutomationTool;
use App\Mcp\Tools\UpdateFunnelSettingsTool;
use App\Models\Funnel;
use App\Models\FunnelAutomation;
use App\Models\FunnelOrder;
use App\Models\Product;
use App\Models\User;

/** Create a funnel owned by the user with one landing step. */
function funnelWithStep(User $user): array
{
    $funnel = Funnel::factory()->create(['user_id' => $user->id, 'status' => 'published']);
    $step = $funnel->steps()->create(['name' => 'Landing', 'slug' => 'landing', 'type' => 'landing', 'sort_order' => 0, 'is_active' => true]);

    return [$funnel, $step];
}

it('adds a step to a funnel', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(AddFunnelStepTool::class, ['funnel_uuid' => $funnel->uuid, 'name' => 'Upsell', 'type' => 'upsell'])
        ->assertOk()
        ->assertSee('Upsell');

    expect($funnel->steps()->where('type', 'upsell')->exists())->toBeTrue();
});

it('will not delete a funnel\'s only step', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel, $step] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(DeleteFunnelStepTool::class, ['funnel_uuid' => $funnel->uuid, 'step_id' => $step->id])
        ->assertHasErrors();

    expect($funnel->steps()->count())->toBe(1);
});

it('assigns an existing catalog product to a step', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel, $step] = funnelWithStep($user);
    $product = Product::factory()->create(['status' => 'active', 'name' => 'Real Book']);

    FunnelStudioServer::actingAs($user)
        ->tool(AddFunnelProductTool::class, [
            'funnel_uuid' => $funnel->uuid, 'step_id' => $step->id,
            'price' => 49, 'product_id' => $product->id, 'type' => 'main',
        ])
        ->assertOk();

    $fp = $step->products()->first();
    expect($fp->product_id)->toBe($product->id);
    expect((float) $fp->funnel_price)->toBe(49.0);
});

it('configures payment methods', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(ConfigurePaymentTool::class, [
            'funnel_uuid' => $funnel->uuid,
            'methods' => ['stripe', 'bayarcash_fpx', 'cod'],
            'default_method' => 'stripe',
        ])
        ->assertOk();

    $ps = $funnel->fresh()->payment_settings;
    expect($ps['enabled_methods'])->toContain('stripe', 'bayarcash_fpx', 'cod');
    expect($ps['stripe_enabled'])->toBeTrue();
    expect($ps['default_method'])->toBe('stripe');
});

it('sets a facebook pixel via configure_tracking', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(ConfigureTrackingTool::class, ['funnel_uuid' => $funnel->uuid, 'facebook_pixel_id' => '1234567890'])
        ->assertOk();

    expect(data_get($funnel->fresh()->settings, 'pixel_settings.facebook.pixel_id'))->toBe('1234567890');
    expect(data_get($funnel->fresh()->settings, 'pixel_settings.facebook.enabled'))->toBeTrue();
});

it('updates funnel settings including a unique slug guard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);
    $other = Funnel::factory()->create(['user_id' => $user->id, 'slug' => 'taken-slug']);

    FunnelStudioServer::actingAs($user)
        ->tool(UpdateFunnelSettingsTool::class, ['funnel_uuid' => $funnel->uuid, 'name' => 'Renamed', 'slug' => 'brand-new'])
        ->assertOk();
    expect($funnel->fresh()->slug)->toBe('brand-new');

    FunnelStudioServer::actingAs($user)
        ->tool(UpdateFunnelSettingsTool::class, ['funnel_uuid' => $funnel->uuid, 'slug' => 'taken-slug'])
        ->assertHasErrors();
});

it('creates and toggles an automation', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(CreateFunnelAutomationTool::class, [
            'funnel_uuid' => $funnel->uuid, 'name' => 'Thank you', 'trigger' => 'purchase',
            'action' => 'send_whatsapp', 'message' => 'Terima kasih!',
        ])
        ->assertOk();

    $automation = FunnelAutomation::where('funnel_id', $funnel->id)->first();
    expect($automation)->not->toBeNull();
    expect($automation->actions()->count())->toBe(1);

    FunnelStudioServer::actingAs($user)
        ->tool(ToggleFunnelAutomationTool::class, ['funnel_uuid' => $funnel->uuid, 'automation_id' => $automation->id, 'active' => false])
        ->assertOk();
    expect((bool) $automation->fresh()->is_active)->toBeFalse();
});

it('enables affiliates with a commission rule per product', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel, $step] = funnelWithStep($user);
    $step->products()->create(['type' => 'main', 'name' => 'X', 'funnel_price' => 50, 'is_active' => true, 'sort_order' => 0]);

    FunnelStudioServer::actingAs($user)
        ->tool(ConfigureAffiliatesTool::class, [
            'funnel_uuid' => $funnel->uuid, 'enabled' => true,
            'commission_type' => 'percentage', 'commission_value' => 10,
        ])
        ->assertOk();

    expect((bool) $funnel->fresh()->affiliate_enabled)->toBeTrue();
    expect($funnel->affiliateCommissionRules()->count())->toBe(1);
});

it('reads a funnel\'s orders', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel, $step] = funnelWithStep($user);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'step_id' => $step->id, 'funnel_revenue' => 88, 'order_type' => 'main']);

    FunnelStudioServer::actingAs($user)
        ->tool(FunnelOrdersTool::class, ['funnel_uuid' => $funnel->uuid])
        ->assertOk()
        ->assertSee('88');
});

it('enables embedding and returns the code', function () {
    $user = User::factory()->create(['role' => 'admin']);
    [$funnel] = funnelWithStep($user);

    FunnelStudioServer::actingAs($user)
        ->tool(GetFunnelEmbedTool::class, ['funnel_uuid' => $funnel->uuid])
        ->assertOk()
        ->assertSee('iframe');

    expect((bool) $funnel->fresh()->embed_enabled)->toBeTrue();
    expect($funnel->fresh()->embed_key)->not->toBeEmpty();
});

it('will not let a fighter manage a funnel they do not own', function () {
    $me = User::factory()->create(['role' => 'fighter']);
    $other = User::factory()->create(['role' => 'fighter']);
    $funnel = Funnel::factory()->create(['user_id' => $other->id]);

    FunnelStudioServer::actingAs($me)
        ->tool(ConfigurePaymentTool::class, ['funnel_uuid' => $funnel->uuid, 'methods' => ['stripe']])
        ->assertHasErrors();
});
