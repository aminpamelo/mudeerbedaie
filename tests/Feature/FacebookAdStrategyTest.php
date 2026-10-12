<?php

declare(strict_types=1);

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\FacebookAdRule;
use App\Models\FacebookAdStrategy;
use App\Models\FacebookCampaignStrategy;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\User;
use App\Services\Funnel\FacebookAdRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function strategyAccount(): FacebookAdAccount
{
    return FacebookAdAccount::factory()->create([
        'facebook_ad_connection_id' => FacebookAdConnection::factory()->create()->id,
    ]);
}

it('seeds the starter strategies once', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/facebook-ads/manage/strategies/starters')->assertOk();
    $this->actingAs($admin)->postJson('/api/v1/facebook-ads/manage/strategies/starters')->assertOk();

    expect(FacebookAdStrategy::count())->toBe(4)
        ->and(FacebookAdStrategy::where('name', 'CBO Scaling')->first()->rule_presets)->toHaveCount(2);
});

it('forbids fighters from the strategy library', function () {
    $this->actingAs(User::factory()->create(['role' => 'fighter']))
        ->getJson('/api/v1/facebook-ads/manage/strategies')
        ->assertForbidden();
});

it('validates bundled scale-up rules need a budget cap', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/v1/facebook-ads/manage/strategies', [
            'name' => 'Scale',
            'color' => 'green',
            'rule_presets' => [[
                'name' => 'Up', 'level' => 'campaign', 'metric' => 'fb_roas', 'operator' => 'gt',
                'threshold' => 3, 'lookback_days' => 3, 'action' => 'increase_budget', 'action_value' => 20,
            ]],
        ])
        ->assertJsonValidationErrors('rule_presets.0.max_daily_budget');
});

it('tags and untags a campaign', function () {
    $admin = User::factory()->admin()->create();
    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create(['name' => 'Broad', 'color' => 'blue']);

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/campaign-strategy", [
            'campaign_id' => '123456789', 'campaign_name' => 'Raya', 'strategy_id' => $strategy->id,
        ])
        ->assertOk()
        ->assertJsonPath('strategy.name', 'Broad');

    expect(FacebookCampaignStrategy::sole()->campaign_id)->toBe('123456789');

    $this->actingAs($admin)
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/campaign-strategy", [
            'campaign_id' => '123456789', 'strategy_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('strategy', null);

    expect(FacebookCampaignStrategy::count())->toBe(0);
});

it('shows the strategy badge on campaign rows', function () {
    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create(['name' => 'Retargeting']);
    FacebookCampaignStrategy::create(['facebook_ad_account_id' => $account->id, 'facebook_ad_strategy_id' => $strategy->id, 'campaign_id' => '111111']);

    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
        ['id' => '111111', 'name' => 'A', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
        ['id' => '222222', 'name' => 'B', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
    ]])]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->getJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/entities?level=campaign")
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows['111111']['strategy']['name'])->toBe('Retargeting')
        ->and($rows['222222']['strategy'])->toBeNull();
});

it('applies bundled rules as dry-run, strategy-scoped, without duplicates', function () {
    $admin = User::factory()->admin()->create();
    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create([
        'name' => 'Broad',
        'rule_presets' => [['name' => 'Kill no-sale ads', 'level' => 'ad', 'metric' => 'purchases', 'operator' => 'lt', 'threshold' => 1, 'min_spend' => 40, 'lookback_days' => 3, 'action' => 'pause']],
    ]);

    $this->actingAs($admin)->postJson("/api/v1/facebook-ads/manage/strategies/{$strategy->id}/apply-rules", ['account_id' => $account->id])->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/facebook-ads/manage/strategies/{$strategy->id}/apply-rules", ['account_id' => $account->id])->assertOk();

    $rule = FacebookAdRule::sole();
    expect($rule->dry_run)->toBeTrue()
        ->and($rule->facebook_ad_strategy_id)->toBe($strategy->id)
        ->and($rule->name)->toBe('Broad: Kill no-sale ads');
});

it('strategy-scoped rules only touch that strategy\'s campaigns', function () {
    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create();
    FacebookCampaignStrategy::create(['facebook_ad_account_id' => $account->id, 'facebook_ad_strategy_id' => $strategy->id, 'campaign_id' => '500']);

    $rule = FacebookAdRule::factory()->create([
        'facebook_ad_account_id' => $account->id, 'facebook_ad_strategy_id' => $strategy->id,
        'level' => 'adset', 'metric' => 'fb_roas', 'operator' => 'lt', 'threshold' => 1.5, 'min_spend' => 0, 'action' => 'pause',
    ]);

    $row = fn (string $campaign, string $adset) => [
        'campaign_id' => $campaign, 'adset_id' => $adset, 'adset_name' => "Set {$adset}", 'spend' => '100',
        'actions' => [], 'action_values' => [],
    ];

    Http::fake(fn (Request $request) => $request->method() === 'GET'
        ? Http::response(['data' => [$row('500', '901'), $row('600', '902')]])
        : Http::response(['success' => true]));

    app(FacebookAdRuleEngine::class)->run($rule);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/901'));
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/902'));
});

it('launch with a strategy tags the campaign and adds its rules', function () {
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

    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create([
        'rule_presets' => [['name' => 'Stop', 'level' => 'adset', 'metric' => 'funnel_roas', 'operator' => 'lt', 'threshold' => 1, 'lookback_days' => 3, 'action' => 'pause']],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/facebook-ads/manage/accounts/{$account->id}/launch", [
            'name' => 'Raya', 'objective' => 'OUTCOME_TRAFFIC', 'link' => 'https://kelasify.com/f/raya',
            'daily_budget' => 30, 'page_id' => '123456789', 'countries' => ['MY'], 'age_min' => 18, 'age_max' => 65,
            'primary_text' => 'Hi', 'headline' => 'Raya', 'call_to_action' => 'LEARN_MORE', 'image_hash' => 'abc',
            'facebook_ad_strategy_id' => $strategy->id,
        ])
        ->assertCreated();

    expect(FacebookCampaignStrategy::sole()->campaign_id)->toBe('100')
        ->and(FacebookAdRule::where('facebook_ad_strategy_id', $strategy->id)->count())->toBe(1);
});

it('reports per-strategy spend and funnel ROAS', function () {
    $account = strategyAccount();
    $strategy = FacebookAdStrategy::factory()->create();
    FacebookCampaignStrategy::create(['facebook_ad_account_id' => $account->id, 'facebook_ad_strategy_id' => $strategy->id, 'campaign_id' => '777']);
    FacebookAdInsight::create(['facebook_ad_account_id' => $account->id, 'date' => now()->subDay()->toDateString(), 'campaign_id' => '777', 'spend' => 100, 'impressions' => 1, 'clicks' => 1]);

    $funnel = Funnel::factory()->create();
    $session = FunnelSession::factory()->create(['funnel_id' => $funnel->id, 'utm_campaign' => '777']);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'session_id' => $session->id, 'funnel_revenue' => 400]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/v1/facebook-ads/manage/strategies')
        ->assertOk()
        ->assertJsonPath('data.0.stats_30d.campaigns', 1)
        ->assertJsonPath('data.0.stats_30d.spend', 100)
        ->assertJsonPath('data.0.stats_30d.funnel_roas', 4);
});
