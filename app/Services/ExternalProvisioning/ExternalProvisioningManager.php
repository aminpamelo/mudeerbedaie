<?php

namespace App\Services\ExternalProvisioning;

use App\Events\ExternalAccountProvisioned;
use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\FunnelProduct;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;

class ExternalProvisioningManager
{
    public function __construct(
        protected ExternalSystemClient $client
    ) {}

    /**
     * Inspect a paid order and dispatch a provisioning job for every eligible
     * funnel product — one that opts in via FunnelProduct.settings.provisioning.
     * Idempotent: repeated calls for the same order create no duplicate requests.
     */
    public function dispatchForOrder(ProductOrder $order): void
    {
        foreach ($this->eligibleFunnelProducts($order) as [$funnelProduct, $system, $settings]) {
            $request = ExternalProvisioningRequest::firstOrCreate(
                ['idempotency_key' => $this->idempotencyKey($order, $funnelProduct, $system)],
                [
                    'external_system_id' => $system->id,
                    'product_order_id' => $order->id,
                    'funnel_product_id' => $funnelProduct->id,
                    'status' => ExternalProvisioningRequest::STATUS_PENDING,
                    'request_payload' => $this->buildPayload($order, $funnelProduct, $system, $settings),
                ]
            );

            if ($request->wasRecentlyCreated) {
                ProvisionExternalAccountJob::dispatch($request->id);
            }
        }
    }

    /**
     * Fulfil a single provisioning request by calling the external system.
     * Exceptions bubble up so the queue can retry; the job's failed() handler
     * marks the request failed once retries are exhausted.
     */
    public function fulfill(int $requestId): void
    {
        $request = ExternalProvisioningRequest::with('externalSystem')->find($requestId);

        if (! $request || $request->isSucceeded()) {
            return; // Nothing to do / already provisioned.
        }

        $system = $request->externalSystem;

        if (! $system || ! $system->is_active) {
            $request->markFailed('External system missing or inactive.');

            return;
        }

        $request->markProcessing();

        $response = $this->client->provision($system, $request->request_payload ?? []);

        $request->markSucceeded(
            $response,
            $this->stringOrNull($response['external_user_id'] ?? null),
            $this->stringOrNull($response['login_url'] ?? null),
            $this->extractCredentials($response),
        );

        ExternalAccountProvisioned::dispatch($request->refresh());
    }

    /**
     * Resolve the funnel products on the order that opt in to provisioning.
     *
     * @return array<int, array{0: FunnelProduct, 1: ExternalSystem, 2: array<string, mixed>}>
     */
    protected function eligibleFunnelProducts(ProductOrder $order): array
    {
        $funnelProductIds = $order->items()
            ->get()
            ->map(fn (ProductOrderItem $item): mixed => $item->item_metadata['funnel_product_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($funnelProductIds->isEmpty()) {
            return [];
        }

        $systems = ExternalSystem::query()->get()->keyBy('id');

        $eligible = [];

        foreach (FunnelProduct::query()->whereIn('id', $funnelProductIds)->with('product')->get() as $funnelProduct) {
            $settings = $funnelProduct->settings['provisioning'] ?? null;

            if (! is_array($settings) || empty($settings['enabled'])) {
                continue;
            }

            $system = $systems->get($settings['external_system_id'] ?? null);

            if (! $system instanceof ExternalSystem || ! $system->is_active) {
                continue;
            }

            $eligible[] = [$funnelProduct, $system, $settings];
        }

        return $eligible;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function buildPayload(ProductOrder $order, FunnelProduct $funnelProduct, ExternalSystem $system, array $settings): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey($order, $funnelProduct, $system),
            'order_ref' => $order->order_number,
            'order_id' => $order->id,
            'customer' => $this->customerIdentity($order),
            'product' => [
                'funnel_product_id' => $funnelProduct->id,
                'name' => $funnelProduct->getDisplayName(),
                'sku' => $funnelProduct->product?->sku,
                'plan' => $settings['plan'] ?? null,
            ],
        ];
    }

    /**
     * Provision a chosen order to a chosen system on demand (the admin "Provision"
     * button), independent of any funnel-product opt-in. Runs synchronously so the
     * admin sees the result immediately; a failed call is captured on the request,
     * not thrown, so one bad endpoint never 500s the page.
     */
    public function provisionManually(ProductOrder $order, ExternalSystem $system, ?string $plan = null): ExternalProvisioningRequest
    {
        $request = ExternalProvisioningRequest::firstOrCreate(
            ['idempotency_key' => $this->manualIdempotencyKey($order, $system)],
            [
                'external_system_id' => $system->id,
                'product_order_id' => $order->id,
                'funnel_product_id' => null,
                'status' => ExternalProvisioningRequest::STATUS_PENDING,
                'request_payload' => $this->buildManualPayload($order, $system, $plan),
            ]
        );

        if ($request->isSucceeded()) {
            return $request; // Already provisioned for this order + system.
        }

        // Refresh the payload in case the plan changed on a retry.
        $request->forceFill(['request_payload' => $this->buildManualPayload($order, $system, $plan)])->save();

        if (blank($request->request_payload['customer']['email'] ?? null)) {
            $request->markFailed('Order has no email or phone to identify the account.');

            return $request->refresh();
        }

        try {
            $this->fulfill($request->id);
        } catch (\Throwable $e) {
            $request->markFailed($e->getMessage());
        }

        return $request->refresh();
    }

    /**
     * The customer block for the provision payload. Email is the account identity;
     * when an order has none (POS/WhatsApp orders), derive a stable placeholder
     * from the phone so the receiver can still create the account.
     *
     * @return array{email: ?string, name: ?string, phone: ?string}
     */
    protected function customerIdentity(ProductOrder $order): array
    {
        $email = $order->guest_email ?: $order->customer?->email;
        $phone = $order->customer_phone ?: $order->customer?->phone;

        return [
            'email' => filled($email) ? $email : $this->syntheticEmail($phone),
            'name' => $order->customer_name ?: $order->customer?->name,
            'phone' => $phone,
        ];
    }

    /**
     * A deterministic, valid email built from a phone number, e.g.
     * "60123456789@noemail.kelasify.com". Same phone → same address, so repeat
     * provisions resolve to the same external account.
     */
    protected function syntheticEmail(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kelasify.com';

        return $digits.'@noemail.'.$host;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildManualPayload(ProductOrder $order, ExternalSystem $system, ?string $plan): array
    {
        return [
            'idempotency_key' => $this->manualIdempotencyKey($order, $system),
            'order_ref' => $order->order_number,
            'order_id' => $order->id,
            'customer' => $this->customerIdentity($order),
            'product' => [
                'funnel_product_id' => null,
                'name' => $order->items()->first()?->product_name ?? $order->order_number,
                'sku' => $order->items()->first()?->sku,
                'plan' => $plan,
            ],
        ];
    }

    protected function idempotencyKey(ProductOrder $order, FunnelProduct $funnelProduct, ExternalSystem $system): string
    {
        return "prov_{$order->id}_{$funnelProduct->id}_{$system->id}";
    }

    protected function manualIdempotencyKey(ProductOrder $order, ExternalSystem $system): string
    {
        return "prov_{$order->id}_manual_{$system->id}";
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function extractCredentials(array $response): array
    {
        return array_filter([
            'username' => $response['username'] ?? null,
            'temp_password' => $response['temp_password'] ?? null,
            'magic_link' => $response['magic_link'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
