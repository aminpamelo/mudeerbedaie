<?php

namespace App\Services\Shipping;

use App\Console\Commands\SyncEasyParcelTracking;
use App\Http\Controllers\Shipping\EasyParcelWebhookController;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles a local {@see ProductOrder} with the live shipping status reported
 * by EasyParcel. This is the single place the courier's status is translated
 * into our own order status, used by BOTH the scheduled poller
 * ({@see SyncEasyParcelTracking}) and the push webhook
 * ({@see EasyParcelWebhookController}).
 *
 * Automatic order-status transitions: a courier-confirmed "delivered" moves the
 * order to delivered (and, for a COD order, auto-marks its payment as paid since
 * the courier collected the cash); "returned" and "cancelled" move it accordingly
 * (which also refunds a paid order's payment_status via the model helpers).
 * In-transit and unmapped statuses only update tracking metadata. A protected
 * order (already delivered/cancelled/returned/refunded) is never re-transitioned.
 */
class EasyParcelTrackingSync
{
    public function __construct(private ShippingManager $shippingManager) {}

    /**
     * Order statuses we never override automatically — terminal states or ones
     * an admin owns.
     *
     * @var array<int, string>
     */
    private const PROTECTED_STATUSES = ['delivered', 'cancelled', 'returned', 'refunded'];

    /**
     * Pull the latest tracking status for an order from EasyParcel and apply it.
     * Returns the resolved local status (e.g. 'delivered'), or null when nothing
     * could be synced.
     */
    public function syncOrder(ProductOrder $order): ?string
    {
        if ($order->shipping_provider !== 'easyparcel' || ! $order->tracking_id) {
            return null;
        }

        try {
            $result = $this->shippingManager->getProvider('easyparcel')->getTracking($order->tracking_id);
        } catch (\Throwable $e) {
            Log::warning('EasyParcel tracking sync failed', [
                'order_id' => $order->id,
                'tracking_id' => $order->tracking_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $result->success) {
            return null;
        }

        return $this->apply($order, $result->currentStatus, $result->currentStatusCode);
    }

    /**
     * Apply an EasyParcel status to an order. The numeric status code, when
     * present, is authoritative — EasyParcel warns the human label varies by
     * courier (and their own samples misspell "Delivered"), so we map the code
     * first and only fall back to keyword-matching the string. Always records the
     * latest status in metadata; transitions the order to delivered / returned /
     * cancelled when EasyParcel reports it and the order is not already in a
     * protected state. Returns the mapped local status (or null when unmapped).
     */
    public function apply(ProductOrder $order, ?string $rawStatus, ?int $statusCode = null): ?string
    {
        $mapped = $statusCode !== null
            ? $this->mapStatusCode($statusCode)
            : $this->mapToOrderStatus($rawStatus);

        $displayStatus = filled($rawStatus)
            ? $rawStatus
            : ($statusCode !== null ? "status code {$statusCode}" : null);

        $metadata = $order->metadata ?? [];
        $previousStatus = $metadata['easyparcel_tracking_status'] ?? null;
        $isNewStatus = $previousStatus !== $displayStatus;

        $metadata['easyparcel_tracking_status'] = $displayStatus;
        $metadata['easyparcel_tracking_synced_at'] = now()->toIso8601String();

        if ($statusCode !== null) {
            $metadata['easyparcel_status_code'] = $statusCode;
        }

        // Persist the latest tracking metadata first; the status transition (if any)
        // is a separate write so the model helpers run their own refund/note logic.
        $order->update(['metadata' => $metadata]);

        $canTransition = ! in_array($order->status, self::PROTECTED_STATUSES, true);
        $context = $displayStatus ? " ({$displayStatus})" : '';

        if ($canTransition && $mapped === 'delivered' && ! $order->delivered_at) {
            $order->update(['status' => 'delivered', 'delivered_at' => now()]);
            $order->addSystemNote("Auto-marked as delivered from EasyParcel tracking{$context}.");

            // COD is collected by the courier at the doorstep, so a delivered COD
            // order is a paid order — reconcile its payment_status automatically.
            if ($order->isCashOnDelivery()) {
                $order->markCodPaymentCollected();
            }

            return 'delivered';
        }

        if ($canTransition && $mapped === 'returned') {
            $order->markAsReturned();
            $order->addSystemNote("Auto-marked as returned from EasyParcel tracking{$context}.");

            return 'returned';
        }

        if ($canTransition && $mapped === 'cancelled') {
            $order->markAsCancelled('EasyParcel reported the shipment cancelled'.$context);

            return 'cancelled';
        }

        // No status transition (in-transit, unmapped, or already protected) — leave
        // a note when it is a genuinely new courier update so the timeline shows it.
        if ($isNewStatus && filled($displayStatus)) {
            $order->addSystemNote("EasyParcel tracking update: {$displayStatus}.");
        }

        return $mapped;
    }

    /**
     * Link an order to its waybill when EasyParcel pushes a freshly generated AWB
     * (the shipment.awb.update topic). This is the bridge for orders booked with a
     * pending/async AWB — saved with only easyparcel_shipment_number and a null
     * tracking_id — without which neither the webhook status topics nor the cron
     * poller (both keyed on tracking_id) could ever sync them. Returns true when
     * something changed.
     */
    public function applyAwb(ProductOrder $order, ?string $awb, ?string $awbUrl = null, ?string $trackingUrl = null): bool
    {
        $metadata = $order->metadata ?? [];
        $original = $metadata;
        $updates = [];

        if (filled($awb) && $order->tracking_id !== $awb) {
            $updates['tracking_id'] = $awb;
        }

        if (filled($awbUrl)) {
            $metadata['shipping_label_url'] = $awbUrl;
        }

        if (filled($trackingUrl)) {
            $metadata['shipping_tracking_url'] = $trackingUrl;
        }

        if (filled($awb)) {
            $metadata['easyparcel_awb_pending'] = false;
        }

        if (empty($updates) && $metadata === $original) {
            return false;
        }

        $updates['metadata'] = $metadata;
        $order->update($updates);

        if (isset($updates['tracking_id'])) {
            $order->addSystemNote("EasyParcel AWB received: {$awb}.");
        }

        return true;
    }

    /**
     * Translate an EasyParcel tracking status string into one of our order
     * statuses, or null when it carries no actionable meaning. Keyword matching
     * order matters: "out for delivery" and delivery failures both contain
     * "deliver" but must not be read as a completed delivery.
     */
    public function mapToOrderStatus(?string $rawStatus): ?string
    {
        $status = strtolower(trim((string) $rawStatus));

        if ($status === '') {
            return null;
        }

        return match (true) {
            str_contains($status, 'fail'),
            str_contains($status, 'unsuccess'),
            str_contains($status, 'exception'),
            str_contains($status, 'problem') => null,

            str_contains($status, 'out for deliver'),
            str_contains($status, 'on the way') => 'shipped',

            str_contains($status, 'deliver'),
            str_contains($status, 'completed') => 'delivered',

            str_contains($status, 'return') => 'returned',

            str_contains($status, 'cancel') => 'cancelled',

            str_contains($status, 'transit'),
            str_contains($status, 'pick'),
            str_contains($status, 'collect') => 'shipped',

            default => null,
        };
    }

    /**
     * Map EasyParcel's authoritative numeric shipment status code to a local
     * order status. The same enum is used by the shipment.status.update and
     * shipment.tracking.update webhooks and the tracking_status REST endpoint:
     *
     * 0=Cancelled · 2=To Be Collected · 3=Collected · 4=In Transit · 5=Delivered
     * 6=Returned · 7=Schedule In Arrangement · 8=On Hold · 11=Drop Off
     *
     * Codes that carry no actionable transition (7, 8, 11, unknown) return null.
     */
    public function mapStatusCode(?int $code): ?string
    {
        return match ($code) {
            0 => 'cancelled',
            2, 3, 4 => 'shipped',
            5 => 'delivered',
            6 => 'returned',
            default => null,
        };
    }
}
