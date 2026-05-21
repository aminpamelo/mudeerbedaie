<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Volt\Volt;

it('shows the upsell card on dashboard for active teacher', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    Teacher::factory()->create(['user_id' => $teacher->id]);

    $session = ClassSession::factory()->create([
        'upsell_funnel_ids' => [1],
        'upsell_teacher_ids' => [$teacher->id],
        'upsell_teacher_commission_rate' => 10,
        'session_date' => now()->toDateString(),
    ]);
    $paid = ProductOrder::factory()->create(['payment_status' => 'paid']);
    FunnelOrder::factory()->create([
        'class_session_id' => $session->id,
        'product_order_id' => $paid->id,
        'funnel_revenue' => 1000,
    ]);

    $this->actingAs($teacher);
    Volt::test('teacher.dashboard')
        ->assertSee('Upsell')   // card heading
        ->assertSee('100.00');  // commission
});
