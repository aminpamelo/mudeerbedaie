<?php

declare(strict_types=1);

it('serves the privacy policy page publicly', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('Privacy Policy');
});

it('includes the Meta/WhatsApp compliance disclosures required for app review', function () {
    $response = $this->get(route('privacy-policy'));

    $response->assertOk()
        ->assertSee('WhatsApp Business Platform')
        ->assertSee('Meta Platform Terms')
        ->assertSee('Meta Developer Policies')
        ->assertSee('Data deletion', false)
        ->assertSee(config('app.company')['name']);
});
