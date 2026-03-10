# WhatsApp Cost Monitoring Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a WhatsApp cost monitoring dashboard that fetches actual costs from Meta's pricing_analytics API and tracks per-message estimated costs locally.

**Architecture:** Hybrid approach — new `whatsapp_cost_analytics` table stores daily synced data from Meta API, while `whatsapp_messages` gets an `estimated_cost_usd` column for real-time cost tracking. A `WhatsAppCostService` handles both Meta API calls and dashboard data aggregation. A scheduled job syncs costs daily.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), Flux UI, Meta Graph API v21.0

---

### Task 1: Create Migration — `whatsapp_cost_analytics` table

**Files:**
- Create: `database/migrations/2026_03_09_000001_create_whatsapp_cost_analytics_table.php`

**Step 1: Create migration**

```bash
php artisan make:migration create_whatsapp_cost_analytics_table --no-interaction
```

**Step 2: Write migration content**

```php
Schema::create('whatsapp_cost_analytics', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('country_code', 5)->default('MY');
    $table->string('pricing_category', 30); // MARKETING, UTILITY, AUTHENTICATION, SERVICE
    $table->integer('message_volume')->default(0);
    $table->decimal('cost_usd', 10, 6)->default(0);
    $table->decimal('cost_myr', 10, 4)->default(0);
    $table->string('granularity', 10)->default('DAILY');
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();

    $table->unique(['date', 'country_code', 'pricing_category'], 'wca_unique_daily');
});
```

**Step 3: Run migration**

```bash
php artisan migrate
```

---

### Task 2: Create Migration — Add `estimated_cost_usd` to `whatsapp_messages`

**Files:**
- Create: `database/migrations/2026_03_09_000002_add_estimated_cost_to_whatsapp_messages_table.php`

**Step 1: Create and write migration**

```bash
php artisan make:migration add_estimated_cost_to_whatsapp_messages_table --no-interaction
```

```php
Schema::table('whatsapp_messages', function (Blueprint $table) {
    $table->decimal('estimated_cost_usd', 10, 6)->nullable()->after('error_message');
});
```

**Step 2: Run migration**

```bash
php artisan migrate
```

---

### Task 3: Create `WhatsAppCostAnalytics` Model

**Files:**
- Create: `app/Models/WhatsAppCostAnalytics.php`

**Step 1: Create model**

```bash
php artisan make:model WhatsAppCostAnalytics --no-interaction
```

**Step 2: Define model with fillable, casts, scopes**

Key properties:
- `$table = 'whatsapp_cost_analytics'`
- `$fillable` with all columns
- `casts()` for date, synced_at, cost decimals
- Scopes: `dateRange`, `byCategory`, `byCountry`

---

### Task 4: Update `WhatsAppMessage` Model

**Files:**
- Modify: `app/Models/WhatsAppMessage.php`

**Step 1: Add `estimated_cost_usd` to fillable and casts**

Add to `$fillable` array: `'estimated_cost_usd'`
Add to `casts()`: `'estimated_cost_usd' => 'decimal:6'`

---

### Task 5: Create Config File `config/whatsapp-pricing.php`

**Files:**
- Create: `config/whatsapp-pricing.php`

```php
return [
    'rates' => [
        'MY' => [
            'marketing' => 0.0860,
            'utility' => 0.0140,
            'authentication' => 0.0140,
            'authentication_international' => 0.0418,
            'service' => 0.0000,
        ],
    ],
    'usd_to_myr' => 4.50,
    'default_country' => 'MY',
];
```

---

### Task 6: Create `WhatsAppCostService`

**Files:**
- Create: `app/Services/WhatsApp/WhatsAppCostService.php`

**Methods:**
1. `getMetaCredentials()` — reuse pattern from TemplateService
2. `fetchFromMeta(Carbon $start, Carbon $end)` — GET `/{apiVersion}/{wabaId}/pricing_analytics` with params
3. `syncDailyAnalytics(?Carbon $date)` — fetch from Meta, upsert into `whatsapp_cost_analytics`
4. `estimateCost(string $category, string $country)` — return USD rate from config
5. `getDashboardData(Carbon $start, Carbon $end)` — return summary, category breakdown, daily trend
6. `convertToMyr(float $usd)` — multiply by configured rate

---

### Task 7: Create `SyncWhatsAppCostAnalyticsJob`

**Files:**
- Create: `app/Jobs/SyncWhatsAppCostAnalyticsJob.php`

**Step 1: Create job**

```bash
php artisan make:job SyncWhatsAppCostAnalyticsJob --no-interaction
```

**Step 2: Implement handle() to call WhatsAppCostService::syncDailyAnalytics()**

---

### Task 8: Register Scheduled Job

**Files:**
- Modify: `routes/console.php`

**Step 1: Add schedule entry**

```php
Schedule::job(new \App\Jobs\SyncWhatsAppCostAnalyticsJob)->dailyAt('06:00');
```

---

### Task 9: Create Volt Dashboard Page

**Files:**
- Create: `resources/views/livewire/admin/whatsapp-cost-monitoring.blade.php`

**Component (class-based Volt):**
- Properties: `$period` (today/week/month/custom), `$startDate`, `$endDate`, `$lastSyncedAt`
- Methods: `setPeriod(string)`, `with()` returning dashboard data
- Uses `WhatsAppCostService` for all data

**Template sections:**
1. Header with title + period filter buttons
2. Summary cards (total cost MYR, total messages, avg cost, free service msgs)
3. Category breakdown cards (marketing, utility, authentication)
4. Daily trend (simple table or list — no JS chart library needed)
5. Recent messages table with estimated cost, paginated

---

### Task 10: Add Route

**Files:**
- Modify: `routes/web.php`

**Step 1: Add route in admin-only section**

```php
Volt::route('whatsapp/costs', 'admin.whatsapp-cost-monitoring')->name('admin.whatsapp.costs');
```

---

### Task 11: Write Feature Test

**Files:**
- Create: `tests/Feature/WhatsAppCostMonitoringTest.php`

**Tests:**
1. Admin can access cost monitoring page
2. Non-admin cannot access cost monitoring page
3. Dashboard shows correct period data
4. Meta API sync job works correctly (with Http::fake)
5. Cost estimation returns correct rates

---

### Task 12: Run Pint and Full Test Suite

```bash
vendor/bin/pint --dirty
php artisan test --compact
```
