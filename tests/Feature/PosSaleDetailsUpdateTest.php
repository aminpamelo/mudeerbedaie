<?php

use App\Models\ProductOrder;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
});

test('can update tracking number for a pos sale', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'tracking_id' => null,
        'metadata' => ['pos_sale' => true],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/details", [
            'tracking_id' => 'TRACK-12345',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.tracking_id', 'TRACK-12345');

    expect($order->fresh()->tracking_id)->toBe('TRACK-12345');
});

test('can update notes for a pos sale', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'internal_notes' => null,
        'metadata' => ['pos_sale' => true],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/details", [
            'internal_notes' => 'Updated note from POS',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.internal_notes', 'Updated note from POS');

    expect($order->fresh()->internal_notes)->toBe('Updated note from POS');
});

test('can update both tracking and notes at once', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'metadata' => ['pos_sale' => true],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/details", [
            'tracking_id' => 'TRACK-999',
            'internal_notes' => 'Both fields updated',
        ])
        ->assertSuccessful();

    $order->refresh();
    expect($order->tracking_id)->toBe('TRACK-999');
    expect($order->internal_notes)->toBe('Both fields updated');
});

test('can clear tracking number by passing null', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'pos',
        'tracking_id' => 'OLD-TRACK',
        'metadata' => ['pos_sale' => true],
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/details", [
            'tracking_id' => null,
        ])
        ->assertSuccessful();

    expect($order->fresh()->tracking_id)->toBeNull();
});

test('cannot update details for non-pos sale', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'website',
    ]);

    actingAs($this->user)
        ->putJson("/api/pos/sales/{$order->id}/details", [
            'tracking_id' => 'TRACK-123',
        ])
        ->assertForbidden();
});
