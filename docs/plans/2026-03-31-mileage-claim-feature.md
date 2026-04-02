# Mileage Claim Feature Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add mileage/petrol claim support with custom vehicle rates, auto-calculation, and trip details to the existing HR claims system.

**Architecture:** Extend ClaimType with `is_mileage_type` flag, add `claim_type_vehicle_rates` table for custom vehicle types per claim type, add mileage fields to `claim_requests`. Amount is auto-calculated server-side as `distance_km × rate_per_km`.

**Tech Stack:** Laravel 12, Pest 4, existing HR API patterns

**Design Doc:** `docs/plans/2026-03-31-mileage-claim-feature-design.md`

---

### Task 1: Migration — Add `is_mileage_type` to `claim_types`

**Files:**
- Create: `database/migrations/2026_03_31_000001_add_is_mileage_type_to_claim_types_table.php`

**Step 1: Create the migration**

```bash
php artisan make:migration add_is_mileage_type_to_claim_types_table --table=claim_types --no-interaction
```

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::table('claim_types', function (Blueprint $table) {
        $table->boolean('is_mileage_type')->default(false)->after('is_active');
    });
}

public function down(): void
{
    Schema::table('claim_types', function (Blueprint $table) {
        $table->dropColumn('is_mileage_type');
    });
}
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: Migration runs successfully, `is_mileage_type` column added.

**Step 4: Commit**

```bash
git add database/migrations/*add_is_mileage_type*
git commit -m "feat(claims): add is_mileage_type column to claim_types table"
```

---

### Task 2: Migration — Create `claim_type_vehicle_rates` table

**Files:**
- Create: `database/migrations/2026_03_31_000002_create_claim_type_vehicle_rates_table.php`

**Step 1: Create the migration**

```bash
php artisan make:migration create_claim_type_vehicle_rates_table --no-interaction
```

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::create('claim_type_vehicle_rates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('claim_type_id')->constrained('claim_types')->cascadeOnDelete();
        $table->string('name');
        $table->decimal('rate_per_km', 8, 2);
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamps();

        $table->index(['claim_type_id', 'is_active']);
    });
}

public function down(): void
{
    Schema::dropIfExists('claim_type_vehicle_rates');
}
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: Table created successfully.

**Step 4: Commit**

```bash
git add database/migrations/*create_claim_type_vehicle_rates*
git commit -m "feat(claims): create claim_type_vehicle_rates table"
```

---

### Task 3: Migration — Add mileage fields to `claim_requests`

**Files:**
- Create: `database/migrations/2026_03_31_000003_add_mileage_fields_to_claim_requests_table.php`

**Step 1: Create the migration**

```bash
php artisan make:migration add_mileage_fields_to_claim_requests_table --table=claim_requests --no-interaction
```

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::table('claim_requests', function (Blueprint $table) {
        $table->foreignId('vehicle_rate_id')->nullable()->after('claim_type_id')
            ->constrained('claim_type_vehicle_rates')->nullOnDelete();
        $table->decimal('distance_km', 10, 2)->nullable()->after('vehicle_rate_id');
        $table->string('origin')->nullable()->after('distance_km');
        $table->string('destination')->nullable()->after('origin');
        $table->string('trip_purpose')->nullable()->after('destination');
    });
}

