<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductCartItem;

/**
 * A cart item's requiresShipping() drives whether the store checkout charges the
 * flat-rate fee. System/digital purchases must ship for free (Amin's KPI request).
 */
function cartItemFor(string $kind, ?string $fulfillmentType): ProductCartItem
{
    $item = new ProductCartItem;

    if ($kind === 'course') {
        $item->course_id = 1;
        $item->setRelation('course', new Course);

        return $item;
    }

    if ($kind === 'package') {
        $item->package_id = 1;
        $item->setRelation('package', new Package(['fulfillment_type' => $fulfillmentType]));

        return $item;
    }

    $item->product_id = 1;
    $item->setRelation('product', new Product(['fulfillment_type' => $fulfillmentType]));

    return $item;
}

it('requires shipping only for physical products and packages', function (string $kind, ?string $type, bool $expected) {
    expect(cartItemFor($kind, $type)->requiresShipping())->toBe($expected);
})->with([
    'physical product' => ['product', 'physical', true],
    'digital product' => ['product', 'digital', false],
    'external system product' => ['product', 'external_system', false],
    'product missing type defaults to physical' => ['product', null, true],
    'physical package' => ['package', 'physical', true],
    'digital package' => ['package', 'digital', false],
    'course is always digital' => ['course', null, false],
]);
