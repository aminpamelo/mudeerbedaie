<?php

declare(strict_types=1);

use App\Services\Funnel\PuckRenderer;

beforeEach(function () {
    $this->renderer = new PuckRenderer;

    $this->render = function (string $html): string {
        $method = new ReflectionMethod(PuckRenderer::class, 'renderCustomHtml');

        return $method->invoke($this->renderer, ['html' => $html]);
    };
});

it('honors a navy body background declared with !important', function () {
    // Real-world pattern: pasted full document with body { background-color: #172554 !important; }
    $html = '<!DOCTYPE html><html><head><style>body{margin:0 !important;background-color:#172554 !important;/* biru gelap */}</style></head><body><h2>Soalan Lazim</h2></body></html>';

    $result = ($this->render)($html);

    expect($result)
        ->toContain('background-color: #172554')
        ->not->toContain('background-color: #ffffff');
});

it('honors a background shorthand that leads with a colour and !important', function () {
    $html = '<!DOCTYPE html><html><head><style>body{background:#0a0a0a !important;color:#ffffff}</style></head><body>x</body></html>';

    $result = ($this->render)($html);

    expect($result)
        ->toContain('background-color: #0a0a0a')
        ->toContain('color: #ffffff');
});

it('resolves a CSS variable background before stripping !important', function () {
    $html = '<!DOCTYPE html><html><head><style>:root{--ink:#0b1b3a}body{background:var(--ink) !important}</style></head><body>x</body></html>';

    expect(($this->render)($html))->toContain('background-color: #0b1b3a');
});

it('falls back to the default background for gradients and image-only bodies', function () {
    $gradient = '<!DOCTYPE html><html><head><style>body{background:linear-gradient(#111,#222) !important}</style></head><body>x</body></html>';
    $image = '<!DOCTYPE html><html><head><style>body{background:url(bg.png) center}</style></head><body>x</body></html>';

    // No usable background-color token → wrapper keeps the default white, does not grab "url" or "linear".
    expect(($this->render)($gradient))
        ->toContain('background-color: #ffffff')
        ->not->toContain('background-color: linear');

    expect(($this->render)($image))
        ->toContain('background-color: #ffffff')
        ->not->toContain('background-color: url');
});

it('still honors a plain body background without !important', function () {
    $html = '<!DOCTYPE html><html><head><style>body{background-color:#123456}</style></head><body>x</body></html>';

    expect(($this->render)($html))->toContain('background-color: #123456');
});

it('honors a body background rule in a fragment with no html/body tags', function () {
    // Real-world pattern (TAFSIR SURAH funnel): a pasted fragment that starts with
    // <title>/<style> — no <!doctype>, <html> or <body> tag — but its stylesheet still
    // sets the intended dark page background via a `body { ... }` rule + :root vars.
    $html = '<title>Soalan Lazim</title><style>:root{--ink:#121825;--text:#EDF0F8}'
        .'*{margin:0;box-sizing:border-box}html{scroll-behavior:smooth}'
        .'body{background:var(--ink);color:var(--text);font-size:19px}</style>'
        .'<section><h2>Sebelum anda bertanya...</h2></section>';

    expect(($this->render)($html))
        ->toContain('background-color: #121825')
        ->toContain('color: #EDF0F8')
        ->not->toContain('background-color: #ffffff');
});

it('keeps the default white for a fragment with no body style rule', function () {
    $html = '<section style="padding:40px"><h2>Hello</h2><p>Just a snippet.</p></section>';

    expect(($this->render)($html))->toContain('background-color: #ffffff');
});
