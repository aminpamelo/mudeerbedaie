<?php

declare(strict_types=1);

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdAction;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdLaunch;
use App\Models\FacebookAdRule;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\User;
use App\Services\Funnel\FacebookAdRuleEngine;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function managedAccount(bool $canManage = true): FacebookAdAccount
{
    $connection = $canManage
        ? FacebookAdConnection::factory()->create()
        : FacebookAdConnection::factory()->readOnly()->create();

    return FacebookAdAccount::factory()->create(['facebook_ad_connection_id' => $connection->id, 'account_id' => '1234567']);
}

function insightRow(string $level, string $id, float $spend, float $purchaseValue = 0, int $purchases = 0): array
{
    return [
        "{$level}_id" => $id,
        "{$level}_name" => "Obj {$id}",
        'spend' => (string) $spend,
        'impressions' => '1000',
        'clicks' => '50',
        'ctr' => '5',
        'cpc' => '0.5',
        'actions' => [['action_type' => 'omni_purchase', 'value' => (string) $purchases]],
        'action_values' => [['action_type' => 'omni_purchase', 'value' => (string) $purchaseValue]],
    ];
}

it('detects ads_management when verifying a connection', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/me/permissions')) {
            return Http::response(['data' => [
                ['permission' => 'ads_read', 'status' => 'granted'],
                ['permission' => 'ads_management', 'status' => 'granted'],
            ]]);
        }

        return Http::response(['id' => '1', 'name' => 'X']);
    });

    $connection = FacebookAdConnection::factory()->readOnly()->create();

    app(FacebookAdsService::class)->verifyConnection($connection);

    expect($connection->fresh()->can_manage_ads)->toBeTrue()
        ->and($connection->fresh()->permissions)->toContain('ads_management');
});

it('keeps a connection read-only when ads_management was declined', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/me/permissions')) {
            return Http::response(['data' => [
                ['permission' => 'ads_read', 'status' => 'granted'],
                ['permission' => 'ads_management', 'status' => 'declined'],
            ]]);
        }

        return Http::response(['id' => '1', 'name' => 'X']);
    });

    $connection = FacebookAdConnection::factory()->create();

    app(FacebookAdsService::class)->verifyConnection($connection);

    expect($connection->fresh()->can_manage_ads)->toBeFalse();
});

it('pauses an ad set and logs the action', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);
    $admin = User::factory()->admin()->create();
    $account = managedAccount();

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/status", [
            'object_type' => 'adset',
            'object_id' => '998877665',
            'object_name' => 'Retarget',
            'status' => 'PAUSED',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/998877665') && $r['status'] === 'PAUSED');

    $action = FacebookAdAction::sole();
    expect($action->user_id)->toBe($admin->id)
        ->and($action->action)->toBe('status')
        ->and($action->success)->toBeTrue()
        ->and($action->after)->toBe(['status' => 'PAUSED']);
});

it('refuses writes when the token is read-only and never calls Facebook', function () {
    Http::fake();
    $admin = User::factory()->admin()->create();
    $account = managedAccount(canManage: false);

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/status", [
            'object_type' => 'ad', 'object_id' => '998877665', 'status' => 'PAUSED',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    Http::assertNothingSent();
    expect(FacebookAdAction::sole()->success)->toBeFalse();
});

it('forbids fighters and plain users from managing ads', function (string $role) {
    Http::fake();
    $user = User::factory()->create(['role' => $role]);
    $account = managedAccount();

    $this->actingAs($user)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/status", [
            'object_type' => 'ad', 'object_id' => '998877665', 'status' => 'PAUSED',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
})->with(['fighter', 'student']);

it('lets employees change a daily budget in minor units', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);
    $employee = User::factory()->create(['role' => 'employee']);
    $account = managedAccount();

    $this->actingAs($employee)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/budget", [
            'object_type' => 'campaign', 'object_id' => '55555555', 'daily_budget' => 75.5, 'current_budget' => 50,
        ])
        ->assertOk();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && (int) $r['daily_budget'] === 7550);
    expect(FacebookAdAction::sole()->before)->toBe(['daily_budget' => 50]);
});

it('rejects budgets on ads', function () {
    $admin = User::factory()->admin()->create();
    $account = managedAccount();

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/budget", [
            'object_type' => 'ad', 'object_id' => '55555555', 'daily_budget' => 50,
        ])
        ->assertJsonValidationErrors('object_type');
});

