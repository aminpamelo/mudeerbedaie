# Sales Sources Feature Design

**Date**: 2026-03-17
**Status**: Approved

## Overview

Add a "Sales Source" concept to the POS system so salespersons can tag each order with where the sale originated (e.g., Lead, Salespage, Adhoc). Includes a CRUD management page, integration into the POS order confirmation modal, and a source-based report tab.

## Database

### New Table: `sales_sources`

| Column       | Type          | Notes                              |
|--------------|---------------|------------------------------------|
| `id`         | bigint        | PK, auto-increment                 |
| `name`       | string(255)   | Required. e.g., "Lead", "Salespage"|
| `description`| text, nullable| What this source means              |
| `color`      | string(7)     | Hex color, e.g., `#3B82F6`         |
| `is_active`  | boolean       | Default true                        |
| `sort_order` | integer       | Default 0, for display ordering     |
| `created_at` | timestamp     |                                     |
| `updated_at` | timestamp     |                                     |

### Migration on `product_orders`

- Add `sales_source_id` as nullable unsigned bigint FK referencing `sales_sources.id`
- Nullable to preserve existing orders

### Model Relationships

- `SalesSource` → `orders(): hasMany(ProductOrder)`
- `ProductOrder` → `salesSource(): belongsTo(SalesSource)`

## Sources CRUD Page

- **Route**: `/admin/sales-sources` (name: `admin.sales-sources`)
- **Menu**: Sales Department → "Sales Sources" (between POS and Report)
- **Component**: Volt class-based at `resources/views/livewire/admin/sales-sources.blade.php`
- **Access**: admin, class_admin, sales roles

### CRUD Features

- **List**: Table with name (color badge), description, active status, sort order
- **Create/Edit**: Flux modal with fields: name, description, color picker, active toggle
- **Disable**: Toggle active/inactive (no hard delete to preserve report integrity)
- **Sort**: `sort_order` field for controlling display order

## POS Order Confirmation Modal

- **File**: `resources/js/pos/components/PaymentModal.jsx`
- **Position**: New "SALES SOURCE" section added before "PAYMENT METHOD"
- **UI**: Horizontal pill/button selector (matching Payment Method style)
- **Data Source**: New API endpoint `GET /api/pos/sales-sources` returns active sources sorted by `sort_order`
- **Validation**: Required — must select a source before confirming order
- **Payload**: Adds `sales_source_id` to the POST `/api/pos/sales` request

### API Changes

- New endpoint: `GET /api/pos/sales-sources` — returns active sources
- Modified endpoint: `POST /api/pos/sales` — accepts `sales_source_id` (required)
- Modified validation: `StorePosSaleRequest` adds `sales_source_id` rule

## Source Report Tab

- **Location**: Sales Department Report page, new sub-tab "Source Report" alongside "Team Sales" and "Product Report"
- **File**: `resources/views/livewire/admin/reports/sales-department.blade.php`

### Report Metrics (per source)

| Metric             | Description                        |
|--------------------|------------------------------------|
| Source Name        | With color badge                   |
| Total Revenue      | Sum of order totals for source     |
| Order Count        | Number of orders for source        |
| Avg Order Value    | Revenue / Order Count              |

### Filters

- Same period filters as existing report (date range, year, month)
- Uses existing salesperson filter context

## Sidebar Menu Update

```
Sales Department
├── POS - Point of Sale
├── Sales Sources        ← NEW
└── Sales Department Report
```

**Location**: `resources/views/components/layouts/app/sidebar.blade.php`

## Decisions

- **Storage**: Dedicated `sales_sources` table with FK on `product_orders` (Approach A — clean relational design)
- **Source required**: Yes, mandatory when creating POS orders
- **No hard delete**: Sources are toggled active/inactive to preserve historical data integrity
- **Source fields**: Name + Description + Color
