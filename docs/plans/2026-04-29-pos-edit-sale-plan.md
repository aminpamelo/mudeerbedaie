# POS Edit Sale Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let admin/sales users edit an existing POS sale (customer info, items, discount, shipping, payment method/reference, sales source) from the Sales History detail panel, with a stock-diff that adjusts on the fly.

**Architecture:** New `PUT /api/pos/sales/{sale}` endpoint on `App\Http\Controllers\Api\PosController` driven by a fresh `UpdatePosSaleRequest`. Stock changes use a diff between old `ProductOrderItem` rows and the incoming payload, applied via `deductStock()` and a new symmetric `restoreStock()`. Frontend gets a new `EditSaleModal.jsx` mounted from `SalesHistory.jsx`, opened by an "Edit Order" button in the existing detail panel.

**Tech Stack:** Laravel 12, Livewire/Volt-free React (POS app under `resources/js/pos/`), Pest 4 feature tests, Tailwind v4.

**Reference design:** [docs/plans/2026-04-29-pos-edit-sale-design.md](2026-04-29-pos-edit-sale-design.md)

---

## Conventions for every task

- **Use `route('api.pos.sales.update')`** in tests once the route is registered (Task 3).
- **Run focused tests:** `php artisan test --compact --filter=<TestNamePart>`. Don't run the full suite per task.
- **Frontend changes** require a build (`npm run build`) or the user's running `npm run dev`. Mention it in the commit body when relevant.
- **Pint:** run `vendor/bin/pint --dirty` before each backend commit.
- **Commit at the end of every task.** Subjects use the existing convention — see `git log --oneline -10`.

---

## Task 1: Add `restoreStock()` to `ProductOrderItem`

**Files:**
- Modify: `app/Models/ProductOrderItem.php` (add method near `deductStock` at line 230)
- Test: `tests/Feature/Pos/PosEditSaleStockTest.php` (new)

### Step 1.1: Write the failing test

Create `tests/Feature/Pos/PosEditSaleStockTest.php`:

```php
<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('restoreStock returns quantity to the warehouse and writes an in movement', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create([
        'status' => 'active',
        'track_quantity' => true,
    ]);
    StockLevel::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    $order = ProductOrder::factory()->create(['source' => 'pos']);
    $item = $order->items()->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'product_name' => $product->name,
        'sku' => $product->sku ?? 'sku',
        'quantity_ordered' => 3,
        'unit_price' => 10,
        'total_price' => 30,
    ]);

    $item->restoreStock();

    $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    expect($level->quantity)->toBe(13);

    $movement = StockMovement::where('reference_id', $order->id)->where('type', 'in')->first();
    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(3);
});
```

**Note:** check existing factories/columns first — adjust field names if `track_quantity` is something else (e.g., `should_track_quantity`). Use `php artisan tinker` and `(new App\Models\Product)->getFillable()` if unsure.

### Step 1.2: Run the test to confirm it fails

```bash
php artisan test --compact --filter=PosEditSaleStockTest
```

**Expected:** ❌ fail with `BadMethodCallException` or `Method ... ::restoreStock does not exist`.

### Step 1.3: Implement `restoreStock()`

Open `app/Models/ProductOrderItem.php`. Right after the existing `deductProductStock()` method (around line 350), add:

```php
/**
 * Restore stock previously deducted by this item.
 * Mirror of deductStock(): increments stock and writes an 'in' StockMovement.
 */
public function restoreStock(): void
{
    if ($this->isPackage() && $this->package) {
        $this->restorePackageStock();
    } elseif ($this->isProduct() && $this->product) {
        $this->restoreProductStock();
    }
}

protected function restorePackageStock(): void
{
    $package = $this->package;

    if (! $package || ! $package->track_stock) {
        return;
    }

    $warehouseId = $this->warehouse_id ?? $package->default_warehouse_id;

    foreach ($package->products as $product) {
        $requiredQuantity = $product->pivot->quantity * $this->quantity_ordered;
        $productWarehouseId = $warehouseId ?? $product->pivot->warehouse_id;

        $stockLevel = $product->stockLevels()
            ->where('warehouse_id', $productWarehouseId)
            ->first();

        if (! $stockLevel) {
            continue;
        }

        $quantityBefore = $stockLevel->quantity;
        $stockLevel->increment('quantity', $requiredQuantity);
        $stockLevel->refresh();
        $stockLevel->update(['last_movement_at' => now()]);

        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $product->pivot->product_variant_id ?? null,
            'warehouse_id' => $productWarehouseId,
            'type' => 'in',
            'quantity' => $requiredQuantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $stockLevel->quantity,
            'unit_cost' => $stockLevel->average_cost ?? 0,
            'reference_type' => ProductOrder::class,
            'reference_id' => $this->order_id,
            'notes' => "POS edit: stock returned for package {$package->name} (Order #{$this->order->order_number})",
            'metadata' => [
                'order_item_id' => $this->id,
                'package_id' => $package->id,
            ],
        ]);
    }
}

protected function restoreProductStock(): void
{
    $product = $this->product;

    if (! $product || ! $product->shouldTrackQuantity()) {
        return;
    }

    $stockLevel = $product->stockLevels()
        ->where('warehouse_id', $this->warehouse_id)
        ->first();

    if (! $stockLevel) {
        return;
    }

    $quantityBefore = $stockLevel->quantity;
    $stockLevel->increment('quantity', $this->quantity_ordered);
    $stockLevel->refresh();
    $stockLevel->update(['last_movement_at' => now()]);

    StockMovement::create([
        'product_id' => $product->id,
        'product_variant_id' => $this->product_variant_id ?? null,
        'warehouse_id' => $this->warehouse_id,
        'type' => 'in',
        'quantity' => $this->quantity_ordered,
        'quantity_before' => $quantityBefore,
        'quantity_after' => $stockLevel->quantity,
        'unit_cost' => $stockLevel->average_cost ?? 0,
        'reference_type' => ProductOrder::class,
        'reference_id' => $this->order_id,
        'notes' => "POS edit: stock returned (Order #{$this->order->order_number})",
        'metadata' => [
            'order_item_id' => $this->id,
            'product_name' => $product->name,
        ],
    ]);
}
```

### Step 1.4: Run the test — expect pass

```bash
php artisan test --compact --filter=PosEditSaleStockTest
vendor/bin/pint --dirty
```

### Step 1.5: Commit

```bash
git add app/Models/ProductOrderItem.php tests/Feature/Pos/PosEditSaleStockTest.php
git commit -m "feat(pos): add ProductOrderItem::restoreStock() for edit/return flows"
```

---

## Task 2: Build `UpdatePosSaleRequest`

**Files:**
- Create: `app/Http/Requests/UpdatePosSaleRequest.php`

### Step 2.1: Generate the request

```bash
php artisan make:request UpdatePosSaleRequest --no-interaction
```

### Step 2.2: Replace contents

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_source_id' => ['required', 'exists:sales_sources,id'],
            'customer_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cod'],
            'payment_reference' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:product_order_items,id'],
            'items.*.itemable_type' => ['required', 'in:product,package,course'],
            'items.*.itemable_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.class_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sales_source_id.required' => 'Please select a sales source.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'payment_reference.required_if' => 'Payment reference is required for bank transfer.',
            'customer_name.required_without' => 'Customer name is required for walk-in customers.',
            'customer_phone.required_without' => 'Customer phone is required for walk-in customers.',
        ];
    }
}
```

### Step 2.3: Pint + commit

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/UpdatePosSaleRequest.php
git commit -m "feat(pos): add UpdatePosSaleRequest validation for sale edits"
```

---

## Task 3: Register the route + stub controller

**Files:**
- Modify: `routes/api.php` (around line 405 where `update-details` lives)
- Modify: `app/Http/Controllers/Api/PosController.php` (add `updateSale` method + import for `UpdatePosSaleRequest`)
- Test: `tests/Feature/Pos/PosEditSaleTest.php` (new)

### Step 3.1: Write a failing route-existence + auth test

Create `tests/Feature/Pos/PosEditSaleTest.php`:

```php
<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('non-pos orders cannot be edited via POS endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['source' => 'website']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $order), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X',
        'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]],
    ]);

    $response->assertForbidden();
});
```

### Step 3.2: Run — expect fail (`Route [api.pos.sales.update] not defined.`)

```bash
php artisan test --compact --filter=PosEditSaleTest
```

### Step 3.3: Add the route

Open `routes/api.php` and after the `update-details` line (around 405), add:

```php
Route::put('sales/{sale}', [\App\Http\Controllers\Api\PosController::class, 'updateSale'])->name('api.pos.sales.update');
```

### Step 3.4: Add stub controller method

In `app/Http/Controllers/Api/PosController.php` add the import at the top:

```php
use App\Http\Requests\UpdatePosSaleRequest;
```

Then add this method after `updateSaleDetails`:

```php
/**
 * Edit a POS sale: customer info, items, discount, shipping, payment, sales source.
 * Salesperson is intentionally not editable. Status is handled by updateSaleStatus.
 */
public function updateSale(UpdatePosSaleRequest $request, ProductOrder $sale): JsonResponse
{
    if ($sale->source !== 'pos') {
        return response()->json(['message' => 'Only POS sales can be edited here.'], 403);
    }

    // Implementation lands in Task 4.
    return response()->json(['message' => 'Not implemented yet.'], 501);
}
```

