<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

it('only counts paid funnel orders toward commission', function () {
    $admin = User::factory()->admin()->create();

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_commission_rate' => 10.0,
    ]);

    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    $pending = ProductOrder::factory()->create(['payment_status' => 'pending']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 100,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $pending->id,
        'funnel_revenue' => 200,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.upsell-dashboard');

    // Look for total commission rendered in the page.
    // 10% of 100 (paid only) = RM 10.00, NOT 30 (which would be 10% of 100+200)
    $component->assertSee('10.00')
        ->assertDontSee('30.00');
});

it('excludes failed funnel orders from revenue', function () {
    $admin = User::factory()->admin()->create();

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_commission_rate' => 10.0,
    ]);

    $failed = ProductOrder::factory()->create(['payment_status' => 'failed']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $failed->id,
        'funnel_revenue' => 500,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.upsell-dashboard');
    $component->assertSee('0.00')
        ->assertDontSee('500.00')
        ->assertDontSee('50.00');
});
