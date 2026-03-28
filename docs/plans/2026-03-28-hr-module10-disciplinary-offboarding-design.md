# Module 10: Disciplinary & Offboarding — Detailed Design

**Date:** 2026-03-28
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 5 (Compliance)
**Dependencies:** Module 1 (Employee), Module 3 (Leave — encashment), Module 5 (Payroll — final settlement), Module 6 (Assets — return), Module 8 (Performance — PIP outcomes)

---

## Key Decisions

- **Malaysian standard** disciplinary flow: verbal warning → 1st written → 2nd written → show cause → domestic inquiry → action (suspension/termination)
- **Full exit process:** resignation workflow + notice period calculation + asset return checklist + exit interview + final payment calculation + system access revocation
- **PDF generation** for warning letters, show cause letters, termination letters from configurable templates
- **Final settlement** auto-calculates prorated salary, leave encashment, statutory deductions
- **Shared `letter_templates` table** with Module 7 (offer letters)
- **Disciplinary chain:** Each action links to previous via `previous_action_id` for full escalation history

---

## Data Models

### `disciplinary_actions` — Warning/action records

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| reference_number | string (unique) | Auto-generated: DA-YYYYMM-0001 |
| employee_id | FK → employees | |
| type | enum | verbal_warning, first_written, second_written, show_cause, suspension, termination |
| reason | text | Description of misconduct |
| incident_date | date | When the incident happened |
| issued_date | date | When the letter/action was issued |
| issued_by | FK → employees | HR/manager who issued |
| response_required | boolean | default false (true for show cause) |
| response_deadline | date | nullable |
| employee_response | text | nullable |
| responded_at | timestamp | nullable |
| outcome | text | nullable, decision after response |
| letter_pdf_path | string | nullable |
| status | enum | draft, issued, pending_response, responded, closed |
| previous_action_id | FK → disciplinary_actions | nullable, link to previous warning in chain |
| created_at | timestamp | |
| updated_at | timestamp | |

### `disciplinary_inquiries` — Domestic inquiry/hearing

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| disciplinary_action_id | FK → disciplinary_actions | |
| hearing_date | date | |
| hearing_time | time | |
| location | string | |
| panel_members | json | Array of employee IDs on the panel |
| minutes | text | nullable, hearing minutes |
| findings | text | nullable |
| decision | enum | nullable — guilty, not_guilty, partially_guilty |
| penalty | text | nullable, if guilty (e.g., "2-week suspension", "termination") |
| status | enum | scheduled, completed, postponed, cancelled |
| created_at | timestamp | |
| updated_at | timestamp | |

### `resignation_requests` — Resignation applications

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| submitted_date | date | |
| reason | text | |
| notice_period_days | int | Calculated from employment type/contract |
| last_working_date | date | Calculated: submitted + notice period |
| requested_last_date | date | nullable, if employee requests early release |
| status | enum | pending, approved, rejected, withdrawn, completed |
| approved_by | FK → employees | nullable |
| approved_at | timestamp | nullable |
| final_last_date | date | nullable, actual approved last date |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `exit_checklists` — Offboarding checklist for departing employee

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| resignation_request_id | FK → resignation_requests | nullable |
| status | enum | in_progress, completed |
| completed_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `exit_checklist_items` — Individual offboarding tasks

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| exit_checklist_id | FK → exit_checklists | |
| title | string | e.g., "Return laptop", "Revoke email access" |
| category | enum | asset_return, system_access, documentation, clearance, other |
| assigned_to | FK → employees | nullable |
| status | enum | pending, completed, not_applicable |
| completed_at | timestamp | nullable |
| completed_by | FK → users | nullable |
| notes | text | nullable |
| sort_order | int | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Default exit checklist items:**

| Title | Category |
|-------|----------|
| Return laptop/PC | asset_return |
| Return access card | asset_return |
| Return office keys | asset_return |
| Return uniform | asset_return |
| Return company phone | asset_return |
| Revoke email access | system_access |
| Revoke system login | system_access |
| Remove VPN access | system_access |
| Handover documents | documentation |
| Knowledge transfer session | documentation |
| Return signed resignation acceptance | documentation |
| Department head clearance | clearance |
| Finance clearance (no outstanding) | clearance |
| HR clearance | clearance |

### `exit_interviews` — Exit interview responses

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| conducted_by | FK → employees | HR person |
| interview_date | date | |
| reason_for_leaving | enum | better_opportunity, salary, work_environment, personal, relocation, career_change, management, other |
| overall_satisfaction | int | 1-5 |
| would_recommend | boolean | |
| feedback | text | nullable, detailed feedback |
| improvements | text | nullable, suggestions for company |
| created_at | timestamp | |
| updated_at | timestamp | |

