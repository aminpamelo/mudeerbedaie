<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function posUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

test('an unrelated role cannot reach the POS API', function () {
    actingAs(posUser('student'))
        ->getJson('/api/pos/sales-sources')
        ->assertForbidden();

    actingAs(posUser('teacher'))
        ->getJson('/api/pos/sales')
        ->assertForbidden();
});

test('a fighter can reach the POS catalog and create, but not the sales back-office', function () {
    $fighter = posUser('fighter');

    // Shared group: catalog + create are allowed for fighters.
    actingAs($fighter)->getJson('/api/pos/sales-sources')->assertOk();

    // The role gate must not block createSale (empty body -> validation 422, never 403).
    expect(actingAs($fighter)->postJson('/api/pos/sales', [])->getStatusCode())->not->toBe(403);

    // Restricted group: viewing/managing existing sales is back-office only.
    actingAs($fighter)->getJson('/api/pos/sales')->assertForbidden();
});

test('back-office roles can view the POS sales history', function () {
    actingAs(posUser('admin'))->getJson('/api/pos/sales')->assertOk();
    actingAs(posUser('employee'))->getJson('/api/pos/sales')->assertOk();
    actingAs(posUser('sales'))->getJson('/api/pos/sales')->assertOk();
});
