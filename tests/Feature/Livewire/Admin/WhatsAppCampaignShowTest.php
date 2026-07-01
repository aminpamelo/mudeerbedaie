<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

function makeCampaignWithRecipients(User $admin): WhatsAppCampaign
{
    $campaign = WhatsAppCampaign::create([
        'name' => 'order_confirmed campaign',
        'template_name' => 'order_confirmed',
        'template_language' => 'ms',
        'status' => 'completed',
        'total_recipients' => 3,
        'sent_count' => 2,
        'delivered_count' => 1,
        'read_count' => 1,
        'failed_count' => 0,
        'skipped_count' => 0,
        'estimated_cost_usd' => 0.0140,
        'created_by' => $admin->id,
    ]);

    foreach (['sent', 'delivered', 'read'] as $i => $status) {
        WhatsAppCampaignRecipient::create([
            'whatsapp_campaign_id' => $campaign->id,
            'customer_name' => "Customer {$i}",
            'phone' => '601650000'.$i.$i,
            'status' => $status,
        ]);
    }

    return $campaign;
}

it('renders the campaign detail page with stats, cost and recipients', function () {
    $this->actingAs($this->admin);

    $campaign = makeCampaignWithRecipients($this->admin);

    Volt::test('admin.whatsapp-campaign-show', ['campaign' => $campaign])
        ->assertStatus(200)
        ->assertSee('Recipients')
        ->assertSee('Delivered')
        ->assertSee('Skipped')
        ->assertSee('Estimated cost')
        ->assertSee('Customer 0');
});

it('filters the recipients table by status', function () {
    $this->actingAs($this->admin);

    $campaign = WhatsAppCampaign::create([
        'name' => 'promo blast',
        'template_name' => 'promo',
        'total_recipients' => 2,
    ]);
    WhatsAppCampaignRecipient::create(['whatsapp_campaign_id' => $campaign->id, 'customer_name' => 'Alpha Person', 'phone' => '60160000001', 'status' => 'read']);
    WhatsAppCampaignRecipient::create(['whatsapp_campaign_id' => $campaign->id, 'customer_name' => 'Bravo Person', 'phone' => '60160000002', 'status' => 'failed']);

    Volt::test('admin.whatsapp-campaign-show', ['campaign' => $campaign])
        ->assertSee('Alpha Person')
        ->assertSee('Bravo Person')
        ->set('statusFilter', 'failed')
        ->assertSee('Bravo Person')
        ->assertDontSee('Alpha Person');
});

it('forbids non-admin users from viewing the campaign detail', function () {
    $user = User::factory()->create(['role' => 'teacher']);
    $campaign = WhatsAppCampaign::create(['name' => 'x', 'template_name' => 't']);

    $this->actingAs($user)
        ->get(route('admin.whatsapp.campaigns.show', $campaign))
        ->assertForbidden();
});
