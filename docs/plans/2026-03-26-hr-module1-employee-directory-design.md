# Module 1: Employee Directory & Profiles — Detailed Design

**Date:** 2026-03-26
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 1 (Foundation)

---

## Key Decisions

- Employee ID format: **BDE-0001** (auto-generated, sequential)
- Employment types: Full-time, Part-time, Contract, Intern
- Departments: Configurable (user will define later)
- Personal fields: Name, IC, DOB, Gender, Religion, Race, Marital Status, Phone, Email, Address
- Documents: Standard Malaysian set (IC, offer letter, contract, bank statement, EPF form, SOCSO form)
- User account: Auto-create + email invite on employee creation
- Access: HR Admin only (employees see own profile via self-service)
- History tracking: Full log of all employment changes
- Data import: Not needed (manual entry only)

---

## Data Models

### `employees` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| user_id | FK → users | Linked User account |
| employee_id | string (unique) | Format: BDE-0001 |
| full_name | string | Full name as per IC |
| ic_number | string | Malaysian IC (encrypted) |
| date_of_birth | date | |
| gender | enum | male, female |
| religion | enum | islam, christian, buddhist, hindu, sikh, other |
| race | enum | malay, chinese, indian, other |
| marital_status | enum | single, married, divorced, widowed |
| phone | string | |
| personal_email | string | Personal email (separate from work email on User) |
| address_line_1 | string | |
| address_line_2 | string | nullable |
| city | string | |
| state | string | Malaysian states dropdown |
| postcode | string | |
| profile_photo | string | File path, nullable |
| department_id | FK → departments | |
| position_id | FK → positions | |
| employment_type | enum | full_time, part_time, contract, intern |
| join_date | date | |
| probation_end_date | date | nullable |
| confirmation_date | date | nullable |
| contract_end_date | date | nullable (for contract/intern) |
| status | enum | active, probation, resigned, terminated |
| resignation_date | date | nullable |
| last_working_date | date | nullable |
| bank_name | string | |
| bank_account_number | string | encrypted |
| epf_number | string | nullable |
| socso_number | string | nullable |
| tax_number | string | nullable (LHDN income tax ref) |
| notes | text | nullable, internal HR notes |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | soft delete |

### `employee_emergency_contacts` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| name | string | |
| relationship | string | e.g., spouse, parent, sibling |
| phone | string | |
| address | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_documents` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| document_type | enum | ic_front, ic_back, offer_letter, contract, bank_statement, epf_form, socso_form |
| file_name | string | Original filename |
| file_path | string | Storage path |
| file_size | integer | Bytes |
| mime_type | string | |
| uploaded_at | timestamp | |
| expiry_date | date | nullable |
| notes | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_histories` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| change_type | enum | position_change, department_transfer, status_change, salary_revision, promotion, general_update |
| field_name | string | Which field changed |
| old_value | string | nullable |
| new_value | string | |
| effective_date | date | |
| remarks | text | nullable |
| changed_by | FK → users | Who made the change |
| created_at | timestamp | |

### `departments` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | |
| code | string (unique) | e.g., IT, SALES, OPS |
| description | text | nullable |
| parent_id | FK → departments | nullable, for sub-departments |
| head_employee_id | FK → employees | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `positions` table

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| title | string | e.g., Manager, Executive, Senior Executive |
| department_id | FK → departments | |
| level | integer | Hierarchy level (1=highest) |
| description | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Screens

### 1. HR Dashboard (`/hr`)

**Layout:** Stats cards at top + charts + recent activity

**Stats Cards:**
- Total Employees (active count)
- New Hires This Month
- On Probation (with confirmation dates approaching)
- Departments count

**Charts:**
- Department headcount (bar chart — Recharts)
- Employment type breakdown (pie chart)

**Recent Activity:**
- Latest employee additions, status changes, document uploads

---

### 2. Employee List (`/hr/employees`)

