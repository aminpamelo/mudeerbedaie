<?php

namespace Database\Factories;

use App\Models\OrderTrackingNotification;
use App\Models\ProductOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTrackingNotification>
 */
class OrderTrackingNotificationFactory extends Factory
{
    protected $model = OrderTrackingNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_order_id' => ProductOrder::factory(),
            'channel' => OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA,
            'recipient' => '60'.$this->faker->numerify('1########'),
            'status' => OrderTrackingNotification::STATUS_SENT,
            'message' => $this->faker->sentence(),
            'provider_message_id' => $this->faker->uuid(),
            'error' => null,
            'meta' => [],
            'sent_by' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => OrderTrackingNotification::STATUS_FAILED,
            'provider_message_id' => null,
            'error' => 'Send failed',
        ]);
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'channel' => OrderTrackingNotification::CHANNEL_EMAIL,
            'recipient' => $this->faker->safeEmail(),
        ]);
    }
}
