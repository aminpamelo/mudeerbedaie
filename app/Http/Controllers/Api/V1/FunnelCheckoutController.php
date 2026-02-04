<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStepProduct;
use App\Services\Funnel\FunnelCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelCheckoutController extends Controller
{
    public function __construct(
        protected FunnelCheckoutService $checkoutService
    ) {}

    /**
     * Initialize checkout and create payment intent.
     */
    public function createCheckout(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'session_uuid' => ['required', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['integer', 'exists:funnel_step_products,id'],
            'bumps' => ['nullable', 'array'],
            'bumps.*' => ['integer', 'exists:funnel_step_order_bumps,id'],
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email'],
            'customer.name' => ['required', 'string', 'min:2'],
            'customer.phone' => ['nullable', 'string'],
            'billing_address' => ['required', 'array'],
            'billing_address.first_name' => ['required', 'string', 'min:2'],
            'billing_address.last_name' => ['required', 'string', 'min:2'],
            'billing_address.address_line_1' => ['required', 'string', 'min:5'],
            'billing_address.city' => ['required', 'string', 'min:2'],
            'billing_address.state' => ['required', 'string', 'min:2'],
            'billing_address.postal_code' => ['required', 'string', 'min:5'],
            'billing_address.country' => ['nullable', 'string'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)
            ->where('status', 'published')
            ->firstOrFail();

        $step = $funnel->steps()->findOrFail($stepId);

        $session = FunnelSession::where('uuid', $request->input('session_uuid'))
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        // Convert products array to keyed array
        $selectedProducts = collect($request->input('products'))
            ->mapWithKeys(fn ($id) => [$id => true])
            ->toArray();

        $selectedBumps = collect($request->input('bumps', []))
            ->mapWithKeys(fn ($id) => [$id => true])
            ->toArray();

        try {
            $result = $this->checkoutService->createCheckout(
                session: $session,
                step: $step,
                selectedProducts: $selectedProducts,
                selectedBumps: $selectedBumps,
                customerData: $request->input('customer'),
                billingAddress: $request->input('billing_address')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $result['order']->id,
                    'order_number' => $result['order']->order_number,
                    'total' => $result['total'],
                    'client_secret' => $result['payment_intent']['client_secret'],
                    'payment_intent_id' => $result['payment_intent']['id'],
                    'publishable_key' => $this->checkoutService->getPublishableKey(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm payment was successful.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->checkoutService->confirmPayment(
                $request->input('payment_intent_id')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $result['order']?->id,
                        'order_number' => $result['order']?->order_number,
                        'status' => $result['status'],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Payment confirmation failed',
                'status' => $result['status'] ?? 'failed',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process one-click upsell purchase.
     */
    public function processUpsell(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'session_uuid' => ['required', 'string'],
            'product_id' => ['required', 'integer', 'exists:funnel_step_products,id'],
            'original_order_id' => ['required', 'integer', 'exists:funnel_orders,id'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)
            ->where('status', 'published')
            ->firstOrFail();

        $step = $funnel->steps()->findOrFail($stepId);

        $session = FunnelSession::where('uuid', $request->input('session_uuid'))
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        $upsellProduct = FunnelStepProduct::where('id', $request->input('product_id'))
            ->where('step_id', $step->id)
            ->where('is_active', true)
            ->firstOrFail();

        $originalOrder = FunnelOrder::findOrFail($request->input('original_order_id'));

        try {
            $result = $this->checkoutService->processOneClickUpsell(
                session: $session,
                upsellStep: $step,
                upsellProduct: $upsellProduct,
                originalOrder: $originalOrder
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $result['order']->id,
                        'order_number' => $result['order']->order_number,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'requires_payment' => $result['requires_payment'] ?? false,
            ], $result['requires_payment'] ? 402 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Decline an upsell offer.
     */
    public function declineUpsell(Request $request, string $funnelUuid, int $stepId): JsonResponse
    {
        $request->validate([
            'session_uuid' => ['required', 'string'],
            'product_id' => ['required', 'integer', 'exists:funnel_step_products,id'],
            'original_order_id' => ['required', 'integer', 'exists:funnel_orders,id'],
        ]);

        $funnel = Funnel::where('uuid', $funnelUuid)
            ->where('status', 'published')
            ->firstOrFail();

        $step = $funnel->steps()->findOrFail($stepId);

        $session = FunnelSession::where('uuid', $request->input('session_uuid'))
            ->where('funnel_id', $funnel->id)
            ->firstOrFail();

        $upsellProduct = FunnelStepProduct::where('id', $request->input('product_id'))
            ->where('step_id', $step->id)
            ->firstOrFail();

        $originalOrder = FunnelOrder::findOrFail($request->input('original_order_id'));

        $this->checkoutService->declineUpsell(
            session: $session,
            upsellStep: $step,
            upsellProduct: $upsellProduct,
            originalOrder: $originalOrder
        );

        return response()->json([
            'success' => true,
            'message' => 'Upsell declined',
        ]);
    }

    /**
     * Get checkout configuration (Stripe publishable key, etc.)
     */
    public function getConfig(): JsonResponse
    {
        $settingsService = app(\App\Services\SettingsService::class);

        return response()->json([
            'success' => true,
            'data' => [
                'publishable_key' => $this->checkoutService->getPublishableKey(),
                'is_configured' => $this->checkoutService->isConfigured(),
                // Include both payment provider configurations for PaymentTab
                'stripe_publishable_key' => $this->checkoutService->getPublishableKey(),
                'stripe_enabled' => $this->checkoutService->isConfigured() && $settingsService->get('enable_stripe_payments', true),
                'bayarcash_enabled' => $settingsService->isBayarcashEnabled(),
                'cod_enabled' => $settingsService->isCodEnabled(),
                'cod_instructions' => $settingsService->getCodInstructions(),
            ],
        ]);
    }
}
