<?php

declare(strict_types=1);

use App\Mcp\Servers\FunnelStudioServer;
use App\Mcp\Tools\TiktokShopDailyGmvTool;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\TiktokShopDailyPerformance;
use App\Models\User;
use App\Services\TikTok\TikTokAnalyticsSyncService;

beforeEach(function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $this->amar = PlatformAccount::factory()->create(['platform_id' => $platform->id, 'name' => 'BeDaie Ustaz Amar', 'is_active' => true]);
    $this->ilmu = PlatformAccount::factory()->create(['platform_id' => $platform->id, 'name' => 'ILMU AGAMA', 'is_active' => true]);
});

it('stores one row per day from the TikTok shop performance API', function () {
    $analytics = new class
    {
        public array $calls = [];

        public function getShopPerformance(array $params): array
        {
            $this->calls[] = $params;

            return ['performance' => ['intervals' => [
                [
                    'start_date' => '2026-10-06', 'end_date' => '2026-10-07',
                    'gmv' => ['amount' => '12847.20', 'currency' => 'MYR'],
                    'gmv_breakdowns' => [
                        ['type' => 'LIVE', 'amount' => '12000.00'], ['type' => 'VIDEO', 'amount' => '500.00'], ['type' => 'PRODUCT_CARD', 'amount' => '347.20'],
                    ],
                    'orders' => 136, 'sku_orders' => 140, 'units_sold' => 136, 'buyers' => 0,
                    'avg_order_value' => ['amount' => '94.46'], 'refunds' => ['amount' => '120.00'], 'cancellations_and_returns' => 3,
                ],
            ]]];
        }
    };
    $client = (object) ['Analytics' => $analytics];

    $service = Mockery::mock(TikTokAnalyticsSyncService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getClient')->andReturn($client);

    $stored = $service->syncShopDailyPerformance($this->amar, 3);
    $service->syncShopDailyPerformance($this->amar, 3); // re-sync updates, no duplicates

    expect($stored)->toBe(1)
        ->and(TiktokShopDailyPerformance::count())->toBe(1)
        ->and($analytics->calls[0]['granularity'])->toBe('1D');

    $row = TiktokShopDailyPerformance::sole();
    expect($row->date->toDateString())->toBe('2026-10-06')
        ->and((float) $row->gmv)->toBe(12847.20)
        ->and((float) $row->gmv_live)->toBe(12000.00)
        ->and($row->orders)->toBe(136)
        ->and((float) $row->refunds)->toBe(120.00);
});

it('splits long backfills into 7-day windows', function () {
    $analytics = new class
    {
        public array $calls = [];

        public function getShopPerformance(array $params): array
        {
            $this->calls[] = $params;

            return ['performance' => ['intervals' => []]];
        }
    };
    $service = Mockery::mock(TikTokAnalyticsSyncService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getClient')->andReturn((object) ['Analytics' => $analytics]);

    $service->syncShopDailyPerformance($this->amar, 30);

    expect(count($analytics->calls))->toBe(5);
    foreach ($analytics->calls as $call) {
        expect(now()->parse($call['start_date_ge'])->diffInDays(now()->parse($call['end_date_lt'])))->toBeLessThanOrEqual(7);
    }
});

it('returns daily GMV per shop for staff through MCP', function () {
    TiktokShopDailyPerformance::factory()->create(['platform_account_id' => $this->amar->id, 'date' => now()->subDays(2)->toDateString(), 'gmv' => 14589.23, 'orders' => 181]);
    TiktokShopDailyPerformance::factory()->create(['platform_account_id' => $this->amar->id, 'date' => now()->subDay()->toDateString(), 'gmv' => 12847.20, 'orders' => 136]);
    TiktokShopDailyPerformance::factory()->create(['platform_account_id' => $this->ilmu->id, 'date' => now()->subDay()->toDateString(), 'gmv' => 3000, 'orders' => 40]);
    TiktokShopDailyPerformance::factory()->create(['platform_account_id' => $this->amar->id, 'date' => now()->subDays(20)->toDateString(), 'gmv' => 99999]);

    $employee = User::factory()->create(['role' => 'employee']);

    FunnelStudioServer::actingAs($employee)
        ->tool(TiktokShopDailyGmvTool::class, ['shop' => 'ustaz amar', 'period' => '7d'])
        ->assertOk()
        ->assertSee('BeDaie Ustaz Amar')
        ->assertSee('27436.43') // 14589.23 + 12847.20, the 20-day-old row excluded
        ->assertSee('12847.2')
        ->assertDontSee('ILMU AGAMA');

    FunnelStudioServer::actingAs($employee)
        ->tool(TiktokShopDailyGmvTool::class, [])
        ->assertOk()
        ->assertSee('ILMU AGAMA')
        ->assertSee('30436.43');
});

it('rejects unknown shops and non-staff users', function () {
    FunnelStudioServer::actingAs(User::factory()->create(['role' => 'admin']))
        ->tool(TiktokShopDailyGmvTool::class, ['shop' => 'kedai tak wujud'])
        ->assertHasErrors();

    FunnelStudioServer::actingAs(User::factory()->create(['role' => 'fighter']))
        ->tool(TiktokShopDailyGmvTool::class, [])
        ->assertHasErrors();
});
