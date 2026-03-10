# WhatsApp Cost Monitoring Dashboard — Design

> Created: 9 March 2026

## Overview

Add a cost monitoring dashboard to track WhatsApp messaging costs. Uses a **hybrid approach**: real-time local cost estimation per message + daily sync of actual costs from Meta's `pricing_analytics` Graph API endpoint.

## Data Sources

### 1. Meta Graph API — `pricing_analytics`
```
GET /v25.0/{WABA_ID}/pricing_analytics
```
- Returns actual message volume and costs from Meta
- Broken down by country, category, pricing tier
- Granularity: `HALF_HOUR`, `DAILY`, `MONTHLY`
- Auth: existing Meta access token

### 2. Local Estimation
- Estimate cost at message send time based on template category
- Store on `whatsapp_messages.estimated_cost_usd`
- Provides real-time visibility before Meta sync

## Database Schema

### New Table: `whatsapp_cost_analytics`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| date | date | Day covered |
| country_code | string(5) | e.g. `MY` |
| pricing_category | string(30) | `MARKETING`, `UTILITY`, `AUTHENTICATION`, `SERVICE` |
| message_volume | integer | Message count |
| cost_usd | decimal(10,6) | Cost in USD from Meta |
| cost_myr | decimal(10,4) | Converted MYR |
| granularity | string(10) | `DAILY` |
| synced_at | timestamp | When fetched |
| timestamps | | created_at, updated_at |

**Unique constraint:** `[date, country_code, pricing_category]`

### Modify: `whatsapp_messages`
- Add `estimated_cost_usd` decimal(10,6) nullable

## Config: `config/whatsapp-pricing.php`
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
    'sync_time' => '06:00',
    'default_country' => 'MY',
];
```

## Service Layer

### `WhatsAppCostService`
- `fetchFromMeta(Carbon $start, Carbon $end): array` — Call Meta API
- `syncDailyAnalytics(?Carbon $date = null): void` — Sync a day's data (default: yesterday)
- `estimateCost(string $category, string $country = 'MY'): float` — Get estimated USD cost
- `getDashboardData(Carbon $start, Carbon $end): array` — Aggregate for dashboard
- `getSummaryCards(Carbon $start, Carbon $end): array` — Summary metrics
- `getCategoryBreakdown(Carbon $start, Carbon $end): array` — Per-category stats
- `getDailyTrend(Carbon $start, Carbon $end): Collection` — Daily chart data

### `SyncWhatsAppCostAnalyticsJob` (Queued)
- Fetches previous day's data from Meta
- Upserts into `whatsapp_cost_analytics`
- Scheduled daily at 06:00 via Laravel Scheduler

## Dashboard Page

**Route:** `GET /admin/whatsapp/costs` — admin only
**Component:** Volt class-based at `resources/views/livewire/admin/whatsapp-cost-monitoring.blade.php`

### Layout

#### 1. Summary Cards (top)
- Total Cost (MYR) | Total Messages | Avg Cost/Message | Free Service Messages

#### 2. Cost by Category (row of cards)
- Marketing: cost + count
- Utility: cost + count
- Authentication: cost + count

#### 3. Daily Cost Trend (chart)
- Bar/line chart, X=date, Y=cost MYR, colored by category

#### 4. Recent Messages Log (table)
- Columns: Date, Phone, Template, Category, Status, Est. Cost
- Paginated from `whatsapp_messages` with `estimated_cost_usd`

### Filters
- Quick: Today | This Week | This Month
- Custom date range picker

## Access
- Admin role only (`admin` middleware)

## Sync Schedule
- Laravel Scheduler: daily at 06:00
- Job: `SyncWhatsAppCostAnalyticsJob`
- Fetches yesterday's data with `DAILY` granularity
- Upserts to avoid duplicates
