<?php

declare(strict_types=1);

use App\Jobs\SyncFacebookAdCreatives;
use App\Jobs\SyncFacebookAdMonitor;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdCreative;
use App\Models\FacebookAdLevelInsight;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function monitorAccount(array $attrs = []): FacebookAdAccount
{
    return FacebookAdAccount::factory()->create(array_merge([
        'facebook_ad_connection_id' => FacebookAdConnection::factory()->create()->id,
        'name' => 'Akaun Haid',
    ], $attrs));
}

function adDay(FacebookAdAccount $account, array $attrs = []): FacebookAdLevelInsight
{
    return FacebookAdLevelInsight::create(array_merge([
        'facebook_ad_account_id' => $account->id,
        'date' => now()->toDateString(),
        'campaign_id' => '9001',
        'campaign_name' => 'HAID_SALES_TOFU',
        'objective' => 'OUTCOME_SALES',
        'adset_id' => '8001',
        'adset_name' => 'Broad',
        'ad_id' => '7001',
        'ad_name' => 'A1_VID_hook',
        'spend' => 100,
        'impressions' => 5000,
        'clicks' => 120,
        'link_clicks' => 80,
        'landing_page_views' => 60,
        'video_views' => 2000,
        'thruplays' => 500,
        'purchases' => 3,
        'purchase_value' => 240,
    ], $attrs));
}

/**
 * A funnel order from a Meta session. $payment: paid | failed | pending; $method: fpx | cod.
 */
function adOrder(Funnel $funnel, float $revenue, string $payment = 'paid', string $method = 'fpx', array $utm = [], ?string $phone = null): FunnelOrder
{
    $session = FunnelSession::factory()->create(array_merge([
        'funnel_id' => $funnel->id,
        'utm_source' => 'fb',
        'utm_campaign' => '9001',
        'utm_term' => '8001',
        'utm_content' => '7001',
    ], $utm));

    $order = ProductOrder::factory()->create([
        'payment_status' => $payment,
        'payment_method' => $method,
        'status' => 'confirmed',
        'customer_phone' => $phone ?? fake()->unique()->numerify('601########'),
    ]);

    return FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'session_id' => $session->id,
        'product_order_id' => $order->id,
        'funnel_revenue' => $revenue,
    ]);
}

it('forbids non-staff from the monitor', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->getJson('/api/v1/ad-monitor')
        ->assertForbidden();
})->with(['fighter', 'student']);

it('syncs ad-level insights, budgets and queues creatives', function () {
    Queue::fake([SyncFacebookAdCreatives::class]);
    $account = monitorAccount(['account_id' => '555']);

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/insights') => Http::response(['data' => [[
                'date_start' => now()->toDateString(), 'campaign_id' => '9001', 'campaign_name' => 'X', 'objective' => 'OUTCOME_SALES',
                'adset_id' => '8001', 'adset_name' => 'S', 'ad_id' => '7001', 'ad_name' => 'A1', 'spend' => '50.5', 'impressions' => '1000',
                'inline_link_clicks' => '20', 'actions' => [['action_type' => 'landing_page_view', 'value' => '15'], ['action_type' => 'omni_purchase', 'value' => '2']],
                'action_values' => [['action_type' => 'omni_purchase', 'value' => '180']],
            ]]]),
            str_contains($url, '/campaigns') => Http::response(['data' => [['id' => '9001', 'daily_budget' => '5000']]]),
            str_contains($url, '/adsets') => Http::response(['data' => []]),
            default => Http::response([]),
        };
    });

    SyncFacebookAdMonitor::dispatchSync($account->id, 3);

    $row = FacebookAdLevelInsight::sole();
    expect((float) $row->spend)->toBe(50.5)
        ->and($row->landing_page_views)->toBe(15)
        ->and($row->purchases)->toBe(2)
        ->and((float) $account->fresh()->daily_budget)->toBe(50.0)
        ->and($account->fresh()->monitor_synced_at)->not->toBeNull()
        ->and($account->fresh()->monitor_sync_error)->toBeNull();

    Queue::assertPushed(SyncFacebookAdCreatives::class);
});

it('records a sync error when Meta rejects the token', function () {
    $account = monitorAccount();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);

    SyncFacebookAdMonitor::dispatchSync($account->id, 3);

    expect($account->fresh()->monitor_sync_error)->toContain('Invalid OAuth');
});

it('fetches creatives for new ads', function () {
    $account = monitorAccount();
    adDay($account);

    Http::fake(['graph.facebook.com/*' => Http::response(['7001' => [
        'effective_status' => 'ACTIVE',
        'creative' => ['id' => 'c1', 'object_type' => 'VIDEO', 'video_id' => 'v1', 'thumbnail_url' => 'https://img/t.jpg', 'title' => 'Hook'],
    ]])]);

    SyncFacebookAdCreatives::dispatchSync($account->id);

    $creative = FacebookAdCreative::sole();
    expect($creative->asset_key)->toBe('v:v1')
        ->and($creative->format)->toBe('video')
        ->and($creative->thumbnail_url)->toBe('https://img/t.jpg');
});

