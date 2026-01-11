<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'agent_id',
        'student_id',
        'guest_email',
        'status',
        'order_type',
        'currency',
        'subtotal',
        'shipping_cost',
        'tax_amount',
        'total_amount',
        'coupon_code',
        'discount_amount',
        'order_date',
        'required_delivery_date',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'customer_notes',
        'internal_notes',
        'metadata',
        // Platform integration fields
        'platform_id',
        'platform_account_id',
        'platform_order_id',
        'platform_order_number',
        'tracking_id',
        'package_id',
        'buyer_username',
        'reference_number',
        'source',
        'source_reference',
        // Platform discount breakdown
        'sku_platform_discount',
        'sku_seller_discount',
        'shipping_fee_seller_discount',
        'shipping_fee_platform_discount',
        'payment_platform_discount',
        'original_shipping_fee',
        // Additional customer fields
        'customer_name',
        'customer_phone',
        'shipping_address',
        // Timeline fields
        'paid_time',
        'rts_time',
        // Platform logistics
        'fulfillment_type',
        'warehouse_name',
        'delivery_option',
        'shipping_provider',
        'payment_method',
        'weight_kg',
        // Platform notes
        'buyer_message',
        'seller_note',
        // Cancellation details
        'cancel_by',
        'cancel_reason',
        // Status tracking
        'checked_status',
        'checked_marked_by',
        // Platform data
        'platform_data',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'order_date' => 'datetime',
            'required_delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            // Platform discount fields
            'sku_platform_discount' => 'decimal:2',
            'sku_seller_discount' => 'decimal:2',
            'shipping_fee_seller_discount' => 'decimal:2',
            'shipping_fee_platform_discount' => 'decimal:2',
            'payment_platform_discount' => 'decimal:2',
            'original_shipping_fee' => 'decimal:2',
            // Platform timeline
            'paid_time' => 'datetime',
            'rts_time' => 'datetime',
            // Platform logistics
            'weight_kg' => 'decimal:3',
            // Platform data
            'shipping_address' => 'array',
            'platform_data' => 'array',
        ];
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class, 'order_id');
    }

    public function returnRefunds(): HasMany
    {
        return $this->hasMany(ReturnRefund::class, 'order_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ProductOrderAddress::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProductOrderPayment::class, 'order_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProductOrderNote::class, 'order_id');
    }

    // Platform relationships
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    // Address helpers
    public function billingAddress(): ?ProductOrderAddress
    {
        return $this->addresses()->where('type', 'billing')->first();
    }

    public function shippingAddress(): ?ProductOrderAddress
    {
        return $this->addresses()->where('type', 'shipping')->first();
    }

    // Status management
    public function markAsConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->addSystemNote('Order confirmed');
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
        $this->addSystemNote('Order moved to processing');
    }

    public function markAsShipped(): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        $this->addSystemNote('Order shipped');
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $this->addSystemNote('Order delivered');
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $message = 'Order cancelled';
        if ($reason) {
            $message .= ': '.$reason;
        }

        $this->addSystemNote($message);
    }

    // Helper methods
    public function addSystemNote(string $message, array $metadata = []): ProductOrderNote
    {
        return $this->notes()->create([
            'type' => 'system',
            'message' => $message,
            'is_visible_to_customer' => false,
            'metadata' => $metadata,
        ]);
    }

    public function addCustomerNote(string $message, ?User $user = null): ProductOrderNote
    {
        return $this->notes()->create([
            'user_id' => $user?->id,
            'type' => 'customer',
            'message' => $message,
            'is_visible_to_customer' => true,
        ]);
    }

    public function addInternalNote(string $message, User $user): ProductOrderNote
    {
        return $this->notes()->create([
            'user_id' => $user->id,
            'type' => 'internal',
            'message' => $message,
            'is_visible_to_customer' => false,
        ]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed', 'processing']);
    }

    public function getCustomerEmail(): string
    {
        return $this->customer?->email ?? $this->guest_email ?? 'No email provided';
    }

    public function getCustomerName(): string
    {
        // Check if customer_name field is set (platform orders)
        if ($this->customer_name) {
            return $this->customer_name;
        }

        if ($this->customer) {
            return $this->customer->name;
        }

        $billing = $this->billingAddress();
        if ($billing) {
            return $billing->first_name.' '.$billing->last_name;
        }

        return 'Guest Customer';
    }

    // Platform order helper methods
    public function isPlatformOrder(): bool
    {
        return ! is_null($this->platform_id) || $this->source === 'platform_import';
    }

    public function getTotalDiscountAttribute(): float
    {
        if ($this->isPlatformOrder()) {
            return $this->sku_platform_discount +
                   $this->sku_seller_discount +
                   $this->shipping_fee_seller_discount +
                   $this->shipping_fee_platform_discount +
                   $this->payment_platform_discount;
        }

        return $this->discount_amount;
    }

    public function getNetShippingFeeAttribute(): float
    {
        if ($this->isPlatformOrder() && $this->original_shipping_fee) {
            return $this->original_shipping_fee -
                   $this->shipping_fee_seller_discount -
                   $this->shipping_fee_platform_discount;
        }

        return $this->shipping_cost;
    }

    public function isPaid(): bool
    {
        if ($this->isPlatformOrder()) {
            return ! is_null($this->paid_time);
        }

        return $this->payments()->where('status', 'completed')->sum('amount') >= $this->total_amount;
    }

    public function isReadyToShip(): bool
    {
        return ! is_null($this->rts_time);
    }

    public function isShipped(): bool
    {
        return ! is_null($this->shipped_at);
    }

    public function isDelivered(): bool
    {
        return ! is_null($this->delivered_at);
    }

    public function isChecked(): bool
    {
        return $this->checked_status === 'checked';
    }

    public function hasBuyerMessage(): bool
    {
        return ! empty($this->buyer_message);
    }

    public function hasSellerNote(): bool
    {
        return ! empty($this->seller_note);
    }

    public function getOrderTimelineAttribute(): array
    {
        $timeline = [];

        if ($this->order_date) {
            $timeline[] = [
                'status' => 'created',
                'timestamp' => $this->order_date,
                'label' => 'Order Created',
            ];
        }

        if ($this->paid_time) {
            $timeline[] = [
                'status' => 'paid',
                'timestamp' => $this->paid_time,
                'label' => 'Payment Confirmed',
            ];
        }

        if ($this->confirmed_at) {
            $timeline[] = [
                'status' => 'confirmed',
                'timestamp' => $this->confirmed_at,
                'label' => 'Order Confirmed',
            ];
        }

        if ($this->rts_time) {
            $timeline[] = [
                'status' => 'rts',
                'timestamp' => $this->rts_time,
                'label' => 'Ready to Ship',
            ];
        }

        if ($this->shipped_at) {
            $timeline[] = [
                'status' => 'shipped',
                'timestamp' => $this->shipped_at,
                'label' => 'Shipped',
            ];
        }

        if ($this->delivered_at) {
            $timeline[] = [
                'status' => 'delivered',
                'timestamp' => $this->delivered_at,
                'label' => 'Delivered',
            ];
        }

        if ($this->cancelled_at) {
            $timeline[] = [
                'status' => 'cancelled',
                'timestamp' => $this->cancelled_at,
                'label' => 'Cancelled',
            ];
        }

        // Sort by timestamp
        usort($timeline, fn ($a, $b) => $a['timestamp']->timestamp <=> $b['timestamp']->timestamp);

        return $timeline;
    }

    public function getFormattedWeightAttribute(): string
    {
        return $this->weight_kg ? number_format($this->weight_kg, 3).' kg' : 'N/A';
    }

    public function getDaysToDeliveryAttribute(): ?int
    {
        if (! $this->shipped_at || ! $this->delivered_at) {
            return null;
        }

        return $this->shipped_at->diffInDays($this->delivered_at);
    }

    public function getDisplayOrderIdAttribute(): string
    {
        return $this->platform_order_number ?: $this->platform_order_id ?: $this->order_number;
    }

    // Static methods
    public static function generateOrderNumber(): string
    {
        do {
            $number = 'PO-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (self::where('order_number', $number)->exists());

        return $number;
    }

    public static function createFromCart(ProductCart $cart, array $customerData, array $addresses): self
    {
        $order = self::create([
            'order_number' => self::generateOrderNumber(),
            'customer_id' => $cart->user_id,
            'guest_email' => $customerData['email'] ?? null,
            'subtotal' => $cart->subtotal,
            'tax_amount' => $cart->tax_amount,
            'total_amount' => $cart->total_amount,
            'coupon_code' => $cart->coupon_code,
            'discount_amount' => $cart->discount_amount,
            'currency' => $cart->currency,
            'customer_notes' => $customerData['notes'] ?? null,
        ]);

        // Create order items from cart items
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'product_variant_id' => $cartItem->product_variant_id,
                'warehouse_id' => $cartItem->warehouse_id,
                'product_name' => $cartItem->product->name,
                'variant_name' => $cartItem->variant?->name,
                'sku' => $cartItem->getSku(),
                'quantity_ordered' => $cartItem->quantity,
                'unit_price' => $cartItem->unit_price,
                'total_price' => $cartItem->total_price,
                'unit_cost' => $cartItem->variant?->cost_price ?? $cartItem->product->cost_price,
                'product_snapshot' => $cartItem->product_snapshot,
            ]);
        }

        // Create addresses
        foreach ($addresses as $type => $addressData) {
            $order->addresses()->create([
                'type' => $type,
                ...$addressData,
            ]);
        }

        $order->addSystemNote('Order created from cart');

        return $order;
    }
}
