<?php

declare(strict_types=1);

use App\Models\FacebookAdConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fbConnection(array $overrides = []): FacebookAdConnection
{
    return FacebookAdConnection::create(array_merge([
        'name' => 'Old Name',
        'business_manager_id' => '111111111111',
        'access_token' => 'old-token-value-1234567890abcdef',
        'status' => 'connected',
        'status_message' => 'Connected as Foo → BM: Bar',
    ], $overrides));
}

function fbGraphSuccess(string $ownedAccountId = '555'): Closure
{
    return function ($request) use ($ownedAccountId) {
        $url = $request->url();

        if (str_contains($url, '/me?')) {
            return Http::response(['id' => '10', 'name' => 'Tester']);
        }
        if (str_contains($url, 'owned_ad_accounts')) {
            return Http::response(['data' => [[
                'id' => 'act_'.$ownedAccountId,
                'account_id' => $ownedAccountId,
                'name' => 'Main Acc',
                'currency' => 'MYR',
                'account_status' => '1',
            ]]]);
        }
        if (str_contains($url, 'client_ad_accounts')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['id' => '999', 'name' => 'My BM']); // BM node lookup
    };
}

it('renames a connection without calling Facebook', function () {
    Http::fake();
    $admin = User::factory()->admin()->create();
    $connection = fbConnection();

    $this->actingAs($admin)
        ->putJson("/api/v1/facebook-ads/connections/{$connection->id}", [
            'name' => 'New Name',
            'business_manager_id' => '111111111111',
            'access_token' => '',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertNothingSent();

    $connection->refresh();
    expect($connection->name)->toBe('New Name')
        ->and($connection->access_token)->toBe('old-token-value-1234567890abcdef')
        ->and($connection->status)->toBe('connected');
});

it('rotates the access token then re-verifies and re-syncs accounts', function () {
    Http::fake(fbGraphSuccess());
    $admin = User::factory()->admin()->create();
    $connection = fbConnection();

    $this->actingAs($admin)
        ->putJson("/api/v1/facebook-ads/connections/{$connection->id}", [
            'name' => 'Old Name',
            'business_manager_id' => '111111111111',
            'access_token' => 'brand-new-rotated-token-abcdef123456',
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'accounts_count' => 1]);

    $connection->refresh();
    expect($connection->access_token)->toBe('brand-new-rotated-token-abcdef123456')
        ->and($connection->status)->toBe('connected')
        ->and($connection->adAccounts()->count())->toBe(1);
});

it('rolls back credentials but keeps the name when the new token fails verification', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/me?')) {
            return Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400);
        }

        return Http::response(['id' => '999', 'name' => 'My BM']);
    });

    $admin = User::factory()->admin()->create();
    $connection = fbConnection();

    $this->actingAs($admin)
        ->putJson("/api/v1/facebook-ads/connections/{$connection->id}", [
            'name' => 'Attempted Name',
            'business_manager_id' => '111111111111',
            'access_token' => 'a-bad-token-that-will-be-rejected-99',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    $connection->refresh();
    expect($connection->access_token)->toBe('old-token-value-1234567890abcdef')
        ->and($connection->status)->toBe('connected')
        ->and($connection->status_message)->toBe('Connected as Foo → BM: Bar')
        ->and($connection->name)->toBe('Attempted Name');
});

it('clears old ad accounts when the Business Manager ID changes', function () {
    Http::fake(fbGraphSuccess('777'));
    $admin = User::factory()->admin()->create();
    $connection = fbConnection();
    $connection->adAccounts()->create([
        'account_id' => '555',
        'name' => 'Stale Old Acc',
        'currency' => 'MYR',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/facebook-ads/connections/{$connection->id}", [
            'name' => 'Old Name',
            'business_manager_id' => '222222222222',
            'access_token' => '',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $connection->refresh();
    expect($connection->business_manager_id)->toBe('222222222222')
        ->and($connection->adAccounts()->pluck('account_id')->all())->toBe(['777']);
});

it('forbids non-admins from editing a connection', function () {
    Http::fake();
    $student = User::factory()->create();
    $connection = fbConnection();

    $this->actingAs($student)
        ->putJson("/api/v1/facebook-ads/connections/{$connection->id}", [
            'name' => 'Hacked',
            'business_manager_id' => '111111111111',
            'access_token' => '',
        ])
        ->assertForbidden();

    expect($connection->fresh()->name)->toBe('Old Name');
});