### Step 3.5: Run — expect pass

```bash
php artisan test --compact --filter='non-pos orders cannot be edited'
```

### Step 3.6: Pint + commit

```bash
vendor/bin/pint --dirty
git add routes/api.php app/Http/Controllers/Api/PosController.php tests/Feature/Pos/PosEditSaleTest.php
git commit -m "feat(pos): wire up edit-sale route + non-pos guard"
```

---

## Task 4: Implement `updateSale` (full edit transaction)

**Files:**
- Modify: `app/Http/Controllers/Api/PosController.php`
- Modify: `tests/Feature/Pos/PosEditSaleTest.php` (extend)

### Step 4.1: Add a happy-path test for customer + totals edit

Append to `tests/Feature/Pos/PosEditSaleTest.php`:

```php
test('admin can edit customer info and recompute totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);

    // Seed an existing POS sale via the create endpoint.
    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'Original',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]],
    ])->assertCreated()->json('data');

    $itemId = $created['items'][0]['id'];

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'Updated Name',
        'customer_phone' => '0199999999',
        'customer_email' => 'foo@bar.com',
        'customer_address' => '1 Jalan ABC',
        'payment_method' => 'cash',
        'discount_amount' => 5,
        'discount_type' => 'fixed',
        'shipping_cost' => 10,
        'items' => [[
            'id' => $itemId,
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
        ]],
    ]);

    $response->assertOk();

    $order = ProductOrder::find($created['id'])->fresh(['items', 'payments']);
    expect($order->customer_name)->toBe('Updated Name');
    expect($order->customer_phone)->toBe('0199999999');
    expect($order->guest_email)->toBe('foo@bar.com');
    expect((float) $order->subtotal)->toBe(100.0);
    expect((float) $order->discount_amount)->toBe(5.0);
    expect((float) $order->shipping_cost)->toBe(10.0);
    expect((float) $order->total_amount)->toBe(105.0);
    expect((float) $order->payments->first()->amount)->toBe(105.0);
});
```

### Step 4.2: Run — expect fail (501 / assertion mismatch)

```bash
php artisan test --compact --filter=PosEditSaleTest
```

### Step 4.3: Replace stub with real implementation

In `app/Http/Controllers/Api/PosController.php`, replace the stub `updateSale` with:

