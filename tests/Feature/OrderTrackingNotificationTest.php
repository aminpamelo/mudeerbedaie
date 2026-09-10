<?php

declare(strict_types=1);

use App\Mail\OrderShippedNotification;
use App\Models\CekbotSession;
use App\Models\OrderTrackingNotification;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\Orders\TrackingNotificationService;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('opens the manage view for an order that already has tracking', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'EP123',
        'status' => 'shipped',
        'customer_phone' => '60123456789',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingManage', $order->id)
        ->assertSet('showTrackingModal', true)
        ->assertSet('trackingMode', 'manage')
        ->assertSet('trackingStep', 'verify')
        ->assertSet('trackingNumber', 'EP123');
});

it('renders the verify step with the channel picker and actions', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
        'guest_email' => 'buyer@example.com',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', 'EP-RENDER-1')
        ->call('checkTracking')
        ->assertSee('Sila semak nombor ini betul')
        ->assertSee('Hantar kepada pelanggan melalui')
        ->assertSee('Tandakan pesanan sebagai')
        ->assertSee('EP-RENDER-1')
        ->assertSee('Simpan sahaja')
        // Switching to the Meta channel renders its branch (no approved templates here).
        ->set('trackingChannel', 'whatsapp_meta')
        ->assertSee('Tiada template diluluskan');
});

it('sends the tracking email and records a sent notification', function () {
    Mail::fake();

    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
        'status' => 'processing',
        'guest_email' => 'buyer@example.com',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', 'EP-EMAIL-1')
        ->call('checkTracking')
        ->set('trackingChannel', 'email')
        ->call('confirmAndSendTracking')
        ->assertSet('showTrackingModal', false);

    Mail::assertQueued(OrderShippedNotification::class, function ($mail) use ($order) {
        return $mail->hasTo('buyer@example.com') && $mail->order->is($order);
    });

    $notification = OrderTrackingNotification::where('product_order_id', $order->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->channel)->toBe('email');
    expect($notification->status)->toBe('sent');

    $fresh = $order->fresh();
    expect($fresh->tracking_id)->toBe('EP-EMAIL-1');
    expect($fresh->metadata['tracking_notified']['status'])->toBe('sent');
});

it('sends the tracking message via WAHA (Cekbot) and records a sent notification', function () {
    CekbotSession::create([
        'session_name' => 'default',
        'label' => 'Test Session',
        'status' => CekbotSession::STATUS_WORKING,
    ]);

    $this->mock(WahaSessionManager::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('sendText')
            ->once()
            ->with('default', '60123456789', Mockery::type('string'))
            ->andReturn(['success' => true, 'message_id' => 'wa-123', 'error' => null]);
    });

    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
        'status' => 'processing',
        'customer_phone' => '60123456789',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', 'EP-WA-1')
        ->call('checkTracking')
        ->set('trackingChannel', 'whatsapp_waha')
        ->call('confirmAndSendTracking')
        ->assertSet('showTrackingModal', false);

    $notification = OrderTrackingNotification::where('product_order_id', $order->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->channel)->toBe('whatsapp_waha');
    expect($notification->status)->toBe('sent');
    expect($notification->provider_message_id)->toBe('wa-123');
});

it('records a failed notification when no Cekbot session is active', function () {
    $this->mock(WahaSessionManager::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('sendText')->never();
    });

    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
        'customer_phone' => '60123456789',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', 'EP-WA-2')
        ->call('checkTracking')
        ->set('trackingChannel', 'whatsapp_waha')
        ->call('confirmAndSendTracking');

    $notification = OrderTrackingNotification::where('product_order_id', $order->id)->first();
    expect($notification->status)->toBe('failed');
    expect($notification->error)->toContain('Cekbot');
});

it('renders the shipped email markdown view without error', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'ORD-MAIL',
        'tracking_id' => 'EP-MAIL-9',
        'customer_name' => 'Ali',
        'shipping_provider' => 'jnt',
    ]);

    $html = (new OrderShippedNotification($order, "Salam Ali\nNo. Tracking: EP-MAIL-9"))->render();

    expect($html)->toContain('EP-MAIL-9')
        ->toContain('Jejak Pakej Anda');
});

it('builds a customer-friendly Malay default message with the tracking details', function () {
    $order = ProductOrder::factory()->create([
        'order_number' => 'ORD-XYZ',
        'tracking_id' => 'EP555',
        'customer_name' => 'Ali',
    ]);

    $message = app(TrackingNotificationService::class)->defaultMessage($order);

    expect($message)->toContain('ORD-XYZ')
        ->toContain('EP555')
        ->toContain('Ali');
});

it('marks every channel unavailable when the customer has no contact details', function () {
    $order = ProductOrder::factory()->create([
        'guest_email' => null,
        'customer_phone' => null,
        'customer_id' => null,
    ]);

    $channels = app(TrackingNotificationService::class)->channelAvailability($order);

    expect($channels['email']['available'])->toBeFalse();
    expect($channels['whatsapp_waha']['available'])->toBeFalse();
    expect($channels['whatsapp_meta']['available'])->toBeFalse();
});
