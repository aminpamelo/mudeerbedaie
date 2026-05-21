<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\UpsellCommissionPayout;
use App\Models\User;
use Livewire\Volt\Volt;

it('renders by-teacher section with paid revenue and commission', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create(['name' => 'Top Teacher']);

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 15,
    ]);

    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('Performance by Teacher')
        ->assertSee('Top Teacher')
        ->assertSee('1,000.00')  // paid revenue
        ->assertSee('150.00');   // commission (15% of 1000)
});

it('shows multiple teachers sorted by commission descending', function () {
    $admin = User::factory()->admin()->create();
    $top = User::factory()->create(['name' => 'AAA First Teacher']);
    $second = User::factory()->create(['name' => 'BBB Second Teacher']);

    // top earns RM 200 commission, second earns RM 50
    $session1 = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$top->id],
        'upsell_teacher_commission_rate' => 10,
    ]);
    $session2 = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(3),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$second->id],
        'upsell_teacher_commission_rate' => 10,
    ]);

    $paid1 = ProductOrder::factory()->create(['payment_status' => 'paid']);
    $paid2 = ProductOrder::factory()->create(['payment_status' => 'paid']);

    FunnelOrder::factory()->create([
        'class_session_id' => $session1->id,
        'product_order_id' => $paid1->id,
        'funnel_revenue' => 2000,
    ]);
    FunnelOrder::factory()->create([
        'class_session_id' => $session2->id,
        'product_order_id' => $paid2->id,
        'funnel_revenue' => 500,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSeeInOrder(['AAA First Teacher', 'BBB Second Teacher']); // top first
});

it('shows empty state when no teacher data', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('No teacher data in selected period');
});

it('shows commission paid and pending columns', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create(['name' => 'Paid Teacher']);

    $session = ClassSession::factory()->create([
        'session_date' => now()->startOfMonth()->addDays(2),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
    ]);

    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $payout = UpsellCommissionPayout::factory()->paid()->create([
        'teacher_user_id' => $teacher->id,
    ]);
    $payout->sessions()->create([
        'class_session_id' => $session->id,
        'paid_revenue' => 1000,
        'commission_rate' => 10,
        'commission_amount' => 60,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.upsell-dashboard')
        ->assertSee('Paid Teacher')
        ->assertSee('100.00')   // commission earned
        ->assertSee('60.00')    // commission paid
        ->assertSee('40.00');   // commission pending
});
