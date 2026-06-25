<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrderBump;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function checkoutStep(): FunnelStep
{
    $funnel = Funnel::factory()->create();

    return FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
        'settings' => [],
    ]);
}

function makeBump(FunnelStep $step, bool $checkedByDefault): FunnelOrderBump
{
    return FunnelOrderBump::create([
        'funnel_step_id' => $step->id,
        'headline' => 'Offer Istimewa',
        'price' => 30,
        'is_checked_by_default' => $checkedByDefault,
        'sort_order' => 0,
    ]);
}

it('does not pre-check an order bump when "checked by default" is off', function () {
    $step = checkoutStep();
    makeBump($step, false);

    Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->assertSet('selectedBumps', []);
});

it('pre-checks an order bump when "checked by default" is on', function () {
    $step = checkoutStep();
    $bump = makeBump($step, true);

    Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->assertSet('selectedBumps', [$bump->id => true]);
});

it('ignores a stale cart bump when "checked by default" is off', function () {
    $step = checkoutStep();
    $bump = makeBump($step, false);

    $session = FunnelSession::factory()->create(['funnel_id' => $step->funnel_id]);
    FunnelCart::create([
        'funnel_id' => $step->funnel_id,
        'session_id' => $session->id,
        'step_id' => $step->id,
        'cart_data' => ['products' => [], 'bumps' => [$bump->id => true]],
        'total_amount' => 30,
        'recovery_status' => 'pending',
    ]);

    Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step, 'session' => $session->fresh()])
        ->assertSet('selectedBumps', []);
});
