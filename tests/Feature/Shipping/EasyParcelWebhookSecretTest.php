<?php

use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('easyparcel webhook is rejected when no secret is configured (fail closed)', function () {
    config()->set('services.easyparcel.webhook_secret', null);

    postJson('/webhooks/easyparcel', [
        'awb_number' => 'EP-TEST-001',
        'latest_shipment_status_code' => 5,
    ])->assertStatus(401);
});

test('easyparcel webhook is rejected when the provided secret is wrong', function () {
    config()->set('services.easyparcel.webhook_secret', 'the-real-secret');

    postJson('/webhooks/easyparcel?secret=wrong', [
        'awb_number' => 'EP-TEST-001',
        'latest_shipment_status_code' => 5,
    ])->assertStatus(401);
});

test('easyparcel webhook is accepted when the correct secret is provided', function () {
    config()->set('services.easyparcel.webhook_secret', 'the-real-secret');

    // Unknown AWB: the handler answers 200 (logs, no retry-storm) once authenticated.
    postJson('/webhooks/easyparcel?secret=the-real-secret', [
        'awb_number' => 'EP-UNKNOWN-999',
        'latest_shipment_status_code' => 5,
    ])->assertOk();
});
