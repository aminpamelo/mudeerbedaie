<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class PackagePurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_number',
        'package_id',
        'user_id',
        'guest_email',
        'amount_paid',
        'original_amount',
        'discount_amount',
        'currency',
        'coupon_code',
        'status',
        'product_order_id',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'payment_method',
        'purchased_at',
        'completed_at',
        'failed_at',
        'refunded_at',
        'stock_allocated',
        'stock_deducted',
        'stock_snapshot',
        'package_snapshot',
        'metadata',
        'customer_notes',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'purchased_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'stock_allocated' => 'boolean',
            'stock_deducted' => 'boolean',
            'stock_snapshot' => 'array',
            'package_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchase) {
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = self::generatePurchaseNumber();
            }
        });
    }

    // Relationships
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function enrollments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Enrollment::class,
            PackagePurchaseEnrollment::class,
            'package_purchase_id',
            'id',
            'id',
            'enrollment_id'
        );
    }

    public function packagePurchaseEnrollments(): HasMany
    {
        return $this->hasMany(PackagePurchaseEnrollment::class);
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

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // Stock management methods
    public function allocateStock(): bool
    {
        if ($this->stock_allocated) {
            return true; // Already allocated
        }

        $stockSnapshot = [];
        $package = $this->package;

        if (! $package->track_stock) {
            $this->update(['stock_allocated' => true]);

            return true;
        }

        // Check stock availability first
        $stockCheck = $package->checkStockAvailability();
        if (! $stockCheck['available']) {
            return false;
        }

        // Reserve stock for each product
        foreach ($package->products as $product) {
            $requiredQuantity = $product->pivot->quantity;
            $warehouseId = $this->getWarehouseForProduct($product->id) ?? $package->default_warehouse_id;

            $stockLevel = $product->stockLevels()
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stockLevel) {
                $stockSnapshot[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'quantity_before' => $stockLevel->quantity,
                    'available_before' => $stockLevel->available_quantity,
                    'reserved_before' => $stockLevel->reserved_quantity,
                    'allocated_quantity' => $requiredQuantity,
                ];

                $stockLevel->increment('reserved_quantity', $requiredQuantity);
                $stockLevel->decrement('available_quantity', $requiredQuantity);
            }
        }

        $this->update([
            'stock_allocated' => true,
            'stock_snapshot' => $stockSnapshot,
        ]);

        return true;
    }

    public function deductStock(): bool
    {
        if ($this->stock_deducted) {
            return true; // Already deducted
        }

        if (! $this->stock_allocated) {
            // Try to allocate first
            if (! $this->allocateStock()) {
                return false;
            }
        }

        return $this->package->deductStock($this);
    }

    public function releaseStock(): bool
    {
        if (! $this->stock_allocated) {
            return true; // No stock to release
        }

        $stockSnapshot = $this->stock_snapshot ?? [];

        foreach ($stockSnapshot as $snapshot) {
            $stockLevel = StockLevel::where('product_id', $snapshot['product_id'])
                ->where('warehouse_id', $snapshot['warehouse_id'])
                ->first();

            if ($stockLevel) {
                $allocatedQuantity = $snapshot['allocated_quantity'];

                if (! $this->stock_deducted) {
                    // Only release reserved stock if not yet deducted
                    $stockLevel->decrement('reserved_quantity', $allocatedQuantity);
                    $stockLevel->increment('available_quantity', $allocatedQuantity);
                }
            }
        }

        $this->update(['stock_allocated' => false]);

        return true;
    }

    // Purchase flow methods
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'purchased_at' => $this->purchased_at ?? now(),
        ]);
    }

    public function markAsCompleted(): bool
    {
        // Ensure stock is properly handled
        if (! $this->deductStock()) {
            return false;
        }

        // Create product order if there are products
        $this->createProductOrder();

        // Create course enrollments if there are courses
        $this->createCourseEnrollments();

        // Update package purchase count
        $this->package->increment('purchased_count');

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'stock_deducted' => true,
        ]);

        return true;
    }

    public function markAsFailed(?string $reason = null): void
    {
        // Release any allocated stock
        $this->releaseStock();

        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['failure_reason'] = $reason;
        }

        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        // Handle stock restoration if needed
        // Note: This might require manual stock adjustment
    }

    // Integration methods
    protected function createProductOrder(): ?ProductOrder
    {
        $package = $this->package;
        $products = $package->products;

        if ($products->isEmpty()) {
            return null;
        }

        // Create ProductOrder
        $productOrder = ProductOrder::create([
            'order_number' => ProductOrder::generateOrderNumber(),
            'customer_id' => $this->user_id,
            'guest_email' => $this->guest_email,
            'status' => 'confirmed',
            'order_type' => 'package',
            'currency' => $this->currency,
            'subtotal' => $products->sum(fn ($p) => $p->pivot->custom_price ?? $p->base_price * $p->pivot->quantity),
            'total_amount' => $products->sum(fn ($p) => $p->pivot->custom_price ?? $p->base_price * $p->pivot->quantity),
            'order_date' => now(),
            'confirmed_at' => now(),
            'customer_notes' => $this->customer_notes,
            'internal_notes' => "Created from package purchase: {$package->name} (#{$this->purchase_number})",
            'metadata' => [
                'package_purchase_id' => $this->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
            ],
        ]);

        // Create order items
        foreach ($products as $product) {
            $productOrder->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $product->pivot->product_variant_id,
                'warehouse_id' => $product->pivot->warehouse_id ?? $package->default_warehouse_id,
                'product_name' => $product->name,
                'variant_name' => $product->pivot->product_variant_id ?
                    ProductVariant::find($product->pivot->product_variant_id)?->name : null,
                'sku' => $product->sku,
                'quantity_ordered' => $product->pivot->quantity,
                'quantity_shipped' => $product->pivot->quantity,
                'unit_price' => $product->pivot->custom_price ?? $product->base_price,
                'total_price' => ($product->pivot->custom_price ?? $product->base_price) * $product->pivot->quantity,
                'unit_cost' => $product->cost_price,
                'product_snapshot' => [
                    'product_data' => $product->toArray(),
                    'package_context' => [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                    ],
                ],
            ]);
        }

        // Update this purchase with the order reference
        $this->update(['product_order_id' => $productOrder->id]);

        return $productOrder;
    }

    protected function createCourseEnrollments(): array
    {
        $package = $this->package;
        $courses = $package->courses;
        $enrollments = [];

        if ($courses->isEmpty() || ! $this->user_id) {
            return $enrollments;
        }

        // Find or create student record
        $student = Student::firstOrCreate(
            ['user_id' => $this->user_id],
            ['name' => $this->user->name, 'email' => $this->user->email]
        );

        foreach ($courses as $course) {
            // Create enrollment
            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'status' => 'enrolled',
                'enrollment_date' => now(),
                'notes' => "Enrolled via package purchase: {$package->name} (#{$this->purchase_number})",
            ]);

            // Link to package purchase
            $this->packagePurchaseEnrollments()->create([
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'student_id' => $student->id,
                'enrollment_status' => 'created',
                'enrolled_at' => now(),
            ]);

            $enrollments[] = $enrollment;
        }

        return $enrollments;
    }

    // Helper methods
    public function getWarehouseForProduct(int $productId): ?int
    {
        $packageItem = $this->package->items()
            ->where('itemable_type', Product::class)
            ->where('itemable_id', $productId)
            ->first();

        return $packageItem?->warehouse_id;
    }

    public function getCustomerEmail(): string
    {
        return $this->user?->email ?? $this->guest_email ?? 'No email provided';
    }

    public function getCustomerName(): string
    {
        return $this->user?->name ?? 'Guest Customer';
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'RM '.number_format($this->amount_paid, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'pending' => 'yellow',
            'failed', 'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    // Static methods
    public static function generatePurchaseNumber(): string
    {
        do {
            $number = 'PKG-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (self::where('purchase_number', $number)->exists());

        return $number;
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPackage($query, $packageId)
    {
        return $query->where('package_id', $packageId);
    }
}