```php
public function updateSale(UpdatePosSaleRequest $request, ProductOrder $sale): JsonResponse
{
    if ($sale->source !== 'pos') {
        return response()->json(['message' => 'Only POS sales can be edited here.'], 403);
    }

    $validated = $request->validated();

    return DB::transaction(function () use ($validated, $sale, $request) {
        // 1. Index existing items by id, build the model-class map.
        $existingItems = $sale->items()->get()->keyBy('id');
        $modelClassMap = [
            'product' => Product::class,
            'package' => Package::class,
            'course' => Course::class,
        ];

        // 2. Walk the incoming payload, build per-row data, recompute subtotal.
        $subtotal = 0;
        $touchedExistingIds = [];
        $itemUpserts = []; // [['existing' => OrderItem|null, 'data' => array]]

        foreach ($validated['items'] as $row) {
            $modelClass = $modelClassMap[$row['itemable_type']];
            $model = $modelClass::findOrFail($row['itemable_id']);
            $totalPrice = $row['quantity'] * $row['unit_price'];
            $subtotal += $totalPrice;

            $variantName = null;
            $sku = $model->sku ?? null;
            $productId = null;
            $productVariantId = null;
            $packageId = null;

            if ($row['itemable_type'] === 'product') {
                $productId = $model->id;
                if (! empty($row['product_variant_id'])) {
                    $variant = $model->variants()->find($row['product_variant_id']);
                    if ($variant) {
                        $variantName = $variant->name;
                        $sku = $variant->sku;
                        $productVariantId = $variant->id;
                    }
                }
            } elseif ($row['itemable_type'] === 'package') {
                $packageId = $model->id;
            }

            $data = [
                'itemable_type' => $modelClass,
                'itemable_id' => $row['itemable_id'],
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'package_id' => $packageId,
                'product_name' => $model->name,
                'variant_name' => $variantName,
                'sku' => $sku ?? '',
                'quantity_ordered' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'total_price' => $totalPrice,
                'item_metadata' => $row['itemable_type'] === 'course' && ! empty($row['class_id'])
                    ? ['class_id' => $row['class_id'], 'class_title' => ClassModel::find($row['class_id'])?->title]
                    : null,
            ];

            $existing = isset($row['id']) ? $existingItems->get($row['id']) : null;
            if ($existing) {
                $touchedExistingIds[] = $existing->id;
            }
            $itemUpserts[] = ['existing' => $existing, 'data' => $data];
        }

        // 3. Stock diff.
        // 3a. Removed items → restore stock then delete.
        foreach ($existingItems as $id => $oldItem) {
            if (! in_array($id, $touchedExistingIds, true)) {
                $oldItem->restoreStock();
                $oldItem->delete();
            }
        }

        // 3b. Matched / new items → adjust by delta.
        foreach ($itemUpserts as $upsert) {
            $existing = $upsert['existing'];
            $data = $upsert['data'];

            if ($existing) {
                $oldQty = (int) $existing->quantity_ordered;
                $newQty = (int) $data['quantity_ordered'];
                $existing->update($data);

                if ($newQty > $oldQty) {
                    // Need to deduct the delta. Easiest: temporarily set qty to delta, deduct, then restore real qty.
                    $delta = $newQty - $oldQty;
                    $tempItem = $existing->replicate();
                    $tempItem->id = $existing->id;
                    $tempItem->quantity_ordered = $delta;
                    $tempItem->order_id = $existing->order_id;
                    $tempItem->setRelations($existing->getRelations());
                    $tempItem->deductStock();
                } elseif ($newQty < $oldQty) {
                    $delta = $oldQty - $newQty;
                    $tempItem = $existing->replicate();
                    $tempItem->id = $existing->id;
                    $tempItem->quantity_ordered = $delta;
                    $tempItem->order_id = $existing->order_id;
                    $tempItem->setRelations($existing->getRelations());
                    $tempItem->restoreStock();
                }
            } else {
                $newItem = $sale->items()->create($data);
                $newItem->deductStock();
            }
        }

        // 4. Recompute discount, total.
        $discountAmount = 0;
        if (! empty($validated['discount_amount']) && $validated['discount_amount'] > 0) {
            if (($validated['discount_type'] ?? null) === 'percentage') {
                $discountAmount = round($subtotal * ($validated['discount_amount'] / 100), 2);
            } else {
                $discountAmount = $validated['discount_amount'];
            }
        }
        $shippingCost = $validated['shipping_cost'] ?? 0;
        $totalAmount = max(0, $subtotal - $discountAmount + $shippingCost);

        // 5. Customer fields + shipping address.
        $shippingAddress = ! empty($validated['customer_address'])
            ? ['full_address' => $validated['customer_address']]
            : null;

        $customerId = $validated['customer_id'] ?? null;
        $customerName = $validated['customer_name'] ?? null;
        $customerPhone = $validated['customer_phone'] ?? null;
        $customerEmail = $validated['customer_email'] ?? null;

        if ($customerId) {
            $customerUser = User::find($customerId);
            if ($customerUser) {
                $customerName = $customerName ?: $customerUser->name;
                $customerPhone = $customerPhone ?: $customerUser->phone;
                $customerEmail = $customerEmail ?: $customerUser->email;
            }
        }

        // 6. Update order, preserving metadata fields not owned by this endpoint.
        $metadata = $sale->metadata ?? [];
        $metadata['payment_reference'] = $validated['payment_reference'] ?? null;
        $metadata['discount_type'] = $validated['discount_type'] ?? null;
        $metadata['discount_input'] = $validated['discount_amount'] ?? null;

        $sale->update([
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'guest_email' => $customerEmail,
            'shipping_address' => $shippingAddress,
            'sales_source_id' => $validated['sales_source_id'],
            'payment_method' => $validated['payment_method'],
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'shipping_cost' => $shippingCost,
            'total_amount' => $totalAmount,
            'internal_notes' => $validated['notes'] ?? $sale->internal_notes,
            'metadata' => $metadata,
        ]);

        // 7. Update primary payment row.
        $payment = $sale->payments()->first();
        if ($payment) {
            $payment->update([
                'payment_method' => $validated['payment_method'],
                'amount' => $totalAmount,
                'reference_number' => $validated['payment_reference'] ?? null,
            ]);
        }

        // 8. Update FunnelOrder revenue if linked.
        $funnelOrder = FunnelOrder::where('product_order_id', $sale->id)->first();
        if ($funnelOrder) {
            $funnelOrder->update(['funnel_revenue' => $totalAmount]);
        }

        // 9. System note for audit.
        $sale->addSystemNote('Order edited from POS by '.$request->user()->name);

        $sale->load(['items', 'customer', 'payments']);

        return response()->json([
            'message' => 'Sale updated successfully.',
            'data' => $sale,
        ]);
    });
}
```

