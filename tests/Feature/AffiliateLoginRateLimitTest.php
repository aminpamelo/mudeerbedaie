<?php

use App\Models\FunnelAffiliate;

use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('affiliate login is rate limited after repeated attempts', function () {
    // Five failed attempts for an unknown phone are allowed (each 404); the
    // sixth is throttled (429) to blunt phone-number enumeration / brute force.
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/v1/affiliate/login', ['phone' => '0111000111'])
            ->assertNotFound();
    }

    postJson('/api/v1/affiliate/login', ['phone' => '0111000111'])
        ->assertStatus(429);
});

test('login throttling is scoped per phone — a different phone is unaffected', function () {
    FunnelAffiliate::factory()->create(['phone' => '+60133000333']);

    // Exhaust the limit for one (unknown) phone.
    for ($i = 0; $i < 6; $i++) {
        postJson('/api/v1/affiliate/login', ['phone' => '0144000444']);
    }
    postJson('/api/v1/affiliate/login', ['phone' => '0144000444'])
        ->assertStatus(429);

    // A different phone is a separate throttle key and still logs in fine.
    postJson('/api/v1/affiliate/login', ['phone' => '0133000333'])
        ->assertSuccessful();
});
