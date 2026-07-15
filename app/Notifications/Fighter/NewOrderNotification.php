<?php

namespace App\Notifications\Fighter;

use App\Models\Funnel;
use App\Models\ProductOrder;
use Illuminate\Notifications\Notification;

/**
 * Alerts a Fighter that a new order has come in from one of their funnels.
 *
 * Stored on the `notifications` table (database channel) and surfaced in the
 * Fighter portal's bell + feed. Delivered synchronously (not queued) so the
 * alert lands even without a running queue worker.
 */
class NewOrderNotification extends Notification
{
    public function __construct(
        protected ProductOrder $order,
        protected Funnel $funnel,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = 'RM '.number_format((float) $this->order->total_amount, 2);

        return [
            'title' => "New order · {$amount}",
            'body' => "{$this->order->order_number} from “{$this->funnel->name}”",
            'url' => '/fighter/orders',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'funnel_uuid' => $this->funnel->uuid,
            'funnel_name' => $this->funnel->name,
            'amount' => (float) $this->order->total_amount,
        ];
    }
}