public function down(): void
{
    Schema::table('claim_requests', function (Blueprint $table) {
        $table->dropForeign(['vehicle_rate_id']);
        $table->dropColumn(['vehicle_rate_id', 'distance_km', 'origin', 'destination', 'trip_purpose']);
    });
}
```

**Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: Columns added to `claim_requests` table.

**Step 4: Commit**

```bash
git add database/migrations/*add_mileage_fields*
git commit -m "feat(claims): add mileage fields to claim_requests table"
```

---

### Task 4: ClaimTypeVehicleRate Model + Factory

**Files:**
- Create: `app/Models/ClaimTypeVehicleRate.php`
- Create: `database/factories/ClaimTypeVehicleRateFactory.php`

**Step 1: Create the model with factory**

```bash
php artisan make:model ClaimTypeVehicleRate --factory --no-interaction
```

**Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimTypeVehicleRate extends Model
{
    /** @use HasFactory<\Database\Factories\ClaimTypeVehicleRateFactory> */
    use HasFactory;

    protected $fillable = [
        'claim_type_id',
        'name',
        'rate_per_km',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_km' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function claimType(): BelongsTo
    {
        return $this->belongsTo(ClaimType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
```

**Step 3: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\ClaimType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClaimTypeVehicleRateFactory extends Factory
{
    public function definition(): array
    {
        $vehicles = [
            ['name' => 'Car', 'rate' => 0.60],
            ['name' => 'Motorcycle', 'rate' => 0.30],
            ['name' => 'Van', 'rate' => 0.80],
            ['name' => '4WD', 'rate' => 0.75],
        ];

        $vehicle = $this->faker->randomElement($vehicles);

        return [
            'claim_type_id' => ClaimType::factory(),
            'name' => $vehicle['name'],
            'rate_per_km' => $vehicle['rate'],
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

**Step 4: Commit**

```bash
git add app/Models/ClaimTypeVehicleRate.php database/factories/ClaimTypeVehicleRateFactory.php
git commit -m "feat(claims): add ClaimTypeVehicleRate model and factory"
```

---

### Task 5: Update ClaimType Model

**Files:**
- Modify: `app/Models/ClaimType.php`

**Step 1: Add `is_mileage_type` to fillable, casts, and add `vehicleRates()` relationship**

Add to `$fillable` array:
```php
'is_mileage_type',
```

Add to `casts()` method:
```php
'is_mileage_type' => 'boolean',
```

Add new relationship method:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function vehicleRates(): HasMany
{
    return $this->hasMany(ClaimTypeVehicleRate::class);
}
```

**Step 2: Verify existing tests still pass**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All existing tests pass.

**Step 3: Commit**

```bash
git add app/Models/ClaimType.php
git commit -m "feat(claims): add is_mileage_type and vehicleRates to ClaimType model"
```

---

### Task 6: Update ClaimRequest Model

**Files:**
- Modify: `app/Models/ClaimRequest.php`

**Step 1: Add mileage fields to fillable and add `vehicleRate()` relationship**

Add to `$fillable` array:
```php
'vehicle_rate_id',
'distance_km',
'origin',
'destination',
'trip_purpose',
```

Add to `casts()` method:
```php
'distance_km' => 'decimal:2',
```

Add new relationship:
```php
public function vehicleRate(): BelongsTo
{
    return $this->belongsTo(ClaimTypeVehicleRate::class);
}
```

**Step 2: Verify existing tests still pass**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All existing tests pass.

**Step 3: Commit**

```bash
git add app/Models/ClaimRequest.php
git commit -m "feat(claims): add mileage fields and vehicleRate to ClaimRequest model"
```

---

### Task 7: Update ClaimType Validation + Controller

**Files:**
- Modify: `app/Http/Requests/Hr/StoreClaimTypeRequest.php`
- Modify: `app/Http/Controllers/Api/Hr/HrClaimTypeController.php`

**Step 1: Add `is_mileage_type` validation rule**

In `StoreClaimTypeRequest.php`, add to `rules()`:
```php
'is_mileage_type' => ['boolean'],
```

**Step 2: Update ClaimType controller to eager-load vehicle rates**

In `HrClaimTypeController.php`, update `index()`:
```php
public function index(): JsonResponse
{
    $claimTypes = ClaimType::query()
        ->with(['vehicleRates' => fn ($q) => $q->active()->ordered()])
        ->ordered()
        ->get();

    return response()->json(['data' => $claimTypes]);
}
```

Update `store()` to return with vehicle rates:
```php
$claimType->load(['vehicleRates' => fn ($q) => $q->active()->ordered()]);
```

Update `update()` similarly:
```php
return response()->json([
    'data' => $type->fresh(['vehicleRates' => fn ($q) => $q->active()->ordered()]),
    'message' => 'Claim type updated successfully.',
]);
```

**Step 3: Write test for mileage claim type creation**

Add to `tests/Feature/Hr/HrClaimsApiTest.php`:
```php
test('admin can create a mileage claim type', function () {
    $admin = createClaimsAdminUser();

    $response = $this->actingAs($admin)->postJson('/api/hr/claims/types', [
        'name' => 'Petrol Mileage',
        'code' => 'MILEAGE',
        'monthly_limit' => 500,
        'requires_receipt' => false,
        'is_active' => true,
        'is_mileage_type' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Petrol Mileage')
        ->assertJsonPath('data.is_mileage_type', true);

    $this->assertDatabaseHas('claim_types', ['code' => 'MILEAGE', 'is_mileage_type' => true]);
});
```

**Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All tests pass including the new one.

**Step 5: Commit**

```bash
git add app/Http/Requests/Hr/StoreClaimTypeRequest.php app/Http/Controllers/Api/Hr/HrClaimTypeController.php tests/Feature/Hr/HrClaimsApiTest.php
git commit -m "feat(claims): add is_mileage_type support to claim type CRUD"
```

---

### Task 8: Vehicle Rate Controller + Form Request + Routes

**Files:**
- Create: `app/Http/Controllers/Api/Hr/HrVehicleRateController.php`
- Create: `app/Http/Requests/Hr/StoreVehicleRateRequest.php`
- Modify: `routes/api.php`

**Step 1: Create the form request**

```bash
php artisan make:request Hr/StoreVehicleRateRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate_per_km' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'rate_per_km.min' => 'The rate per km must be at least RM 0.01.',
        ];
    }
}
```

**Step 2: Create the controller**

```bash
php artisan make:controller Api/Hr/HrVehicleRateController --no-interaction
```

```php
<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreVehicleRateRequest;
use App\Models\ClaimType;
use App\Models\ClaimTypeVehicleRate;
use Illuminate\Http\JsonResponse;

class HrVehicleRateController extends Controller
{
    public function index(ClaimType $type): JsonResponse
    {
        $rates = $type->vehicleRates()
            ->ordered()
            ->get();

        return response()->json(['data' => $rates]);
    }

    public function store(StoreVehicleRateRequest $request, ClaimType $type): JsonResponse
    {
        $rate = $type->vehicleRates()->create($request->validated());

        return response()->json([
            'data' => $rate,
            'message' => 'Vehicle rate created successfully.',
        ], 201);
    }

    public function update(StoreVehicleRateRequest $request, ClaimType $type, ClaimTypeVehicleRate $rate): JsonResponse
    {
        if ($rate->claim_type_id !== $type->id) {
            return response()->json(['message' => 'Vehicle rate does not belong to this claim type.'], 404);
        }

        $rate->update($request->validated());

        return response()->json([
            'data' => $rate->fresh(),
            'message' => 'Vehicle rate updated successfully.',
        ]);
    }

    public function destroy(ClaimType $type, ClaimTypeVehicleRate $rate): JsonResponse
    {
        if ($rate->claim_type_id !== $type->id) {
            return response()->json(['message' => 'Vehicle rate does not belong to this claim type.'], 404);
        }

        $rate->delete();

        return response()->json(['message' => 'Vehicle rate deleted successfully.']);
    }
}
```

**Step 3: Add routes**

In `routes/api.php`, after the claim types `apiResource` line (~line 703), add:

```php
// Vehicle Rates (for mileage claim types)
Route::get('claims/types/{type}/vehicle-rates', [HrVehicleRateController::class, 'index'])->name('api.hr.claims.vehicle-rates.index');
Route::post('claims/types/{type}/vehicle-rates', [HrVehicleRateController::class, 'store'])->name('api.hr.claims.vehicle-rates.store');
Route::put('claims/types/{type}/vehicle-rates/{rate}', [HrVehicleRateController::class, 'update'])->name('api.hr.claims.vehicle-rates.update');
Route::delete('claims/types/{type}/vehicle-rates/{rate}', [HrVehicleRateController::class, 'destroy'])->name('api.hr.claims.vehicle-rates.destroy');
```

Add the import at top of file:
```php
use App\Http\Controllers\Api\Hr\HrVehicleRateController;
```

**Step 4: Write tests for vehicle rates CRUD**

Add to `tests/Feature/Hr/HrClaimsApiTest.php`:

```php
/*
|--------------------------------------------------------------------------
| Vehicle Rate Tests
|--------------------------------------------------------------------------
*/

test('admin can list vehicle rates for a claim type', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    ClaimTypeVehicleRate::factory()->count(2)->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->getJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('admin can create a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", [
        'name' => 'Car',
        'rate_per_km' => 0.60,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Car')
        ->assertJsonPath('data.rate_per_km', '0.60')
        ->assertJsonPath('message', 'Vehicle rate created successfully.');

    $this->assertDatabaseHas('claim_type_vehicle_rates', [
        'claim_type_id' => $claimType->id,
        'name' => 'Car',
    ]);
});

test('admin can update a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);
    $rate = ClaimTypeVehicleRate::factory()->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->putJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates/{$rate->id}", [
        'name' => 'Updated Vehicle',
        'rate_per_km' => 0.75,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Vehicle')
        ->assertJsonPath('message', 'Vehicle rate updated successfully.');
});

test('admin can delete a vehicle rate', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);
    $rate = ClaimTypeVehicleRate::factory()->create(['claim_type_id' => $claimType->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates/{$rate->id}");

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Vehicle rate deleted successfully.');

    $this->assertDatabaseMissing('claim_type_vehicle_rates', ['id' => $rate->id]);
});

test('non-admin cannot create vehicle rates', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($data['user'])->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", [
        'name' => 'Car',
        'rate_per_km' => 0.60,
    ]);

    $response->assertForbidden();
});

test('vehicle rate validates required fields', function () {
    $admin = createClaimsAdminUser();
    $claimType = ClaimType::factory()->create(['is_mileage_type' => true]);

    $response = $this->actingAs($admin)->postJson("/api/hr/claims/types/{$claimType->id}/vehicle-rates", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'rate_per_km']);
});
```

**Step 5: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All tests pass.

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrVehicleRateController.php app/Http/Requests/Hr/StoreVehicleRateRequest.php routes/api.php tests/Feature/Hr/HrClaimsApiTest.php
git commit -m "feat(claims): add vehicle rates CRUD API with tests"
```

---

### Task 9: Update Employee Claim Submission for Mileage

**Files:**
- Modify: `app/Http/Requests/Hr/StoreClaimRequestRequest.php`
- Modify: `app/Http/Controllers/Api/Hr/HrMyClaimController.php`

**Step 1: Update validation to handle mileage claims conditionally**

Replace `StoreClaimRequestRequest.php` rules:

```php
public function rules(): array
{
    $claimType = null;
    if ($this->input('claim_type_id')) {
        $claimType = \App\Models\ClaimType::find($this->input('claim_type_id'));
    }

    $rules = [
        'claim_type_id' => ['required', 'exists:claim_types,id'],
        'claim_date' => ['required', 'date'],
        'description' => ['required', 'string', 'max:1000'],
        'receipt' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
    ];

    if ($claimType && $claimType->is_mileage_type) {
        $rules['vehicle_rate_id'] = ['required', 'exists:claim_type_vehicle_rates,id'];
        $rules['distance_km'] = ['required', 'numeric', 'min:0.01'];
        $rules['origin'] = ['required', 'string', 'max:255'];
        $rules['destination'] = ['required', 'string', 'max:255'];
        $rules['trip_purpose'] = ['required', 'string', 'max:255'];
    } else {
        $rules['amount'] = ['required', 'numeric', 'min:0.01'];
    }

    return $rules;
}
```

Add mileage-specific messages:
```php
public function messages(): array
{
    return [
        'claim_type_id.exists' => 'The selected claim type does not exist.',
        'amount.min' => 'The claim amount must be at least RM 0.01.',
        'receipt.max' => 'The receipt file must not exceed 5MB.',
        'receipt.mimes' => 'The receipt must be a PDF, JPG, JPEG, or PNG file.',
        'vehicle_rate_id.required' => 'Please select a vehicle type.',
        'vehicle_rate_id.exists' => 'The selected vehicle type does not exist.',
        'distance_km.required' => 'Please enter the distance traveled.',
        'distance_km.min' => 'The distance must be at least 0.01 km.',
        'origin.required' => 'Please enter the starting location.',
        'destination.required' => 'Please enter the destination.',
        'trip_purpose.required' => 'Please enter the trip purpose.',
    ];
}
```

**Step 2: Update `HrMyClaimController::store()` to auto-calculate amount for mileage**

In the `store()` method, after `$claimType = ClaimType::findOrFail(...)`, add mileage calculation:

```php
$claimType = ClaimType::findOrFail($validated['claim_type_id']);

// Auto-calculate amount for mileage claims
$amount = $validated['amount'] ?? null;
if ($claimType->is_mileage_type) {
    $vehicleRate = \App\Models\ClaimTypeVehicleRate::where('id', $validated['vehicle_rate_id'])
        ->where('claim_type_id', $claimType->id)
        ->where('is_active', true)
        ->firstOrFail();

    $amount = round($validated['distance_km'] * $vehicleRate->rate_per_km, 2);
}
```

Then update the `ClaimRequest::create()` call to use `$amount` and include mileage fields:

```php
$claim = ClaimRequest::create([
    'claim_number' => ClaimRequest::generateClaimNumber(),
    'employee_id' => $employee->id,
    'claim_type_id' => $validated['claim_type_id'],
    'amount' => $amount,
    'claim_date' => $validated['claim_date'],
    'description' => $validated['description'],
    'receipt_path' => $receiptPath,
    'status' => 'draft',
    'vehicle_rate_id' => $validated['vehicle_rate_id'] ?? null,
    'distance_km' => $validated['distance_km'] ?? null,
    'origin' => $validated['origin'] ?? null,
    'destination' => $validated['destination'] ?? null,
    'trip_purpose' => $validated['trip_purpose'] ?? null,
]);
```

Also update the limit check to use `$amount` instead of `$validated['amount']`:
```php
if ($claimType->monthly_limit && ($monthlyUsed + $amount) > $claimType->monthly_limit) {
```
```php
} elseif ($claimType->yearly_limit && ($yearlyUsed + $amount) > $claimType->yearly_limit) {
```

**Step 3: Update `HrMyClaimController::update()` for mileage draft updates**

Add mileage recalculation logic in the `update()` method:

```php
$claimType = $claimRequest->claimType;

if ($claimType->is_mileage_type && isset($validated['vehicle_rate_id'])) {
    $vehicleRate = \App\Models\ClaimTypeVehicleRate::where('id', $validated['vehicle_rate_id'])
        ->where('claim_type_id', $claimType->id)
        ->where('is_active', true)
        ->firstOrFail();

    $validated['amount'] = round($validated['distance_km'] * $vehicleRate->rate_per_km, 2);
}
```

**Step 4: Update `HrMyClaimController::show()` to eager-load vehicleRate**

```php
$claimRequest->load('claimType', 'vehicleRate');
```

**Step 5: Update `HrMyClaimController::index()` to eager-load vehicleRate**

```php
$query = ClaimRequest::query()
    ->with('claimType', 'vehicleRate')
    ->where('employee_id', $employee->id);
```

**Step 6: Commit**

```bash
git add app/Http/Requests/Hr/StoreClaimRequestRequest.php app/Http/Controllers/Api/Hr/HrMyClaimController.php
git commit -m "feat(claims): add mileage auto-calculation to employee claim submission"
```

---

### Task 10: Write Mileage Claim Submission Tests

**Files:**
- Modify: `tests/Feature/Hr/HrClaimsApiTest.php`

**Step 1: Add use statement for ClaimTypeVehicleRate at top of test file**

```php
use App\Models\ClaimTypeVehicleRate;
```

**Step 2: Write mileage claim submission tests**

```php
/*
|--------------------------------------------------------------------------
| Mileage Claim Tests
|--------------------------------------------------------------------------
*/

test('employee can submit a mileage claim with auto-calculated amount', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => true,
        'requires_receipt' => false,
    ]);
    $vehicleRate = ClaimTypeVehicleRate::factory()->create([
        'claim_type_id' => $claimType->id,
        'name' => 'Car',
        'rate_per_km' => 0.60,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'vehicle_rate_id' => $vehicleRate->id,
        'distance_km' => 50,
        'origin' => 'Kuala Lumpur',
        'destination' => 'Putrajaya',
        'trip_purpose' => 'Client meeting',
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Travel to client office',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $claim = ClaimRequest::where('employee_id', $data['employee']->id)->first();
    expect((float) $claim->amount)->toBe(30.00) // 50 * 0.60
        ->and($claim->vehicle_rate_id)->toBe($vehicleRate->id)
        ->and((float) $claim->distance_km)->toBe(50.00)
        ->and($claim->origin)->toBe('Kuala Lumpur')
        ->and($claim->destination)->toBe('Putrajaya')
        ->and($claim->trip_purpose)->toBe('Client meeting');
});

test('mileage claim requires vehicle_rate_id and distance_km', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => true,
        'requires_receipt' => false,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Missing mileage fields',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['vehicle_rate_id', 'distance_km', 'origin', 'destination', 'trip_purpose']);
});

test('mileage claim does not require manual amount', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => true,
        'requires_receipt' => false,
    ]);
    $vehicleRate = ClaimTypeVehicleRate::factory()->create([
        'claim_type_id' => $claimType->id,
        'rate_per_km' => 0.30,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'vehicle_rate_id' => $vehicleRate->id,
        'distance_km' => 100,
        'origin' => 'Shah Alam',
        'destination' => 'Klang',
        'trip_purpose' => 'Delivery',
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Delivery run',
    ]);

    $response->assertCreated();

    $claim = ClaimRequest::where('employee_id', $data['employee']->id)->first();
    expect((float) $claim->amount)->toBe(30.00); // 100 * 0.30
});

