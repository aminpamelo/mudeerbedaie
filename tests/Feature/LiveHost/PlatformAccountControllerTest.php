<?php

use App\Models\LiveSchedule;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists platform accounts with pagination (15 per page)', function () {
    PlatformAccount::factory()->count(20)->create();

    actingAs($this->pic)
        ->get('/livehost/platform-accounts')
        ->assertInertia(fn (Assert $p) => $p
            ->component('platform-accounts/Index', false)
            ->has('accounts.data', 15)
            ->has('accounts.links')
            ->has('filters')
            ->has('platforms')
            ->has('users'));
});

it('filters platform accounts by search on name', function () {
    PlatformAccount::factory()->create(['name' => 'Sarah Chen Shopee']);
    PlatformAccount::factory()->create(['name' => 'John Doe TikTok']);

    actingAs($this->pic)
        ->get('/livehost/platform-accounts?search=Sarah')
        ->assertInertia(fn (Assert $p) => $p
            ->has('accounts.data', 1)
            ->where('filters.search', 'Sarah'));
});

it('filters platform accounts by account_id search', function () {
    PlatformAccount::factory()->create(['account_id' => 'ACC-ALPHA-123']);
    PlatformAccount::factory()->create(['account_id' => 'ACC-BETA-999']);

    actingAs($this->pic)
        ->get('/livehost/platform-accounts?search=ALPHA')
        ->assertInertia(fn (Assert $p) => $p->has('accounts.data', 1));
});

it('filters platform accounts by platform_id', function () {
    $shopee = Platform::factory()->create(['name' => 'Shopee', 'slug' => 'shopee']);
    $tiktok = Platform::factory()->create(['name' => 'TikTok Shop', 'slug' => 'tiktok-shop']);

    PlatformAccount::factory()->count(3)->create(['platform_id' => $shopee->id]);
    PlatformAccount::factory()->count(2)->create(['platform_id' => $tiktok->id]);

    actingAs($this->pic)
        ->get('/livehost/platform-accounts?platform_id='.$shopee->id)
        ->assertInertia(fn (Assert $p) => $p
            ->has('accounts.data', 3)
            ->where('filters.platform_id', (string) $shopee->id));
});

it('filters platform accounts by user_id', function () {
    $owner = User::factory()->create();
    PlatformAccount::factory()->count(2)->create(['user_id' => $owner->id]);
    PlatformAccount::factory()->count(3)->create();

    actingAs($this->pic)
        ->get('/livehost/platform-accounts?user_id='.$owner->id)
        ->assertInertia(fn (Assert $p) => $p->has('accounts.data', 2));
});

it('filters platform accounts by is_active', function () {
    PlatformAccount::factory()->count(2)->create(['is_active' => true]);
    PlatformAccount::factory()->count(3)->create(['is_active' => false]);

    actingAs($this->pic)
        ->get('/livehost/platform-accounts?is_active=0')
        ->assertInertia(fn (Assert $p) => $p->has('accounts.data', 3));
});

it('renders the create form with platforms and users', function () {
    Platform::factory()->count(2)->create();

    actingAs($this->pic)
        ->get('/livehost/platform-accounts/create')
        ->assertInertia(fn (Assert $p) => $p
            ->component('platform-accounts/Create', false)
            ->has('platforms')
            ->has('users'));
});

it('creates a new platform account', function () {
    $platform = Platform::factory()->create();
    $owner = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->post('/livehost/platform-accounts', [
            'name' => "Sarah Chen's Shopee",
            'platform_id' => $platform->id,
            'user_id' => $owner->id,
            'account_id' => 'SHP-1001',
            'description' => 'Primary Shopee account for Sarah.',
            'country_code' => 'MY',
            'currency' => 'MYR',
            'is_active' => true,
        ])
        ->assertRedirect('/livehost/platform-accounts')
        ->assertSessionHas('success');

    $account = PlatformAccount::where('account_id', 'SHP-1001')->first();
    expect($account)->not->toBeNull();
    expect($account->platform_id)->toBe($platform->id);
    expect($account->user_id)->toBe($owner->id);
    expect($account->country_code)->toBe('MY');
    expect($account->currency)->toBe('MYR');
    expect($account->is_active)->toBeTrue();
});