### Step 4.4: Run — expect pass

```bash
php artisan test --compact --filter=PosEditSaleTest
```

### Step 4.5: Pint + commit

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Api/PosController.php tests/Feature/Pos/PosEditSaleTest.php
git commit -m "feat(pos): implement updateSale with stock diff + total recompute"
```

---

## Task 5: Stock-diff coverage tests

**Files:**
- Modify: `tests/Feature/Pos/PosEditSaleTest.php`

### Step 5.1: Add tests for adding, removing, qty-up, qty-down

Append:

```php
test('adding a new item deducts its full quantity from stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $warehouse = \App\Models\Warehouse::factory()->create();
    $a = Product::factory()->create(['status' => 'active', 'track_quantity' => true]);
    $b = Product::factory()->create(['status' => 'active', 'track_quantity' => true]);
    \App\Models\StockLevel::create(['product_id' => $a->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);
    \App\Models\StockLevel::create(['product_id' => $b->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash', 'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $a->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data');

    $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [
            ['id' => $created['items'][0]['id'], 'itemable_type' => 'product', 'itemable_id' => $a->id, 'quantity' => 1, 'unit_price' => 10],
            ['itemable_type' => 'product', 'itemable_id' => $b->id, 'quantity' => 3, 'unit_price' => 5],
        ],
    ])->assertOk();

    expect(\App\Models\StockLevel::where('product_id', $b->id)->first()->quantity)->toBe(7);
});

test('removing an item restores its full quantity to stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $warehouse = \App\Models\Warehouse::factory()->create();
    $a = Product::factory()->create(['status' => 'active', 'track_quantity' => true]);
    $b = Product::factory()->create(['status' => 'active', 'track_quantity' => true]);
    \App\Models\StockLevel::create(['product_id' => $a->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);
    \App\Models\StockLevel::create(['product_id' => $b->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash', 'payment_status' => 'pending',
        'items' => [
            ['itemable_type' => 'product', 'itemable_id' => $a->id, 'quantity' => 1, 'unit_price' => 10],
            ['itemable_type' => 'product', 'itemable_id' => $b->id, 'quantity' => 2, 'unit_price' => 5],
        ],
    ])->assertCreated()->json('data');

    $bItemId = collect($created['items'])->firstWhere('product_id', $b->id)['id'];
    $aItemId = collect($created['items'])->firstWhere('product_id', $a->id)['id'];

    $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [
            ['id' => $aItemId, 'itemable_type' => 'product', 'itemable_id' => $a->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertOk();

    // b started 10, was deducted 2 (now 8), edit removed it, expect back to 10.
    expect(\App\Models\StockLevel::where('product_id', $b->id)->first()->quantity)->toBe(10);
});

test('quantity increase deducts only the delta, decrease restores only the delta', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $warehouse = \App\Models\Warehouse::factory()->create();
    $p = Product::factory()->create(['status' => 'active', 'track_quantity' => true]);
    \App\Models\StockLevel::create(['product_id' => $p->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash', 'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 2, 'unit_price' => 5]],
    ])->assertCreated()->json('data');
    $itemId = $created['items'][0]['id'];

    // 10 - 2 = 8 after create.

    // Increase to 5 → delta 3 → 8 - 3 = 5.
    $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [['id' => $itemId, 'itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 5, 'unit_price' => 5]],
    ])->assertOk();
    expect(\App\Models\StockLevel::where('product_id', $p->id)->first()->quantity)->toBe(5);

    // Decrease to 1 → delta 4 returned → 5 + 4 = 9.
    $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [['id' => $itemId, 'itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 1, 'unit_price' => 5]],
    ])->assertOk();
    expect(\App\Models\StockLevel::where('product_id', $p->id)->first()->quantity)->toBe(9);
});

