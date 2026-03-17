# Sales Sources Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add sales source tracking to POS orders — a CRUD page for managing sources, a source selector in the POS order confirmation modal, and a source report tab in the Sales Department Report.

**Architecture:** New `sales_sources` table with `sales_source_id` FK on `product_orders`. SalesSource model with CRUD via Volt component. React POS modal gets a new source selector fetching from API. Sales Department Report gets a "Source Report" sub-tab.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), React (POS), Flux UI, SQLite/MySQL

---

### Task 1: Create SalesSource Model and Migration

**Files:**
- Create: `app/Models/SalesSource.php`
- Create: `database/migrations/xxxx_create_sales_sources_table.php`
- Create: `database/migrations/xxxx_add_sales_source_id_to_product_orders_table.php`
- Modify: `app/Models/ProductOrder.php:19` (add `sales_source_id` to fillable)
- Create: `database/factories/SalesSourceFactory.php`

**Step 1: Create the model with migration and factory**

Run:
```bash
php artisan make:model SalesSource -mf --no-interaction
```

**Step 2: Define the sales_sources migration**

Edit the generated migration file:
```php
public function up(): void
{
    Schema::create('sales_sources', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('color', 7)->default('#3B82F6');
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
}
```

**Step 3: Create migration to add sales_source_id to product_orders**

Run:
```bash
php artisan make:migration add_sales_source_id_to_product_orders_table --table=product_orders --no-interaction
```

Edit the generated migration:
```php
public function up(): void
{
    Schema::table('product_orders', function (Blueprint $table) {
        $table->foreignId('sales_source_id')->nullable()->after('source_reference')->constrained('sales_sources')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('product_orders', function (Blueprint $table) {
        $table->dropForeign(['sales_source_id']);
        $table->dropColumn('sales_source_id');
    });
}
```

**Step 4: Update the SalesSource model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ProductOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
```

**Step 5: Update the SalesSource factory**

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SalesSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

**Step 6: Add salesSource relationship and fillable to ProductOrder**

In `app/Models/ProductOrder.php`, add `'sales_source_id'` to the `$fillable` array (after `'source_reference'` on line ~53).

Add the relationship method:
```php
public function salesSource(): BelongsTo
{
    return $this->belongsTo(SalesSource::class);
}
```

**Step 7: Run migrations**

```bash
php artisan migrate --no-interaction
```
Expected: Both migrations succeed.

**Step 8: Commit**

```bash
git add app/Models/SalesSource.php app/Models/ProductOrder.php database/migrations/ database/factories/SalesSourceFactory.php
git commit -m "feat: add SalesSource model with migration and ProductOrder relationship"
```

---

### Task 2: Sales Sources CRUD Page (Volt Component)

**Files:**
- Create: `resources/views/livewire/admin/sales-sources.blade.php`
- Modify: `routes/web.php:242-244` (add route in sales department group)
- Modify: `resources/views/components/layouts/app/sidebar.blade.php:263-264` (add menu item)

**Step 1: Create the Volt component**

Run:
```bash
php artisan make:volt admin/sales-sources --class --no-interaction
```

**Step 2: Implement the CRUD component**

Replace the contents of `resources/views/livewire/admin/sales-sources.blade.php` with a Volt class-based component that includes:

PHP logic section:
```php
<?php

use App\Models\SalesSource;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $description = '';
    public string $color = '#3B82F6';
    public bool $isActive = true;
    public int $sortOrder = 0;
    public ?int $editingId = null;
    public bool $showModal = false;

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'class_admin', 'sales'])) {
            abort(403, 'Access denied');
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        $this->editingId = $source->id;
        $this->name = $source->name;
        $this->description = $source->description ?? '';
        $this->color = $source->color;
        $this->isActive = $source->is_active;
        $this->sortOrder = $source->sort_order;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'isActive' => ['boolean'],
            'sortOrder' => ['integer', 'min:0'],
        ]);

        SalesSource::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'color' => $validated['color'],
                'is_active' => $validated['isActive'],
                'sort_order' => $validated['sortOrder'],
            ]
        );

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        $source->update(['is_active' => !$source->is_active]);
    }

    public function deleteSource(int $id): void
    {
        $source = SalesSource::findOrFail($id);
        if ($source->orders()->exists()) {
            session()->flash('error', 'Cannot delete a source that has orders. Deactivate it instead.');
            return;
        }
        $source->delete();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->color = '#3B82F6';
        $this->isActive = true;
        $this->sortOrder = 0;
    }

    public function with(): array
    {
        return [
            'sources' => SalesSource::ordered()->get(),
        ];
    }
} ?>
```

Blade template section: A table listing all sources with columns for color badge, name, description, status, sort order, and action buttons (edit, toggle, delete). A Flux modal for create/edit with inputs for name, description, color (input type="color"), active switch, and sort order.

Follow the Flux UI header pattern from CLAUDE.md:
```html
<div class="mb-6 flex items-center justify-between">
    <div>
        <flux:heading size="xl">Sales Sources</flux:heading>
        <flux:text class="mt-2">Manage sales source categories for POS orders</flux:text>
    </div>
    <flux:button variant="primary" wire:click="openCreateModal">Add Source</flux:button>
