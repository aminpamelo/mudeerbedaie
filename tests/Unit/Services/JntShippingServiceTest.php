<?php

use App\DTOs\Shipping\ShipmentResult;
use App\DTOs\Shipping\ShippingRateRequest;
use App\DTOs\Shipping\TrackingResult;
use App\Services\Shipping\JntAreaCodeMapper;

test('ShippingRateRequest DTO creates correctly', function () {
    $request = new ShippingRateRequest(
        originPostalCode: '50000',
        originCity: 'Kuala Lumpur',
        originState: 'W.P. Kuala Lumpur',
        destinationPostalCode: '40000',
        destinationCity: 'Shah Alam',
        destinationState: 'Selangor',
        weightKg: 1.5,
        lengthCm: 20,
        widthCm: 15,
        heightCm: 10,
    );

    expect($request->originPostalCode)->toBe('50000');
    expect($request->destinationCity)->toBe('Shah Alam');
    expect($request->weightKg)->toBe(1.5);
    expect($request->lengthCm)->toBe(20.0);
    expect($request->widthCm)->toBe(15.0);
    expect($request->heightCm)->toBe(10.0);
});

test('ShipmentResult DTO creates correctly', function () {
    $result = new ShipmentResult(
        success: true,
        trackingNumber: 'JNT001234',
        waybillNumber: 'JNT001234',
        message: 'Created successfully',
    );

    expect($result->success)->toBeTrue();
    expect($result->trackingNumber)->toBe('JNT001234');
    expect($result->waybillNumber)->toBe('JNT001234');
    expect($result->message)->toBe('Created successfully');
});

test('TrackingResult DTO creates correctly', function () {
    $result = new TrackingResult(
        success: true,
        trackingNumber: 'JNT001234',
        currentStatus: 'in_transit',
        events: [
            ['status' => 'PICKUP', 'datetime' => '2025-01-01 10:00:00', 'location' => 'KL Hub', 'description' => 'Picked up'],
        ],
    );

    expect($result->success)->toBeTrue();
    expect($result->events)->toHaveCount(1);
    expect($result->currentStatus)->toBe('in_transit');
    expect($result->trackingNumber)->toBe('JNT001234');
});

test('JntAreaCodeMapper returns state codes', function () {
    expect(JntAreaCodeMapper::getStateCode('Selangor'))->toBe('SGR');
    expect(JntAreaCodeMapper::getStateCode('W.P. Kuala Lumpur'))->toBe('KUL');
    expect(JntAreaCodeMapper::getStateCode('Johor'))->toBe('JHR');
    expect(JntAreaCodeMapper::getStateCode('Sabah'))->toBe('SBH');
    expect(JntAreaCodeMapper::getStateCode('Sarawak'))->toBe('SWK');
});

test('JntAreaCodeMapper returns state name from code', function () {
    expect(JntAreaCodeMapper::getStateName('SGR'))->toBe('Selangor');
    expect(JntAreaCodeMapper::getStateName('KUL'))->toBe('W.P. Kuala Lumpur');
    expect(JntAreaCodeMapper::getStateName('UNKNOWN'))->toBeNull();
});

test('JntAreaCodeMapper returns input for unknown state', function () {
    expect(JntAreaCodeMapper::getStateCode('Unknown State'))->toBe('Unknown State');
});
