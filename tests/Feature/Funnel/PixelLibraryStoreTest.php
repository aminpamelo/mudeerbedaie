<?php

declare(strict_types=1);

use App\Models\FunnelPixel;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('stores a facebook pixel with a long conversions api token', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $longToken = str_repeat('A', 1200); // Real Meta system-user/CAPI tokens can exceed 500 chars.

    actingAs($user)
        ->postJson('/api/v1/pixel-library', [
            'name' => 'Tafsir Surah',
            'platform' => 'facebook',
            'group_name' => null,
            'settings' => [
                'pixel_id' => '1833361654496026',
                'access_token' => $longToken,
                'test_event_code' => '',
            ],
        ])
        ->assertCreated();

    expect(FunnelPixel::where('name', 'Tafsir Surah')->first())
        ->settings->toMatchArray(['access_token' => $longToken]);
});

it('rejects a token longer than the allowed maximum', function () {
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson('/api/v1/pixel-library', [
            'name' => 'Too Long',
            'platform' => 'facebook',
            'settings' => [
                'pixel_id' => '1833361654496026',
                'access_token' => str_repeat('A', 2100),
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.access_token');
});