it('rejects create with missing required fields', function () {
    actingAs($this->pic)
        ->post('/livehost/platform-accounts', [])
        ->assertSessionHasErrors(['name', 'platform_id']);
});

it('rejects create with invalid platform_id', function () {
    actingAs($this->pic)
        ->post('/livehost/platform-accounts', [
            'name' => 'Ghost',
            'platform_id' => 999999,
        ])
        ->assertSessionHasErrors('platform_id');
});

it('rejects create with duplicate account_id for same platform', function () {
    $platform = Platform::factory()->create();
    PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'account_id' => 'SHP-DUPE',
    ]);

    actingAs($this->pic)
        ->post('/livehost/platform-accounts', [
            'name' => 'Second',
            'platform_id' => $platform->id,
            'account_id' => 'SHP-DUPE',
        ])
        ->assertSessionHasErrors('account_id');
});

it('updates a platform account', function () {
    $account = PlatformAccount::factory()->create([
        'name' => 'Old Name',
        'is_active' => true,
    ]);

    actingAs($this->pic)
        ->put("/livehost/platform-accounts/{$account->id}", [
            'name' => 'Renamed Account',
            'platform_id' => $account->platform_id,
            'user_id' => $account->user_id,
            'account_id' => $account->account_id,
            'description' => 'Updated note',
            'country_code' => 'SG',
            'currency' => 'SGD',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($account->fresh())
        ->name->toBe('Renamed Account')
        ->country_code->toBe('SG')
        ->currency->toBe('SGD')
        ->is_active->toBeFalse()
        ->description->toBe('Updated note');
});

it('does not mass-assign excluded sync fields on update', function () {
    $account = PlatformAccount::factory()->create([
        'api_version' => 'v1',
        'sync_status' => 'idle',
    ]);

    actingAs($this->pic)
        ->put("/livehost/platform-accounts/{$account->id}", [
            'name' => $account->name,
            'platform_id' => $account->platform_id,
            'api_version' => 'HACKED',
            'sync_status' => 'syncing',
            'metadata' => ['evil' => true],
        ]);

    $fresh = $account->fresh();
    expect($fresh->api_version)->toBe('v1');
    expect($fresh->sync_status)->toBe('idle');
});

it('hard-deletes a platform account with no references', function () {
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->delete("/livehost/platform-accounts/{$account->id}")
        ->assertRedirect('/livehost/platform-accounts')
        ->assertSessionHas('success');

    expect(PlatformAccount::find($account->id))->toBeNull();
});

it('refuses to delete a platform account referenced by live sessions', function () {
    $account = PlatformAccount::factory()->create();
    LiveSession::factory()->create(['platform_account_id' => $account->id]);

    actingAs($this->pic)
        ->delete("/livehost/platform-accounts/{$account->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PlatformAccount::find($account->id))->not->toBeNull();
});

it('refuses to delete a platform account referenced by live schedules', function () {
    $account = PlatformAccount::factory()->create();
    LiveSchedule::factory()->create(['platform_account_id' => $account->id]);

    actingAs($this->pic)
        ->delete("/livehost/platform-accounts/{$account->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PlatformAccount::find($account->id))->not->toBeNull();
});

it('refuses to delete a platform account referenced by schedule assignments', function () {
    $account = PlatformAccount::factory()->create();
    $timeSlot = LiveTimeSlot::factory()->create(['platform_account_id' => $account->id]);
    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'time_slot_id' => $timeSlot->id,
    ]);

    actingAs($this->pic)
        ->delete("/livehost/platform-accounts/{$account->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PlatformAccount::find($account->id))->not->toBeNull();
});

it('forbids non-PIC from listing platform accounts', function () {
    $regular = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($regular)
        ->get('/livehost/platform-accounts')
        ->assertForbidden();
});

it('forbids non-PIC from creating platform accounts', function () {
    $regular = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    $this->actingAs($regular)
        ->post('/livehost/platform-accounts', [
            'name' => 'X',
            'platform_id' => $platform->id,
        ])
        ->assertForbidden();
});
