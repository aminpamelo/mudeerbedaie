<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppCampaign;
use Livewire\Volt\Volt;

it('renders the full recipient error (expandable) on the campaign detail page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $campaign = WhatsAppCampaign::create([
        'name' => 'Test campaign',
        'source' => 'orders_bulk',
        'template_name' => 'kelas_solat_97_baru',
        'template_language' => 'ms',
        'variable_mapping' => [],
        'status' => 'completed',
        'total_recipients' => 1,
        'sent_count' => 0,
        'failed_count' => 1,
        'skipped_count' => 0,
        'created_by' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $error = '(#132000) Number of parameters does not match the expected number of params in the localizable_params array.';

    $campaign->recipients()->create([
        'phone' => '60123456789',
        'customer_name' => 'Test Customer',
        'status' => 'failed',
        'error_message' => $error,
    ]);

    Volt::test('admin.whatsapp-campaign-show', ['campaign' => $campaign])
        ->assertOk()
        ->assertSee($error)                             // full error text is in the DOM
        ->assertSeeHtml('Click to view the full error'); // expand affordance rendered
});