**Layout:** Toggle between Table View and Card Grid View

**Table View Columns:**
| Photo | Name | Employee ID | Department | Position | Type | Status | Join Date | Actions |

**Card Grid View:**
- Avatar, name, employee ID, department, position, status badge

**Toolbar:**
- Search input (searches name, employee ID, IC number)
- Filter dropdowns: Department, Position, Status, Employment Type
- "Export CSV" button
- "+ Add Employee" button

**Pagination:** 20 per page

---

### 3. Add Employee Wizard (`/hr/employees/create`)

**Step 1 — Personal Information:**
- Full Name * (text)
- IC Number * (text, formatted: 901215-14-5678)
- Date of Birth * (date picker, auto-populated from IC)
- Gender * (radio: Male / Female)
- Religion (select: Islam, Christian, Buddhist, Hindu, Sikh, Other)
- Race (select: Malay, Chinese, Indian, Other)
- Marital Status (select: Single, Married, Divorced, Widowed)
- Phone * (text)
- Personal Email * (text)
- Address Line 1 * (text)
- Address Line 2 (text)
- City * (text)
- State * (select: all Malaysian states)
- Postcode * (text)
- Profile Photo (file upload, optional)

**Step 2 — Employment Details:**
- Department * (select from departments)
- Position * (select, filtered by department)
- Employment Type * (select: Full-time, Part-time, Contract, Intern)
- Join Date * (date picker)
- Probation End Date (date picker, auto-suggest 3/6 months from join date)
- Contract End Date (date picker, shown only for Contract/Intern)
- Reporting Manager (select from existing employees, optional)
- Work Email * (auto-suggest: firstname@company.com)
- Notes (textarea, internal HR notes)

**Step 3 — Bank & Statutory:**
- Bank Name * (select: common Malaysian banks — Maybank, CIMB, RHB, Public Bank, Bank Islam, etc.)
- Bank Account Number * (text, will be encrypted)
- EPF Number (text)
- SOCSO Number (text)
- Tax Reference Number (text, LHDN)

**Step 4 — Documents:**
- IC Front * (file upload)
- IC Back * (file upload)
- Offer Letter (file upload)
- Employment Contract (file upload)
- Bank Statement (file upload)
- EPF Nomination Form (file upload)
- SOCSO Registration Form (file upload)

Accepted formats: PDF, JPG, PNG (max 5MB each)

**Step 5 — Review & Submit:**
- Summary of all entered data grouped by section
- Edit buttons per section to go back and modify
- "Create Employee" button
- On submit: creates Employee + User account + sends welcome email

---

### 4. Employee Profile (`/hr/employees/:id`)

**Header Section:**
- Profile photo (large)
- Full name
- Employee ID (BDE-0001)
- Department → Position
- Status badge (color-coded: green=active, yellow=probation, red=resigned/terminated)
- Join date + tenure (e.g., "Joined 15 Jan 2024 · 2 years 2 months")
- Action buttons: [Edit] [Change Status ▼] [More ▼]

**Tabs:**

**Tab 1 — Personal Info:**
| Field | Value |
|-------|-------|
| Full Name | Ahmad Najmi bin Abdullah |
| IC Number | 901215-14-**** (masked, click to reveal) |
| Date of Birth | 15 Dec 1990 (35 years old) |
| Gender | Male |
| Religion | Islam |
| Race | Malay |
| Marital Status | Married |
| Phone | +60 12-345 6789 |
| Personal Email | ahmad.personal@gmail.com |
| Address | 123 Jalan Ampang, 50450 KL, Selangor |

**Tab 2 — Employment:**
| Field | Value |
|-------|-------|
| Employee ID | BDE-0001 |
| Department | IT |
| Position | Manager |
| Employment Type | Full-time |
| Join Date | 15 Jan 2024 |
| Probation End | 15 Jul 2024 |
| Confirmation Date | 15 Jul 2024 |
| Reporting Manager | Ali bin Abu (BDE-0005) |
| Work Email | ahmad@mudeerbedaie.com |
| Status | Active |

