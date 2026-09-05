<?php

use function Pest\Laravel\post;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('bayarcash callback route is exempt from CSRF so real server-to-server posts are not 419d', function () {
    // Bayarcash posts server-to-server with no Laravel session/CSRF token. The
    // route must not 419 (which would prevent orders from ever settling); an
    // unsigned/empty payload is rejected at the signature check (400) instead.
    $response = post('/bayarcash/callback', [
        'order_number' => 'PO-DOES-NOT-EXIST',
        'status' => '3',
    ]);

    expect($response->getStatusCode())->not->toBe(419);
    $response->assertStatus(400);
});
