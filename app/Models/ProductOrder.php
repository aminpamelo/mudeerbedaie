<?php

namespace App\Models;

use App\Observers\ProductOrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[ObservedBy(ProductOrderObserver::class)]
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
        'payment_status',
        'payment_confirmed_by_user_id',
        'payment_confirmed_at',
        'payment_rejection_reason',
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
        'matched_live_session_id',
        'platform_order_id',
        'platform_order_number',
        'tracking_id',
        'package_id',
        'buyer_username',
        'reference_number',
        'source',
        'source_reference',
        'sales_source_id',
        'hidden_from_admin',
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
        'receipt_attachment',
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
            'payment_confirmed_at' => 'datetime',
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
            'hidden_from_admin' => 'boolean',
        ];
    }

    protected $appends = ['receipt_attachment_url'];

    public function getReceiptAttachmentUrlAttribute(): ?string
    {
        if (! $this->receipt_attachment) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_attachment);
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

    public function classAssignmentApprovals(): HasMany
    {
        return $this->hasMany(ClassAssignmentApproval::class);
    }

    public function whatsAppCampaignRecipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'product_order_id');
    }

    public function salesSource(): BelongsTo
    {
        return $this->belongsTo(SalesSource::class);
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

    public function matchedLiveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'matched_live_session_id');
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

    /**
     * The effective address for shipping/rate/booking purposes, resolved across
     * BOTH stores: the typed address row (preferred) with every blank field
     * backfilled from the `shipping_address`/`billing_address` JSON column.
     *
     * Funnel, POS, lead (e.g. "Lead FB") and platform orders keep the address
     * in the JSON column with varied key names instead of as address rows —
     * and sometimes leave a row with a missing postcode while the full address
     * sits in the JSON. Reading only the row (as the edit form and J&T/EasyParcel
     * callers used to) made those orders look like they had "no shipping
     * postcode" even when one was on file. Returns null only when neither store
     * has a usable address.
     *
     * @param  'shipping'|'billing'  $type
     */
    public function effectiveAddress(string $type = 'shipping', bool $rowFallback = false): ?object
    {
        $row = $this->addresses()->where('type', $type)->first();

        if ($row === null && $rowFallback) {
            $other = $type === 'shipping' ? 'billing' : 'shipping';
            $row = $this->addresses()->where('type', $other)->first();
        }

        $json = static::normalizeAddressData($this->addressJson($type))
            ?? static::normalizeAddressData($this->addressJson($type === 'shipping' ? 'billing' : 'shipping'));

        if ($row === null && $json === null) {
            return null;
        }

        $fields = ['first_name', 'last_name', 'company', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country'];
        $merged = [];

        foreach ($fields as $field) {
            $rowValue = $row?->getAttribute($field);
            $merged[$field] = filled($rowValue) ? $rowValue : (string) ($json->{$field} ?? '');
        }

        return (object) $merged;
    }

    /**
     * Read one of the loose JSON address columns as an array, tolerating the
     * `billing_address` column not being cast (it may return a JSON string).
     *
     * @return array<string, mixed>
     */
    private function addressJson(string $type): array
    {
        $raw = $type === 'billing' ? $this->getAttribute('billing_address') : $this->shipping_address;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Normalise a loose address array (varied key names across funnel/POS/
     * platform/lead sources) into a consistent object. Returns null when
     * there's nothing usable to ship with.
     *
     * @param  array<string, mixed>  $data
     */
    public static function normalizeAddressData(array $data): ?object
    {
        if (empty($data)) {
            return null;
        }

        $pick = fn (array $keys) => collect($keys)
            ->map(fn ($key) => $data[$key] ?? null)
            ->first(fn ($value) => filled($value));

        $postal = $pick(['postal_code', 'postcode', 'zipcode', 'zip']);
        $line1 = $pick(['address_line_1', 'address_line1', 'address_1', 'full_address', 'address']);

        if (blank($postal) && blank($line1)) {
            return null;
        }

        // POS / lead / free-text orders often type the WHOLE address — including a
        // 5-digit Malaysian postcode — into a single field, leaving no separate
        // postcode. Extract it from the address text so courier booking isn't
        // blocked. Malaysian postcodes are exactly 5 digits; take the last group
        // (the postcode sits after the street/house number).
        if (blank($postal)) {
            $haystack = implode(' ', array_filter([
                (string) $line1,
                (string) ($pick(['full_address']) ?? ''),
                (string) ($pick(['city', 'town']) ?? ''),
                (string) ($pick(['state', 'region', 'province']) ?? ''),
            ]));

            if (preg_match_all('/(?<!\d)\d{5}(?!\d)/', $haystack, $matches) && ! empty($matches[0])) {
                $postal = end($matches[0]);
            }
        }

        $name = (string) ($pick(['name', 'full_name', 'recipient_name']) ?? '');
        $first = (string) ($pick(['first_name']) ?? '');
        $last = (string) ($pick(['last_name']) ?? '');

        if ($name === '') {
            $name = trim($first.' '.$last);
        }

        return (object) [
            'first_name' => $name !== '' ? $name : $first,
            'last_name' => $name !== '' ? '' : $last,
            'company' => (string) ($pick(['company']) ?? ''),
            'phone' => (string) ($pick(['phone', 'phone_number', 'mobile']) ?? ''),
            'address_line_1' => (string) ($line1 ?? ''),
            'address_line_2' => (string) ($pick(['address_line_2', 'address_line2', 'address_2']) ?? ''),
            'city' => (string) ($pick(['city', 'town']) ?? ''),
            'state' => (string) ($pick(['state', 'region', 'province']) ?? ''),
            'postal_code' => (string) ($postal ?? ''),
            'country' => (string) ($pick(['country']) ?? ''),
        ];
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

    public function markAsReturned(): void
    {
        $updates = ['status' => 'returned'];

        // Flip payment_status so upsell commission, reports, and other
        // payment-gated logic stop counting this order. Only adjust if
        // it was previously 'paid' — we don't want to overwrite 'failed'
        // or an existing 'refunded' value.
        if ($this->payment_status === 'paid') {
            $updates['payment_status'] = 'refunded';
        }

        $this->update($updates);

        $this->addSystemNote('Order marked as returned');
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $updates = [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ];

        // A paid order that's later cancelled should no longer count as
        // confirmed revenue. Leave non-paid statuses alone.
        if ($this->payment_status === 'paid') {
            $updates['payment_status'] = 'refunded';
        }

        $this->update($updates);

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

    public function getCustomerPhone(): string
    {
        // Check if customer_phone field is set (platform orders)
        if ($this->customer_phone) {
            return $this->customer_phone;
        }

        if ($this->customer?->phone) {
            return $this->customer->phone;
        }

        $shipping = $this->shippingAddress();
        if ($shipping?->phone) {
            return $shipping->phone;
        }

        $billing = $this->billingAddress();
        if ($billing?->phone) {
            return $billing->phone;
        }

        return 'No phone provided';
    }

    public function hasContactablePhone(): bool
    {
        $phone = $this->getCustomerPhone();

        if ($phone === 'No phone provided' || str_contains($phone, '*')) {
            return false;
        }

        return strlen(preg_replace('/\D/', '', $phone)) >= 9;
    }

    public function getWhatsAppUrl(): ?string
    {
        if (! $this->hasContactablePhone()) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->getCustomerPhone());

        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
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
        // For platform orders (TikTok, etc.), check paid_time
        if ($this->isPlatformOrder()) {
            return ! is_null($this->paid_time);
        }

        // For funnel orders paid via Bayarcash/FPX, check paid_time
        // Bayarcash sets paid_time when payment is successful
        if ($this->source === 'funnel' && ! is_null($this->paid_time)) {
            return true;
        }

        // For other orders, check payment records in the payments table
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

    public function getPaymentMethodLabelAttribute(): string
    {
        $methods = [
            'stripe' => 'Stripe',
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'manual' => 'Manual Payment',
        ];

        return $methods[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method ?? 'Not Set'));
    }

    public function scopeVisibleInAdmin($query)
    {
        return $query->where('hidden_from_admin', false);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeAwaitingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function paymentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by_user_id');
    }

    public function markPaymentAsConfirmed(int $userId, string $receiptPath): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_confirmed_by_user_id' => $userId,
            'payment_confirmed_at' => now(),
            'receipt_attachment' => $receiptPath,
            'paid_time' => $this->paid_time ?? now(),
            'status' => $this->status === 'pending' ? 'confirmed' : $this->status,
        ]);
    }

    public function markPaymentAsRejected(int $userId, string $reason): void
    {
        $this->update([
            'payment_status' => 'failed',
            'payment_confirmed_by_user_id' => $userId,
            'payment_confirmed_at' => now(),
            'payment_rejection_reason' => $reason,
        ]);
    }

    /**
     * Whether the courier collects payment on delivery for this order — either the
     * order's own payment method is COD, or it was booked as an EasyParcel COD
     * shipment (flagged in metadata at booking time).
     */
    public function isCashOnDelivery(): bool
    {
        return $this->payment_method === 'cod'
            || (bool) data_get($this->metadata, 'easyparcel_cod', false);
    }

    /**
     * Record that a COD order's cash was collected on delivery. Only flips a
     * not-yet-paid order; never overrides a 'refunded'/'failed' history.
     */
    public function markCodPaymentCollected(): void
    {
        if ($this->payment_status === 'paid') {
            return;
        }

        $this->update([
            'payment_status' => 'paid',
            'paid_time' => $this->paid_time ?? now(),
        ]);

        $this->addSystemNote('COD payment auto-marked as paid on delivery.');
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
        // Capture the buyer's name/phone directly on the order (not just the
        // address rows) so admin lists, exports and shipping read them reliably.
        $billing = $addresses['billing'] ?? [];
        $customerName = trim((string) ($customerData['name']
            ?? trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')))) ?: null;
        $customerPhone = $customerData['phone'] ?? ($billing['phone'] ?? null) ?: null;

        $order = self::create([
            'order_number' => self::generateOrderNumber(),
            'customer_id' => $cart->user_id,
            'guest_email' => $customerData['email'] ?? null,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'source' => 'storefront',
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
