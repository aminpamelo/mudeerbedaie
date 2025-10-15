<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClassDocumentShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'product_id',
        'shipment_number',
        'period_label',
        'period_start_date',
        'period_end_date',
        'status',
        'total_recipients',
        'quantity_per_student',
        'total_quantity',
        'warehouse_id',
        'total_cost',
        'shipping_cost',
        'notes',
        'metadata',
        'scheduled_at',
        'processed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'total_cost' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shipment) {
            if (empty($shipment->shipment_number)) {
                $shipment->shipment_number = self::generateShipmentNumber();
            }
        });
    }

    // Relationships
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClassDocumentShipmentItem::class, 'class_document_shipment_id');
    }

    // Status helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // Status management methods
    public function markAsProcessing(): void
    {
        // Deduct stock when we start processing (preparing shipment)
        if ($this->isPending() && ! $this->hasStockBeenDeducted()) {
            $this->deductStock();
        }

        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    public function markAsShipped(): void
    {
        // Deduct stock if not already deducted
        if (! $this->hasStockBeenDeducted()) {
            $this->deductStock();
        }

        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        // Update all items to shipped
        $this->items()->where('status', 'pending')->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Update all items to delivered
        $this->items()->whereIn('status', ['pending', 'shipped'])->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        // Release stock if processing
        if ($this->isProcessing()) {
            $this->releaseStock();
        }

        $this->update(['status' => 'cancelled']);

        // Cancel all items
        $this->items()->whereIn('status', ['pending', 'packed'])->update([
            'status' => 'failed',
        ]);
    }

    // Stock management
    public function reserveStock(): bool
    {
        if (! $this->product || ! $this->product->shouldTrackQuantity()) {
            return true;
        }

        if (! $this->product->checkStockAvailability($this->total_quantity, $this->warehouse_id)) {
            return false;
        }

        $stockLevel = $this->product->stockLevels()
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        if ($stockLevel) {
            // Increment reserved quantity (available_quantity will auto-calculate)
            $stockLevel->increment('reserved_quantity', $this->total_quantity);

            return true;
        }

        return false;
    }

    public function deductStock(): bool
    {
        if (! $this->product || ! $this->product->shouldTrackQuantity()) {
            return true;
        }

        // Get all items that haven't been deducted yet
        $itemsToDeduct = $this->items()->get()->filter(function ($item) {
            return ! $item->hasStockBeenDeducted();
        });

        // If no items need deduction, we're done
        if ($itemsToDeduct->isEmpty()) {
            return true;
        }

        // Calculate total quantity to deduct (only for items not yet deducted)
        $quantityToDeduct = $itemsToDeduct->sum('quantity');

        $stockLevel = $this->product->stockLevels()
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        if ($stockLevel) {
            // Check if we have enough stock
            if ($stockLevel->quantity < $quantityToDeduct) {
                return false;
            }

            // Deduct from main quantity
            $stockLevel->decrement('quantity', $quantityToDeduct);

            // If this was reserved, also deduct from reserved
            // Note: available_quantity is auto-calculated as (quantity - reserved_quantity)
            if ($stockLevel->reserved_quantity >= $quantityToDeduct) {
                $stockLevel->decrement('reserved_quantity', $quantityToDeduct);
            }

            // Refresh to get updated quantity
            $stockLevel->refresh();

            // Create stock movement record
            $quantityBefore = $stockLevel->quantity + $quantityToDeduct;

            StockMovement::create([
                'product_id' => $this->product_id,
                'warehouse_id' => $this->warehouse_id,
                'type' => 'out', // Stock going out for class shipment
                'quantity' => $quantityToDeduct,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stockLevel->quantity,
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'notes' => "Class document shipment: {$this->class->title} - {$this->period_label} ({$itemsToDeduct->count()} items)",
                'metadata' => [
                    'shipment_id' => $this->id,
                    'class_id' => $this->class_id,
                    'period_label' => $this->period_label,
                    'items_deducted' => $itemsToDeduct->count(),
                ],
            ]);

            return true;
        }

        return false;
    }

    public function releaseStock(): bool
    {
        if (! $this->product || ! $this->product->shouldTrackQuantity()) {
            return true;
        }

        $stockLevel = $this->product->stockLevels()
            ->where('warehouse_id', $this->warehouse_id)
            ->first();

        if ($stockLevel) {
            // Decrement reserved quantity (available_quantity will auto-calculate)
            $stockLevel->decrement('reserved_quantity', $this->total_quantity);

            return true;
        }

        return false;
    }

    // Helper methods
    public function hasStockBeenDeducted(): bool
    {
        // Check if a stock movement exists for this shipment
        return StockMovement::where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->exists();
    }

    public function getDeliveryRate(): float
    {
        $totalItems = $this->items()->count();

        if ($totalItems === 0) {
            return 0;
        }

        $deliveredItems = $this->items()->where('status', 'delivered')->count();

        return round(($deliveredItems / $totalItems) * 100, 2);
    }

    public function getPendingItemsCount(): int
    {
        return $this->items()->where('status', 'pending')->count();
    }

    public function getShippedItemsCount(): int
    {
        return $this->items()->where('status', 'shipped')->count();
    }

    public function getDeliveredItemsCount(): int
    {
        return $this->items()->where('status', 'delivered')->count();
    }

    public function getFormattedTotalCostAttribute(): string
    {
        return 'RM '.number_format($this->total_cost, 2);
    }

    public function getFormattedShippingCostAttribute(): string
    {
        return 'RM '.number_format($this->shipping_cost, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    // Static methods
    public static function generateShipmentNumber(): string
    {
        do {
            $number = 'SHIP-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (self::where('shipment_number', $number)->exists());

        return $number;
    }

    public static function createForClass(ClassModel $class, \Carbon\Carbon $periodStart, \Carbon\Carbon $periodEnd): ?self
    {
        if (! $class->enable_document_shipment || ! $class->shipment_product_id) {
            return null;
        }

        // Get active students with active subscriptions only
        $activeStudents = $class->activeStudents()
            ->whereHas('student.enrollments', function ($query) use ($class) {
                $query->where('course_id', $class->course_id)
                    ->whereNotNull('stripe_subscription_id')
                    ->whereIn('subscription_status', ['active', 'trialing']);
            })
            ->get();

        if ($activeStudents->isEmpty()) {
            return null;
        }

        $totalRecipients = $activeStudents->count();
        $quantityPerStudent = $class->shipment_quantity_per_student ?? 1;
        $totalQuantity = $totalRecipients * $quantityPerStudent;

        $product = $class->shipmentProduct;
        $itemCost = $product ? $product->base_price : 0;

        // Create shipment
        $shipment = self::create([
            'class_id' => $class->id,
            'product_id' => $class->shipment_product_id,
            'period_label' => $periodStart->format('F Y'),
            'period_start_date' => $periodStart->toDateString(),
            'period_end_date' => $periodEnd->toDateString(),
            'status' => 'pending',
            'total_recipients' => $totalRecipients,
            'quantity_per_student' => $quantityPerStudent,
            'total_quantity' => $totalQuantity,
            'warehouse_id' => $class->shipment_warehouse_id,
            'total_cost' => $itemCost * $totalQuantity,
            'scheduled_at' => $periodStart,
            'notes' => $class->shipment_notes,
        ]);

        // Create shipment items for each student with active subscription
        foreach ($activeStudents as $classStudent) {
            $shipment->items()->create([
                'class_student_id' => $classStudent->id,
                'student_id' => $classStudent->student_id,
                'quantity' => $quantityPerStudent,
                'item_cost' => $itemCost,
                'status' => 'pending',
            ]);
        }

        return $shipment;
    }

    public static function updateShipmentStudents(self $shipment, ClassModel $class): array
    {
        // Get current students with active subscriptions
        $currentSubscribedStudents = $class->activeStudents()
            ->whereHas('student.enrollments', function ($query) use ($class) {
                $query->where('course_id', $class->course_id)
                    ->whereNotNull('stripe_subscription_id')
                    ->whereIn('subscription_status', ['active', 'trialing']);
            })
            ->get();

        if ($currentSubscribedStudents->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No students with active subscriptions found.',
            ];
        }

        // Get existing shipment items
        $existingItems = $shipment->items()->with('classStudent')->get();
        $existingStudentIds = $existingItems->pluck('student_id')->toArray();
        $currentStudentIds = $currentSubscribedStudents->pluck('student_id')->toArray();

        // Find students to add (new subscriptions)
        $studentsToAdd = $currentSubscribedStudents->filter(function ($classStudent) use ($existingStudentIds) {
            return ! in_array($classStudent->student_id, $existingStudentIds);
        });

        // Find students to remove (inactive subscriptions)
        $studentIdsToRemove = array_diff($existingStudentIds, $currentStudentIds);

        $added = 0;
        $removed = 0;

        $quantityPerStudent = $shipment->quantity_per_student ?? 1;
        $product = $class->shipmentProduct;
        $itemCost = $product ? $product->base_price : 0;

        // Add new students
        foreach ($studentsToAdd as $classStudent) {
            $shipment->items()->create([
                'class_student_id' => $classStudent->id,
                'student_id' => $classStudent->student_id,
                'quantity' => $quantityPerStudent,
                'item_cost' => $itemCost,
                'status' => 'pending',
            ]);
            $added++;
        }

        // Remove students with inactive subscriptions
        if (! empty($studentIdsToRemove)) {
            $shipment->items()
                ->whereIn('student_id', $studentIdsToRemove)
                ->delete();
            $removed = count($studentIdsToRemove);
        }

        // Update shipment totals
        $totalRecipients = $shipment->items()->count();
        $totalQuantity = $totalRecipients * $quantityPerStudent;

        $shipment->update([
            'total_recipients' => $totalRecipients,
            'total_quantity' => $totalQuantity,
            'total_cost' => $itemCost * $totalQuantity,
        ]);

        return [
            'success' => true,
            'added' => $added,
            'removed' => $removed,
            'total_recipients' => $totalRecipients,
        ];
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForPeriod($query, $year, $month = null)
    {
        if ($month) {
            $start = now()->setYear($year)->setMonth($month)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return $query->where('period_start_date', '>=', $start)
                ->where('period_start_date', '<=', $end);
        }

        return $query->whereYear('period_start_date', $year);
    }
}
