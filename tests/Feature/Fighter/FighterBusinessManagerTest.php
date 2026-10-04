<?php

declare(strict_types=1);

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function bmFighter(): User
{
    return User::factory()->create(['role' => 'fighter']);
}

/**
 * @return array{0: FacebookAdConnection, 1: FacebookAdAccount}
 */
function bmConnectionFor(?User $owner, string $accountId = '555'): array
{
    $connection = FacebookAdConnection::create([
        'user_id' => $owner?->id,
        'name' => $owner ? "BM {$owner->id}" : 'Company BM',
        'business_manager_id' => '111'.$accountId,
        'access_token' => 'token-'.$accountId,
        'status' => 'connected',
    ]);
    $account = FacebookAdAccount::create([
        'facebook_ad_connection_id' => $connection->id,
        'account_id' => $accountId,
        'name' => "Acc {$accountId}",
        'currency' => 'MYR',
    ]);

    return [$connection, $account];
}

function bmGraphFake(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/me?')) {
            return Http::response(['id' => '10', 'name' => 'Tester']);
        }
        if (str_contains($url, 'owned_ad_accounts')) {
            return Http::response(['data' => [[
                'id' => 'act_777', 'account_id' => '777', 'name' => 'Fighter Acc', 'currency' => 'MYR', 'account_status' => '1',
            ]]]);
        }
        if (str_contains($url, 'client_ad_accounts') || str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['id' => '999', 'name' => 'Fighter BM']);
    });
}

it('shows only the fighter\'s own Business Managers', function () {
    $me = bmFighter();
    bmConnectionFor($me, '1');
    bmConnectionFor(bmFighter(), '2');
    bmConnectionFor(null, '3');

    $this->actingAs($me)->get('/fighter/business-manager')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('BusinessManager', false)
            ->has('connections', 1)
            ->where('connections.0.accounts.0.account_id', '1'));
});

it('connects a Business Manager owned by the fighter', function () {
    bmGraphFake();
    $me = bmFighter();

    $this->actingAs($me)->postJson('/fighter/business-manager', [
        'name' => 'My BM',
        'business_manager_id' => '123 456',
        'access_token' => 'EAAG-valid-token',
    ])->assertCreated()->assertJson(['success' => true, 'accounts_count' => 1]);

    $connection = FacebookAdConnection::sole();
    expect($connection->user_id)->toBe($me->id)
        ->and($connection->business_manager_id)->toBe('123456')
        ->and($connection->adAccounts()->pluck('account_id')->all())->toBe(['777']);
});

it('drops a connection that fails verification', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400)]);

    $this->actingAs(bmFighter())->postJson('/fighter/business-manager', [
        'name' => 'Bad',
        'business_manager_id' => '123',
        'access_token' => 'nope',
    ])->assertStatus(422)->assertJson(['success' => false]);

    expect(FacebookAdConnection::count())->toBe(0);
});

it('validates the connect form', function () {
    $this->actingAs(bmFighter())->postJson('/fighter/business-manager', [
        'name' => '',
        'business_manager_id' => 'abc',
    ])->assertJsonValidationErrors(['name', 'business_manager_id', 'access_token']);
});

it('blocks a fighter from touching another fighter\'s or the company BM', function () {
    $me = bmFighter();
    [$theirs] = bmConnectionFor(bmFighter(), '2');
    [$company] = bmConnectionFor(null, '3');

    foreach ([$theirs, $company] as $connection) {
        $this->actingAs($me)->deleteJson("/fighter/business-manager/{$connection->id}")->assertNotFound();
        $this->actingAs($me)->postJson("/fighter/business-manager/{$connection->id}/sync")->assertNotFound();
        $this->actingAs($me)->putJson("/fighter/business-manager/{$connection->id}", [
            'name' => 'Hijack', 'business_manager_id' => '1',
        ])->assertNotFound();
    }

    expect(FacebookAdConnection::count())->toBe(2);
});

it('disconnects a BM and clears funnels linked to its accounts', function () {
    $me = bmFighter();
    [$connection, $account] = bmConnectionFor($me, '1');
    $funnel = Funnel::factory()->create(['user_id' => $me->id, 'settings' => ['ads' => ['facebook_ad_account_id' => $account->id]]]);

    $this->actingAs($me)->deleteJson("/fighter/business-manager/{$connection->id}")->assertOk();

    expect(FacebookAdConnection::count())->toBe(0)
        ->and(data_get($funnel->fresh()->settings, 'ads.facebook_ad_account_id'))->toBeNull();
});