**Tab 3 — Bank & Statutory:**
| Field | Value |
|-------|-------|
| Bank Name | Maybank |
| Account Number | ****4567 (masked, click to reveal) |
| EPF Number | 12345678 |
| SOCSO Number | S12345678 |
| Tax Reference | SG12345678 |

**Tab 4 — Documents:**
- Table: Document Type | File Name | Uploaded Date | Size | Actions [View] [Download] [Delete]
- "+ Upload Document" button
- Document preview modal (for images/PDFs)

**Tab 5 — Emergency Contacts:**
- Cards for each contact: Name, Relationship, Phone, Address
- "+ Add Contact" button
- Edit/Delete per contact

**Tab 6 — History:**
- Timeline view (newest first):
  ```
  ● 15 Jul 2024 — Status Change
    Probation → Active (Confirmed)
    Changed by: HR Admin

  ● 01 Apr 2024 — Department Transfer
    Sales → IT
    Changed by: HR Admin
    Remarks: Transferred per management decision

  ● 15 Jan 2024 — Employee Created
    New employee record created
    Changed by: HR Admin
  ```

---

### 5. Edit Employee (`/hr/employees/:id/edit`)

- Same field layout as the wizard but pre-filled with current data
- Can edit individual sections without stepping through all
- Tabbed form matching the profile tabs
- On save: detect changed fields → auto-log to EmployeeHistory for tracked fields (department, position, status, employment_type)
- Effective date picker for tracked changes

---

### 6. Department Management (`/hr/departments`)

- Department list table: Name | Code | Head | Employees Count | Parent Dept | Actions
- "+ Add Department" button → modal form
- Edit/Delete actions
- Tree view toggle showing department hierarchy
- Click department → shows employees in that department

---

### 7. Position Management (`/hr/positions`)

- Positions grouped by department (accordion or grouped table)
- Columns: Title | Level | Department | Employees Count | Actions
- "+ Add Position" button → modal form
- Edit/Delete actions

---

## Workflows

### New Employee Creation Flow

```
HR Admin opens wizard
  → Fills Step 1-4
  → Reviews in Step 5
  → Clicks "Create Employee"
  → System:
    1. Generates Employee ID (BDE-XXXX, next sequential)
    2. Creates Employee record
    3. Creates User account (email = work email, random password)
    4. Assigns 'employee' role to User
    5. Sends welcome email with password reset link
    6. Creates EmployeeHistory entry: "Employee Created"
  → Redirects to new Employee Profile page
```

### Edit Employee Flow (with history)

```
HR Admin opens Edit Employee
  → Modifies fields
  → For tracked fields (department, position, status, employment_type):
    - System shows "Effective Date" picker
    - System shows "Remarks" textarea
  → Clicks Save
  → System:
    1. Compares old vs new values for tracked fields
    2. Creates EmployeeHistory entries for each changed tracked field
    3. Updates Employee record
  → Redirects to Employee Profile with success notification
```

### Employee Status Change Flow

```
Active → Resigned:
  - Prompt: Resignation Date, Last Working Day, Remarks
  - Creates history entry
  - Optionally deactivates User account

Active → Terminated:
  - Prompt: Effective Date, Reason, Remarks
  - Creates history entry
  - Deactivates User account

Probation → Active (Confirmation):
  - Prompt: Confirmation Date, Remarks
  - Creates history entry
  - Updates confirmation_date
```

---

## API Endpoints

