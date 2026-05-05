<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has matched_live_session_id column on product_orders', function () {
    expect(\Schema::hasColumn('product_orders', 'matched_live_session_id'))->toBeTrue();
});

it('allows setting matched_live_session_id via fillable', function () {
    $order = ProductOrder::factory()->create(['matched_live_session_id' => null]);
    expect($order->matched_live_session_id)->toBeNull();
});

it('exposes matchedLiveSession relationship', function () {
    $order = new ProductOrder;
    $relation = $order->matchedLiveSession();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('matched_live_session_id');
});
