<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createFunnelWithFullPageHtml(string $html): Funnel
{
    $funnel = Funnel::factory()->published()->create([
        'slug' => 'test-funnel',
        'settings' => [],
    ]);

    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Landing Page',
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
