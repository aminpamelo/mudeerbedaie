<?php

declare(strict_types=1);

use App\Models\User;

it('generates an mcp connection token and returns it once', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/v1/studio/mcp-tokens', ['name' => 'My Claude']);

    $response->assertCreated();
    expect($response->json('data.plain_text_token'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.name'))->toBe('My Claude');

    $token = $user->tokens()->first();
    expect($token->name)->toBe('My Claude');
    expect($token->abilities)->toContain('mcp:use');
});

it('lists only mcp tokens with the server url', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $user->createToken('AI One', ['mcp:use']);
    $user->createToken('Some Other Token', ['*']); // not an MCP token

    $response = $this->actingAs($user)->getJson('/api/v1/studio/mcp-tokens');

    $response->assertOk();
    expect($response->json('data.server_url'))->toContain('/mcp/funnel-studio');
    $names = collect($response->json('data.tokens'))->pluck('name');
    expect($names)->toContain('AI One');
    expect($names)->not->toContain('Some Other Token');
});

it('revokes an mcp token', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = $user->createToken('Revoke Me', ['mcp:use']);
    $id = $token->accessToken->getKey();

    $this->actingAs($user)->deleteJson("/api/v1/studio/mcp-tokens/{$id}")->assertOk();

    expect($user->tokens()->whereKey($id)->exists())->toBeFalse();
});

it('will not revoke another user\'s token', function () {
    $me = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create(['role' => 'admin']);
    $otherToken = $other->createToken('Theirs', ['mcp:use']);
    $id = $otherToken->accessToken->getKey();

    $this->actingAs($me)->deleteJson("/api/v1/studio/mcp-tokens/{$id}")->assertOk();

    // Still there — scoped to $me->tokens(), so it was untouched.
    expect($other->tokens()->whereKey($id)->exists())->toBeTrue();
});