### `final_settlements` — Final payment calculation

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| resignation_request_id | FK → resignation_requests | nullable |
| prorated_salary | decimal(10,2) | Days worked in final month |
| leave_encashment | decimal(10,2) | Unused annual leave × daily rate |
| leave_encashment_days | decimal(5,1) | Number of days being encashed |
| other_earnings | decimal(10,2) | default 0, bonus/allowances owed |
| other_deductions | decimal(10,2) | default 0, loans/advances to recover |
| epf_employee | decimal(8,2) | Final month EPF (employee) |
| epf_employer | decimal(8,2) | Final month EPF (employer) |
| socso_employee | decimal(8,2) | |
| eis_employee | decimal(8,2) | |
| pcb_amount | decimal(8,2) | |
| total_gross | decimal(10,2) | |
| total_deductions | decimal(10,2) | |
| net_amount | decimal(10,2) | Final payout |
| status | enum | draft, calculated, approved, paid |
| notes | text | nullable |
| pdf_path | string | nullable |
| approved_by | FK → users | nullable |
| paid_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `letter_templates` — Shared templates for all letter types

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Verbal Warning Letter", "Show Cause Letter" |
| type | enum | verbal_warning, first_written, second_written, show_cause, termination, offer_letter, resignation_acceptance |
| content | text | HTML template with {{placeholders}} |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Available placeholders:**
- `{{employee_name}}`, `{{employee_id}}`, `{{position}}`, `{{department}}`
- `{{incident_date}}`, `{{issued_date}}`, `{{reason}}`
- `{{response_deadline}}`, `{{company_name}}`
- `{{offered_salary}}`, `{{start_date}}` (for offer letters)

---

## Screens

### Admin Screens (11)

1. **Disciplinary Dashboard** — Stats (active cases, warnings this month, pending responses), recent actions timeline, cases by type pie chart
2. **Disciplinary Records** — Table of all actions, filterable by type/employee/status/date range
3. **Disciplinary Detail** — Full case view: escalation chain (verbal → written → show cause), employee response, inquiry details, outcome, generate/download letter PDF
4. **Create Disciplinary Action** — Form: select employee, type, reason, incident date, link to previous action
5. **Inquiry Management** — Schedule domestic inquiry: date, panel members, location. Record minutes, findings, decision
6. **Resignation Requests** — Pending resignations table, notice period info, approve/reject
7. **Resignation Detail** — Employee info, notice period calculation, exit checklist progress, final settlement link
8. **Exit Checklists** — All departing employees with checklist completion percentage
9. **Exit Interviews** — Completed interviews, analytics (reasons for leaving pie chart, satisfaction trend)
10. **Final Settlements** — Calculate, review breakdown, approve, mark as paid
11. **Letter Templates** — CRUD for all letter templates (shared with Module 7), HTML editor with placeholder insertion

### Employee Self-Service (2)

12. **My Disciplinary** — View any warnings/actions issued to me, submit response to show cause
13. **Submit Resignation** — Resignation form, notice period preview, track status

---

## API Endpoints (~35)

### Disciplinary Actions
```
GET    /api/hr/disciplinary/dashboard           — Dashboard stats
GET    /api/hr/disciplinary/actions             — List all (filterable)
POST   /api/hr/disciplinary/actions             — Create action
GET    /api/hr/disciplinary/actions/{id}        — Detail with chain + inquiry
PUT    /api/hr/disciplinary/actions/{id}        — Update
PATCH  /api/hr/disciplinary/actions/{id}/issue  — Issue (change to issued status)
PATCH  /api/hr/disciplinary/actions/{id}/close  — Close case
GET    /api/hr/disciplinary/actions/{id}/pdf    — Generate/download letter PDF
GET    /api/hr/disciplinary/employee/{employeeId} — All actions for employee (chain view)
```

### Inquiries
```
POST   /api/hr/disciplinary/inquiries           — Schedule inquiry
GET    /api/hr/disciplinary/inquiries/{id}      — Detail
PUT    /api/hr/disciplinary/inquiries/{id}      — Update (minutes, findings)
PATCH  /api/hr/disciplinary/inquiries/{id}/complete — Complete with decision
```

### Resignations
```
GET    /api/hr/offboarding/resignations         — List all
POST   /api/hr/offboarding/resignations         — Submit (admin on behalf)
GET    /api/hr/offboarding/resignations/{id}    — Detail
PATCH  /api/hr/offboarding/resignations/{id}/approve — Approve
PATCH  /api/hr/offboarding/resignations/{id}/reject  — Reject
PATCH  /api/hr/offboarding/resignations/{id}/complete — Mark completed
```

### Exit Checklists
```
GET    /api/hr/offboarding/checklists           — List all
POST   /api/hr/offboarding/checklists/{employeeId} — Create checklist for employee
GET    /api/hr/offboarding/checklists/{id}      — Detail with items
PATCH  /api/hr/offboarding/checklists/{id}/items/{itemId} — Update item status
```

### Exit Interviews
```
GET    /api/hr/offboarding/exit-interviews      — List all
POST   /api/hr/offboarding/exit-interviews      — Create
GET    /api/hr/offboarding/exit-interviews/{id} — Detail
PUT    /api/hr/offboarding/exit-interviews/{id} — Update
GET    /api/hr/offboarding/exit-interviews/analytics — Reasons/satisfaction data
```