</div>
```

Use `<flux:modal>`, `<flux:input>`, `<flux:switch>`, `<flux:button>` components. Display color as a small circle badge next to the name. Use `<flux:badge>` for active/inactive status.

**Step 3: Add route**

In `routes/web.php`, inside the sales department route group (line 242-244), add:
```php
Volt::route('sales-sources', 'admin.sales-sources')->name('admin.sales-sources');
```

Also add `'admin.sales-sources'` to the `sectionRoutes` for `salesDept` in the sidebar (line 25):
```javascript
'salesDept': ['pos.*', 'admin.reports.sales-department', 'admin.sales-sources'],
```

**Step 4: Add sidebar menu item**

In `resources/views/components/layouts/app/sidebar.blade.php`, between the POS item (line 263) and the Sales Department Report item (line 264), add:
```blade
<flux:navlist.item icon="tag" :href="route('admin.sales-sources')" :current="request()->routeIs('admin.sales-sources')" wire:navigate>{{ __('Sales Sources') }}</flux:navlist.item>
```

**Step 5: Test in browser**

Navigate to `/admin/sales-sources` — verify the page loads, CRUD operations work (create, edit, toggle, delete).

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/sales-sources.blade.php routes/web.php resources/views/components/layouts/app/sidebar.blade.php
git commit -m "feat: add Sales Sources CRUD page under Sales Department"
```

---

### Task 3: POS API Endpoint for Sales Sources

**Files:**
- Modify: `app/Http/Controllers/Api/PosController.php` (add `salesSources` method)
- Modify: `routes/api.php:275-289` (add route)
- Modify: `app/Http/Requests/StorePosSaleRequest.php:24-45` (add validation rule)
- Modify: `app/Http/Controllers/Api/PosController.php:225-254` (add sales_source_id to order creation)

**Step 1: Add salesSources endpoint to PosController**

In `app/Http/Controllers/Api/PosController.php`, add this method:
```php
use App\Models\SalesSource;

/**
 * List active sales sources for POS dropdown.
 */
public function salesSources(): JsonResponse
{
    $sources = SalesSource::active()->ordered()->get(['id', 'name', 'description', 'color']);

    return response()->json(['data' => $sources]);
}
```

**Step 2: Add API route**

In `routes/api.php`, in the POS routes section (around line 275-289), add before the existing routes:
```php
Route::get('pos/sales-sources', [PosController::class, 'salesSources']);
```

**Step 3: Add validation rule to StorePosSaleRequest**

In `app/Http/Requests/StorePosSaleRequest.php`, add to the `rules()` array:
```php
'sales_source_id' => ['required', 'exists:sales_sources,id'],
```

Add to `messages()`:
```php
'sales_source_id.required' => 'Please select a sales source.',
'sales_source_id.exists' => 'The selected sales source is invalid.',
```

**Step 4: Include sales_source_id in order creation**

In `app/Http/Controllers/Api/PosController.php`, in the `createSale` method, add `'sales_source_id' => $validated['sales_source_id']` to the `ProductOrder::create([...])` array (around line 225-254). Add it after the `'source_reference'` line:
```php
'sales_source_id' => $validated['sales_source_id'],
```

Also add it to the response data array (around line 279-299):
```php
'sales_source_id' => $order->sales_source_id,
```

**Step 5: Test the API**

Use tinker or a quick curl to verify:
```bash
php artisan tinker --execute="App\Models\SalesSource::create(['name' => 'Lead', 'color' => '#3B82F6']); App\Models\SalesSource::create(['name' => 'Salespage', 'color' => '#10B981']); App\Models\SalesSource::create(['name' => 'Adhoc', 'color' => '#F59E0B']);"
```

Then test the endpoint responds:
```bash
curl -s localhost:8000/api/pos/sales-sources -H "Accept: application/json" | head
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/PosController.php routes/api.php app/Http/Requests/StorePosSaleRequest.php
git commit -m "feat: add sales sources API endpoint and validation for POS orders"
```

---

### Task 4: POS Payment Modal — Sales Source Selector (React)

**Files:**
- Modify: `resources/js/pos/components/PaymentModal.jsx:16-27` (add state)
- Modify: `resources/js/pos/components/PaymentModal.jsx:70-88` (add to payload)
- Modify: `resources/js/pos/components/PaymentModal.jsx:277-312` (add UI before Payment Method)
- Modify: `resources/js/pos/services/api.js` (add salesSourceApi)