test('bank_transfer payment requires reference', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);
    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'cash', 'payment_status' => 'pending',
        'items' => [['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data');

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X', 'customer_phone' => '0',
        'payment_method' => 'bank_transfer',
        'items' => [['id' => $created['items'][0]['id'], 'itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payment_reference']);
});
```

> **Note:** the exact warehouse-association on the order item (`warehouse_id`) needs to come from the product's default. Verify by reading how `createSale` sets it; if items are created with `warehouse_id = null`, the stock-restore path may early-return. If tests fail here, fix the seed setup (use the warehouse the existing flow chooses) rather than touching production code — we want parity with `createSale`.

### Step 5.2: Run — expect pass

```bash
php artisan test --compact --filter=PosEditSaleTest
```

### Step 5.3: Commit

```bash
git add tests/Feature/Pos/PosEditSaleTest.php
git commit -m "test(pos): cover stock diff + bank_transfer reference validation on edit"
```

---

## Task 6: Frontend — `saleApi.update`

**Files:**
- Modify: `resources/js/pos/services/api.js`

### Step 6.1: Add the method

Inside `export const saleApi = { ... }`, after `updateDetails` (around line 108), add:

```js
update: (id, data) => request(`/sales/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
}),
```

### Step 6.2: Commit

```bash
git add resources/js/pos/services/api.js
git commit -m "feat(pos): add saleApi.update for editing existing sales"
```

---

## Task 7: Build `EditSaleModal.jsx` (skeleton + customer + totals)

**Files:**
- Create: `resources/js/pos/components/EditSaleModal.jsx`

### Step 7.1: Create the component

```jsx
import React, { useEffect, useMemo, useState } from 'react';
import { saleApi, salesSourceApi } from '../services/api';

const PAYMENT_METHODS = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cod', label: 'COD' },
];

export default function EditSaleModal({ sale, onClose, onSaved }) {
    const [customer, setCustomer] = useState({
        customer_id: sale.customer?.id || sale.customer_id || null,
        name: sale.customer?.name || sale.customer_name || '',
        phone: sale.customer?.phone || sale.customer_phone || '',
        email: sale.customer?.email || sale.guest_email || '',
        address: typeof sale.shipping_address === 'string'
            ? sale.shipping_address
            : sale.shipping_address?.full_address || '',
    });
    const [items, setItems] = useState(() => (sale.items || []).map((item) => ({
        id: item.id,
        itemable_type: item.itemable_type?.toLowerCase().includes('package') ? 'package'
            : item.itemable_type?.toLowerCase().includes('course') ? 'course' : 'product',
        itemable_id: item.product_id || item.package_id || item.itemable_id,
        product_variant_id: item.product_variant_id || null,
        product_name: item.product_name,
        variant_name: item.variant_name,
        quantity: Number(item.quantity_ordered || item.quantity || 1),
        unit_price: Number(item.unit_price),
    })));
    const [paymentMethod, setPaymentMethod] = useState(sale.payment_method || 'cash');
    const [paymentReference, setPaymentReference] = useState(sale.metadata?.payment_reference || '');
    const [salesSourceId, setSalesSourceId] = useState(sale.sales_source_id || null);
    const [salesSources, setSalesSources] = useState([]);
    const [discountType, setDiscountType] = useState(sale.metadata?.discount_type || 'fixed');
    const [discountAmount, setDiscountAmount] = useState(Number(sale.metadata?.discount_input ?? sale.discount_amount ?? 0));
    const [shippingCost, setShippingCost] = useState(Number(sale.shipping_cost || 0));
    const [notes, setNotes] = useState(sale.internal_notes || '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        salesSourceApi.list().then((res) => setSalesSources(res.data || [])).catch(() => {});
    }, []);

    const subtotal = useMemo(
        () => items.reduce((sum, it) => sum + Number(it.quantity) * Number(it.unit_price), 0),
        [items]
    );
    const discountValue = discountType === 'percentage'
        ? Math.round(subtotal * (Number(discountAmount) / 100) * 100) / 100
        : Number(discountAmount) || 0;
    const total = Math.max(0, subtotal - discountValue + Number(shippingCost || 0));

    const updateItem = (index, patch) =>
        setItems((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const removeItem = (index) =>
        setItems((prev) => prev.filter((_, i) => i !== index));

    const handleSave = async () => {
        if (saving) return;
        setSaving(true);
        setError(null);
        try {
            const payload = {
                sales_source_id: salesSourceId,
                customer_id: customer.customer_id,
                customer_name: customer.name,
                customer_phone: customer.phone,
                customer_email: customer.email || null,
                customer_address: customer.address || null,
                payment_method: paymentMethod,
                payment_reference: paymentMethod === 'bank_transfer' ? paymentReference : null,
                discount_amount: Number(discountAmount) || 0,
                discount_type: discountType,
                shipping_cost: Number(shippingCost) || 0,
                notes,
                items: items.map((it) => ({
                    id: it.id || undefined,
                    itemable_type: it.itemable_type,
                    itemable_id: it.itemable_id,
                    product_variant_id: it.product_variant_id || undefined,
                    quantity: Number(it.quantity),
                    unit_price: Number(it.unit_price),
                })),
            };
            const res = await saleApi.update(sale.id, payload);
            onSaved(res.data);
        } catch (err) {
            setError(err.message || 'Failed to save changes.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl">
                <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between shrink-0">
                    <h2 className="text-lg font-semibold text-gray-900">Edit Sale {sale.order_number}</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">
                    {/* Customer */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">Customer</h3>
                        <div className="grid grid-cols-2 gap-3">
                            <input className="input" placeholder="Name" value={customer.name}
                                onChange={(e) => setCustomer({ ...customer, name: e.target.value })} />
                            <input className="input" placeholder="Phone" value={customer.phone}
                                onChange={(e) => setCustomer({ ...customer, phone: e.target.value })} />
                            <input className="input" placeholder="Email" value={customer.email}
                                onChange={(e) => setCustomer({ ...customer, email: e.target.value })} />
                            <input className="input" placeholder="Address" value={customer.address}
                                onChange={(e) => setCustomer({ ...customer, address: e.target.value })} />
                        </div>
                    </section>

                    {/* Items */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">Items</h3>
                        <div className="border border-gray-200 rounded-lg divide-y divide-gray-100">
                            {items.map((it, i) => (
                                <div key={it.id || `new-${i}`} className="flex items-center gap-3 px-3 py-2">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{it.product_name || 'Item'}</p>
                                        {it.variant_name && <p className="text-xs text-gray-500">{it.variant_name}</p>}
                                    </div>
                                    <input type="number" min="0" step="0.01" value={it.unit_price}
                                        onChange={(e) => updateItem(i, { unit_price: e.target.value })}
                                        className="w-24 input" />
                                    <input type="number" min="1" step="1" value={it.quantity}
                                        onChange={(e) => updateItem(i, { quantity: e.target.value })}
                                        className="w-20 input" />
                                    <span className="w-24 text-right text-sm font-medium text-gray-900">
                                        RM {(Number(it.quantity) * Number(it.unit_price)).toFixed(2)}
                                    </span>
                                    <button onClick={() => removeItem(i)} className="text-red-500 hover:text-red-700 px-2">×</button>
                                </div>
                            ))}
                            {items.length === 0 && (
                                <p className="px-3 py-4 text-sm text-gray-400">No items.</p>
                            )}
                        </div>
                        {/* Add Item picker comes in Task 8 */}
                    </section>

                    {/* Payment */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">Payment</h3>
                        <div className="grid grid-cols-2 gap-3">
                            <select className="input" value={paymentMethod}
                                onChange={(e) => setPaymentMethod(e.target.value)}>
                                {PAYMENT_METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                            </select>
                            <select className="input" value={salesSourceId || ''}
                                onChange={(e) => setSalesSourceId(e.target.value ? Number(e.target.value) : null)}>
                                <option value="">Select sales source</option>
                                {salesSources.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            {paymentMethod === 'bank_transfer' && (
                                <input className="input col-span-2" placeholder="Payment reference"
                                    value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} />
                            )}
                        </div>
                    </section>

                    {/* Totals */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">Totals</h3>
                        <div className="grid grid-cols-3 gap-3 mb-3">
                            <select className="input" value={discountType} onChange={(e) => setDiscountType(e.target.value)}>
                                <option value="fixed">Fixed (RM)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                            <input type="number" min="0" step="0.01" className="input" placeholder="Discount"
                                value={discountAmount} onChange={(e) => setDiscountAmount(e.target.value)} />
                            <input type="number" min="0" step="0.01" className="input" placeholder="Shipping"
                                value={shippingCost} onChange={(e) => setShippingCost(e.target.value)} />
                        </div>
                        <div className="flex items-center justify-between text-sm text-gray-600">
                            <span>Subtotal</span><span>RM {subtotal.toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm text-red-500">
                            <span>Discount</span><span>- RM {discountValue.toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm text-gray-600">
                            <span>Shipping</span><span>RM {Number(shippingCost || 0).toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between text-base font-semibold text-blue-600 mt-2">
                            <span>Total</span><span>RM {total.toFixed(2)}</span>
                        </div>
                    </section>

                    {/* Notes */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                        <textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} className="input w-full" />
                    </section>

                    {error && <p className="text-sm text-red-600">{error}</p>}
                </div>

                <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-2 shrink-0">
                    <button onClick={onClose} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button onClick={handleSave} disabled={saving}
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-50">
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </div>

            <style>{`
                .input { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; outline: none; }
                .input:focus { box-shadow: 0 0 0 2px rgba(59,130,246,0.5); border-color: rgb(59,130,246); }
            `}</style>
        </div>
    );
}
```

### Step 7.2: Commit (no wiring yet — modal not used)

```bash
git add resources/js/pos/components/EditSaleModal.jsx
git commit -m "feat(pos): scaffold EditSaleModal (customer/items/totals/payment)"
```

---

## Task 8: Wire the modal into `SalesHistory.jsx`

**Files:**
- Modify: `resources/js/pos/components/SalesHistory.jsx`

### Step 8.1: Import + state + button

At the top of the file, add the import:

```jsx
import EditSaleModal from './EditSaleModal';
```

Inside the component (near the other state declarations), add:

```jsx
const [editingSale, setEditingSale] = useState(false);
```

Update the detail panel header to include an "Edit Order" button. Locate the desktop panel header (`<h3 className="font-semibold text-gray-900 mb-4">{selectedSale.order_number}</h3>`) and replace with:

```jsx
<div className="flex items-center justify-between mb-4">
    <h3 className="font-semibold text-gray-900">{selectedSale.order_number}</h3>
    <button
        onClick={() => setEditingSale(true)}
        className="text-xs font-medium text-blue-600 hover:text-blue-700 px-2 py-1 rounded hover:bg-blue-50"
    >
        Edit Order
    </button>
</div>
```

Do the same for the mobile overlay header.

### Step 8.2: Render the modal

Just before the closing `</div>` of the outermost `return`, add:

```jsx
{editingSale && selectedSale && (
    <EditSaleModal
        sale={selectedSale}
        onClose={() => setEditingSale(false)}
        onSaved={(updated) => {
            setSales((prev) => prev.map((s) => (s.id === updated.id ? { ...s, ...updated } : s)));
            setSelectedSale((prev) => ({ ...prev, ...updated }));
            setEditingSale(false);
        }}
    />
)}
```

### Step 8.3: Build assets and verify

```bash
npm run build
```

Run dev server (`composer run dev`) and:
1. Open POS → Sales History.
2. Click a sale → click "Edit Order".
3. Change the customer name, change qty on an item, click Save.
4. Confirm the row in the list updates and the detail panel reflects new total.

### Step 8.4: Commit

```bash
git add resources/js/pos/components/SalesHistory.jsx
git commit -m "feat(pos): mount EditSaleModal from SalesHistory + Edit Order button"
```

---

## Task 9: Add-item picker (deferred MVP enhancement)

> Not on the v1 critical path. The modal lets users adjust qty / price / remove existing items already, which covers the majority of corrections. Adding a brand-new product line requires the ProductSearch sub-picker.

**Files:**
- Modify: `resources/js/pos/components/EditSaleModal.jsx`

### Step 9.1: Add a minimal product search inside the modal

Reuse `ProductSearch` from `resources/js/pos/components/ProductSearch.jsx` if its interface allows passing a callback like `onSelect(product, variant?)`. If not, create a small inline search that calls `productApi.list({ search })` from `services/api.js` and renders a dropdown of results. On selection, append:

```js
setItems((prev) => [
    ...prev,
    {
        id: null,
        itemable_type: 'product',
        itemable_id: product.id,
        product_variant_id: variant?.id || null,
        product_name: product.name,
        variant_name: variant?.name || null,
        quantity: 1,
        unit_price: Number(variant?.price ?? product.price ?? 0),
    },
]);
```

### Step 9.2: Manual test

1. Click "+ Add Item" → search → select.
2. New row appears with qty=1.
3. Save.
4. Backend should deduct stock for the new product (check via order detail page or stock movements).

### Step 9.3: Commit

```bash
git add resources/js/pos/components/EditSaleModal.jsx
git commit -m "feat(pos): allow adding new line items inside the edit modal"
```

---

## Task 10: Final verification

### Step 10.1: Run the focused suite

```bash
php artisan test --compact --filter=PosEditSale
php artisan test --compact --filter=PosSaleTest
```

Both must pass.

### Step 10.2: Pint sweep

```bash
vendor/bin/pint --dirty
```

### Step 10.3: Manual smoke test in browser

1. Create a fresh POS sale.
2. Open it from Sales History → Edit Order → modify each section in turn → Save → re-open and confirm changes stuck.
3. Toggle Paid/Pending/Cancelled → still works alongside edit.
4. Try `payment_method = bank_transfer` with empty reference → expect inline error.

### Step 10.4: Final summary commit (only if any cleanup happened)

```bash
git status
# if anything's pending: git add … && git commit -m "chore(pos): post-edit-sale cleanup"
```

---

## Risk register / things to watch

- **Warehouse field on order items**: `createSale` doesn't explicitly set `warehouse_id`. If items have `warehouse_id = null`, `restoreStock` early-returns. Verify behavior matches the create flow — do not introduce a new invariant in this PR.
- **Variant swaps**: not supported in v1. Removing + re-adding the line is the workaround.
- **Concurrent edits**: no optimistic-lock / version column. Out of scope.
- **Cancelled-sale edits**: stock diff applies regardless of status (consistent with the current invariant that quantity_ordered always represents deducted stock).
