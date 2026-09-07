<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelStudioServer;
use App\Mcp\Tools\CreateLandingPageTool;
use App\Mcp\Tools\ListProductsTool;
use App\Mcp\Tools\PublishFunnelTool;
use App\Mcp\Tools\UpdateLandingPageTool;
use App\Models\Funnel;
use App\Models\Product;
use App\Models\User;

function landingHtml(Funnel $funnel): string
{
    return $funnel->steps()->where('type', 'landing')->first()
        ->content()->first()->content['content'][0]['props']['html'];
}

it('creates a draft selling funnel with checkout-ready content and a product', function () {
    $user = User::factory()->create(['role' => 'admin']);

    FunnelStudioServer::actingAs($user)
        ->tool(CreateLandingPageTool::class, [
            'name' => 'Qada Solat Offer',
            'html' => '<h1>Buy my book</h1>',
            'price' => 49,
            'product_name' => 'Qada Solat Book',
        ])
        ->assertOk()
        ->assertSee('draft')
        ->assertSee('Qada Solat Offer');

    $funnel = Funnel::where('user_id', $user->id)->first();
    expect($funnel)->not->toBeNull();
    expect($funnel->status)->toBe('draft');

    $step = $funnel->steps()->where('type', 'landing')->first();
    expect($step)->not->toBeNull();

    $block = $step->content()->first()->content['content'][0];
    expect($block['type'])->toBe('CustomHtml');
    expect($block['props']['html'])->toContain('Buy my book');
    expect($block['props']['html'])->toContain('[checkout_form]'); // auto-appended

    $product = $step->products()->where('type', 'main')->first();
    expect($product)->not->toBeNull();
    expect((float) $product->funnel_price)->toBe(49.0);
    expect($product->name)->toBe('Qada Solat Book');
    expect((bool) $product->is_active)->toBeTrue();
});

it('publishes immediately when publish=true and returns a public url', function () {
    $user = User::factory()->create(['role' => 'admin']);

    FunnelStudioServer::actingAs($user)
        ->tool(CreateLandingPageTool::class, [
            'name' => 'Live Page',
            'html' => '<h1>Hi</h1>[checkout_form]',
            'price' => 10,
            'publish' => true,
        ])
        ->assertOk()
        ->assertSee('published');

    $funnel = Funnel::where('user_id', $user->id)->first();
    expect($funnel->status)->toBe('published');
    expect($funnel->published_at)->not->toBeNull();
});

it('does not duplicate a checkout tag the author already included', function () {
    $user = User::factory()->create(['role' => 'admin']);

    FunnelStudioServer::actingAs($user)
        ->tool(CreateLandingPageTool::class, [
            'name' => 'Tagged',
            'html' => '<h1>Hi</h1>[checkout_form]<footer>x</footer>',
            'price' => 5,
        ])
        ->assertOk();

    $html = landingHtml(Funnel::where('user_id', $user->id)->first());
    expect(substr_count(strtolower($html), '[checkout_form]'))->toBe(1);
});

it('sells an existing catalog product by id', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Real Product', 'status' => 'active', 'base_price' => 120]);

    FunnelStudioServer::actingAs($user)
        ->tool(CreateLandingPageTool::class, [
            'name' => 'Sells Real Product',
            'html' => '<h1>x</h1>[checkout_form]',
            'price' => 99,
            'product_id' => $product->id,
        ])
        ->assertOk();

    $main = Funnel::where('user_id', $user->id)->first()
        ->steps()->first()->products()->where('type', 'main')->first();
    expect($main->product_id)->toBe($product->id);
    expect((float) $main->funnel_price)->toBe(99.0);
});

it('rejects a catalog product a fighter is not allowed to sell', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $otherFighter = User::factory()->create(['role' => 'fighter']);
    $private = Product::factory()->create(['status' => 'active', 'created_by_fighter_id' => $otherFighter->id]);

    FunnelStudioServer::actingAs($fighter)
        ->tool(CreateLandingPageTool::class, [
            'name' => 'Sneaky',
            'html' => '<h1>x</h1>',
            'price' => 9,
            'product_id' => $private->id,
        ])
        ->assertHasErrors();

    expect(Funnel::where('user_id', $fighter->id)->count())->toBe(0);
});

it('updates the landing page html and price', function () {
    $user = User::factory()->create(['role' => 'admin']);
    FunnelStudioServer::actingAs($user)->tool(CreateLandingPageTool::class, [
        'name' => 'Editable', 'html' => '<h1>Old headline</h1>', 'price' => 20, 'product_name' => 'Thing',
    ])->assertOk();
    $funnel = Funnel::where('user_id', $user->id)->first();

    FunnelStudioServer::actingAs($user)->tool(UpdateLandingPageTool::class, [
        'funnel_uuid' => $funnel->uuid, 'html' => '<h1>New headline</h1>', 'price' => 35,
    ])->assertOk();

    expect(landingHtml($funnel->fresh()))->toContain('New headline');
    expect((float) $funnel->steps()->first()->products()->where('type', 'main')->first()->funnel_price)->toBe(35.0);
});

it('will not let a fighter update a funnel they do not own', function () {
    $me = User::factory()->create(['role' => 'fighter']);
    $other = User::factory()->create(['role' => 'fighter']);
    $funnel = Funnel::factory()->create(['user_id' => $other->id]);

    FunnelStudioServer::actingAs($me)->tool(UpdateLandingPageTool::class, [
        'funnel_uuid' => $funnel->uuid, 'html' => '<h1>hax</h1>',
    ])->assertHasErrors();
});

it('publishes and unpublishes a funnel', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $funnel = Funnel::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

    FunnelStudioServer::actingAs($user)
        ->tool(PublishFunnelTool::class, ['funnel_uuid' => $funnel->uuid])
        ->assertOk()
        ->assertSee($funnel->slug);
    expect($funnel->fresh()->status)->toBe('published');

    FunnelStudioServer::actingAs($user)
        ->tool(PublishFunnelTool::class, ['funnel_uuid' => $funnel->uuid, 'unpublish' => true])
        ->assertOk();
    expect($funnel->fresh()->status)->toBe('draft');
});

it('lists only sellable (active) products', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Sellable Widget', 'status' => 'active']);
    Product::factory()->create(['name' => 'Draft Widget', 'status' => 'draft']);

    FunnelStudioServer::actingAs($user)
        ->tool(ListProductsTool::class, [])
        ->assertOk()
        ->assertSee('Sellable Widget')
        ->assertDontSee('Draft Widget');
});
