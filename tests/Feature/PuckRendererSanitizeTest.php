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
