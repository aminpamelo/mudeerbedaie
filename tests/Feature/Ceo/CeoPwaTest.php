<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CEO PWA manifest', function () {
    it('serves a valid web app manifest publicly', function () {
        $this->get('/ceo/manifest.json')
            ->assertOk()
            ->assertJson([
                'short_name' => 'CEO',
                'start_url' => '/ceo',
                'scope' => '/ceo',
                'display' => 'standalone',
            ])
            ->assertJsonStructure([
                'name',
                'theme_color',
                'background_color',
                'icons' => [['src', 'sizes', 'type', 'purpose']],
            ]);
    });

    it('points the manifest icons at existing files', function () {
        $icons = $this->get('/ceo/manifest.json')->json('icons');

        foreach ($icons as $icon) {
            expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
        }
    });
});

describe('CEO PWA assets', function () {
    it('ships a service worker scoped to the CEO app', function () {
        $path = public_path('ceo-sw.js');

        expect($path)->toBeFile();
        expect(file_get_contents($path))->toContain('mudeer-ceo-v1');
    });

    it('includes PWA meta tags and the manifest link on the CEO page', function () {
        $ceo = User::factory()->create(['role' => 'ceo']);

        $this->actingAs($ceo)
            ->get('/ceo')
            ->assertOk()
            ->assertSee('/ceo/manifest.json', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('#6366F1', false);
    });
});