**Step 1: Add salesSourceApi to api.js**

In `resources/js/pos/services/api.js`, add after the `customerApi` export (line 60):
```javascript
export const salesSourceApi = {
    list: () => request('/sales-sources'),
};
```

**Step 2: Add state and data fetching to PaymentModal.jsx**

At the top of the component (after line 24), add:
```javascript
const [salesSourceId, setSalesSourceId] = useState(null);
const [salesSources, setSalesSources] = useState([]);
const [loadingSources, setLoadingSources] = useState(true);
```

Import `salesSourceApi`:
```javascript
import { saleApi, salesSourceApi } from '../services/api';
```

Add a `useEffect` to fetch sources on mount (after the state declarations):
```javascript
React.useEffect(() => {
    salesSourceApi.list().then(res => {
        setSalesSources(res.data || []);
        setLoadingSources(false);
    }).catch(() => setLoadingSources(false));
}, []);
```

Also import `useEffect` from React:
```javascript
import React, { useState, useEffect } from 'react';
```

**Step 3: Add sales_source_id to the payload**

In the `handleSubmit` function (line 75-88), add to the payload object:
```javascript
sales_source_id: salesSourceId,
```

**Step 4: Add validation to prevent submit without source**

Update the disabled check on the confirm button (line 434):
```javascript
disabled={loading || !salesSourceId || (paymentMethod === 'bank_transfer' && !paymentReference)}
```

**Step 5: Add the Sales Source selector UI**

In the JSX, add a new section **before** the Payment Method section (before line 277). Place it after the Price Breakdown section (after line 275):

```jsx
{/* Sales Source */}
<div>
    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Sales Source</label>
    {loadingSources ? (
        <div className="text-sm text-gray-400">Loading sources...</div>
    ) : salesSources.length === 0 ? (
        <div className="text-sm text-red-500">No sales sources configured. Please add sources first.</div>
    ) : (
        <div className="flex flex-wrap gap-2">
            {salesSources.map(source => (
                <button
                    key={source.id}
                    onClick={() => setSalesSourceId(source.id)}
                    className={`py-2 px-4 rounded-xl border-2 text-sm font-medium transition-all flex items-center gap-2 ${
                        salesSourceId === source.id
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                    }`}
                >
                    <span
                        className="w-3 h-3 rounded-full shrink-0"
                        style={{ backgroundColor: source.color }}
                    />
                    {source.name}
                </button>
            ))}
        </div>
    )}
</div>
```

**Step 6: Build and test**

```bash
npm run build
```

Open the POS, add an item, open the payment modal, verify:
- Sales Source buttons appear with colors
- Selecting a source is required
- Order creation includes the source

**Step 7: Commit**

```bash
git add resources/js/pos/components/PaymentModal.jsx resources/js/pos/services/api.js
git commit -m "feat: add sales source selector to POS order confirmation modal"
```

---

### Task 5: Source Report Tab in Sales Department Report

**Files:**
- Modify: `resources/views/livewire/admin/reports/sales-department.blade.php`

**Step 1: Add source report properties and methods**

In the PHP section of the component, add new public properties:
```php
// Source Report data
public array $sourceReportData = [];
public array $sourceSummary = [];
```

Add a method to load source report data:
```php
public function loadSourceReportData(): void
{
    $query = $this->baseQuery();

    $sources = \App\Models\SalesSource::ordered()->get();
    $sourceData = [];

    foreach ($sources as $source) {
        $sourceQuery = (clone $query)->where('sales_source_id', $source->id);
        $revenue = (float) $sourceQuery->sum('total_amount');
        $orderCount = $sourceQuery->count();

        $sourceData[] = [
            'id' => $source->id,
            'name' => $source->name,
            'color' => $source->color,
            'description' => $source->description,
            'revenue' => round($revenue, 2),
            'order_count' => $orderCount,
            'avg_order_value' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0,
        ];
    }

    // Also count orders with no source (legacy orders)
    $noSourceQuery = (clone $query)->whereNull('sales_source_id');
    $noSourceRevenue = (float) $noSourceQuery->sum('total_amount');
    $noSourceCount = $noSourceQuery->count();
    if ($noSourceCount > 0) {
        $sourceData[] = [
            'id' => null,
            'name' => 'Unassigned',
            'color' => '#9CA3AF',
            'description' => 'Orders without a sales source',
            'revenue' => round($noSourceRevenue, 2),
            'order_count' => $noSourceCount,
            'avg_order_value' => round($noSourceRevenue / $noSourceCount, 2),
        ];
    }

    $this->sourceReportData = $sourceData;
    $this->sourceSummary = [
        'total_revenue' => round(array_sum(array_column($sourceData, 'revenue')), 2),
        'total_orders' => array_sum(array_column($sourceData, 'order_count')),
    ];
}
```

