<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFunnelWithFullPageHtml(string $html, string $type = 'landing'): Funnel
{
    $funnel = Funnel::factory()->published()->create([
        'slug' => 'test-funnel',
        'settings' => [],
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Landing Page',
        'slug' => 'landing',
        'type' => $type,
        'sort_order' => 0,
        'is_active' => true,
        'settings' => [],
    ]);

    FunnelStepContent::create([
        'funnel_step_id' => $step->id,
        'content' => [
            'content' => [
                [
                    'type' => 'TextBlock',
                    'props' => ['content' => $html],
                ],
            ],
        ],
        'is_published' => true,
        'published_at' => now(),
        'version' => 1,
    ]);

    return $funnel;
}

it('renders full HTML page directly without stripping scripts', function () {
    $html = '<!DOCTYPE html><html lang="ms"><head><script src="https://cdn.tailwindcss.com"></script></head><body><p>Hello World</p><script>console.log("test")</script></body></html>';

    createFunnelWithFullPageHtml($html);

    $response = $this->get('/f/test-funnel');

    $response->assertSuccessful();
    $content = $response->getContent();

    expect($content)
        ->toContain('cdn.tailwindcss.com')
        ->toContain('<p>Hello World</p>')
        ->toContain('console.log("test")')
        ->toContain('window.funnelConfig');
});

it('preserves external CSS and font links in full HTML pages', function () {
    $html = '<!DOCTYPE html><html><head><link href="https://fonts.googleapis.com/css2?family=Poppins" rel="stylesheet"></head><body><p>Styled</p></body></html>';

    createFunnelWithFullPageHtml($html);

    $response = $this->get('/f/test-funnel');

    $response->assertSuccessful();
    expect($response->getContent())
        ->toContain('fonts.googleapis.com')
        ->toContain('<p>Styled</p>');
});

it('injects funnel config script into full HTML pages', function () {
    $html = '<!DOCTYPE html><html><head></head><body><p>Config test</p></body></html>';

    $funnel = createFunnelWithFullPageHtml($html);

    $response = $this->get('/f/test-funnel');

    $response->assertSuccessful();
    $content = $response->getContent();

    expect($content)
        ->toContain('window.funnelConfig')
        ->toContain("funnelSlug: 'test-funnel'")
        ->toContain("stepType: 'landing'");
});

it('does not use full page rendering for regular Puck content', function () {
    $funnel = Funnel::factory()->published()->create([
        'slug' => 'regular-funnel',
        'settings' => [],
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Regular Page',
        'slug' => 'landing',
        'type' => 'landing',
        'sort_order' => 0,
        'is_active' => true,
        'settings' => [],
    ]);

    FunnelStepContent::create([
        'funnel_step_id' => $step->id,
        'content' => [
            'content' => [
                [
                    'type' => 'HeroSection',
                    'props' => ['title' => 'Welcome', 'subtitle' => 'Hello'],
                ],
            ],
        ],
        'is_published' => true,
        'published_at' => now(),
        'version' => 1,
    ]);

    $response = $this->get('/f/regular-funnel');

    $response->assertSuccessful();
    $content = $response->getContent();

    // Regular Puck content should render through the Blade template
    expect($content)
        ->toContain('puck-hero')
        ->toContain('Welcome');
});

it('preserves inline styles in full HTML pages', function () {
    $html = '<!DOCTYPE html><html><head><style>body { background: #F8F1EA; } .hero { color: gold; }</style></head><body><div class="hero">Styled</div></body></html>';

    createFunnelWithFullPageHtml($html);

    $response = $this->get('/f/test-funnel');

    $response->assertSuccessful();
    expect($response->getContent())
        ->toContain('background: #F8F1EA')
        ->toContain('.hero { color: gold; }')
        ->toContain('<div class="hero">Styled</div>');
});

/*
|--------------------------------------------------------------------------
| [checkout_form] tag → isolated checkout <iframe>
|--------------------------------------------------------------------------
| Full-page HTML pages can't host the Livewire checkout inline, so the tag is
| swapped for an isolated same-origin frame that renders the real form, and the
| parent page gets the resize + post-purchase redirect listener.
*/

it('replaces the [checkout_form] tag in full-page HTML with the checkout frame', function () {
    $html = '<!DOCTYPE html><html><head></head><body><h1>Offer</h1><p>[checkout_form]</p></body></html>';

    $funnel = createFunnelWithFullPageHtml($html, 'checkout');

    $content = $this->get('/f/test-funnel')->assertSuccessful()->getContent();

    expect($content)
        ->not->toContain('[checkout_form]')
        ->toContain('id="funnel-checkout-frame"')
        ->toContain('/f/test-funnel/landing/checkout-frame?session_uuid=')
        ->toContain('funnel-checkout-frame-redirect')
        ->toContain('funnel-checkout-frame-resize')
        ->toContain('<h1>Offer</h1>');
});

it('replaces the space-separated [checkout form] variant too', function () {
    $html = '<!DOCTYPE html><html><head></head><body>[checkout form]</body></html>';

    createFunnelWithFullPageHtml($html, 'checkout');

    $content = $this->get('/f/test-funnel')->assertSuccessful()->getContent();

    expect($content)
        ->not->toContain('[checkout form]')
        ->toContain('id="funnel-checkout-frame"');
});

it('appends the checkout frame on a checkout step that has no tag', function () {
    $html = '<!DOCTYPE html><html><head></head><body><p>No tag here</p></body></html>';

    createFunnelWithFullPageHtml($html, 'checkout');

    $content = $this->get('/f/test-funnel')->assertSuccessful()->getContent();

    expect($content)
        ->toContain('<p>No tag here</p>')
        ->toContain('id="funnel-checkout-frame"');
});

it('does not inject a checkout frame on a non-checkout page without the tag', function () {
    $html = '<!DOCTYPE html><html><head></head><body><p>Just a landing page</p></body></html>';

    createFunnelWithFullPageHtml($html, 'landing');

    $content = $this->get('/f/test-funnel')->assertSuccessful()->getContent();

    expect($content)->not->toContain('funnel-checkout-frame');
});

it('serves the checkout-frame route with the live checkout form', function () {
    $funnel = createFunnelWithFullPageHtml(
        '<!DOCTYPE html><html><head></head><body>[checkout_form]</body></html>',
        'checkout'
    );

    $content = $this->get("/f/{$funnel->slug}/landing/checkout-frame")
        ->assertSuccessful()
        ->getContent();

    expect($content)
        ->toContain('window.funnelConfig')
        ->toContain('funnel-checkout-frame-resize')
        ->toContain('wire:id'); // the Livewire checkout component mounted
});