test('regular claim still requires amount field', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => false,
        'requires_receipt' => false,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Regular claim without amount',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('mileage claim warns when monthly limit exceeded', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => true,
        'monthly_limit' => 100.00,
        'requires_receipt' => false,
    ]);
    $vehicleRate = ClaimTypeVehicleRate::factory()->create([
        'claim_type_id' => $claimType->id,
        'rate_per_km' => 0.60,
    ]);

    // Create existing claim that uses most of the limit
    ClaimRequest::factory()->create([
        'employee_id' => $data['employee']->id,
        'claim_type_id' => $claimType->id,
        'amount' => 80.00,
        'status' => 'approved',
        'claim_date' => now(),
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'vehicle_rate_id' => $vehicleRate->id,
        'distance_km' => 50, // 50 * 0.60 = 30.00 → total 110 > 100 limit
        'origin' => 'KL',
        'destination' => 'PJ',
        'trip_purpose' => 'Meeting',
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Over limit mileage claim',
    ]);

    $response->assertCreated();
    expect($response->json('warning'))->not->toBeNull();
});

test('mileage claim rejects inactive vehicle rate', function () {
    $data = createClaimsEmployeeWithRecord();
    $claimType = ClaimType::factory()->create([
        'is_mileage_type' => true,
        'requires_receipt' => false,
    ]);
    $vehicleRate = ClaimTypeVehicleRate::factory()->inactive()->create([
        'claim_type_id' => $claimType->id,
    ]);

    $response = $this->actingAs($data['user'])->postJson('/api/hr/me/claims', [
        'claim_type_id' => $claimType->id,
        'vehicle_rate_id' => $vehicleRate->id,
        'distance_km' => 50,
        'origin' => 'KL',
        'destination' => 'PJ',
        'trip_purpose' => 'Meeting',
        'claim_date' => now()->format('Y-m-d'),
        'description' => 'Using inactive rate',
    ]);

    $response->assertStatus(404);
});
```

**Step 3: Run all claim tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All tests pass.

**Step 4: Commit**

```bash
git add tests/Feature/Hr/HrClaimsApiTest.php
git commit -m "test(claims): add comprehensive mileage claim tests"
```

---

### Task 11: Update Admin Claim Views to Show Mileage Details

**Files:**
- Modify: `app/Http/Controllers/Api/Hr/HrClaimRequestController.php`

**Step 1: Eager-load vehicleRate in admin claim views**

In `index()`, update the eager loading:
```php
->with(['employee.user', 'claimType', 'vehicleRate'])
```

In `show()`, update the eager loading:
```php
$claimRequest->load(['employee.user', 'claimType', 'approver.user', 'vehicleRate']);
```

**Step 2: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All tests pass.

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/Hr/HrClaimRequestController.php
git commit -m "feat(claims): show mileage details in admin claim views"
```

