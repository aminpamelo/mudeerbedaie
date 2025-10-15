<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'stripe_line_item_id',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Helper methods
    public function getFormattedUnitPriceAttribute(): string
    {
        return 'RM '.number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return 'RM '.number_format($this->total_price, 2);
    }

    // Calculate total price from unit price and quantity
    public function calculateTotal(): void
    {
        $this->total_price = $this->unit_price * $this->quantity;
    }

    // Create order item from Stripe line item
    public static function createFromStripeLineItem(Order $order, array $stripeLineItem): self
    {
        $quantity = $stripeLineItem['quantity'] ?? 1;
        $unitPrice = $stripeLineItem['amount'] / 100; // Convert from cents

        return self::create([
            'order_id' => $order->id,
            'description' => $stripeLineItem['description'] ?? 'Subscription charge',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity, // Properly calculate total
            'stripe_line_item_id' => $stripeLineItem['id'] ?? null,
            'metadata' => [
                'stripe_price_id' => $stripeLineItem['price']['id'] ?? null,
                'stripe_product_id' => $stripeLineItem['price']['product'] ?? null,
            ],
        ]);
    }
}
