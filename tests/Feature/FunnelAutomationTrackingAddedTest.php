<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelAutomation;
use App\Models\FunnelAutomationAction;
use App\Models\FunnelAutomationLog;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Http;

function makeTrackingAutomation(Funnel $funnel): FunnelAutomation
{
    $automation = FunnelAutomation::factory()->create([
        'funnel_id' => $funnel->id,
        'trigger_type' => 'tracking_added',
        'is_active' => true,
    ]);

    FunnelAutomationAction::factory()->create([
        'automation_id' => $automation->id,
        'action_type' => 'webhook',
        'action_config' => [
            'url' => 'https://hooks.test/tracking',
            'method' => 'POST',
        ],
        'delay_minutes' => 0,
    ]);

    return $automation;
}

test('keying in a tracking number fires the tracking_added automation for a funnel order', function () {
    Http::fake(['hooks.test/*' => Http::response(['ok' => true], 200)]);

    $funnel = Funnel::factory()->create();
    $order = ProductOrder::factory()->create(['tracking_id' => null]);
    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'product_order_id' => $order->id,
    ]);

    $automation = makeTrackingAutomation($funnel);

    $order->update(['tracking_id' => 'TRK-123']);

    expect(FunnelAutomationLog::where('automation_id', $automation->id)->exists())->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/tracking');
});

test('automation does not fire when a non-funnel order gets a tracking number', function () {
    Http::fake(['hooks.test/*' => Http::response(['ok' => true], 200)]);

    $funnel = Funnel::factory()->create();
    $automation = makeTrackingAutomation($funnel);

    // Order with no linked FunnelOrder — must not trigger the funnel automation.
    $order = ProductOrder::factory()->create(['tracking_id' => null]);
    $order->update(['tracking_id' => 'TRK-999']);

    expect(FunnelAutomationLog::where('automation_id', $automation->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('automation fires only on the first key-in, not on subsequent tracking edits', function () {
    Http::fake(['hooks.test/*' => Http::response(['ok' => true], 200)]);

    $funnel = Funnel::factory()->create();
    $order = ProductOrder::factory()->create(['tracking_id' => null]);
    FunnelOrder::factory()->create([
        'funnel_id' => $funnel->id,
        'product_order_id' => $order->id,
    ]);

    $automation = makeTrackingAutomation($funnel);

    $order->update(['tracking_id' => 'TRK-123']);   // empty -> filled: fires
    $order->update(['tracking_id' => 'TRK-456']);   // filled -> filled: must not re-fire

    expect(FunnelAutomationLog::where('automation_id', $automation->id)->count())->toBe(1);
});
