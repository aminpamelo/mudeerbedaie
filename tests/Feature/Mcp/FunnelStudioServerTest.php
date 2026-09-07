<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelStudioServer;
use App\Mcp\Tools\DailyReportTool;
use App\Mcp\Tools\FacebookAdsInsightsTool;
use App\Mcp\Tools\ListFunnelsTool;
use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\User;

/**
 * Create a connected FB ad account, optionally owned by a specific user's
 * connection (null = company-wide).
 */
function seedAdAccount(?User $owner = null): FacebookAdAccount
{
    $connection = FacebookAdConnection::create([
        'user_id' => $owner?->id,
        'name' => 'Test BM',
        'business_manager_id' => '123456',
        'access_token' => 'token',
        'status' => 'connected',
    ]);

    return FacebookAdAccount::create([
        'facebook_ad_connection_id' => $connection->id,
        'account_id' => 'act_'.uniqid(),
        'name' => 'Main Ad Account',
        'currency' => 'MYR',
    ]);
}

it('lists the marketer funnels with 30-day sales', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $funnel = Funnel::factory()->create([
        'user_id' => $user->id,
        'name' => 'Qada Solat Funnel',
        'status' => 'published',
    ]);
    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'funnel_revenue' => 50,
        'created_at' => now()->subDay(),
    ]);

    FunnelStudioServer::actingAs($user)
        ->tool(ListFunnelsTool::class, [])
        ->assertOk()
        ->assertSee('Qada Solat Funnel')
        ->assertSee('50');
});

it('reports facebook ad spend grossed up by 8% SST', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $account = seedAdAccount();
    FacebookAdInsight::create([
        'facebook_ad_account_id' => $account->id,
        'date' => now()->subDay()->toDateString(),
        'campaign_id' => 'C1',
        'spend' => 100,
        'impressions' => 1000,
        'clicks' => 50,
    ]);

    FunnelStudioServer::actingAs($user)
        ->tool(FacebookAdsInsightsTool::class, ['days' => 30])
        ->assertOk()
        ->assertSee('108'); // 100 spend + 8% SST
});

it('daily_report pairs spend and sales with correct SST, ROAS and net', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $funnel = Funnel::factory()->create(['user_id' => $user->id, 'status' => 'published']);
    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'funnel_revenue' => 200,
        'created_at' => now()->subDay(),
    ]);
    $account = seedAdAccount();
    FacebookAdInsight::create([
        'facebook_ad_account_id' => $account->id,
        'date' => now()->subDay()->toDateString(),
        'campaign_id' => 'C1',
        'spend' => 80,
    ]);

    FunnelStudioServer::actingAs($user)
        ->tool(DailyReportTool::class, ['days' => 30])
        ->assertOk()
        ->assertSee('86.4') // 80 spend + 8% SST
        ->assertSee('2.5');  // ROAS = 200 / 80
});

it('scopes funnels to the fighter who owns them', function () {
    $me = User::factory()->create(['role' => 'fighter']);
    $other = User::factory()->create(['role' => 'fighter']);
    Funnel::factory()->create(['user_id' => $me->id, 'name' => 'My Funnel']);
    Funnel::factory()->create(['user_id' => $other->id, 'name' => 'Rival Funnel']);

    FunnelStudioServer::actingAs($me)
        ->tool(ListFunnelsTool::class, [])
        ->assertOk()
        ->assertSee('My Funnel')
        ->assertDontSee('Rival Funnel');
});

it('hides ad spend from fighters without their own connected account', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    // Company-wide account (no owner) — a fighter must NOT see its spend.
    $account = seedAdAccount();
    FacebookAdInsight::create([
        'facebook_ad_account_id' => $account->id,
        'date' => now()->subDay()->toDateString(),
        'campaign_id' => 'C1',
        'spend' => 500,
    ]);

    FunnelStudioServer::actingAs($fighter)
        ->tool(FacebookAdsInsightsTool::class, ['days' => 30])
        ->assertOk()
        ->assertSee('No Facebook ad accounts are connected')
        ->assertDontSee('500');
});
