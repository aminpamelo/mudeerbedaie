# Module 6: Claims, Benefits & Asset Tracking — Detailed Design

**Date:** 2026-03-27
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 3 (Payroll & Benefits)
**Dependencies:** Module 1 (Employee), Module 5 (Payroll — reimbursement integration)

---

## Key Decisions

- **Claim Types:** Custom-defined by HR (medical, transport, parking, meals, training, equipment, etc.)
- **Claim Limits:** Both monthly and yearly caps per claim type
- **Reimbursement:** Separate payment (not bundled into payroll)
- **Approval:** Separate claim approvers (not using shared department_approvers from Leave/Attendance)
- **Benefits:** Track assigned benefits per employee (insurance, allowances, etc.)
- **Asset Tracking:** Full assignment + return tracking for company assets
- **Asset Types:** IT equipment, furniture, vehicles, uniforms, access cards, tools — all configurable
- **Receipts:** Required for all claims (photo upload or file upload)

---

## Data Models

### `claim_types` — Configurable claim categories

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Medical Claim", "Transport Claim" |
| code | string (unique) | e.g., "MEDICAL", "TRANSPORT", "PARKING" |
| description | text | nullable |
| monthly_limit | decimal(10,2) | nullable, max per month |
| yearly_limit | decimal(10,2) | nullable, max per year |
| requires_receipt | boolean | default true |
| is_active | boolean | |
| sort_order | int | Display order |
| created_at | timestamp | |
| updated_at | timestamp | |

### `claim_requests` — Employee claim submissions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| claim_number | string (unique) | Auto-generated: CLM-YYYYMM-0001 |
| employee_id | FK → employees | |
| claim_type_id | FK → claim_types | |
| amount | decimal(10,2) | Claimed amount |
| approved_amount | decimal(10,2) | nullable, may differ from claimed |
| claim_date | date | Date of expense |
| description | text | What was purchased/spent |
| receipt_path | string | File path to uploaded receipt |
| status | enum | draft, pending, approved, rejected, paid |
| submitted_at | timestamp | nullable |
| approved_by | FK → employees | nullable |
| approved_at | timestamp | nullable |
| rejected_reason | text | nullable |
| paid_at | timestamp | nullable |
| paid_reference | string | nullable, payment reference number |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:** employee_id + status, claim_type_id + status, claim_date

### `claim_approvers` — Per-employee claim approvers (separate from leave/attendance)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | The employee whose claims need approval |
| approver_id | FK → employees | The person who can approve |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique:** employee_id + approver_id

### `benefit_types` — Configurable benefit categories

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Health Insurance", "Dental Coverage" |
| code | string (unique) | e.g., "HEALTH_INS", "DENTAL" |
| description | text | nullable |
| category | enum | insurance, allowance, subsidy, other |
| is_active | boolean | |
| sort_order | int | Display order |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_benefits` — Benefits assigned to employees

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| benefit_type_id | FK → benefit_types | |
| provider | string | nullable, e.g., "AIA", "Prudential" |
| policy_number | string | nullable |
| coverage_amount | decimal(10,2) | nullable |
| employer_contribution | decimal(10,2) | nullable, monthly/annual employer cost |
| employee_contribution | decimal(10,2) | nullable, employee share |
| start_date | date | Coverage start |
| end_date | date | nullable (null = ongoing) |
| notes | text | nullable |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `asset_categories` — Configurable asset categories

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Laptop", "Monitor", "Office Chair" |
| code | string (unique) | e.g., "LAPTOP", "MONITOR", "CHAIR" |
| description | text | nullable |
| requires_serial_number | boolean | default false |
| is_active | boolean | |
| sort_order | int | Display order |
| created_at | timestamp | |
| updated_at | timestamp | |

### `assets` — Individual asset inventory

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| asset_tag | string (unique) | Auto-generated: AST-0001 |
| asset_category_id | FK → asset_categories | |
| name | string | e.g., "MacBook Pro 14" M3" |
| brand | string | nullable |
| model | string | nullable |
| serial_number | string | nullable |
| purchase_date | date | nullable |
| purchase_price | decimal(10,2) | nullable |
| warranty_expiry | date | nullable |
| condition | enum | new, good, fair, poor, damaged, disposed |
| status | enum | available, assigned, under_maintenance, disposed |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `asset_assignments` — Track who has what asset

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| asset_id | FK → assets | |
| employee_id | FK → employees | |
| assigned_by | FK → employees | Who assigned it |
| assigned_date | date | |
| expected_return_date | date | nullable |
| returned_date | date | nullable |
| returned_condition | enum | nullable — good, fair, poor, damaged |
| return_notes | text | nullable |
| status | enum | active, returned, lost, damaged |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:** asset_id + status, employee_id + status

---

## Screens

### Admin Screens (8)

#### 1. Claims Dashboard
- Summary cards: pending claims, total this month, total this year, average processing time
- Recent pending claims (quick approve/reject)
- Claims by type chart (pie/donut)
- Monthly claims trend (bar chart)

#### 2. Claim Requests Management
- Table: claim_number, employee, type, amount, date, status
- Filters: status, claim_type, date_range, employee
- Search by claim number or employee name
- Bulk approve/reject
- View receipt inline
- Approve with different amount (partial approval)

#### 3. Claim Types Configuration
- CRUD for claim types
- Set monthly and yearly limits
- Toggle receipt requirement
- Reorder via drag-and-drop

#### 4. Claim Approvers Configuration
- Assign approvers per employee
- Bulk assign (e.g., all employees in a department → same approver)

#### 5. Claims Reports
- Claims by employee summary
- Claims by type summary
- Monthly/yearly totals
- Export to CSV
- Filter by date range, department, claim type

#### 6. Benefits Management
- List all employee benefits
- Assign benefit to employee
- View by employee or by benefit type
- Track expiry dates
- Cost summary (employer + employee contributions)

#### 7. Benefit Types Configuration
- CRUD for benefit types
- Categories: insurance, allowance, subsidy, other

#### 8. Asset Inventory
- Table: asset_tag, name, category, status, assigned_to
- Filters: category, status, condition
- Search by asset tag, name, serial number
- Add new asset, edit, dispose
- View assignment history per asset
- QR code generation for asset tags (future)

#### 9. Asset Categories Configuration
- CRUD for asset categories
- Toggle serial number requirement

#### 10. Asset Assignments
- Assign asset to employee
- View all current assignments
- Process returns (condition, notes)
- Filter by employee, category, status

### Employee Self-Service Screens (2)

#### 11. My Claims
- List of my claim requests with status
- Submit new claim (form with receipt upload)
- View claim details and approval status
- Filter by status, type, date range
- Monthly/yearly usage summary per claim type

#### 12. My Assets
- List of currently assigned assets
- Asset details (name, tag, assigned date)
- History of returned assets

---

## API Endpoints

### Claims (~15 endpoints)

```
GET    /api/hr/claims/dashboard          — Dashboard stats
GET    /api/hr/claims/requests           — List all claims (admin, filterable)
POST   /api/hr/claims/requests           — Submit new claim
GET    /api/hr/claims/requests/{id}      — Claim details
PUT    /api/hr/claims/requests/{id}      — Update draft claim
POST   /api/hr/claims/requests/{id}/submit    — Submit draft for approval
POST   /api/hr/claims/requests/{id}/approve   — Approve claim
POST   /api/hr/claims/requests/{id}/reject    — Reject claim
POST   /api/hr/claims/requests/{id}/mark-paid — Mark as paid
DELETE /api/hr/claims/requests/{id}      — Delete draft claim

