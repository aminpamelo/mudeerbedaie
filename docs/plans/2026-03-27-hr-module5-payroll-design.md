# Module 5: Payroll & Compensation — Detailed Design

**Date:** 2026-03-27
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 3 (Payroll)
**Dependencies:** Module 1 (Employee), Module 3 (Leave — unpaid leave days), Module 4 (Attendance — OT hours)

---

## Key Decisions

- **Salary Structure:** Basic + fixed allowances + variable components (commissions, bonuses)
- **Statutory:** Full auto-calculation — EPF, SOCSO, EIS, PCB (with manual override for PCB)
- **Frequency:** Monthly payroll
- **Payment:** Individual bank transfer (no bulk file generation needed)
- **OT Pay:** No OT pay — replacement hours only (no payroll impact)
- **Unpaid Leave:** Deducted from salary, configurable divisor (26 or 30 days)
- **Workflow:** Draft → Review → Approve → Finalize (4-step approval)
- **EA Form:** Generate annually for LHDN tax filing
- **Payslips:** PDF generation, employees view + download via self-service
- **PCB:** Auto-calculate using LHDN monthly tax deduction tables, with manual override option

---

## Data Models

### `salary_components` — Configurable earning/deduction types

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Basic Salary", "Housing Allowance" |
| code | string (unique) | e.g., "BASIC", "HOUSING", "TRANSPORT" |
| type | enum | earning, deduction |
| category | enum | basic, fixed_allowance, variable_allowance, fixed_deduction, variable_deduction |
| is_taxable | boolean | Subject to PCB? |
| is_epf_applicable | boolean | Included in EPF calculation? |
| is_socso_applicable | boolean | Included in SOCSO calculation? |
| is_eis_applicable | boolean | Included in EIS calculation? |
| is_system | boolean | System-defined (can't delete) |
| is_active | boolean | |
| sort_order | int | Display order |
| created_at | timestamp | |
| updated_at | timestamp | |

**Default system components:**
| Code | Name | Type | Category | Taxable | EPF | SOCSO | EIS |
|------|------|------|----------|---------|-----|-------|-----|
| BASIC | Basic Salary | earning | basic | Yes | Yes | Yes | Yes |
| EPF_EE | EPF (Employee) | deduction | fixed_deduction | No | No | No | No |
| EPF_ER | EPF (Employer) | deduction | fixed_deduction | No | No | No | No |
| SOCSO_EE | SOCSO (Employee) | deduction | fixed_deduction | No | No | No | No |
| SOCSO_ER | SOCSO (Employer) | deduction | fixed_deduction | No | No | No | No |
| EIS_EE | EIS (Employee) | deduction | fixed_deduction | No | No | No | No |
| EIS_ER | EIS (Employer) | deduction | fixed_deduction | No | No | No | No |
| PCB | PCB / MTD | deduction | fixed_deduction | No | No | No | No |

### `employee_salaries` — Employee salary structure

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| salary_component_id | FK → salary_components | |
| amount | decimal(10,2) | Monthly amount |
| effective_from | date | |
| effective_to | date | nullable (null = ongoing) |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_tax_profiles` — PCB tax information per employee

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees (unique) | |
| tax_number | string | nullable, LHDN reference |
| marital_status | enum | single, married_spouse_not_working, married_spouse_working |
| num_children | int | default 0 |
| num_children_studying | int | default 0 (18+ in higher education) |
| disabled_individual | boolean | default false |
| disabled_spouse | boolean | default false |
| is_pcb_manual | boolean | default false |
| manual_pcb_amount | decimal(8,2) | nullable, used when is_pcb_manual = true |
| created_at | timestamp | |
| updated_at | timestamp | |

### `statutory_rates` — EPF/SOCSO/EIS rate tables

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| type | enum | epf_employee, epf_employer, socso_employee, socso_employer, eis_employee, eis_employer |
| min_salary | decimal(10,2) | Salary range start |
| max_salary | decimal(10,2) | nullable |
| rate_percentage | decimal(5,2) | nullable |
| fixed_amount | decimal(8,2) | nullable (SOCSO uses fixed amounts) |
| effective_from | date | |
| effective_to | date | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `pcb_rates` — Monthly Tax Deduction schedule (LHDN)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| category | enum | single, married_spouse_not_working, married_spouse_working |
| num_children | int | 0-20 |
| min_monthly_income | decimal(10,2) | |
| max_monthly_income | decimal(10,2) | nullable |
| pcb_amount | decimal(8,2) | Monthly tax amount |
| year | int | Tax year |
| created_at | timestamp | |
| updated_at | timestamp | |

### `payroll_runs` — Monthly payroll batch

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| month | int | 1-12 |
| year | int | |
| status | enum | draft, review, approved, finalized |
| total_gross | decimal(12,2) | |
| total_deductions | decimal(12,2) | |
| total_net | decimal(12,2) | |
| total_employer_cost | decimal(12,2) | Gross + employer EPF/SOCSO/EIS |
| employee_count | int | |
| prepared_by | FK → users | |
| reviewed_by | FK → users | nullable |
| approved_by | FK → users | nullable |
| approved_at | datetime | nullable |
| finalized_at | datetime | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `month + year`

### `payroll_items` — Individual payroll line items per employee

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| payroll_run_id | FK → payroll_runs | |
| employee_id | FK → employees | |
| salary_component_id | FK → salary_components | nullable |
| component_code | string | e.g., "BASIC", "EPF_EE", "PCB" |
| component_name | string | Display name |
| type | enum | earning, deduction, employer_contribution |
| amount | decimal(10,2) | |
| is_statutory | boolean | Auto-calculated statutory item |
| created_at | timestamp | |
| updated_at | timestamp | |

### `payslips` — Generated payslip records

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| payroll_run_id | FK → payroll_runs | |
| employee_id | FK → employees | |
| month | int | |
| year | int | |
| gross_salary | decimal(10,2) | |
| total_deductions | decimal(10,2) | |
| net_salary | decimal(10,2) | |
| epf_employee | decimal(8,2) | |
| epf_employer | decimal(8,2) | |
| socso_employee | decimal(8,2) | |
| socso_employer | decimal(8,2) | |
| eis_employee | decimal(8,2) | |
| eis_employer | decimal(8,2) | |
| pcb_amount | decimal(8,2) | |
| unpaid_leave_days | int | default 0 |
| unpaid_leave_deduction | decimal(8,2) | default 0 |
| pdf_path | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `employee_id + month + year`

### `salary_revisions` — Salary change history

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| salary_component_id | FK → salary_components | |
| old_amount | decimal(10,2) | |
| new_amount | decimal(10,2) | |
| effective_date | date | |
| reason | text | nullable |
| changed_by | FK → users | |
| created_at | timestamp | |

### `payroll_settings` — Configurable settings

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| key | string (unique) | |
| value | string | |
| description | string | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Default settings:**
| Key | Default | Description |
|-----|---------|-------------|
| unpaid_leave_divisor | 26 | Days divisor for daily rate (26 or 30) |
| pay_day | 25 | Salary payment day of month |
| epf_employee_default_rate | 11 | Default EPF employee % |
| company_name | - | For payslip header |
| company_address | - | For payslip header |
| company_epf_number | - | Company EPF registration |
| company_socso_number | - | Company SOCSO registration |
| company_eis_number | - | Company EIS registration |

---

## Statutory Calculation Logic

### EPF (KWSP) — Employees Provident Fund

**Employee contribution:**
- Default: 11% of monthly wages
- Optional lower: 0% (foreign workers, age >60)
- Wages subject to EPF: basic + fixed allowances + variable allowances

**Employer contribution:**
- Salary ≤ RM5,000: 13%
- Salary > RM5,000: 12%
- Age 60+: 4%

**Maximum contribution wage:** RM20,000/month

**Rounding:** EPF is rounded to nearest ringgit (RM). If 50 sen or more, round up.

### SOCSO (PERKESO) — Social Security

**Two categories:**
- Category 1 (Employment Injury + Invalidity): For employees <60 years old
- Category 2 (Employment Injury only): For employees ≥60 years old

**Contribution:** Based on salary bracket table (fixed amounts, NOT percentage)
- Uses lookup table from `statutory_rates` where type = socso_employee / socso_employer
- Max subject salary: RM6,000/month

**Example SOCSO brackets (Category 1):**
| Salary Range | Employee | Employer |
|-------------|----------|----------|
| RM1-RM30 | RM0.10 | RM0.30 |
| RM31-RM50 | RM0.20 | RM0.60 |
| ... | ... | ... |
| RM4,001-RM5,000 | RM19.75 | RM69.05 |
| RM5,001-RM6,000 | RM19.75 | RM69.05 |

### EIS (SIP) — Employment Insurance System

- Employee: 0.2% of monthly salary
- Employer: 0.2% of monthly salary
- Max subject salary: RM6,000/month
- Max contribution: RM12.00 per party

### PCB (MTD) — Monthly Tax Deduction

**Calculation method (simplified LHDN approach):**

```
1. Monthly gross remuneration (R)
   = Basic + Allowances + Bonuses (prorated) + Benefits

2. Monthly EPF deduction (E)
   = Employee EPF contribution

3. Monthly taxable income (T)
   = R - E

4. Lookup PCB table:
   Input: category, num_children, T (monthly taxable income)
   Output: PCB amount

5. Additional deductions:
   - Disabled individual: reduce PCB by RM100/month
   - Disabled spouse: reduce PCB by RM29.17/month
   - Children studying: additional RM66.67/child/month relief

6. Final PCB = max(0, looked_up_amount - additional_deductions)
```

**Manual override:** If `employee_tax_profiles.is_pcb_manual = true`, use `manual_pcb_amount` instead.

---

## Screens

### Admin Pages (10)

#### 1. Payroll Dashboard (`/hr/payroll`)

**Stats Cards:** Total Gross, Total Deductions, Total Net, Employer Cost (current/latest month)
**Charts:** Monthly payroll trend (12 months, bar chart), Statutory breakdown (pie chart)
**Recent Runs:** Table with status, employee count, totals, quick actions

#### 2. Payroll Run Detail (`/hr/payroll/run/:id`) — Core Page

**Header:** Month/Year, Status badge, prepared/reviewed/approved by
**Summary Cards:** Employee count, Gross, Deductions, Net, Employer Cost
**Employee Table:** All employees with full payroll breakdown per row
**Actions per status:**
- Draft: [Calculate All] [Submit for Review]
- Review: [Approve] [Return to Draft]
- Approved: [Finalize & Generate Payslips]
- Finalized: [Download All Payslips] [View Reports]

**Employee row actions:** [Edit] → modal for ad-hoc adjustments, [View] → detailed breakdown

#### 3. Payroll History (`/hr/payroll/history`)

Table of all payroll runs by year with compare functionality.

#### 4. Salary Components (`/hr/payroll/components`)

CRUD table for salary component types. System components locked.

#### 5. Employee Salary Setup (`/hr/payroll/salaries`)

Per-employee salary breakdown. Edit with effective date. Bulk revision.

#### 6. Employee Tax Profiles (`/hr/payroll/tax-profiles`)

Per-employee tax info for PCB calculation. Manual override toggle.

#### 7. Statutory Rates (`/hr/payroll/statutory-rates`)

Tabbed view: EPF | SOCSO | EIS | PCB rate tables. Editable when rates change.

#### 8. Payroll Reports (`/hr/payroll/reports`)

Monthly summary, statutory submissions, bank payment list, YTD, EA Forms.

#### 9. Payroll Settings (`/hr/payroll/settings`)

Configuration: divisor, pay day, company info, default rates.

#### 10. EA Form Management (`/hr/payroll/ea-forms`)

Generate, preview, and bulk download EA Forms per year.

### Employee Self-Service (1 page)

#### 11. My Payslips (`/hr/my/payslips`)

Payslip list with view detail + download PDF. YTD summary.

---

## Workflows

### Monthly Payroll Processing

```
HR creates new payroll run → Status: DRAFT
  ↓
HR clicks "Calculate All"
  → For each active employee:
    1. Fetch salary components (current effective amounts)
    2. Sum earnings: basic + allowances + variable items
    3. Get unpaid leave days (from leave_requests, status=approved, type=UL, for this month)
    4. Unpaid deduction = (basic_salary / divisor) × unpaid_days
    5. Gross = total_earnings - unpaid_deduction
    6. Calculate EPF (employee + employer)
    7. Calculate SOCSO (employee + employer) — lookup bracket table
    8. Calculate EIS (employee + employer)
    9. Calculate PCB — lookup or manual
    10. Total deductions = EPF_EE + SOCSO_EE + EIS_EE + PCB + other_deductions
    11. Net = Gross - Total deductions
    12. Employer cost = Gross + EPF_ER + SOCSO_ER + EIS_ER
  → Create payroll_items per employee per component
  → Update payroll_run totals
  ↓
HR reviews, makes ad-hoc adjustments if needed
  → Add bonus, commission, deduction for specific employees
  → Override PCB for specific employees
  → Recalculate affected employees
  ↓
HR clicks "Submit for Review" → Status: REVIEW
  → Notify reviewer
  ↓
Reviewer reviews → "Approve" → Status: APPROVED
  (or "Return to Draft" → Status: DRAFT with comments)
  ↓
HR clicks "Finalize & Generate Payslips" → Status: FINALIZED
  → Generate payslip records (one per employee)
  → Generate PDF payslips (store in storage/app/payslips/)
  → Lock payroll run (no more edits)
  → Employees can now view their payslips
```

### Salary Revision Flow

```
HR edits employee salary (e.g., raise basic from RM3,500 to RM4,000)
  → Sets effective_date
  → System:
    1. End current salary record (set effective_to = day before effective_date)
    2. Create new salary record (effective_from = effective_date)
    3. Create salary_revision record (old amount, new amount, reason)
  → Next payroll run will use the new amount
```

### EA Form Generation Flow

```
HR navigates to EA Forms page → selects year
  → Click "Generate EA Forms"
  → For each employee who received salary in that year:
    1. Sum all payslip data for the year
    2. Populate EA Form sections:
       - Section A: Company info (from payroll_settings)
       - Section B: Employee info (from employee + tax profile)
       - Section C: Total remuneration by category
       - Section D: Total deductions (EPF, SOCSO, EIS, PCB)
    3. Generate PDF
  → Download individually or bulk ZIP
```

---

## API Endpoints

### Payroll Runs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/runs` | List all payroll runs (filter: year, status) |
| POST | `/api/hr/payroll/runs` | Create new payroll run |
| GET | `/api/hr/payroll/runs/{id}` | Get detail with all items |
| POST | `/api/hr/payroll/runs/{id}/calculate` | Calculate all employees |
| POST | `/api/hr/payroll/runs/{id}/calculate/{employeeId}` | Recalculate single employee |
| PATCH | `/api/hr/payroll/runs/{id}/submit-review` | Submit for review |
| PATCH | `/api/hr/payroll/runs/{id}/approve` | Approve |
| PATCH | `/api/hr/payroll/runs/{id}/return-draft` | Return to draft |
| PATCH | `/api/hr/payroll/runs/{id}/finalize` | Finalize + generate payslips |
| DELETE | `/api/hr/payroll/runs/{id}` | Delete draft only |

### Payroll Items

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/hr/payroll/runs/{id}/items` | Add ad-hoc item |
| PUT | `/api/hr/payroll/runs/{id}/items/{itemId}` | Update item |
| DELETE | `/api/hr/payroll/runs/{id}/items/{itemId}` | Remove item |

### Salary Components

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/components` | List all |
| POST | `/api/hr/payroll/components` | Create |
| PUT | `/api/hr/payroll/components/{id}` | Update |
| DELETE | `/api/hr/payroll/components/{id}` | Delete (non-system only) |

### Employee Salaries

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/salaries` | List all employee salaries |
| GET | `/api/hr/payroll/salaries/{employeeId}` | Employee salary detail |
| POST | `/api/hr/payroll/salaries` | Set employee salary component |
| PUT | `/api/hr/payroll/salaries/{id}` | Update salary |
| GET | `/api/hr/payroll/salaries/{employeeId}/revisions` | Revision history |
| POST | `/api/hr/payroll/salaries/bulk-revision` | Bulk salary increase |

### Tax Profiles

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/tax-profiles` | List all |
| GET | `/api/hr/payroll/tax-profiles/{employeeId}` | Single profile |
| PUT | `/api/hr/payroll/tax-profiles/{employeeId}` | Update profile |

### Statutory Rates

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/statutory-rates` | List by type |
| PUT | `/api/hr/payroll/statutory-rates/{id}` | Update rate |
| POST | `/api/hr/payroll/statutory-rates/bulk-update` | Bulk update |

### Payslips

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/payslips` | List (filter: month, year, employee) |
| GET | `/api/hr/payroll/payslips/{id}` | Detail |
| GET | `/api/hr/payroll/payslips/{id}/pdf` | Download PDF |
| GET | `/api/hr/payroll/payslips/bulk-pdf/{runId}` | Download all as ZIP |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/reports/monthly-summary` | Monthly by department |
| GET | `/api/hr/payroll/reports/statutory` | Statutory contributions |
| GET | `/api/hr/payroll/reports/bank-payment` | Bank payment list |
| GET | `/api/hr/payroll/reports/ytd` | Year-to-date |
| GET | `/api/hr/payroll/reports/ea-form/{employeeId}` | Single EA Form PDF |
| GET | `/api/hr/payroll/reports/ea-forms/{year}` | Bulk EA Forms ZIP |

### Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/settings` | List all settings |
| PUT | `/api/hr/payroll/settings` | Update settings |

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/payroll/dashboard/stats` | Overview stats |
| GET | `/api/hr/payroll/dashboard/trend` | Monthly trend chart data |
| GET | `/api/hr/payroll/dashboard/statutory-breakdown` | Statutory pie chart data |

### Employee Self-Service

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/me/payslips` | My payslips list |
| GET | `/api/hr/me/payslips/{id}` | My payslip detail |
| GET | `/api/hr/me/payslips/{id}/pdf` | Download my payslip PDF |
| GET | `/api/hr/me/payslips/ytd` | My year-to-date summary |

---

## Validation Rules

### Payroll Run
- month: required, integer, between:1,12
- year: required, integer, min:2020
- unique: month + year combination

### Salary Component
- name: required, string, max:255
- code: required, string, max:20, unique
- type: required, in:earning,deduction
- category: required, in:basic,fixed_allowance,variable_allowance,fixed_deduction,variable_deduction

### Employee Salary
- employee_id: required, exists:employees,id
- salary_component_id: required, exists:salary_components,id
- amount: required, numeric, min:0
- effective_from: required, date

### Tax Profile
- marital_status: required, in:single,married_spouse_not_working,married_spouse_working
- num_children: required, integer, min:0, max:20
- num_children_studying: required, integer, min:0, max:num_children
- manual_pcb_amount: required_if:is_pcb_manual,true, numeric, min:0

### Ad-hoc Payroll Item
- employee_id: required, exists:employees,id
- component_name: required, string
- type: required, in:earning,deduction
- amount: required, numeric, min:0

---

## Integration Points

### With Module 3 (Leave Management)
- Unpaid leave days queried from `leave_requests` (status=approved, type=UL) for the payroll month
- Deduction calculated: (basic_salary / divisor) × unpaid_days

### With Module 1 (Employee Directory)
- Employee records, department, bank details for payment list
- Employment type affects which employees are included in payroll

### Future: With Module 6 (Claims & Benefits)
- Approved claims reimbursed through payroll (added as earning items)

---

## React File Structure

```
resources/js/hr/
├── pages/
│   ├── payroll/
│   │   ├── PayrollDashboard.jsx
│   │   ├── PayrollRun.jsx          (core processing page)
│   │   ├── PayrollHistory.jsx
│   │   ├── SalaryComponents.jsx
│   │   ├── EmployeeSalaries.jsx
│   │   ├── TaxProfiles.jsx
│   │   ├── StatutoryRates.jsx
│   │   ├── PayrollReports.jsx
│   │   ├── PayrollSettings.jsx
│   │   └── EaForms.jsx
│   ├── my/
│   │   └── MyPayslips.jsx
├── components/
│   ├── payroll/
│   │   ├── PayrollRunTable.jsx      (main employee payroll table)
│   │   ├── AdHocItemModal.jsx       (add bonus/deduction)
│   │   ├── SalarySetupForm.jsx
│   │   ├── TaxProfileForm.jsx
│   │   ├── PayslipPreview.jsx       (payslip detail view)
│   │   ├── StatutoryRateTable.jsx
│   │   └── PayrollStatusBadge.jsx
│   ├── charts/
│   │   ├── PayrollTrend.jsx
│   │   └── StatutoryBreakdown.jsx
├── hooks/
│   ├── usePayroll.js
│   └── useStatutory.js
├── services/
│   └── payrollCalculation.js        (client-side preview calculations)
```

---

## PDF Generation

### Payslip PDF Template

```
+-----------------------------------------------+
| COMPANY NAME                                   |
| Company Address                                |
+-----------------------------------------------+
| PAYSLIP                                        |
| Month: March 2026                              |
+-----------------------------------------------+
| Employee: Ahmad Najmi bin Abdullah             |
| Employee ID: BDE-0001                          |
| Department: IT | Position: Manager             |
| Bank: Maybank | Account: ****4567              |
+--------------------+--------------------------+
| EARNINGS           | DEDUCTIONS               |
+--------------------+--------------------------+
| Basic Salary 5,000 | EPF (11%)         715.00 |
| Housing Allow  800 | SOCSO              19.75 |
| Transport      500 | EIS                13.00 |
| Phone Allow    200 | PCB               210.00 |
|                    |                          |
+--------------------+--------------------------+
| Total Earnings     | Total Deductions         |
| RM 6,500.00        | RM 957.75                |
+--------------------+--------------------------+
| NET PAY: RM 5,542.25                          |
+-----------------------------------------------+
| Employer Contributions:                        |
| EPF: RM 845.00 | SOCSO: RM 69.05             |
| EIS: RM 13.00                                 |
+-----------------------------------------------+
```

Generated using Laravel DomPDF.
