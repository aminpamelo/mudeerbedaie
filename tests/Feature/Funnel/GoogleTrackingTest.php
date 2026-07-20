<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\User;
use App\Services\Funnel\GoogleTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function googleFunnel(array $google): Funnel
{
    return Funnel::factory()->make([
        'settings' => ['pixel_settings' => ['google' => $google]],
    ]);
}

function renderGooglePartial(Funnel $funnel, string $stepType, array $extra = []): string
{
    $step = (object) ['type' => $stepType, 'name' => 'Step'];

    return view('funnel.partials.google-tracking', array_merge([
        'funnel' => $funnel,
        'step' => $step,
        'viewContentData' => null,
        'checkoutData' => null,
        'purchaseData' => null,
    ], $extra))->render();
}

/*
|--------------------------------------------------------------------------
| Service
|--------------------------------------------------------------------------
*/

it('is disabled unless enabled with at least one destination id', function () {
    $service = app(GoogleTrackingService::class);

    expect($service->isEnabled(googleFunnel([])))->toBeFalse()
        ->and($service->isEnabled(googleFunnel(['enabled' => true])))->toBeFalse()
        ->and($service->isEnabled(googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123'])))->toBeTrue()
        ->and($service->isEnabled(googleFunnel(['enabled' => true, 'ads_conversion_id' => 'AW-999'])))->toBeTrue()
        ->and($service->isEnabled(googleFunnel(['enabled' => false, 'ga4_measurement_id' => 'G-ABC123'])))->toBeFalse();
});

it('reports GA4 and Google Ads availability independently', function () {
    $service = app(GoogleTrackingService::class);
    $ga4Only = googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123']);
    $adsOnly = googleFunnel(['enabled' => true, 'ads_conversion_id' => 'AW-999']);

    expect($service->hasGa4($ga4Only))->toBeTrue()
        ->and($service->hasGoogleAds($ga4Only))->toBeFalse()
        ->and($service->hasGa4($adsOnly))->toBeFalse()
        ->and($service->hasGoogleAds($adsOnly))->toBeTrue();
});

it('builds the Google Ads purchase send_to target from id + label', function () {
    $service = app(GoogleTrackingService::class);

    expect($service->adsPurchaseSendTo(googleFunnel(['enabled' => true, 'ads_conversion_id' => 'AW-999', 'ads_purchase_label' => 'LabelX'])))
        ->toBe('AW-999/LabelX')
        ->and($service->adsPurchaseSendTo(googleFunnel(['enabled' => true, 'ads_conversion_id' => 'AW-999'])))
        ->toBe('AW-999')
        ->and($service->adsPurchaseSendTo(googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123'])))
        ->toBeNull();
});

it('defaults events to enabled but honours explicit false', function () {
    $service = app(GoogleTrackingService::class);
    $funnel = googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123', 'events' => ['page_view' => false]]);

    expect($service->isEventEnabled($funnel, 'page_view'))->toBeFalse()
        ->and($service->isEventEnabled($funnel, 'purchase'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Partial rendering
|--------------------------------------------------------------------------
*/

it('renders nothing when Google tracking is disabled', function () {
    $html = renderGooglePartial(googleFunnel(['enabled' => false, 'ga4_measurement_id' => 'G-ABC123']), 'landing');

    expect(trim($html))->toBe('')
        ->and($html)->not->toContain('googletagmanager.com/gtag');
});

it('emits the gtag loader + GA4 config when enabled', function () {
    $html = renderGooglePartial(
        googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123']),
        'landing',
        ['viewContentData' => ['content_ids' => ['5'], 'value' => 49.9, 'currency' => 'MYR']],
    );

    expect($html)->toContain('googletagmanager.com/gtag/js?id=G-ABC123')
        ->and($html)->toContain("gtag('config', \"G-ABC123\"")
        ->and($html)->toContain("gtag('event', 'view_item'");
});

it('sets send_page_view false when the page_view event is off', function () {
    $html = renderGooglePartial(
        googleFunnel(['enabled' => true, 'ga4_measurement_id' => 'G-ABC123', 'events' => ['page_view' => false]]),
        'landing',
    );

    expect($html)->toContain('send_page_view: false');
});

it('fires GA4 purchase and Google Ads conversion on the thank-you step', function () {
    $funnel = googleFunnel([
        'enabled' => true,
        'ga4_measurement_id' => 'G-ABC123',
        'ads_conversion_id' => 'AW-999',
        'ads_purchase_label' => 'LabelX',
    ]);

    $html = renderGooglePartial($funnel, 'thankyou', [
        'purchaseData' => [
            'transaction_id' => 'ORD-777',
            'value' => 120.5,
            'currency' => 'MYR',
            'contents' => [['id' => '5', 'quantity' => 2, 'item_price' => 60.25]],
        ],
    ]);

    expect($html)->toContain("gtag('event', 'purchase'")
        ->and($html)->toContain("transaction_id: 'ORD-777'")
        ->and($html)->toContain("gtag('event', 'conversion'")
        ->and($html)->toContain("send_to: 'AW-999/LabelX'");
});

it('skips the GA4 purchase block when only Google Ads is configured', function () {
    $funnel = googleFunnel(['enabled' => true, 'ads_conversion_id' => 'AW-999', 'ads_purchase_label' => 'LabelX']);

    $html = renderGooglePartial($funnel, 'thankyou', [
        'purchaseData' => ['transaction_id' => 'ORD-1', 'value' => 10, 'currency' => 'MYR', 'contents' => []],
    ]);

    expect($html)->toContain("gtag('event', 'conversion'")
        ->and($html)->not->toContain("gtag('event', 'purchase'");
});

/*
|--------------------------------------------------------------------------
| Persistence via the funnel update API (shared blob with Facebook)
|--------------------------------------------------------------------------
*/

it('saves google settings via the API without clobbering facebook', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $funnel = Funnel::factory()->create([
        'user_id' => $user->id,
        'settings' => ['pixel_settings' => ['facebook' => ['enabled' => true, 'pixel_id' => '123456789']]],
    ]);

    $newSettings = $funnel->settings;
    $newSettings['pixel_settings']['google'] = [
        'enabled' => true,
        'ga4_measurement_id' => 'G-XYZ789',
        'ads_conversion_id' => 'AW-111',
        'ads_purchase_label' => 'Lbl',
        'events' => ['page_view' => true, 'purchase' => true],
    ];

    $this->actingAs($user)
        ->putJson("/api/v1/funnels/{$funnel->uuid}", ['settings' => $newSettings])
        ->assertOk();

    $funnel->refresh();

    expect($funnel->settings['pixel_settings']['google']['ga4_measurement_id'])->toBe('G-XYZ789')
        ->and($funnel->settings['pixel_settings']['google']['enabled'])->toBeTrue()
        // Facebook settings must survive the Google save.
        ->and($funnel->settings['pixel_settings']['facebook']['pixel_id'])->toBe('123456789')
        ->and(app(GoogleTrackingService::class)->isEnabled($funnel))->toBeTrue();
});
