# Mileage Claim Feature Design

**Date:** 2026-03-31
**Status:** Approved
**Module:** HR Claims

## Overview

Extend the existing HR claims system to support mileage/petrol claims with automatic amount calculation based on vehicle type and distance traveled. Admin defines custom vehicle types with per-km rates on each mileage claim type. Employees select their vehicle, enter distance and route details, and the system auto-calculates the claim amount.

## Design Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Where to store vehicle rates | New `claim_type_vehicle_rates` table | Normalized, queryable, referential integrity |
| Vehicle types | Fully custom per claim type | Admin defines all vehicle types freely |
| Employee input fields | Vehicle + distance + origin/destination + trip purpose | Comprehensive audit trail |
| Limit basis | RM amount | Consistent with existing claim types |
| Approach | Extend ClaimType + new VehicleRate table | Reuses existing approval/limit/reporting logic |

## Database Changes

### 1. New column on `claim_types`

```
is_mileage_type  boolean  default: false
```

When enabled, this claim type uses mileage calculation instead of manual amount entry.

### 2. New table: `claim_type_vehicle_rates`

| Column | Type | Description |
|--------|------|-------------|
| id | bigIncrements | Primary key |
| claim_type_id | foreignId | References claim_types (cascade delete) |
| name | string | Vehicle name (e.g., "Car", "Motorcycle", "Van") |
| rate_per_km | decimal(8,2) | Rate in MYR per km (e.g., 0.60) |
| is_active | boolean, default: true | Can be deactivated without deletion |
| sort_order | integer, default: 0 | Display ordering |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:** (claim_type_id, is_active)

### 3. New columns on `claim_requests`

| Column | Type | Description |
|--------|------|-------------|
| vehicle_rate_id | foreignId, nullable | References claim_type_vehicle_rates |
| distance_km | decimal(10,2), nullable | Distance traveled in km |
| origin | string(255), nullable | Starting location |
| destination | string(255), nullable | Ending location |
| trip_purpose | string(255), nullable | Purpose of the trip |

**Auto-calculation:** `amount = distance_km × rate_per_km` (computed server-side)

## Models

### ClaimTypeVehicleRate (new)

- **Relationships:** `claimType()` → BelongsTo ClaimType
- **Scopes:** `active()`, `ordered()`
- **Fillable:** name, rate_per_km, is_active, sort_order

### ClaimType (updated)

- Add `is_mileage_type` to fillable/casts
- Add `vehicleRates()` → HasMany ClaimTypeVehicleRate

### ClaimRequest (updated)

- Add mileage fields to fillable
- Add `vehicleRate()` → BelongsTo ClaimTypeVehicleRate

## API Endpoints

### Vehicle Rates Management (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/claims/types/{type}/vehicle-rates` | List vehicle rates for a claim type |
| POST | `/api/hr/claims/types/{type}/vehicle-rates` | Create a vehicle rate |
| PUT | `/api/hr/claims/types/{type}/vehicle-rates/{rate}` | Update a vehicle rate |
| DELETE | `/api/hr/claims/types/{type}/vehicle-rates/{rate}` | Delete a vehicle rate |

### Claim Type Changes

- `GET/POST/PUT` claim types now include `is_mileage_type` field
- When `is_mileage_type` is true, response includes `vehicle_rates` relationship

### Employee Claim Submission Changes

- `POST /api/hr/me/claims` — when claim type is mileage:
  - **Required:** `vehicle_rate_id`, `distance_km`, `origin`, `destination`, `trip_purpose`
  - **Ignored:** `amount` (auto-calculated server-side)
  - **Optional:** `receipt` (based on requires_receipt setting), `description`

## Admin Flow

1. Create/edit a claim type (e.g., "Petrol Mileage")
2. Toggle "This is a mileage claim type" ON
3. Add vehicle rates:
   - Car → RM 0.60/km
   - Motorcycle → RM 0.30/km
   - Van → RM 0.80/km
4. Set monthly/yearly limits in RM as usual

## Employee Flow

1. Select mileage claim type (e.g., "Petrol Mileage")
2. Form shows mileage-specific fields:
   - Vehicle type dropdown (from claim type's rates)
   - Distance (km) input
   - Origin text field
   - Destination text field
   - Trip purpose text field
3. Amount auto-calculates: `50 km × RM 0.60 = RM 30.00`
4. Submit for approval

## Approval & Reporting

- **No changes to approval workflow** — approvers see the claim amount plus mileage details
- **Existing reports work unchanged** — RM-based aggregation applies
- **Dashboard stats** — no modifications needed

## Validation Rules

### Vehicle Rate (Admin)
- `name`: required, string, max 255
- `rate_per_km`: required, numeric, min 0.01
- `is_active`: boolean
- `sort_order`: integer, min 0

### Mileage Claim (Employee)
- `vehicle_rate_id`: required (when mileage type), exists in claim_type_vehicle_rates + must belong to selected claim type + must be active
- `distance_km`: required (when mileage type), numeric, min 0.01
- `origin`: required (when mileage type), string, max 255
- `destination`: required (when mileage type), string, max 255
- `trip_purpose`: required (when mileage type), string, max 255
