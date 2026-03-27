<?php

use App\Models\User;

test('manifest.json exists in public directory with required PWA fields', function () {
    $manifestPath = public_path('manifest.json');

    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect($manifest)
        ->toHaveKey('name', 'Mudeer HR')
        ->toHaveKey('short_name', 'HR')
        ->toHaveKey('start_url', '/hr/clock')
        ->toHaveKey('display', 'standalone')
        ->toHaveKey('theme_color', '#1e40af')
        ->toHaveKey('icons');

    expect($manifest['icons'])->toHaveCount(2);
});

test('service worker exists in public directory', function () {
    $swPath = public_path('sw.js');

    expect(file_exists($swPath))->toBeTrue();

    $content = file_get_contents($swPath);

    expect($content)
        ->toContain('mudeer-hr-v1')
        ->toContain('clock-sync')
        ->toContain('pending-clocks');
});

test('hr page includes PWA meta tags', function () {
    $user = User::factory()->create(['role' => 'employee']);

    $response = $this->actingAs($user)->get('/hr');

    $response->assertSuccessful();
    $response->assertSee('rel="manifest"', false);
    $response->assertSee('name="theme-color"', false);
    $response->assertSee('name="apple-mobile-web-app-capable"', false);
    $response->assertSee('apple-touch-icon', false);
});