### Employees
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/employees` | List (search, filter, paginate) |
| POST | `/api/hr/employees` | Create new employee + user account |
| GET | `/api/hr/employees/{id}` | Get employee detail |
| PUT | `/api/hr/employees/{id}` | Update employee |
| PATCH | `/api/hr/employees/{id}/status` | Change employee status |
| DELETE | `/api/hr/employees/{id}` | Soft delete employee |
| GET | `/api/hr/employees/export` | Export CSV |
| GET | `/api/hr/employees/next-id` | Get next available employee ID |

### Employee Sub-resources
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/employees/{id}/history` | Employment history timeline |
| GET | `/api/hr/employees/{id}/documents` | List documents |
| POST | `/api/hr/employees/{id}/documents` | Upload document |
| GET | `/api/hr/employees/{id}/documents/{docId}/download` | Download document |
| DELETE | `/api/hr/employees/{id}/documents/{docId}` | Delete document |
| GET | `/api/hr/employees/{id}/emergency-contacts` | List contacts |
| POST | `/api/hr/employees/{id}/emergency-contacts` | Add contact |
| PUT | `/api/hr/employees/{id}/emergency-contacts/{contactId}` | Update contact |
| DELETE | `/api/hr/employees/{id}/emergency-contacts/{contactId}` | Delete contact |

### Departments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/departments` | List all departments |
| POST | `/api/hr/departments` | Create department |
| GET | `/api/hr/departments/{id}` | Get department detail |
| PUT | `/api/hr/departments/{id}` | Update department |
| DELETE | `/api/hr/departments/{id}` | Delete department |
| GET | `/api/hr/departments/{id}/employees` | List employees in department |
| GET | `/api/hr/departments/tree` | Get department hierarchy tree |

### Positions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/positions` | List all positions |
| POST | `/api/hr/positions` | Create position |
| GET | `/api/hr/positions/{id}` | Get position detail |
| PUT | `/api/hr/positions/{id}` | Update position |
| DELETE | `/api/hr/positions/{id}` | Delete position |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hr/dashboard/stats` | Dashboard statistics |
| GET | `/api/hr/dashboard/recent-activity` | Recent activity feed |
| GET | `/api/hr/dashboard/headcount-by-department` | Department headcount chart data |

---

## Validation Rules

### Employee Creation
- full_name: required, string, max:255
- ic_number: required, string, unique, regex: /^\d{6}-\d{2}-\d{4}$/
- date_of_birth: required, date, before:today
- gender: required, in:male,female
- religion: nullable, in:islam,christian,buddhist,hindu,sikh,other
- race: nullable, in:malay,chinese,indian,other
- marital_status: nullable, in:single,married,divorced,widowed
- phone: required, string
- personal_email: required, email
- address_line_1: required, string
- city: required, string
- state: required, string
- postcode: required, string, regex: /^\d{5}$/
- department_id: required, exists:departments,id
- position_id: required, exists:positions,id
- employment_type: required, in:full_time,part_time,contract,intern
- join_date: required, date
- bank_name: required, string
- bank_account_number: required, string

### Document Upload
- file: required, file, max:5120 (5MB), mimes:pdf,jpg,jpeg,png
- document_type: required, in:ic_front,ic_back,offer_letter,contract,bank_statement,epf_form,socso_form

### Department
- name: required, string, max:255
- code: required, string, max:10, unique
- parent_id: nullable, exists:departments,id

### Position
- title: required, string, max:255
- department_id: required, exists:departments,id
- level: required, integer, min:1

---

## Malaysian State List (for address dropdown)

Johor, Kedah, Kelantan, Melaka, Negeri Sembilan, Pahang, Perak, Perlis, Pulau Pinang, Sabah, Sarawak, Selangor, Terengganu, W.P. Kuala Lumpur, W.P. Putrajaya, W.P. Labuan

## Malaysian Bank List (for bank dropdown)

Maybank, CIMB Bank, Public Bank, RHB Bank, Hong Leong Bank, AmBank, Bank Islam, Bank Rakyat, OCBC Bank, UOB Bank, HSBC Bank, Standard Chartered, Affin Bank, Alliance Bank, Bank Muamalat, Agrobank, BSN (Bank Simpanan Nasional)
