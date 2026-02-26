<?php

use App\Models\FunnelAffiliate;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('affiliate can register with name and phone', function () {
    $response = postJson('/api/v1/affiliate/register', [
        'name' => 'Test Affiliate',
        'phone' => '0123456789',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('affiliate.name', 'Test Affiliate');
    $response->assertJsonPath('affiliate.phone', '+60123456789');

    expect(FunnelAffiliate::where('phone', '+60123456789')->exists())->toBeTrue();
});

test('affiliate registration requires name and phone', function () {
    $response = postJson('/api/v1/affiliate/register', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'phone']);
});

test('affiliate registration rejects duplicate phone', function () {
    FunnelAffiliate::factory()->create(['phone' => '+60123456789']);

    $response = postJson('/api/v1/affiliate/register', [
        'name' => 'Another Affiliate',
        'phone' => '0123456789',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['phone']);
});

test('affiliate can login with phone', function () {
    FunnelAffiliate::factory()->create(['phone' => '+60198765432']);

    $response = postJson('/api/v1/affiliate/login', [
        'phone' => '0198765432',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('affiliate.phone', '+60198765432');
});

test('affiliate login fails with unknown phone', function () {
    $response = postJson('/api/v1/affiliate/login', [
        'phone' => '0000000000',
    ]);

    $response->assertNotFound();
});

test('affiliate login fails when banned', function () {
    FunnelAffiliate::factory()->banned()->create(['phone' => '+60111111111']);

    $response = postJson('/api/v1/affiliate/login', [
        'phone' => '0111111111',
    ]);

    $response->assertForbidden();
});

test('authenticated affiliate can access profile', function () {
    $affiliate = FunnelAffiliate::factory()->create();

    postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    $meResponse = getJson('/api/v1/affiliate/me');
    $meResponse->assertSuccessful();
    $meResponse->assertJsonPath('affiliate.id', $affiliate->id);
});

test('unauthenticated request to protected route returns 401', function () {
    $response = getJson('/api/v1/affiliate/me');

    $response->assertUnauthorized();
});

test('affiliate can logout', function () {
    $affiliate = FunnelAffiliate::factory()->create();

    postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    postJson('/api/v1/affiliate/logout')->assertSuccessful();

    getJson('/api/v1/affiliate/me')->assertUnauthorized();
});

test('affiliate can update profile', function () {
    $affiliate = FunnelAffiliate::factory()->create();

    postJson('/api/v1/affiliate/login', [
        'phone' => $affiliate->phone,
    ])->assertSuccessful();

    $response = putJson('/api/v1/affiliate/me', [
        'name' => 'Updated Name',
        'phone' => $affiliate->phone,
        'email' => 'updated@example.com',
    ]);

    $response->assertSuccessful();

    $affiliate->refresh();
    expect($affiliate->name)->toBe('Updated Name');
    expect($affiliate->email)->toBe('updated@example.com');
});

test('affiliate gets auto-generated ref_code on creation', function () {
    $affiliate = FunnelAffiliate::factory()->create();

    expect($affiliate->ref_code)->not->toBeNull();
    expect(str_starts_with($affiliate->ref_code, 'AF'))->toBeTrue();
    expect(strlen($affiliate->ref_code))->toBe(8);
});

test('phone normalization works for various formats', function () {
    // Register with +60 prefix (already international)
    $response = postJson('/api/v1/affiliate/register', [
        'name' => 'Test +60',
        'phone' => '+60123456789',
    ]);
    $response->assertSuccessful();
    $response->assertJsonPath('affiliate.phone', '+60123456789');

    // Register with 60 prefix (no +)
    $response2 = postJson('/api/v1/affiliate/register', [
        'name' => 'Test 60',
        'phone' => '60198765432',
    ]);
    $response2->assertSuccessful();
    $response2->assertJsonPath('affiliate.phone', '+60198765432');

    // Register with Singapore +65 prefix
    $response3 = postJson('/api/v1/affiliate/register', [
        'name' => 'Test SG',
        'phone' => '+6591234567',
    ]);
    $response3->assertSuccessful();
    $response3->assertJsonPath('affiliate.phone', '+6591234567');

    // Register with Indonesia +62 prefix
    $response4 = postJson('/api/v1/affiliate/register', [
        'name' => 'Test ID',
        'phone' => '+628123456789',
    ]);
    $response4->assertSuccessful();
    $response4->assertJsonPath('affiliate.phone', '+628123456789');
});

test('affiliate can login with international phone number', function () {
    FunnelAffiliate::factory()->create(['phone' => '+6591234567']);

    $response = postJson('/api/v1/affiliate/login', [
        'phone' => '+6591234567',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('affiliate.phone', '+6591234567');
});
