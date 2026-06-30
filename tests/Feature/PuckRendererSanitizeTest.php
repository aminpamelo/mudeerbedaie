<?php

declare(strict_types=1);

use App\Services\Funnel\PuckRenderer;

beforeEach(function () {
    $this->renderer = new PuckRenderer;

    $this->sanitize = function (string $content): string {
        $method = new ReflectionMethod(PuckRenderer::class, 'sanitizeFullHtmlDocument');

        return $method->invoke($this->renderer, $content);
    };
});

it('returns content unchanged when not a full HTML document', function () {
    $content = '<p>Simple paragraph</p>';

    $result = ($this->sanitize)($content);

    expect($result)->toBe($content);
});

it('strips DOCTYPE, html, head, and body tags from full HTML documents', function () {
    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello</p></body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->not->toContain('<!DOCTYPE')
        ->not->toContain('<html')
        ->not->toContain('<head')
        ->not->toContain('<body')
        ->toContain('<p>Hello</p>');
});

it('wraps extracted content in a scoped container div', function () {
    $html = '<!DOCTYPE html><html><body><p>Content</p></body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->toContain('class="puck-scoped-')
        ->toContain('style="transform: translateZ(0); overflow: hidden; position: relative;"');
});

it('removes script tags from body content', function () {
    $html = '<!DOCTYPE html><html><body><p>Hello</p><script>alert("xss")</script><script src="framework.js"></script></body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->toContain('<p>Hello</p>')
        ->not->toContain('<script')
        ->not->toContain('alert')
        ->not->toContain('framework.js');
});

it('preserves the Tailwind CDN loader and inline config while stripping other scripts', function () {
    $html = '<!DOCTYPE html><html><head>'
        .'<script src="https://cdn.tailwindcss.com"></script>'
        .'<script>tailwind.config = { theme: { extend: { colors: { maroon: { 700: "#881337" } } } } }</script>'
        .'</head><body><p>Hello</p>'
        .'<script>alert("xss")</script>'
        .'<script src="https://example.com/framework.js"></script>'
        .'</body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->toContain('cdn.tailwindcss.com')
        ->toContain('tailwind.config')
        ->toContain('#881337')
        ->toContain('<p>Hello</p>')
        ->not->toContain('alert("xss")')
        ->not->toContain('framework.js');
});

it('keeps custom theme colors working through the render method for multi-component funnels', function () {
    $puckContent = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<!DOCTYPE html><html><head>'
                        .'<script src="https://cdn.tailwindcss.com"></script>'
                        .'<script>tailwind.config = { theme: { extend: { colors: { maroon: { 700: "#881337" } }, animation: { "pulse-slow": "pulse 2.5s infinite" } } } }</script>'
                        .'</head><body><a class="bg-maroon-700 animate-pulse-slow">CTA</a>'
                        .'<script>console.log("tracking")</script>'
                        .'</body></html>',
                ],
            ],
            [
                'type' => 'CheckoutForm',
                'props' => [],
            ],
        ],
    ];

    $result = (string) $this->renderer->render($puckContent);

    expect($result)
        ->toContain('cdn.tailwindcss.com')
        ->toContain('pulse-slow')
        ->toContain('class="bg-maroon-700 animate-pulse-slow"')
        ->not->toContain('console.log("tracking")');
});

it('scopes CSS selectors with container class', function () {
    $html = '<!DOCTYPE html><html><head><style>body { color: red; } .test { font-size: 14px; }</style></head><body><p>Styled</p></body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->toContain('<style>')
        ->toContain('.puck-scoped-')
        ->toContain('<p>Styled</p>');
});

it('handles html tag without DOCTYPE', function () {
    $html = '<html><body><p>No doctype</p></body></html>';

    $result = ($this->sanitize)($html);

    expect($result)
        ->toContain('<p>No doctype</p>')
        ->toContain('class="puck-scoped-')
        ->not->toContain('<html');
});

it('renders TextBlock with full HTML document through the render method', function () {
    $puckContent = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<!DOCTYPE html><html><head><style>.btn { color: blue; }</style></head><body><div class="btn">Click</div><script>var x=1;</script></body></html>',
                ],
            ],
        ],
    ];

    $result = $this->renderer->render($puckContent);

    expect((string) $result)
        ->toContain('class="puck-scoped-')
        ->toContain('<div class="btn">Click</div>')
        ->not->toContain('<script')
        ->not->toContain('var x=1')
        ->toContain('transform: translateZ(0)');
});

it('detects full page HTML when single TextBlock has DOCTYPE', function () {
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<!DOCTYPE html><html><head></head><body><p>Full page</p></body></html>',
                ],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeTrue();
});

it('detects full page HTML when single TextBlock starts with html tag', function () {
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<html lang="en"><head></head><body><p>Full page</p></body></html>',
                ],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeTrue();
});

it('does not detect full page HTML when multiple components exist', function () {
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<!DOCTYPE html><html><body><p>Page</p></body></html>',
                ],
            ],
            [
                'type' => 'ButtonBlock',
                'props' => ['text' => 'Click'],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeFalse();
});