**Step 2: Call loadSourceReportData when sub-tab changes**

Update `setReportSubTab` method to also load source data:
```php
public function setReportSubTab(string $subTab): void
{
    $this->reportSubTab = $subTab;

    if ($subTab === 'source_report') {
        $this->loadSourceReportData();
    }

    $this->dispatchChartsForSubTab();
}
```

Also call it in `loadReportData` if the current sub-tab is source_report.

**Step 3: Add the sub-tab button in the Blade template**

Find the sub-tab navigation in the report template (where "Team Sales" and "Product Report" buttons are). Add a third button:
```blade
<button wire:click="setReportSubTab('source_report')"
    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $reportSubTab === 'source_report' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
    Source Report
</button>
```

**Step 4: Add the source report content section**

After the existing sub-tab content sections (team_sales and product_report), add:
```blade
@if($reportSubTab === 'source_report')
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-sm text-gray-500">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900">RM {{ number_format($sourceSummary['total_revenue'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-sm text-gray-500">Total Orders</p>
            <p class="text-2xl font-bold text-gray-900">{{ $sourceSummary['total_orders'] ?? 0 }}</p>
        </div>
    </div>

    {{-- Source Table --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Order Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($sourceReportData as $source)
                    <tr wire:key="source-{{ $source['id'] ?? 'none' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $source['color'] }}"></span>
                                <span class="text-sm font-medium text-gray-900">{{ $source['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 font-medium">
                            RM {{ number_format($source['revenue'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-600">
                            {{ $source['order_count'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-600">
                            RM {{ number_format($source['avg_order_value'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                            No source data available for the selected period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
```

**Step 5: Test in browser**

Navigate to `/admin/reports/sales-department`, click the "Source Report" sub-tab. Verify data loads and displays correctly.

**Step 6: Commit**

```bash
git add resources/views/livewire/admin/reports/sales-department.blade.php
git commit -m "feat: add Source Report tab to Sales Department Report"
```

---

### Task 6: Write Feature Test

**Files:**
- Create: `tests/Feature/SalesSourceTest.php`

**Step 1: Create the test file**

```bash
php artisan make:test SalesSourceTest --pest --no-interaction
```

**Step 2: Write tests**

```php
<?php

use App\Models\SalesSource;
use App\Models\User;
use Livewire\Volt\Volt;

test('sales sources page is accessible by admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.sales-sources'))
        ->assertSuccessful();
});

test('sales sources page is not accessible by students', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('admin.sales-sources'))
        ->assertForbidden();
});

test('can create a sales source', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::test('admin.sales-sources')
        ->actingAs($admin)
        ->set('name', 'Test Source')
        ->set('description', 'A test source')
        ->set('color', '#FF5733')
        ->call('save')
        ->assertHasNoErrors();

    expect(SalesSource::where('name', 'Test Source')->exists())->toBeTrue();
});

test('can toggle sales source active status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);

    Volt::test('admin.sales-sources')
        ->actingAs($admin)
        ->call('toggleActive', $source->id);

    expect($source->fresh()->is_active)->toBeFalse();
});

test('cannot delete sales source with orders', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create();

    // Create a product order linked to this source
    \App\Models\ProductOrder::factory()->create(['sales_source_id' => $source->id]);

    Volt::test('admin.sales-sources')
        ->actingAs($admin)
        ->call('deleteSource', $source->id);

    expect(SalesSource::find($source->id))->not->toBeNull();
});

test('api returns active sales sources', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SalesSource::factory()->create(['name' => 'Lead', 'is_active' => true]);
    SalesSource::factory()->create(['name' => 'Inactive', 'is_active' => false]);

    $this->actingAs($admin)
        ->getJson('/api/pos/sales-sources')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Lead');
});

test('pos sale requires sales_source_id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = \App\Models\Product::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->postJson('/api/pos/sales', [
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0123456789',
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sales_source_id');
});

test('pos sale stores sales_source_id on order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create();
    $product = \App\Models\Product::factory()->create(['status' => 'active', 'price' => 10.00]);

    $response = $this->actingAs($admin)
        ->postJson('/api/pos/sales', [
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0123456789',
            'sales_source_id' => $source->id,
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ],
            ],
        ])
        ->assertCreated();

    $order = \App\Models\ProductOrder::latest()->first();
    expect($order->sales_source_id)->toBe($source->id);
});
```

**Step 3: Run the tests**

```bash
php artisan test --compact tests/Feature/SalesSourceTest.php
```
Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/SalesSourceTest.php
git commit -m "test: add feature tests for sales sources CRUD, API, and POS integration"
```

---

### Task 7: Run Pint and Full Test Suite

**Step 1: Run Pint**

```bash
./vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```
Expected: All tests pass.

**Step 3: Final commit if Pint made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting"
```
