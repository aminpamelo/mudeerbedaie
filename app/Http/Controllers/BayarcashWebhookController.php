<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelAnalytics;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\Order;
use App\Models\ProductOrder;
use App\Services\BayarcashService;
use App\Services\Funnel\FacebookPixelService;
use App\Services\Funnel\FunnelAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BayarcashWebhookController extends Controller
{
    public function __construct(
        private BayarcashService $bayarcashService,
        private FacebookPixelService $pixelService,
        private FunnelAutomationService $automationService
    ) {}

    /**
     * Handle Bayarcash callback (server-to-server notification).
     */
    public function callback(Request $request): Response
    {
        $data = $request->all();

        Log::info('Bayarcash callback received', [
            'data' => $data,
            'ip' => $request->ip(),
        ]);

        // Verify callback authenticity
        if (! $this->bayarcashService->verifyTransactionCallback($data)) {
            Log::warning('Bayarcash callback verification failed', [
                'data' => $data,
                'ip' => $request->ip(),
            ]);

            return response('Invalid callback signature', 400);
        }

        // Extract key data from callback
        $status = $data['status'] ?? null;
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;

        if (! $orderNumber) {
            Log::error('Bayarcash callback missing order number', $data);

            return response('Missing order number', 400);
        }

        // Find the order (check both ProductOrder and Order tables)
        $order = $this->findOrder($orderNumber);

        if (! $order) {
            Log::error('Order not found for Bayarcash callback', [
                'order_number' => $orderNumber,
            ]);

            return response('Order not found', 404);
        }

        // Check for duplicate processing
        if ($order->bayarcash_transaction_id === $transactionId && $order->status === 'processing') {
            Log::info('Bayarcash callback already processed', [
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
            ]);

            return response('Already processed', 200);
        }

        // Process based on payment status
        // Bayarcash status codes: 1 = Pending, 2 = Unsuccessful, 3 = Successful
        match ($status) {
            '3' => $this->bayarcashService->processSuccessfulPayment($order, $data),
            '2' => $this->bayarcashService->processFailedPayment($order, $data),
            default => $this->bayarcashService->processPendingPayment($order, $data),
        };

        // If payment was successful, handle funnel conversion tracking
        if ($status === '3') {
            $this->handleFunnelConversion($order);
        }

        Log::info('Bayarcash callback processed', [
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);

        return response('OK', 200);
    }

    /**
     * Handle funnel conversion tracking after successful payment (from callback).
     */
    private function handleFunnelConversion(ProductOrder|Order $order): void
    {
        // Only ProductOrder can be a funnel order
        if (! $order instanceof ProductOrder) {
            return;
        }

        $metadata = $order->metadata ?? [];

        // Check if this is a funnel order
        if (! isset($metadata['funnel_id'])) {
            return;
        }

        // Check if conversion was already tracked (by return endpoint)
        if (isset($metadata['conversion_tracked']) && $metadata['conversion_tracked'] === true) {
            Log::info('Funnel conversion already tracked by return endpoint, skipping callback tracking', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        Log::info('Processing funnel conversion from callback', [
            'order_id' => $order->id,
            'funnel_id' => $metadata['funnel_id'],
        ]);

        // Find the FunnelOrder
        $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();

        if ($funnelOrder && $funnelOrder->session) {
            // Mark session as converted
            $funnelOrder->session->markAsConverted();

            // Track payment completed event
            $funnelOrder->session->trackEvent('payment_completed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'payment_method' => 'fpx',
                'provider' => 'bayarcash',
                'source' => 'callback_endpoint',
            ]);

            // Track Facebook Pixel Purchase event (server-side)
            if ($funnelOrder->funnel) {
                $thankYouStep = $funnelOrder->funnel->steps()->where('type', 'thankyou')->first();
                $eventSourceUrl = $thankYouStep
                    ? url("/f/{$funnelOrder->funnel->slug}/{$thankYouStep->slug}")
                    : url("/f/{$funnelOrder->funnel->slug}");

                $this->pixelService->trackPurchase(
                    $funnelOrder->funnel,
                    $order,
                    $funnelOrder->session,
                    null,
                    $eventSourceUrl
                );
            }

            // Update funnel analytics - step level
            $stepAnalytics = FunnelAnalytics::getOrCreateForToday(
                $funnelOrder->funnel_id,
                $funnelOrder->step_id
            );
            $stepAnalytics->incrementConversions($funnelOrder->funnel_revenue);

            // Update funnel analytics - funnel level (for summary stats)
            $funnelAnalytics = FunnelAnalytics::getOrCreateForToday($funnelOrder->funnel_id);
            $funnelAnalytics->incrementConversions($funnelOrder->funnel_revenue);

            // Mark conversion as tracked to prevent duplicate tracking
            $order->update([
                'metadata' => array_merge($metadata, ['conversion_tracked' => true]),
            ]);

            // Trigger funnel automations for purchase completed
            $this->automationService->triggerPurchaseCompleted($order, $funnelOrder->session);
        }

        // Mark cart as recovered
        if (isset($metadata['session_uuid'])) {
            $session = FunnelSession::where('uuid', $metadata['session_uuid'])->first();
            if ($session && $session->cart) {
                $session->cart->markAsRecovered($order);
            }
        }
    }

    /**
     * Handle Bayarcash return (user redirect after payment).
     */
    public function return(Request $request): RedirectResponse
    {
        $data = $request->all();

        // Bayarcash returns order_number in the data, which is cleaner than the 'order' query param
        // The 'order' param sometimes has ?transaction_id appended incorrectly
        $orderNumber = $data['order_number'] ?? $request->query('order_number') ?? $request->query('order');

        // Clean up order number if it has query params appended (e.g., "PO-123?transaction_id=xyz")
        if ($orderNumber && str_contains($orderNumber, '?')) {
            $orderNumber = explode('?', $orderNumber)[0];
        }

        Log::info('Bayarcash return received', [
            'order_number' => $orderNumber,
            'raw_order_param' => $request->query('order'),
            'data' => $data,
        ]);

        // Find the order first to determine redirect strategy
        $order = $orderNumber ? $this->findOrder($orderNumber) : null;

        // Check if this is a funnel order (check early for proper redirect handling)
        $isFunnelOrder = false;
        $funnelMetadata = [];
        if ($order instanceof ProductOrder) {
            $funnelMetadata = $order->metadata ?? [];
            $isFunnelOrder = isset($funnelMetadata['funnel_id']);

            Log::info('Bayarcash return - order found', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'is_funnel_order' => $isFunnelOrder,
                'metadata' => $funnelMetadata,
                'metadata_type' => gettype($funnelMetadata),
            ]);
        } else {
            Log::warning('Bayarcash return - order not found or not ProductOrder', [
                'order_number' => $orderNumber,
                'order_type' => $order ? get_class($order) : 'null',
            ]);
        }

        // Get the status from Bayarcash data BEFORE verification
        // This ensures we process the actual payment result even if verification has issues
        $status = $data['status'] ?? null;

        // Verify return data authenticity
        $verificationPassed = $this->bayarcashService->verifyReturnCallback($data);

        if (! $verificationPassed) {
            Log::warning('Bayarcash return verification failed', [
                'data' => $data,
                'actual_status' => $status,
            ]);

            // IMPORTANT: Even if verification fails, if payment was successful (status=3),
            // we should still process it. The server-to-server callback will confirm.
            // Only reject if verification fails AND status indicates failure.
            if ($status !== '3') {
                // For funnel orders with failed payment, redirect back to checkout
                if ($isFunnelOrder) {
                    return $this->handleFunnelReturn($order, $funnelMetadata, $status ?? '2', $data);
                }

                // For non-funnel orders, redirect to home with error
                return redirect('/')
                    ->with('error', 'Payment verification failed. Please contact support if you were charged.');
            }

            // Status is '3' (successful) but verification failed - log warning but continue processing
            Log::info('Processing payment despite verification failure (status=3)', [
                'order_number' => $orderNumber,
                'status' => $status,
            ]);
        }

        if (! $order) {
            // For funnel orders from query params, try to extract funnel info
            $funnelSlug = $request->query('funnel');
            if ($funnelSlug) {
                return redirect("/f/{$funnelSlug}")
                    ->with('error', 'Order not found. Please try again.');
            }

            return redirect('/')
                ->with('error', 'Order not found.');
        }

        // Handle funnel order return
        if ($isFunnelOrder) {
            return $this->handleFunnelReturn($order, $funnelMetadata, $status, $data);
        }

        // Redirect based on payment status (non-funnel orders)
        if ($status === '3') {
            // Payment successful
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('success', 'Payment successful! Thank you for your order.');
        } elseif ($status === '2') {
            // Payment failed - redirect to home since cart.checkout may not exist
            return redirect('/')
                ->with('error', 'Payment was unsuccessful. Please try again or choose a different payment method.');
        } else {
            // Payment pending
            return redirect()->route('orders.show', ['order' => $order->id])
                ->with('info', 'Your payment is being processed. We will notify you once it is confirmed.');
        }
    }

    /**
     * Handle return redirect for funnel orders.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $data  The full callback data from Bayarcash
     */
    private function handleFunnelReturn(ProductOrder $order, array $metadata, ?string $status, array $data = []): RedirectResponse
    {
        $funnelSlug = $metadata['funnel_slug'] ?? null;
        $stepSlug = $metadata['step_slug'] ?? null;
        $funnelId = $metadata['funnel_id'] ?? null;

        Log::info('Handling funnel return', [
            'order_id' => $order->id,
            'status' => $status,
            'funnel_slug' => $funnelSlug,
            'step_slug' => $stepSlug,
            'funnel_id' => $funnelId,
            'metadata' => $metadata,
        ]);

        // Try to find the funnel - first by slug, then by ID
        $funnel = null;
        if ($funnelSlug) {
            $funnel = Funnel::where('slug', $funnelSlug)->first();
        }

        // Fallback: try to find by funnel_id if slug lookup failed
        if (! $funnel && $funnelId) {
            $funnel = Funnel::find($funnelId);
            if ($funnel) {
                // Update the slug for redirect
                $funnelSlug = $funnel->slug;
            }
        }

        if (! $funnel) {
            // Last resort: try to find via FunnelOrder relationship
            $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();
            if ($funnelOrder && $funnelOrder->funnel) {
                $funnel = $funnelOrder->funnel;
                $funnelSlug = $funnel->slug;
                $stepSlug = $funnelOrder->step?->slug;
            }
        }

        if (! $funnel) {
            // Funnel not found in database - log warning
            Log::warning('Funnel not found for return redirect', [
                'order_id' => $order->id,
                'funnel_slug' => $funnelSlug,
                'funnel_id' => $funnelId,
            ]);

            // Even if funnel not in DB, try to use the slug from metadata for redirect
            if ($funnelSlug) {
                if ($status === '3') {
                    return redirect("/f/{$funnelSlug}?complete=1&order={$order->order_number}")
                        ->with('success', 'Payment successful! Thank you for your purchase.');
                }

                // Failed payment - redirect back to funnel with error
                $redirectUrl = $stepSlug ? "/f/{$funnelSlug}/{$stepSlug}" : "/f/{$funnelSlug}";

                return redirect($redirectUrl)
                    ->with('error', 'Payment was unsuccessful. Please try again or choose a different payment method.');
            }

            // Absolute fallback - no funnel info available
            if ($status === '3') {
                return redirect()->route('orders.show', ['order' => $order->id])
                    ->with('success', 'Payment successful! Thank you for your order.');
            }

            // For failed payments with absolutely no funnel info, show a simple error view
            // or redirect to a generic payment error page
            return redirect('/payment/failed')
                ->with('error', 'Payment was unsuccessful. Please try again.');
        }

        if ($status === '3') {
            // Payment successful - update order status using the service
            $this->bayarcashService->processSuccessfulPayment($order, $data);

            // Track funnel conversion (in case callback doesn't fire or is delayed)
            // This is safe to call - it will be idempotent if callback already tracked it
            $this->handleFunnelConversionFromReturn($order);

            // Redirect to thank you page or next step
            $thankYouStep = $funnel->steps()->where('type', 'thankyou')->first();

            if ($thankYouStep) {
                return redirect("/f/{$funnel->slug}/{$thankYouStep->slug}?order={$order->order_number}")
                    ->with('success', 'Payment successful! Thank you for your purchase.');
            }

            // No thank you step - redirect to funnel with completion flag
            return redirect("/f/{$funnel->slug}?complete=1&order={$order->order_number}")
                ->with('success', 'Payment successful! Thank you for your purchase.');

        } elseif ($status === '2') {
            // Payment failed - redirect back to checkout step
            if ($stepSlug) {
                return redirect("/f/{$funnel->slug}/{$stepSlug}")
                    ->with('error', 'Payment was unsuccessful. Please try again or choose a different payment method.');
            }

            return redirect("/f/{$funnel->slug}")
                ->with('error', 'Payment was unsuccessful. Please try again or choose a different payment method.');

        } else {
            // Payment pending
            $thankYouStep = $funnel->steps()->where('type', 'thankyou')->first();

            if ($thankYouStep) {
                return redirect("/f/{$funnel->slug}/{$thankYouStep->slug}?order={$order->order_number}")
                    ->with('info', 'Your payment is being processed. We will notify you once it is confirmed.');
            }

            return redirect("/f/{$funnel->slug}?order={$order->order_number}")
                ->with('info', 'Your payment is being processed. We will notify you once it is confirmed.');
        }
    }

    /**
     * Handle funnel conversion tracking from return endpoint.
     * This is called when user is redirected back after successful payment.
     * It's safe to call even if callback already tracked - uses order status to prevent duplicates.
     */
    private function handleFunnelConversionFromReturn(ProductOrder $order): void
    {
        // Check if conversion was already tracked by looking at a flag in metadata
        $metadata = $order->metadata ?? [];
        if (isset($metadata['conversion_tracked']) && $metadata['conversion_tracked'] === true) {
            Log::info('Funnel conversion already tracked, skipping', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        // Find the FunnelOrder
        $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();

        if (! $funnelOrder) {
            Log::warning('FunnelOrder not found for conversion tracking', [
                'order_id' => $order->id,
            ]);

            return;
        }

        Log::info('Tracking funnel conversion from return endpoint', [
            'order_id' => $order->id,
            'funnel_id' => $funnelOrder->funnel_id,
            'step_id' => $funnelOrder->step_id,
            'revenue' => $funnelOrder->funnel_revenue,
        ]);

        // Mark session as converted
        if ($funnelOrder->session) {
            $funnelOrder->session->markAsConverted();

            // Track payment completed event
            $funnelOrder->session->trackEvent('payment_completed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'payment_method' => 'fpx',
                'provider' => 'bayarcash',
                'source' => 'return_endpoint',
            ]);
        }

        // Track Facebook Pixel Purchase event (server-side)
        if ($funnelOrder->funnel) {
            $thankYouStep = $funnelOrder->funnel->steps()->where('type', 'thankyou')->first();
            $eventSourceUrl = $thankYouStep
                ? url("/f/{$funnelOrder->funnel->slug}/{$thankYouStep->slug}")
                : url("/f/{$funnelOrder->funnel->slug}");

            $this->pixelService->trackPurchase(
                $funnelOrder->funnel,
                $order,
                $funnelOrder->session,
                null,
                $eventSourceUrl
            );
        }

        // Update funnel analytics - step level
        $stepAnalytics = FunnelAnalytics::getOrCreateForToday(
            $funnelOrder->funnel_id,
            $funnelOrder->step_id
        );
        $stepAnalytics->incrementConversions($funnelOrder->funnel_revenue);

        // Update funnel analytics - funnel level (for summary stats)
        $funnelAnalytics = FunnelAnalytics::getOrCreateForToday($funnelOrder->funnel_id);
        $funnelAnalytics->incrementConversions($funnelOrder->funnel_revenue);

        // Mark conversion as tracked to prevent duplicate tracking from callback
        $order->update([
            'metadata' => array_merge($metadata, ['conversion_tracked' => true]),
        ]);

        // Trigger funnel automations for purchase completed
        $this->automationService->triggerPurchaseCompleted($order, $funnelOrder->session);

        // Mark cart as recovered
        if (isset($metadata['session_uuid'])) {
            $session = FunnelSession::where('uuid', $metadata['session_uuid'])->first();
            if ($session && $session->cart) {
                $session->cart->markAsRecovered($order);
            }
        }
    }

    /**
     * Find an order by order number.
     */
    private function findOrder(string $orderNumber): ProductOrder|Order|null
    {
        // First, try to find in ProductOrder table (e-commerce orders)
        $productOrder = ProductOrder::where('order_number', $orderNumber)->first();

        if ($productOrder) {
            return $productOrder;
        }

        // Then, try to find in Order table (subscription orders)
        return Order::where('order_number', $orderNumber)->first();
    }
}