it('does not detect full page HTML for regular TextBlock content', function () {
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => [
                    'content' => '<p>Just a paragraph</p>',
                ],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeFalse();
});

it('does not detect full page HTML when component is not TextBlock', function () {
    $content = [
        'content' => [
            [
                'type' => 'HeroSection',
                'props' => [
                    'title' => '<!DOCTYPE html>',
                ],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeFalse();
});

it('extracts raw HTML from full page content', function () {
    $fullHtml = '<!DOCTYPE html><html><head><script src="https://cdn.tailwindcss.com"></script></head><body><p>Hello</p><script>alert(1)</script></body></html>';
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => ['content' => $fullHtml],
            ],
        ],
    ];

    $result = $this->renderer->extractFullPageHtml($content);

    expect($result)
        ->toBe($fullHtml)
        ->toContain('cdn.tailwindcss.com')
        ->toContain('<script>alert(1)</script>');
});

it('injects tracking scripts before closing body tag', function () {
    $fullHtml = '<!DOCTYPE html><html><body><p>Page</p></body></html>';
    $content = [
        'content' => [
            [
                'type' => 'TextBlock',
                'props' => ['content' => $fullHtml],
            ],
        ],
    ];

    $result = $this->renderer->extractFullPageHtml($content, [
        '<script>window.funnelConfig = {};</script>',
    ]);

    expect($result)
        ->toContain('window.funnelConfig')
        ->toContain('<script>window.funnelConfig = {};</script>')
        ->toContain('</body>');
});

/*
|--------------------------------------------------------------------------
| Custom HTML block
|--------------------------------------------------------------------------
| Unlike TextBlock, the Custom HTML block renders the author's markup
| verbatim — scripts and styles are intentionally preserved so embeds and
| tracking pixels work on the published page.
*/

it('renders CustomHtml verbatim through the render method, preserving scripts and styles', function () {
    $puckContent = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => [
                    'html' => '<style>.x{color:red}</style><div class="x">Hi</div><script>track();</script>',
                    'maxWidth' => '800px',
                    'align' => 'center',
                    'padding' => '20px',
                ],
            ],
        ],
    ];

    $result = (string) $this->renderer->render($puckContent);

    expect($result)
        ->toContain('class="puck-custom-html"')
        ->toContain('<style>.x{color:red}</style>')
        ->toContain('<div class="x">Hi</div>')
        ->toContain('<script>track();</script>')
        ->toContain('max-width: 800px')
        ->toContain('margin: 0 auto')
        ->toContain('padding: 20px');
});

it('renders CustomHtml when nested alongside another component (not the sole block)', function () {
    $puckContent = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => '<div>embed here</div>'],
            ],
            [
                'type' => 'ButtonBlock',
                'props' => ['text' => 'Click'],
            ],
        ],
    ];

    $result = (string) $this->renderer->render($puckContent);

    expect($result)
        ->toContain('class="puck-custom-html"')
        ->toContain('<div>embed here</div>')
        ->toContain('puck-button');
});

it('suppresses output for a blank CustomHtml block', function () {
    $puckContent = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => '   '],
            ],
        ],
    ];

    expect((string) $this->renderer->render($puckContent))
        ->not->toContain('puck-custom-html');
});

it('maps CustomHtml alignment to the same margins as the editor preview', function () {
    $render = function (string $align): string {
        return (string) $this->renderer->render([
            'content' => [[
                'type' => 'CustomHtml',
                'props' => ['html' => '<div>a</div>', 'maxWidth' => '600px', 'align' => $align],
            ]],
        ]);
    };

    expect($render('left'))->toContain('margin: 0 auto 0 0');
    expect($render('center'))->toContain('margin: 0 auto;');
    expect($render('right'))->toContain('margin: 0 0 0 auto');
});

it('detects full page HTML when a single CustomHtml has a DOCTYPE', function () {
    $content = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => '<!DOCTYPE html><html><head></head><body><p>Full page</p></body></html>'],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeTrue();
});

it('does not detect full page HTML for a CustomHtml fragment', function () {
    $content = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => '<div>hello</div>'],
            ],
        ],
    ];

    expect($this->renderer->isFullPageHtml($content))->toBeFalse();
});

it('extracts and preserves raw HTML from a full-page CustomHtml block', function () {
    $fullHtml = '<!DOCTYPE html><html><head><script src="https://cdn.tailwindcss.com"></script></head><body><p>Hello</p><script>alert(1)</script></body></html>';
    $content = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => $fullHtml],
            ],
        ],
    ];

    expect($this->renderer->extractFullPageHtml($content))
        ->toBe($fullHtml)
        ->toContain('cdn.tailwindcss.com')
        ->toContain('<script>alert(1)</script>');
});

it('injects tracking scripts before </body> for a full-page CustomHtml block', function () {
    $content = [
        'content' => [
            [
                'type' => 'CustomHtml',
                'props' => ['html' => '<!DOCTYPE html><html><body><p>Page</p></body></html>'],
            ],
        ],
    ];

    expect($this->renderer->extractFullPageHtml($content, ['<script>window.funnelConfig = {};</script>']))
        ->toContain('<script>window.funnelConfig = {};</script>')
        ->toContain('</body>');
});
