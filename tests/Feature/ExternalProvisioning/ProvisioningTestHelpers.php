<?php

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\FunnelProduct;
use App\Models\Product;
use App\Models\ProductOrder;

if (! function_exists('makeExternalSystem')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function makeExternalSystem(array $overrides = []): ExternalSystem
    {
        return ExternalSystem::factory()->create(array_merge([
            'base_url' => 'https://external.test',
            'provision_path' => '/api/v1/provision',
            'auth_type' => 'both',
            'api_key' => 'secret-key',
            'signing_secret' => 'signing-secret',
        ], $overrides));
    }
}

if (! function_exists('makeEligibleFunnelOrder')) {
    /**
     * Build a paid funnel order whose single item maps to a funnel product that
     * has opted in to provisioning against the given external system.
     *
     * @return array{0: ProductOrder, 1: FunnelProduct}
     */
    function makeEligibleFunnelOrder(ExternalSystem $system, ?string $plan = null): array
    {
        $order = ProductOrder::factory()->create([
            'source' => 'funnel',
            'guest_email' => 'buyer@example.test',
            'customer_name' => 'Buyer Test',
            'customer_phone' => '+60123456789',
            'metadata' => ['funnel_id' => 1],
        ]);

        $funnelProduct = FunnelProduct::factory()
            ->provisioning($system->id, $plan)
            ->create(['name' => 'Premium Membership']);

        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'product_name' => 'Premium Membership',
            'quantity_ordered' => 1,
            'unit_price' => 100,
            'total_price' => 100,
            'item_metadata' => ['funnel_product_id' => $funnelProduct->id],
        ]);

        return [$order, $funnelProduct];
    }
}

if (! function_exists('makePendingProvisioningRequest')) {
    /**
     * @param  array<string, mixed>  $payload
     */
    function makePendingProvisioningRequest(ExternalSystem $system, ProductOrder $order, ?FunnelProduct $funnelProduct = null, array $payload = []): ExternalProvisioningRequest
    {
        return ExternalProvisioningRequest::create([
            'external_system_id' => $system->id,
            'product_order_id' => $order->id,
            'funnel_product_id' => $funnelProduct?->id,
            'idempotency_key' => 'prov_'.$order->id.'_'.($funnelProduct?->id ?? 0).'_'.$system->id,
            'status' => ExternalProvisioningRequest::STATUS_PENDING,
            'request_payload' => $payload ?: ['order_ref' => $order->order_number],
        ]);
    }
}
