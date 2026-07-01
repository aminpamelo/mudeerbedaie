<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('shows a WhatsApp blast indicator linking to the campaign for a blasted order', function () {
    $this->actingAs($this->admin);

    $order = ProductOrder::factory()->create();
    $campaign = WhatsAppCampaign::create(['name' => 'blast', 'template_name' => 'order_confirmed', 'total_recipients' => 1]);
    WhatsAppCampaignRecipient::create([
        'whatsapp_campaign_id' => $campaign->id,
        'product_order_id' => $order->id,
        'phone' => '60160000000',
        'status' => 'delivered',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee($order->order_number)
        ->assertSee('Blasted')
        ->assertSee('/admin/whatsapp/campaigns/'.$campaign->id);
});

it('does not show the blast indicator for orders that were never blasted', function () {
    $this->actingAs($this->admin);

    $order = ProductOrder::factory()->create();

    Volt::test('admin.orders.order-list')
        ->assertSee($order->order_number)
        ->assertDontSee('Blasted');
});

it('reflects the latest recipient status and counts multiple campaigns', function () {
    $this->actingAs($this->admin);

    $order = ProductOrder::factory()->create();
    $first = WhatsAppCampaign::create(['name' => 'first blast', 'template_name' => 't', 'total_recipients' => 1]);
    $second = WhatsAppCampaign::create(['name' => 'second blast', 'template_name' => 't', 'total_recipients' => 1]);

    WhatsAppCampaignRecipient::create(['whatsapp_campaign_id' => $first->id, 'product_order_id' => $order->id, 'phone' => '60160000000', 'status' => 'sent']);
    WhatsAppCampaignRecipient::create(['whatsapp_campaign_id' => $second->id, 'product_order_id' => $order->id, 'phone' => '60160000000', 'status' => 'read']);

    // Latest recipient (highest id) is the "read" one from the second campaign.
    Volt::test('admin.orders.order-list')
        ->assertSee('Blasted · Read')
        ->assertSee('×2')
        ->assertSee('/admin/whatsapp/campaigns/'.$second->id);
});