### Final Settlements
```
GET    /api/hr/offboarding/settlements          — List all
POST   /api/hr/offboarding/settlements/{employeeId}/calculate — Calculate
GET    /api/hr/offboarding/settlements/{id}     — Detail
PUT    /api/hr/offboarding/settlements/{id}     — Adjust amounts
PATCH  /api/hr/offboarding/settlements/{id}/approve — Approve
PATCH  /api/hr/offboarding/settlements/{id}/paid    — Mark as paid
GET    /api/hr/offboarding/settlements/{id}/pdf     — Download PDF
```

### Letter Templates
```
GET    /api/hr/letter-templates                 — List all
POST   /api/hr/letter-templates                 — Create
PUT    /api/hr/letter-templates/{id}            — Update
DELETE /api/hr/letter-templates/{id}            — Delete
```

### Employee Self-Service
```
GET    /api/hr/me/disciplinary                  — My disciplinary records
POST   /api/hr/me/disciplinary/{id}/respond     — Submit response to show cause
POST   /api/hr/me/resignation                   — Submit resignation
GET    /api/hr/me/resignation                   — View my resignation status
```

---

## Workflows

### Disciplinary Escalation Flow

```
Incident occurs → HR/manager documents it
  → Verbal warning (DA-202603-0001) → status: issued
    → If repeats → 1st written warning (links to verbal via previous_action_id)
      → If repeats → 2nd written warning (links to 1st written)
        → Show cause letter → status: pending_response
          → response_required = true, response_deadline set
            → Employee responds within deadline → status: responded
              → HR reviews response:
                → Satisfactory → status: closed (no further action)
                → Not satisfactory → Schedule domestic inquiry
                  → Inquiry panel hearing
                    → Decision: guilty → Penalty (suspension or termination)
                    → Decision: not guilty → status: closed
                    → Decision: partially guilty → reduced penalty
```

### Resignation & Offboarding Flow

```
Employee submits resignation
  → System calculates notice period:
    - Full-time < 2 years: 30 days
    - Full-time 2-5 years: 60 days
    - Full-time > 5 years: 90 days
    - Contract: per contract terms
    - Probation: 14 days
  → Manager/HR reviews → approve (with optional adjusted last date)
    → Auto-create exit checklist (from default items)
    → Asset return items auto-populated from Module 6 (assigned assets)
      → HR tracks checklist progress:
        - Asset return (laptop, phone, access card)
        - System access revocation (email, VPN, HR system)
        - Documentation (handover, knowledge transfer)
        - Clearance (department, finance, HR)
      → Conduct exit interview
      → Calculate final settlement:
        1. Prorated salary = (basic / days_in_month) × days_worked
        2. Leave encashment = unused_AL_days × (basic / 26)
        3. Other earnings (unpaid bonus, etc.)
        4. Statutory deductions (EPF/SOCSO/EIS/PCB on final amount)
        5. Other deductions (outstanding loans, advances)
        6. Net = Total earnings - Total deductions
      → Approve final settlement → Mark as paid
      → Update employee status to "resigned"
      → Offboarding complete
```

### Final Settlement Calculation (Auto)

```
Input: employee_id, final_last_date

1. Get employee's current salary (basic + allowances from employee_salaries)
2. Calculate days worked in final month
3. Prorated salary = (total_monthly_earnings / days_in_month) × days_worked

4. Get unused annual leave balance from leave_balances (Module 3)
5. Leave encashment = unused_days × (basic_salary / unpaid_leave_divisor)

6. Sum other earnings/deductions (manual input)

7. Calculate statutory using StatutoryCalculationService (Module 5):
   - EPF on prorated earnings
   - SOCSO on prorated earnings
   - EIS on prorated earnings
   - PCB on prorated earnings

8. Total gross = prorated + encashment + other earnings
9. Total deductions = EPF_EE + SOCSO_EE + EIS_EE + PCB + other deductions
10. Net = Total gross - Total deductions
```

---

## Integration Points

| Integration | Direction | Details |
|-------------|-----------|---------|
| Module 1 (Employee) | Read/Write | Get employee info, update status to resigned/terminated |
| Module 3 (Leave) | Read | Get unused leave balance for encashment calculation |
| Module 5 (Payroll) | Read | Use StatutoryCalculationService for final settlement |
| Module 6 (Assets) | Read | Auto-populate asset return items from assigned assets |
| Module 7 (Recruitment) | Shared | letter_templates table shared for offer letters |
| Module 8 (Performance) | Read | PIP outcome may trigger disciplinary action |

---

## Future Enhancements (Not in Initial Build)

- Automated email notifications for deadlines (response deadline, hearing date)
- Digital signature on letters (employee acknowledgment)
- Rehire eligibility tracking (blacklist management)
- Notice period buy-out calculation
- Separation certificate generation
- Turnover analytics dashboard (trends, cost of turnover)