it('lists campaigns with FB and funnel ROAS', function () {
    $account = managedAccount();
    $funnel = Funnel::factory()->create();
    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id, 'utm_campaign' => '111111']);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'session_id' => $session->id, 'funnel_revenue' => 300]);

    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [[
        'id' => '111111',
        'name' => 'Raya Sale',
        'status' => 'ACTIVE',
        'effective_status' => 'ACTIVE',
        'objective' => 'OUTCOME_SALES',
        'daily_budget' => '5000',
        'insights' => ['data' => [insightRow('campaign', '111111', 100, 250, 5)]],
    ]]])]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/entities?level=campaign&date_preset=last_7d")
        ->assertOk()
        ->assertJsonPath('data.0.daily_budget', 50)
        ->assertJsonPath('data.0.fb_roas', 2.5)
        ->assertJsonPath('data.0.purchases', 5)
        ->assertJsonPath('data.0.funnel_revenue', 300)
        ->assertJsonPath('data.0.funnel_roas', 3);
});

it('launches a full campaign chain as paused with utm url tags', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/campaigns') => Http::response(['id' => '100']),
            str_contains($url, '/adsets') => Http::response(['id' => '200']),
            str_contains($url, '/adcreatives') => Http::response(['id' => '300']),
            str_contains($url, '/ads') => Http::response(['id' => '400']),
            default => Http::response([], 404),
        };
    });

    $admin = User::factory()->admin()->create();
    $account = managedAccount();

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/launch", launchPayload())
        ->assertCreated()
        ->assertJsonPath('launch.ad_id', '400');

    $launch = FacebookAdLaunch::sole();
    expect($launch->status)->toBe('created')
        ->and($launch->campaign_id)->toBe('100');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/campaigns') && $r['status'] === 'PAUSED' && (int) $r['daily_budget'] === 3000);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/adsets') && str_contains($r['promoted_object'], '"custom_event_type":"PURCHASE"'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/adcreatives') && str_contains($r['url_tags'], 'utm_campaign={{campaign.id}}'));
    Http::assertSent(fn (Request $r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/ads') && $r['status'] === 'PAUSED');
});

it('rolls back the campaign when a later launch step fails', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $request->method() === 'DELETE' => Http::response(['success' => true]),
            str_contains($url, '/campaigns') => Http::response(['id' => '100']),
            str_contains($url, '/adsets') => Http::response(['error' => ['message' => 'Invalid targeting']], 400),
            default => Http::response([], 404),
        };
    });

    $account = managedAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/launch", launchPayload())
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/100'));
    expect(FacebookAdLaunch::sole()->status)->toBe('failed')
        ->and(FacebookAdLaunch::sole()->error)->toContain('Invalid targeting');
});

it('requires a pixel for sales campaigns', function () {
    $account = managedAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/launch", array_merge(launchPayload(), ['pixel_id' => null]))
        ->assertJsonValidationErrors('pixel_id');
});

it('requires a budget cap for scale-up rules', function () {
    $account = managedAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/v1/facebook-ads/manage/rules', [
            'facebook_ad_account_id' => $account->id,
            'name' => 'Scale winners',
            'level' => 'campaign',
            'metric' => 'fb_roas',
            'operator' => 'gt',
            'threshold' => 3,
            'lookback_days' => 3,
            'action' => 'increase_budget',
            'action_value' => 20,
            'cooldown_hours' => 24,
        ])
        ->assertJsonValidationErrors('max_daily_budget');
});

it('creates rules as dry run by default', function () {
    $account = managedAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/v1/facebook-ads/manage/rules', [
            'facebook_ad_account_id' => $account->id,
            'name' => 'Kill losers',
            'level' => 'adset',
            'metric' => 'funnel_roas',
            'operator' => 'lt',
            'threshold' => 1.5,
            'min_spend' => 50,
            'lookback_days' => 3,
            'action' => 'pause',
            'cooldown_hours' => 24,
        ])
        ->assertCreated()
        ->assertJsonPath('data.dry_run', true)
        ->assertJsonPath('data.description', 'Funnel ROAS < 1.5 (3d, min RM50 spend) → Pause');
});