GET    /api/hr/claims/types              — List claim types
POST   /api/hr/claims/types              — Create claim type
PUT    /api/hr/claims/types/{id}         — Update claim type
DELETE /api/hr/claims/types/{id}         — Delete claim type

GET    /api/hr/claims/approvers          — List claim approvers
POST   /api/hr/claims/approvers          — Assign approver
DELETE /api/hr/claims/approvers/{id}     — Remove approver

GET    /api/hr/claims/reports            — Claims report data
GET    /api/hr/claims/my                 — My claims (employee)
```

### Benefits (~8 endpoints)

```
GET    /api/hr/benefits/types            — List benefit types
POST   /api/hr/benefits/types            — Create benefit type
PUT    /api/hr/benefits/types/{id}       — Update benefit type
DELETE /api/hr/benefits/types/{id}       — Delete benefit type

GET    /api/hr/benefits                  — List employee benefits
POST   /api/hr/benefits                  — Assign benefit to employee
PUT    /api/hr/benefits/{id}             — Update employee benefit
DELETE /api/hr/benefits/{id}             — Remove employee benefit
```

### Assets (~12 endpoints)

```
GET    /api/hr/assets/categories         — List asset categories
POST   /api/hr/assets/categories         — Create category
PUT    /api/hr/assets/categories/{id}    — Update category
DELETE /api/hr/assets/categories/{id}    — Delete category

GET    /api/hr/assets                    — List all assets (filterable)
POST   /api/hr/assets                    — Create asset
GET    /api/hr/assets/{id}               — Asset details + assignment history
PUT    /api/hr/assets/{id}               — Update asset
DELETE /api/hr/assets/{id}               — Dispose asset

GET    /api/hr/assets/assignments        — List all assignments
POST   /api/hr/assets/assignments        — Assign asset to employee
PUT    /api/hr/assets/assignments/{id}/return — Process asset return

GET    /api/hr/assets/my                 — My assigned assets (employee)
```

---

## Workflows

### Claim Submission & Approval

```
Employee creates draft claim
  → Fills in: type, amount, date, description, receipt
  → Submits claim (status: pending)
    → Assigned approver gets notification
      → Approver reviews (view receipt)
        → Approve (full or partial amount) → status: approved
        → Reject (with reason) → status: rejected
          → Employee notified of outcome
      → Approved claims marked as paid by admin
        → status: paid (with payment reference)
```

### Asset Assignment & Return

```
Admin creates asset in inventory
  → status: available
Admin assigns asset to employee
  → Creates assignment record (status: active)
  → Asset status: assigned
  → Employee sees asset in "My Assets"
Employee returns asset / Admin processes return
  → Records return date, condition, notes
  → Assignment status: returned
  → Asset status: available (or under_maintenance/disposed)
```

### Claim Limit Validation

```
Employee submits claim for RM150 (Medical Claim)
  → System checks:
    1. Monthly limit: RM500 → Used this month: RM400 → Remaining: RM100
    2. Yearly limit: RM3000 → Used this year: RM2500 → Remaining: RM500
  → Monthly limit exceeded (RM150 > RM100 remaining)
  → Show warning: "Monthly limit exceeded. Maximum claimable: RM100"
  → Employee can still submit (up to remaining amount) or save as draft
```

---

## Integration Points

| Integration | Direction | Details |
|-------------|-----------|---------|
| Employee (Module 1) | Read | Employee info, department, employment type |
| Payroll (Module 5) | Optional | Future: include approved claims in payroll run |
| Notifications | Push | Claim submitted, approved, rejected, paid |

---

## Future Enhancements (Not in Initial Build)

- QR code on asset tags for quick lookup via camera
- Bulk claim submission (multiple receipts at once)
- Claim categories with sub-categories
- Asset depreciation tracking
- Insurance claim integration
- Benefits enrollment portal (employee self-selects benefits)
- Asset maintenance scheduling
- Reimbursement via payroll integration