it('links a funnel only to the fighter\'s own ad accounts', function () {
    $me = bmFighter();
    [, $mine] = bmConnectionFor($me, '1');
    [, $company] = bmConnectionFor(null, '3');
    $funnel = Funnel::factory()->create(['user_id' => $me->id]);
    $otherFunnel = Funnel::factory()->create(['user_id' => bmFighter()->id]);

    $this->actingAs($me)->putJson("/fighter/business-manager/funnels/{$funnel->uuid}", ['facebook_ad_account_id' => $mine->id])
        ->assertOk();
    expect(data_get($funnel->fresh()->settings, 'ads.facebook_ad_account_id'))->toBe($mine->id);

    $this->actingAs($me)->putJson("/fighter/business-manager/funnels/{$funnel->uuid}", ['facebook_ad_account_id' => $company->id])
        ->assertJsonValidationErrors('facebook_ad_account_id');

    $this->actingAs($me)->putJson("/fighter/business-manager/funnels/{$otherFunnel->uuid}", ['facebook_ad_account_id' => $mine->id])
        ->assertNotFound();

    $this->actingAs($me)->putJson("/fighter/business-manager/funnels/{$funnel->uuid}", ['facebook_ad_account_id' => null])
        ->assertOk();
    expect(data_get($funnel->fresh()->settings, 'ads.facebook_ad_account_id'))->toBeNull();
});

it('scopes the builder ad-account picker and Ads Source validation for fighters', function () {
    $me = bmFighter();
    [, $mine] = bmConnectionFor($me, '1');
    [, $company] = bmConnectionFor(null, '3');
    $funnel = Funnel::factory()->create(['user_id' => $me->id]);

    $this->actingAs($me)->getJson('/api/v1/facebook-ads/accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($me)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'settings' => ['ads' => ['facebook_ad_account_id' => $company->id]],
    ])->assertJsonValidationErrors('settings.ads.facebook_ad_account_id');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->getJson('/api/v1/facebook-ads/accounts')->assertJsonCount(2, 'data');
});

it('reports own spend vs own funnel sales with SST, ROAS and net', function () {
    $me = bmFighter();
    [, $mine] = bmConnectionFor($me, '1');
    [, $company] = bmConnectionFor(null, '3');
    $funnel = Funnel::factory()->create(['user_id' => $me->id, 'settings' => ['ads' => ['facebook_ad_account_id' => $mine->id]]]);
    $otherFunnel = Funnel::factory()->create(['user_id' => bmFighter()->id]);

    $day = now()->subDay();
    FacebookAdInsight::create(['facebook_ad_account_id' => $mine->id, 'date' => $day->toDateString(), 'campaign_id' => 'C1', 'spend' => 40]);
    FacebookAdInsight::create(['facebook_ad_account_id' => $company->id, 'date' => $day->toDateString(), 'campaign_id' => 'C2', 'spend' => 999]);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'funnel_revenue' => 100, 'created_at' => $day]);
    FunnelOrder::factory()->create(['funnel_id' => $otherFunnel->id, 'funnel_revenue' => 5000, 'created_at' => $day]);

    $this->actingAs($me)->get('/fighter/daily-reporting?days=7')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DailyReporting', false)
            ->where('connectionsCount', 1)
            ->where('report.days', 7)
            ->where('report.totals.spend', 40)
            ->where('report.totals.spend_with_sst', 43.2)
            ->where('report.totals.sales', 100)
            ->where('report.totals.roas', 2.5)
            ->where('report.totals.net', 56.8)
            ->has('report.by_funnel', 1)
            ->where('report.by_funnel.0.linked_spend', 40)
            ->where('report.by_funnel.0.roas', 2.5));
});

it('renders the daily report with no Business Manager connected', function () {
    $this->actingAs(bmFighter())->get('/fighter/daily-reporting')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('connectionsCount', 0)->where('report.totals.spend', 0)->where('report.totals.roas', null));
});
