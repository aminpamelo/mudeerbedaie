<?php

declare(strict_types=1);

use App\Models\LiveAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists live accounts with shops and hosts', function () {
    $account = LiveAccount::factory()->create(['nickname' => 'amarmirzabedaie']);
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);
    $account->shops()->attach($shop->id, ['is_primary' => true]);
    $account->hosts()->attach($host->id);

    actingAs($this->pic)
        ->get('/livehost/live-accounts')
        ->assertInertia(fn (Assert $p) => $p
            ->component('live-accounts/Index', false)
            ->has('accounts.data', 1)
            ->where('accounts.data.0.nickname', 'amarmirzabedaie')
            ->has('accounts.data.0.shops', 1)
            ->has('accounts.data.0.hosts', 1)
            ->has('shops')
            ->has('hosts'));
});

it('creates a live account with shop and host links', function () {
    $shop = PlatformAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->post('/livehost/live-accounts', [
            'creator_user_id' => '6526684195492729856',
            'nickname' => '@AmarMirzaBeDaie',
            'display_name' => 'BeDaie Ustaz Amar',
            'shop_ids' => [$shop->id],
            'primary_shop_id' => $shop->id,
            'host_ids' => [$host->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $account = LiveAccount::first();
    expect($account->creator_user_id)->toBe('6526684195492729856')
        ->and($account->normalized_handle)->toBe('amarmirzabedaie')
        ->and($account->shops()->count())->toBe(1)
        ->and($account->hosts()->count())->toBe(1)
        ->and($account->shops()->first()->pivot->is_primary)->toBeTruthy();
});

it('requires a nickname or creator id', function () {
    actingAs($this->pic)
        ->post('/livehost/live-accounts', ['display_name' => 'Only display'])
        ->assertSessionHasErrors('nickname');
});

it('rejects a duplicate creator id', function () {
    LiveAccount::factory()->create(['creator_user_id' => '999']);

    actingAs($this->pic)
        ->post('/livehost/live-accounts', ['creator_user_id' => '999', 'nickname' => 'x'])
        ->assertSessionHasErrors('creator_user_id');
});

it('updates an account and resyncs shops/hosts and clears review', function () {
    $account = LiveAccount::factory()->create(['needs_review' => true]);
    $shop = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->put("/livehost/live-accounts/{$account->id}", [
            'nickname' => 'renamed',
            'needs_review' => false,
            'shop_ids' => [$shop->id],
            'primary_shop_id' => $shop->id,
            'host_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $account->refresh();
    expect($account->nickname)->toBe('renamed')
        ->and($account->needs_review)->toBeFalse()
        ->and($account->shops()->count())->toBe(1);
});

it('attaches and detaches a host from the host page', function () {
    $account = LiveAccount::factory()->create();
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->post("/livehost/hosts/{$host->id}/live-accounts", ['live_account_id' => $account->id])
        ->assertRedirect();
    expect($account->hosts()->whereKey($host->id)->exists())->toBeTrue();

    actingAs($this->pic)
        ->delete("/livehost/hosts/{$host->id}/live-accounts/{$account->id}")
        ->assertRedirect();
    expect($account->hosts()->whereKey($host->id)->exists())->toBeFalse();
});

it('forbids a live host from managing accounts', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)
        ->post('/livehost/live-accounts', ['nickname' => 'x'])
        ->assertForbidden();
});

it('forbids the assistant from writing accounts', function () {
    $assistant = User::factory()->create(['role' => 'livehost_assistant']);

    actingAs($assistant)
        ->post('/livehost/live-accounts', ['nickname' => 'x'])
        ->assertForbidden();
});
