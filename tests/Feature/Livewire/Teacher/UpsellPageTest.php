<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'teacher']);
});

it('shows the teachers own upsell stats', function () {
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$this->teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => now()->toDateString(),
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $this->actingAs($this->teacher);
    Volt::test('teacher.upsell')
        ->assertSee('1,000.00')   // paid revenue
        ->assertSee('100.00');     // commission earned (10%)
});

it('does not show another teachers upsell data', function () {
    $otherTeacher = User::factory()->create(['role' => 'teacher']);
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$otherTeacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => now()->toDateString(),
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 5000,
    ]);

    $this->actingAs($this->teacher);
    Volt::test('teacher.upsell')
        ->assertDontSee('5,000.00');
});

it('splits revenue equally for multi-teacher sessions', function () {
    $co = User::factory()->create(['role' => 'teacher']);
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$this->teacher->id, $co->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => now()->toDateString(),
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $this->actingAs($this->teacher);
    Volt::test('teacher.upsell')
        ->assertSee('500.00')  // teachers share of revenue (1000/2)
        ->assertSee('50.00');   // commission share (100/2)
});

it('reacts to date range changes', function () {
    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$this->teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => '2026-02-15',
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 500,
    ]);

    $this->actingAs($this->teacher);
    Volt::test('teacher.upsell')
        ->set('dateFrom', '2026-02-01')
        ->set('dateTo', '2026-02-28')
        ->assertSee('500.00');
});

it('shows empty state when no upsell activity', function () {
    $this->actingAs($this->teacher);
    Volt::test('teacher.upsell')
        ->assertSee('No upsell');
});

it('blocks non-teachers from accessing the page', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)
        ->get(route('teacher.upsell'))
        ->assertForbidden();
});
