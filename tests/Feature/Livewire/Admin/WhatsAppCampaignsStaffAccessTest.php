<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WhatsAppCampaign;

/**
 * The WhatsApp Campaigns pages (list + detail) were admin-only at the component
 * level even though the route + sidebar already allow the employee role. Staff
 * on the employee role can now open them; other roles stay blocked (403).
 *
 * These hit the real routes so they cover both the route middleware and the
 * in-component gate — an employee used to get 403 from mount() and now gets 200.
 */
it('lets an employee open the WhatsApp campaigns list', function () {
    $this->actingAs(User::factory()->create(['role' => 'employee']));

    $this->get(route('admin.whatsapp.campaigns'))
        ->assertOk()
        ->assertSee('WhatsApp Campaigns');
});

it('still lets an admin open the WhatsApp campaigns list', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get(route('admin.whatsapp.campaigns'))
        ->assertOk()
        ->assertSee('WhatsApp Campaigns');
});

it('blocks a non-staff role from the WhatsApp campaigns list', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']));

    $this->get(route('admin.whatsapp.campaigns'))->assertForbidden();
});

it('lets an employee open a campaign detail page', function () {
    $this->actingAs(User::factory()->create(['role' => 'employee']));
    $campaign = WhatsAppCampaign::create([
        'name' => 'Raya blast',
        'template_name' => 'order_confirmed',
        'total_recipients' => 1,
    ]);

    $this->get(route('admin.whatsapp.campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('Raya blast');
});

it('blocks a non-staff role from a campaign detail page', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']));
    $campaign = WhatsAppCampaign::create([
        'name' => 'Raya blast',
        'template_name' => 't',
        'total_recipients' => 1,
    ]);

    $this->get(route('admin.whatsapp.campaigns.show', $campaign))->assertForbidden();
});
