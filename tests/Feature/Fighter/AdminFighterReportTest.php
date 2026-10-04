<?php

declare(strict_types=1);

use App\Models\FacebookAdAccount;
use App\Models\FacebookAdConnection;
use App\Models\FacebookAdInsight;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Fighter\FighterPerformanceReport;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function reportFighter(string $name): User
{
    $user = User::factory()->create(['role' => 'fighter', 'name' => $name]);
    app(FighterProvisioner::class)->ensureSalesSource($user);

    return $user->fresh();
}

function reportSpend(User $owner, float $spend, string $date): void
{
    $connection = FacebookAdConnection::create([
        'user_id' => $owner->id, 'name' => 'BM', 'business_manager_id' => (string) $owner->id,
        'access_token' => 't', 'status' => 'connected',
    ]);
    $account = FacebookAdAccount::create(['facebook_ad_connection_id' => $connection->id, 'account_id' => 'a'.$owner->id, 'name' => 'Acc']);
    FacebookAdInsight::create(['facebook_ad_account_id' => $account->id, 'date' => $date, 'campaign_id' => 'C', 'spend' => $spend]);
}

it('aggregates funnel sales, manual sales, spend, ROAS and net per fighter', function () {
    $ali = reportFighter('Ali');
    $abu = reportFighter('Abu');
    $today = now()->toDateString();

    $funnel = Funnel::factory()->create(['user_id' => $ali->id, 'status' => 'published']);
    $funnelOrder = ProductOrder::factory()->create(['sales_source_id' => $ali->sales_source_id, 'total_amount' => 100]);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'product_order_id' => $funnelOrder->id, 'funnel_revenue' => 100, 'order_type' => 'main']);
    ProductOrder::factory()->create(['sales_source_id' => $ali->sales_source_id, 'total_amount' => 50]);
    reportSpend($ali, 40, $today);
    reportSpend(User::factory()->create(['role' => 'admin']), 999, $today); // company/other spend ignored

    ProductOrder::factory()->create(['sales_source_id' => $abu->sales_source_id, 'total_amount' => 30, 'created_at' => now()->subMonths(3)]);

    $report = app(FighterPerformanceReport::class)->build(now()->startOfMonth(), now());
    $aliRow = $report['rows']->firstWhere('id', $ali->id);
    $abuRow = $report['rows']->firstWhere('id', $abu->id);

    expect($aliRow)->toMatchArray([
        'funnels' => 1, 'published_funnels' => 1,
        'funnel_sales' => 100.0, 'funnel_orders' => 1,
        'manual_sales' => 50.0, 'manual_orders' => 1,
        'total_sales' => 150.0, 'total_orders' => 2,
        'spend' => 40.0, 'spend_with_sst' => 43.2, 'roas' => 2.5, 'net' => 56.8,
        'bm_connected' => 1,
    ])
        ->and($abuRow['total_sales'])->toBe(0.0)
        ->and($abuRow['roas'])->toBeNull()
        ->and($report['totals'])->toMatchArray(['fighters' => 2, 'active_fighters' => 1, 'total_sales' => 150.0, 'spend' => 40.0, 'roas' => 2.5]);
});

it('includes spend on the last day of the range', function () {
    $ali = reportFighter('Ali');
    reportSpend($ali, 10, now()->toDateString());

    $report = app(FighterPerformanceReport::class)->build(now()->subDays(6), now());

    expect($report['rows']->first()['spend'])->toBe(10.0);
});

it('renders the admin report and sorts fighters', function () {
    $ali = reportFighter('Ali Kecil');
    $abu = reportFighter('Abu Besar');
    ProductOrder::factory()->create(['sales_source_id' => $abu->sales_source_id, 'total_amount' => 500]);
    ProductOrder::factory()->create(['sales_source_id' => $ali->sales_source_id, 'total_amount' => 20]);

    $this->actingAs(User::factory()->admin()->create());

    Volt::test('admin.reports.fighters')
        ->assertSee('Fighter Performance Report')
        ->assertSeeInOrder(['Abu Besar', 'Ali Kecil'])
        ->set('search', 'Ali')
        ->assertSee('Ali Kecil')
        ->assertDontSee('Abu Besar');
});

it('is admin only', function () {
    $this->actingAs(User::factory()->create(['role' => 'employee']))
        ->get('/admin/reports/fighters')
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/reports/fighters')
        ->assertOk();
});
