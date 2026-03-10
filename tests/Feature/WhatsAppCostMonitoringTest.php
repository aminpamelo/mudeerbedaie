<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppCostAnalytics;
use App\Services\SettingsService;
use App\Services\WhatsApp\WhatsAppCostService;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->create(['role' => 'teacher']);
});

it('allows admin to access cost monitoring page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.whatsapp.costs'))
        ->assertOk();
});

it('denies non-admin access to cost monitoring page', function () {
    $this->actingAs($this->teacher)
        ->get(route('admin.whatsapp.costs'))
        ->assertForbidden();
});

it('displays summary cards with correct data', function () {
    WhatsAppCostAnalytics::create([
        'date' => now()->toDateString(),
        'country_code' => 'MY',
        'pricing_category' => 'UTILITY',
        'message_volume' => 50,
        'cost_usd' => 0.70,
        'cost_myr' => 3.15,
        'synced_at' => now(),
    ]);

    WhatsAppCostAnalytics::create([
        'date' => now()->toDateString(),
        'country_code' => 'MY',
        'pricing_category' => 'MARKETING',
        'message_volume' => 10,
        'cost_usd' => 0.86,
        'cost_myr' => 3.87,
        'synced_at' => now(),
    ]);

    $this->actingAs($this->admin);

    Volt::test('admin.whatsapp-cost-monitoring')
        ->assertSee('7.02')  // Total cost MYR (3.15 + 3.87)
        ->assertSee('60');    // Total messages (50 + 10)
});

it('shows category breakdown', function () {
    WhatsAppCostAnalytics::create([
        'date' => now()->toDateString(),
        'country_code' => 'MY',
        'pricing_category' => 'UTILITY',
        'message_volume' => 100,
        'cost_usd' => 1.40,
        'cost_myr' => 6.30,
        'synced_at' => now(),
    ]);

    $this->actingAs($this->admin);

    Volt::test('admin.whatsapp-cost-monitoring')
        ->assertSee('6.30')
        ->assertSee('100');
});

it('filters by period correctly', function () {
    // Create data for today
    WhatsAppCostAnalytics::create([
        'date' => now()->toDateString(),
        'country_code' => 'MY',
        'pricing_category' => 'UTILITY',
        'message_volume' => 20,
        'cost_usd' => 0.28,
        'cost_myr' => 1.26,
        'synced_at' => now(),
    ]);

    // Create data for last month (should not show in "today" filter)
    WhatsAppCostAnalytics::create([
        'date' => now()->subMonth()->toDateString(),
        'country_code' => 'MY',
        'pricing_category' => 'UTILITY',
        'message_volume' => 500,
        'cost_usd' => 7.00,
        'cost_myr' => 31.50,
        'synced_at' => now(),
    ]);

    $this->actingAs($this->admin);

    Volt::test('admin.whatsapp-cost-monitoring')
        ->call('setPeriod', 'today')
        ->assertSee('1.26')
        ->assertDontSee('31.50');
});

it('returns correct estimated cost from config', function () {
    $costService = app(WhatsAppCostService::class);

    expect($costService->estimateCost('marketing', 'MY'))->toBe(0.0860);
    expect($costService->estimateCost('utility', 'MY'))->toBe(0.0140);
    expect($costService->estimateCost('authentication', 'MY'))->toBe(0.0140);
    expect($costService->estimateCost('service', 'MY'))->toBe(0.0);
});

it('converts USD to MYR correctly', function () {
    $costService = app(WhatsAppCostService::class);

    expect($costService->convertToMyr(1.00))->toBe(4.5);
    expect($costService->convertToMyr(0.086))->toBe(0.387);
});

it('syncs daily analytics from meta api', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->with('meta_waba_id')->andReturn('test-waba-123');
    $settings->shouldReceive('get')->with('meta_access_token')->andReturn('test-token');
    $settings->shouldReceive('get')->with('meta_api_version', 'v21.0')->andReturn('v21.0');

    $costService = new WhatsAppCostService($settings);

    $yesterday = now()->subDay();

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'data_points' => [
                        [
                            'start' => $yesterday->copy()->startOfDay()->timestamp,
                            'end' => $yesterday->copy()->endOfDay()->timestamp,
                            'country' => 'MY',
                            'pricing_category' => 'UTILITY',
                            'volume' => 25,
                            'cost' => 0.35,
                        ],
                        [
                            'start' => $yesterday->copy()->startOfDay()->timestamp,
                            'end' => $yesterday->copy()->endOfDay()->timestamp,
                            'country' => 'MY',
                            'pricing_category' => 'MARKETING',
                            'volume' => 5,
                            'cost' => 0.43,
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $count = $costService->syncDailyAnalytics($yesterday);

    expect($count)->toBe(2);
    expect(WhatsAppCostAnalytics::count())->toBe(2);

    $utility = WhatsAppCostAnalytics::where('pricing_category', 'UTILITY')->first();
    expect($utility->message_volume)->toBe(25);
    expect((float) $utility->cost_usd)->toBe(0.35);
});
