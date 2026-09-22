<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastConversion;
use App\Models\BroadcastLog;
use App\Models\Order;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function cvStudent(string $email = 'buyer@gmail.com'): Student
{
    $user = User::factory()->create(['email' => $email]);

    return Student::factory()->create(['user_id' => $user->id]);
}

function cvBroadcast(array $attrs = []): Broadcast
{
    return Broadcast::create(array_merge([
        'name' => 'Promo Blast',
        'type' => 'standard',
        'status' => 'sent',
        'from_name' => 'Sender',
        'from_email' => 'from@example.com',
        'subject' => 'Deal',
        'content' => 'Buy now',
        'editor_type' => 'text',
        'total_recipients' => 1,
    ], $attrs));
}

function cvLog(Broadcast $b, Student $s, array $attrs = []): BroadcastLog
{
    return BroadcastLog::create(array_merge([
        'broadcast_id' => $b->id,
        'student_id' => $s->id,
        'email' => $s->user->email,
        'status' => 'sent',
        'sent_at' => now()->subDay(),
    ], $attrs));
}

it('sets a bcast_ref cookie when a recipient clicks an email link', function () {
    $b = cvBroadcast();
    $s = cvStudent();
    $log = cvLog($b, $s);

    $res = $this->get(URL::signedRoute('email.track.click', ['log' => $log->id, 'u' => 'https://kelasify.com/shop']));

    $res->assertRedirect('https://kelasify.com/shop');
    $cookieNames = collect($res->headers->getCookies())->map(fn ($c) => $c->getName());
    expect($cookieNames)->toContain('bcast_ref');
});

it('attributes a product purchase to a broadcast the buyer clicked', function () {
    $s = cvStudent();
    $b = cvBroadcast();
    cvLog($b, $s, ['clicked_at' => now()->subDay()]);

    $order = ProductOrder::factory()->create([
        'student_id' => $s->id,
        'customer_id' => $s->user_id,
        'total_amount' => 150.00,
    ]);
    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    $conv = BroadcastConversion::first();
    expect($conv)->not->toBeNull()
        ->and($conv->broadcast_id)->toBe($b->id)
        ->and($conv->order_type)->toBe('product')
        ->and((int) $conv->order_id)->toBe($order->id)
        ->and((float) $conv->amount)->toBe(150.00)
        ->and($conv->attribution)->toBe('clicked');
});

it('attributes as assisted when the buyer received but did not click', function () {
    $s = cvStudent();
    $b = cvBroadcast();
    cvLog($b, $s); // no clicked_at

    $order = ProductOrder::factory()->create(['student_id' => $s->id, 'total_amount' => 80.00]);
    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    expect(BroadcastConversion::first()->attribution)->toBe('assisted');
});

it('does not attribute a purchase outside the 7-day window', function () {
    $s = cvStudent();
    $b = cvBroadcast();
    cvLog($b, $s, ['sent_at' => now()->subDays(30), 'clicked_at' => now()->subDays(30)]);

    $order = ProductOrder::factory()->create(['student_id' => $s->id, 'total_amount' => 100.00]);
    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    expect(BroadcastConversion::count())->toBe(0);
});

it('does not attribute a purchase by a non-recipient', function () {
    $s = cvStudent();
    // no broadcast log for this student at all

    $order = ProductOrder::factory()->create(['student_id' => $s->id, 'total_amount' => 100.00]);
    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    expect(BroadcastConversion::count())->toBe(0);
});

it('attributes a course/subscription purchase too', function () {
    $s = cvStudent();
    $b = cvBroadcast();
    cvLog($b, $s, ['clicked_at' => now()->subDay()]);

    $order = Order::factory()->pending()->create(['student_id' => $s->id, 'amount' => 99.00]);
    $order->update(['status' => 'paid', 'paid_at' => now()]);

    $conv = BroadcastConversion::first();
    expect($conv)->not->toBeNull()
        ->and($conv->order_type)->toBe('course')
        ->and((float) $conv->amount)->toBe(99.00)
        ->and($conv->broadcast_id)->toBe($b->id);
});

it('credits a purchase to only one campaign (no double counting)', function () {
    $s = cvStudent();
    $b = cvBroadcast();
    cvLog($b, $s, ['clicked_at' => now()->subDay()]);

    $order = ProductOrder::factory()->create(['student_id' => $s->id, 'total_amount' => 120.00]);
    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    // Re-running attribution must not create a second row.
    app(\App\Services\Broadcast\BroadcastConversionService::class)->attributeProductOrder($order->fresh());

    expect(BroadcastConversion::where('order_type', 'product')->where('order_id', $order->id)->count())->toBe(1);
});

it('shows attributed revenue and per-recipient purchases in the report tab', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $s = cvStudent();
    $b = cvBroadcast(['selected_students' => [$s->id]]);
    cvLog($b, $s, ['clicked_at' => now()->subDay()]);

    BroadcastConversion::create([
        'broadcast_id' => $b->id,
        'broadcast_log_id' => null,
        'student_id' => $s->id,
        'order_type' => 'product',
        'order_id' => 999,
        'amount' => 150.00,
        'attribution' => 'clicked',
        'converted_at' => now(),
    ]);

    Volt::actingAs($admin)->test('crm.broadcast-show', ['broadcast' => $b])
        ->set('activeTab', 'report')
        ->assertSee('Revenue')
        ->assertSee('RM150.00');
});