it('rule pauses only losers past the min-spend guard', function () {
    $account = managedAccount();
    $rule = FacebookAdRule::factory()->create([
        'facebook_ad_account_id' => $account->id, 'level' => 'adset', 'metric' => 'fb_roas',
        'operator' => 'lt', 'threshold' => 1.5, 'min_spend' => 50, 'action' => 'pause',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(['data' => [
                insightRow('adset', '901', 100, 50),   // ROAS 0.5 → pause
                insightRow('adset', '902', 100, 400),  // ROAS 4 → keep
                insightRow('adset', '903', 20, 0),     // under min spend → ignore
            ]]);
        }

        return Http::response(['success' => true]);
    });

    $result = app(FacebookAdRuleEngine::class)->run($rule);

    expect($result['matched'])->toBe(1)->and($result['applied'])->toBe(1);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/901') && $r['status'] === 'PAUSED');
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && (str_contains($r->url(), '/902') || str_contains($r->url(), '/903')));
    expect($rule->fresh()->last_run_summary)->toContain('1 matched');
});

it('dry-run rules log but never write to Facebook', function () {
    $account = managedAccount();
    $rule = FacebookAdRule::factory()->dryRun()->create(['facebook_ad_account_id' => $account->id]);

    Http::fake(fn (Request $request) => $request->method() === 'GET'
        ? Http::response(['data' => [insightRow('adset', '901', 100, 10)]])
        : Http::response(['success' => true]));

    app(FacebookAdRuleEngine::class)->run($rule);

    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    $action = FacebookAdAction::sole();
    expect($action->dry_run)->toBeTrue()->and($action->message)->toContain('[Dry run]');
});

it('respects the cooldown so the same object is not actioned twice', function () {
    $account = managedAccount();
    $rule = FacebookAdRule::factory()->create(['facebook_ad_account_id' => $account->id, 'cooldown_hours' => 24]);

    Http::fake(fn (Request $request) => $request->method() === 'GET'
        ? Http::response(['data' => [insightRow('adset', '901', 100, 10)]])
        : Http::response(['success' => true]));

    $engine = app(FacebookAdRuleEngine::class);
    $engine->run($rule);
    $second = $engine->run($rule);

    expect($second['skipped'])->toBe(1)->and(FacebookAdAction::count())->toBe(1);
});

it('scales budget up but never past the cap', function () {
    $account = managedAccount();
    $rule = FacebookAdRule::factory()->create([
        'facebook_ad_account_id' => $account->id, 'level' => 'campaign', 'metric' => 'fb_roas',
        'operator' => 'gt', 'threshold' => 3, 'min_spend' => 0, 'action' => 'increase_budget',
        'action_value' => 50, 'max_daily_budget' => 120,
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/insights')) {
            return Http::response(['data' => [insightRow('campaign', '777', 100, 500)]]);
        }
        if ($request->method() === 'GET') {
            return Http::response(['id' => '777', 'daily_budget' => '10000']); // RM100
        }

        return Http::response(['success' => true]);
    });

    app(FacebookAdRuleEngine::class)->run($rule);

    // +50% would be RM150 → capped to RM120
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && (int) $r['daily_budget'] === 12000);
});

it('evaluates funnel ROAS from UTM-attributed orders', function () {
    $account = managedAccount();
    $funnel = Funnel::factory()->create();
    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id, 'utm_term' => '901']);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'session_id' => $session->id, 'funnel_revenue' => 500]);

    $rule = FacebookAdRule::factory()->create([
        'facebook_ad_account_id' => $account->id, 'level' => 'adset', 'metric' => 'funnel_roas',
        'operator' => 'lt', 'threshold' => 1.5, 'min_spend' => 0, 'action' => 'pause',
    ]);

    Http::fake(fn (Request $request) => $request->method() === 'GET'
        ? Http::response(['data' => [insightRow('adset', '901', 100, 0), insightRow('adset', '902', 100, 0)]])
        : Http::response(['success' => true]));

    app(FacebookAdRuleEngine::class)->run($rule);

    // 901 has funnel ROAS 5 (FB says 0) → kept; 902 has no funnel sales → paused
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/902'));
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/901'));
});

function launchPayload(): array
{
    return [
        'name' => 'Raya Promo',
        'objective' => 'OUTCOME_SALES',
        'link' => 'https://kelasify.com/f/raya',
        'daily_budget' => 30,
        'page_id' => '123456789',
        'pixel_id' => '987654321',
        'countries' => ['MY'],
        'age_min' => 18,
        'age_max' => 65,
        'advantage_audience' => true,
        'primary_text' => 'Jom beli!',
        'headline' => 'Promo Raya',
        'call_to_action' => 'SHOP_NOW',
        'image_hash' => 'abc123hash',
    ];
}
