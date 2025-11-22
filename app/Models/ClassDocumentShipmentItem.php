<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDocumentShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_document_shipment_id',
        'class_student_id',
        'student_id',
        'product_order_id',
        'product_order_item_id',
        'tracking_number',
        'status',
        'quantity',
        'item_cost',
        'shipping_cost',
        'shipping_provider',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'delivery_notes',
        'packed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'item_cost' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // Relationships
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ClassDocumentShipment::class, 'class_document_shipment_id');
    }

    public function classStudent(): BelongsTo
    {
        return $this->belongsTo(ClassStudent::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function productOrderItem(): BelongsTo
    {
        return $this->belongsTo(ProductOrderItem::class);
    }

    // Status helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPacked(): bool
    {
        return $this->status === 'packed';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    // Status management methods
    public function markAsPacked(): void
    {
        $this->update([
            'status' => 'packed',
            'packed_at' => now(),
        ]);
    }

    public function markAsShipped(?string $trackingNumber = null, ?string $provider = null): void
    {
        $data = [
            'status' => 'shipped',
            'shipped_at' => now(),
        ];

        if ($trackingNumber) {
            $data['tracking_number'] = $trackingNumber;
        }

        if ($provider) {
            $data['shipping_provider'] = $provider;
        }

        $this->update($data);

        // Deduct stock for this individual item if not already deducted at shipment level
        $this->deductStockForItem();
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $data = ['status' => 'failed'];

        if ($reason) {
            $data['delivery_notes'] = $reason;
        }

        $this->update($data);
    }

    public function markAsReturned(?string $reason = null): void
    {
        $data = ['status' => 'returned'];

        if ($reason) {
            $data['delivery_notes'] = $reason;
        }

        $this->update($data);
    }

    // Stock management methods
    public function deductStockForItem(): bool
    {
        // Skip if already deducted
        if ($this->hasStockBeenDeducted()) {
            return true;
        }

        // Load the shipment relationship
        $shipment = $this->shipment;

        if (! $shipment || ! $shipment->product || ! $shipment->product->shouldTrackQuantity()) {
            return true;
        }

        $stockLevel = $shipment->product->stockLevels()
            ->where('warehouse_id', $shipment->warehouse_id)
            ->first();

        if ($stockLevel) {
            // Check if we have enough stock
            if ($stockLevel->quantity < $this->quantity) {
                return false;
            }

            // Deduct from main quantity
            $stockLevel->decrement('quantity', $this->quantity);

            // If this was reserved, also deduct from reserved
            if ($stockLevel->reserved_quantity >= $this->quantity) {
                $stockLevel->decrement('reserved_quantity', $this->quantity);
            }

            // Refresh to get updated quantity
            $stockLevel->refresh();

            // Create stock movement record
            $quantityBefore = $stockLevel->quantity + $this->quantity;

            StockMovement::create([
                'product_id' => $shipment->product_id,
                'warehouse_id' => $shipment->warehouse_id,
                'type' => 'out',
                'quantity' => -$this->quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'notes' => "Individual shipment item for {$this->student->name} - {$shipment->period_label}",
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'shipment_item_id' => $this->id,
                    'student_id' => $this->student_id,
                    'class_id' => $shipment->class_id,
                    'period_label' => $shipment->period_label,
                ],
            ]);

            return true;
        }

        return false;
    }

    public function hasStockBeenDeducted(): bool
    {
        // Check if a stock movement exists for this specific item
        return StockMovement::where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->exists();
    }

    // Helper methods
    public function getFullShippingAddress(): string
    {
        $parts = array_filter([
            $this->shipping_address_line_1,
            $this->shipping_address_line_2,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_postcode,
            $this->shipping_country,
        ]);

        return implode(', ', $parts);
    }

    public function hasShippingAddress(): bool
    {
        return ! empty($this->shipping_address_line_1);
    }

    public function updateShippingAddress(array $addressData): void
    {
        $this->update([
            'shipping_address_line_1' => $addressData['line_1'] ?? null,
            'shipping_address_line_2' => $addressData['line_2'] ?? null,
            'shipping_city' => $addressData['city'] ?? null,
            'shipping_state' => $addressData['state'] ?? null,
            'shipping_postcode' => $addressData['postcode'] ?? null,
            'shipping_country' => $addressData['country'] ?? 'Malaysia',
        ]);
    }

    public function getFormattedItemCostAttribute(): string
    {
        return 'RM '.number_format($this->item_cost, 2);
    }

    public function getFormattedShippingCostAttribute(): string
    {
        return 'RM '.number_format($this->shipping_cost, 2);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->item_cost + $this->shipping_cost;
    }

    public function getFormattedTotalCostAttribute(): string
    {
        return 'RM '.number_format($this->total_cost, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'packed' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'failed', 'returned' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePacked($query)
    {
        return $query->where('status', 'packed');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