---

### Task 12: Update ClaimTypeFactory for Mileage Support

**Files:**
- Modify: `database/factories/ClaimTypeFactory.php`

**Step 1: Add `is_mileage_type` default and a `mileage()` state**

Add to `definition()`:
```php
'is_mileage_type' => false,
```

Add new state method:
```php
public function mileage(): static
{
    return $this->state(fn (array $attributes) => [
        'is_mileage_type' => true,
    ]);
}
```

**Step 2: Run tests**

```bash
php artisan test --compact tests/Feature/Hr/HrClaimsApiTest.php
```

Expected: All tests pass.

**Step 3: Commit**

```bash
git add database/factories/ClaimTypeFactory.php
git commit -m "feat(claims): add mileage state to ClaimTypeFactory"
```

---

### Task 13: Run Pint + Full Test Suite

**Step 1: Run Laravel Pint**

```bash
vendor/bin/pint --dirty
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass with no formatting issues.

**Step 3: Final commit if Pint made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting to mileage claim feature"
```

---

## Summary of Files Changed/Created

| Action | File |
|--------|------|
| Create | `database/migrations/2026_03_31_*_add_is_mileage_type_to_claim_types_table.php` |
| Create | `database/migrations/2026_03_31_*_create_claim_type_vehicle_rates_table.php` |
| Create | `database/migrations/2026_03_31_*_add_mileage_fields_to_claim_requests_table.php` |
| Create | `app/Models/ClaimTypeVehicleRate.php` |
| Create | `database/factories/ClaimTypeVehicleRateFactory.php` |
| Create | `app/Http/Controllers/Api/Hr/HrVehicleRateController.php` |
| Create | `app/Http/Requests/Hr/StoreVehicleRateRequest.php` |
| Modify | `app/Models/ClaimType.php` |
| Modify | `app/Models/ClaimRequest.php` |
| Modify | `app/Http/Controllers/Api/Hr/HrClaimTypeController.php` |
| Modify | `app/Http/Controllers/Api/Hr/HrMyClaimController.php` |
| Modify | `app/Http/Controllers/Api/Hr/HrClaimRequestController.php` |
| Modify | `app/Http/Requests/Hr/StoreClaimTypeRequest.php` |
| Modify | `app/Http/Requests/Hr/StoreClaimRequestRequest.php` |
| Modify | `database/factories/ClaimTypeFactory.php` |
| Modify | `routes/api.php` |
| Modify | `tests/Feature/Hr/HrClaimsApiTest.php` |