it('attributes real funnel sales to the ad account by campaign id', function () {
    $account = monitorAccount();
    $other = monitorAccount(['name' => 'Lain']);
    adDay($account);
    $funnel = Funnel::factory()->create();

    adOrder($funnel, 150);                              // paid online → sale
    adOrder($funnel, 100, 'pending', 'cod');            // open COD → sale
    adOrder($funnel, 80, 'failed', 'fpx');              // failed → not a sale
    adOrder($funnel, 999, 'paid', 'fpx', ['utm_source' => 'google', 'utm_campaign' => '9001']); // not Meta

    $response = $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/v1/ad-monitor?range=today')
        ->assertOk();

    $rows = collect($response->json('clients'))->keyBy('id');
    $row = $rows[$account->id];

    expect($row['spend'])->toEqual(100)
        ->and($row['actual_count'])->toBe(2)
        ->and($row['actual_value'])->toEqual(250)
        ->and($row['actual_roas'])->toEqual(2.5)
        ->and($row['meta_roas'])->toEqual(2.4)
        ->and($row['checkouts'])->toBe(3)
        ->and($row['payment_success_rate'])->toEqual(50)   // online: 1 paid of 2 people
        ->and($row['campaigns'][0]['layer'])->toBe('sales')
        ->and($rows[$other->id]['actual_count'])->toBe(0);
});

it('falls back to the funnel linked account for untagged Meta traffic', function () {
    $account = monitorAccount();
    $funnel = Funnel::factory()->create(['settings' => ['ads' => ['facebook_ad_account_id' => $account->id]]]);
    adOrder($funnel, 200, 'paid', 'fpx', ['utm_campaign' => null, 'utm_content' => null, 'utm_term' => null]);

    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/ad-monitor?range=today');

    $row = collect($response->json('clients'))->firstWhere('id', $account->id);
    expect($row['actual_value'])->toEqual(200)
        ->and($row['raised_total'])->toEqual(200)
        ->and($row['funnels'][0]['linked'])->toBeTrue();
});

it('flags spend without sales as critical and names the leak', function () {
    $account = monitorAccount();
    adDay($account, ['spend' => 300, 'impressions' => 10000, 'link_clicks' => 40, 'clicks' => 60, 'landing_page_views' => 35, 'purchase_value' => 0, 'purchases' => 0]);

    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/ad-monitor?range=today')->assertOk();

    $row = collect($response->json('clients'))->firstWhere('id', $account->id);
    expect(collect($row['alerts'])->pluck('message')->join(' '))->toContain('tiada jualan sebenar')
        ->and($row['diagnosis']['bottleneck']['key'])->toBe('ctr')        // 0.4% link CTR < 0.7
        ->and($response->json('totals.clients_needing_attention'))->toBe(1);
});

it('matches sales to creatives by ad id and groups ads by asset', function () {
    $account = monitorAccount();
    adDay($account, ['ad_id' => '7001', 'spend' => 100]);
    adDay($account, ['ad_id' => '7002', 'ad_name' => 'A1_VID_copy', 'spend' => 50]);
    FacebookAdCreative::create(['facebook_ad_account_id' => $account->id, 'ad_id' => '7001', 'asset_key' => 'v:v1', 'format' => 'video', 'status' => 'ACTIVE', 'thumbnail_url' => 'https://img/1.jpg']);
    FacebookAdCreative::create(['facebook_ad_account_id' => $account->id, 'ad_id' => '7002', 'asset_key' => 'v:v1', 'format' => 'video', 'status' => 'PAUSED']);

    $funnel = Funnel::factory()->create();
    adOrder($funnel, 300, 'paid', 'fpx', ['utm_content' => '7002']);

    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/ad-monitor?range=today');

    $creatives = collect($response->json('clients'))->firstWhere('id', $account->id)['creatives'];
    expect($creatives['items'])->toHaveCount(1)
        ->and($creatives['items'][0]['ads'])->toBe(2)
        ->and($creatives['items'][0]['spend'])->toEqual(150)
        ->and($creatives['items'][0]['actual_value'])->toEqual(300)
        ->and($creatives['items'][0]['active_ads'])->toBe(1)
        ->and($creatives['items'][0]['thumbnail_url'])->toBe('https://img/1.jpg')
        ->and($creatives['roas_basis'])->toBe('actual');
});

it('builds the daily report of cost vs ad income per account', function () {
    $account = monitorAccount();
    adDay($account, ['spend' => 80]);
    adOrder(Funnel::factory()->create(), 120);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/v1/ad-monitor/daily?year='.now()->year)
        ->assertOk();

    expect($response->json('days.'.now()->toDateString().'.'.$account->id))->toEqual([80, 120, 1])
        ->and($response->json('clients.0.id'))->toBe($account->id);
});

it('sync now runs every monitorable account in-request', function () {
    Queue::fake([SyncFacebookAdCreatives::class]);
    monitorAccount();
    monitorAccount(['account_status' => '2']); // disabled on Meta → skipped
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/v1/ad-monitor/sync')
        ->assertOk()
        ->assertJsonPath('message', '1 akaun iklan disync.');
});
