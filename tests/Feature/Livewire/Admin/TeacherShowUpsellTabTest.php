<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Volt\Volt;

it('shows upsell stats for the teacher', function () {
    $admin = User::factory()->admin()->create();
    $teacherUser = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

    $session = ClassSession::factory()->create([
        'session_date' => now()->subDays(5),
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacherUser->id],
        'upsell_teacher_commission_rate' => 15,
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $this->actingAs($admin);
    Volt::test('admin.teacher-show', ['teacher' => $teacher])
        ->assertSee('Upsell Performance')
        ->assertSee('1,000.00')  // paid revenue
        ->assertSee('150.00');   // commission (15% of 1000)
});

it('reacts to date range changes', function () {
    $admin = User::factory()->admin()->create();
    $teacherUser = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacherUser->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-02-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 500,
    ]);

    $this->actingAs($admin);

    // Outside range
    Volt::test('admin.teacher-show', ['teacher' => $teacher])
        ->set('upsellDateFrom', '2026-05-01')
        ->set('upsellDateTo', '2026-05-31')
        ->assertDontSee('500.00');

    // Inside range
    Volt::test('admin.teacher-show', ['teacher' => $teacher])
        ->set('upsellDateFrom', '2026-02-01')
        ->set('upsellDateTo', '2026-02-28')
        ->assertSee('500.00');
});

it('shows empty state when teacher has no upsell sessions', function () {
    $admin = User::factory()->admin()->create();
    $teacherUser = User::factory()->create();
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

    $this->actingAs($admin);
    Volt::test('admin.teacher-show', ['teacher' => $teacher])
        ->assertSee('No upsell sessions in selected period');
});
