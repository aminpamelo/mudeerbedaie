<?php

namespace App\Support;

use App\Models\ProductOrder;

/**
 * The slice of the order lifecycle a fighter owns.
 *
 * Fighters run their own funnels, so they manage an order right up to the point
 * it leaves their hands. Everything past that — partially_shipped, shipped,
 * delivered, refunded, returned — belongs to the e-commerce fulfilment team and
 * stays read-only in the fighter portal, so the two sides can never silently
 * overwrite each other's work.
 */
final class FighterOrderStatus
{
    /**
     * Statuses a fighter may set.
     *
     * @var list<string>
     */
    public const EDITABLE = ['pending', 'processing', 'completed', 'cancelled'];

    /**
     * Statuses a fighter may move *away from*. `confirmed` is included because
     * paying for an order flips it to `confirmed` automatically — the order is
     * still the fighter's at that point, they just can't select it by hand.
     *
     * @var list<string>
     */
    public const OWNED = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

    /**
     * Can the fighter still drive this order's status, or has the fulfilment
     * team taken it over?
     */
    public static function isOwnedByFighter(ProductOrder $order): bool
    {
        return in_array($order->status, self::OWNED, true);
    }

    /**
     * Fold a fighter-set status into an order update payload, applying the
     * lifecycle side effects the rest of the app expects.
     *
     * Cancelling a paid order flips its payment to `refunded` (mirroring
     * ProductOrder::markAsCancelled) so it stops counting as revenue in
     * reports and commission; un-cancelling puts the payment back.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function apply(array $payload, ProductOrder $order, string $status): array
    {
        $payload['status'] = $status;

        $paymentStatus = $payload['payment_status'] ?? $order->payment_status;

        if ($status === 'cancelled') {
            $payload['cancelled_at'] = $order->cancelled_at ?? now();

            if ($paymentStatus === 'paid') {
                $payload['payment_status'] = 'refunded';
                // The money genuinely arrived before the cancellation, so keep
                // paid_time — only the revenue attribution is being undone.
                $payload['paid_time'] = $order->paid_time ?? now();
            }
        } else {
            $payload['cancelled_at'] = null;

            // Reversing a cancellation restores the payment it had flipped.
            if ($order->status === 'cancelled' && $paymentStatus === 'refunded' && $order->paid_time) {
                $payload['payment_status'] = 'paid';
            }
        }

        if ($status === 'completed') {
            $payload['delivered_at'] = $order->delivered_at ?? now();
        }

        if (in_array($status, ['processing', 'completed'], true) && ! $order->confirmed_at) {
            $payload['confirmed_at'] = now();
        }

        return $payload;
    }

    /**
     * Human sentence for the order timeline.
     */
    public static function note(string $status, string $actor): string
    {
        $label = match ($status) {
            'pending' => 'moved back to pending',
            'processing' => 'moved to processing',
            'completed' => 'marked as completed',
            'cancelled' => 'cancelled',
            default => 'set to '.$status,
        };

        return 'Order '.$label.' by fighter '.$actor;
    }
}
