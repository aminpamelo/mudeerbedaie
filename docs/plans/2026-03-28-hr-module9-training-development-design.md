# Module 9: Training & Development — Detailed Design

**Date:** 2026-03-28
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 5 (Compliance)
**Dependencies:** Module 1 (Employee), Module 2 (Departments)

---

## Key Decisions

- **Full training management** — programs, enrollment, attendance, feedback, budget
- **HR assigns only** — no employee self-request flow (simpler: HR/managers decide who attends)
- **Certifications** with expiry date tracking and automated alerts (30/60/90 days)
- **Budget tracking** per department per year
- **Training types:** Internal (in-house) and external (third-party providers)
- **Cost tracking** per program with receipt uploads

---

## Data Models

### `training_programs` — Training events/courses

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| title | string | e.g., "Fire Safety Training" |
| description | text | nullable |
| type | enum | internal, external |
| category | enum | mandatory, technical, soft_skill, compliance, other |
| provider | string | nullable, external provider name |
| location | string | nullable |
| start_date | date | |
| end_date | date | |
| start_time | time | nullable |
| end_time | time | nullable |
| max_participants | int | nullable |
| cost_per_person | decimal(10,2) | nullable |
| total_budget | decimal(10,2) | nullable |
| status | enum | planned, ongoing, completed, cancelled |
| created_by | FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `training_enrollments` — Employee assigned to training

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| training_program_id | FK → training_programs | |
| employee_id | FK → employees | |
| enrolled_by | FK → users | HR/manager who assigned |
| status | enum | enrolled, attended, absent, cancelled |
| attendance_confirmed_at | timestamp | nullable |
| feedback | text | nullable, post-training feedback |
| feedback_rating | int | nullable, 1-5 |
| certificate_path | string | nullable, uploaded certificate |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique:** training_program_id + employee_id

### `training_costs` — Track actual costs per training

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| training_program_id | FK → training_programs | |
| description | string | e.g., "Venue rental", "Trainer fee" |
| amount | decimal(10,2) | |
| receipt_path | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `certifications` — Certification types

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "ISO 9001 Auditor", "First Aid" |
| issuing_body | string | nullable, e.g., "SIRIM", "Red Crescent" |
| description | text | nullable |
| validity_months | int | nullable, how long cert is valid |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `employee_certifications` — Certs held by employees

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| certification_id | FK → certifications | |
| certificate_number | string | nullable |
| issued_date | date | |
| expiry_date | date | nullable |
| certificate_path | string | nullable, uploaded cert file |
| status | enum | active, expired, revoked |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `training_budgets` — Annual department training budget

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| department_id | FK → departments | |
| year | int | |
| allocated_amount | decimal(10,2) | |
| spent_amount | decimal(10,2) | default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique:** department_id + year

---

## Screens

### Admin Screens (7)

1. **Training Dashboard** — Stats cards (upcoming trainings, completed this year, total spend, cert expiring soon), training calendar view, budget utilization by department bar chart
2. **Training Programs** — CRUD table, filterable by type/category/status/date range
3. **Training Detail** — Program info, enrolled employees with attendance status, cost breakdown, feedback summary
4. **Certifications** — CRUD table of certification types
5. **Employee Certifications** — All certs across employees, filter by employee/certification/status, expiry warning badges (red=expired, yellow=expiring soon, green=valid)
6. **Training Budget** — Budget allocation per department per year, utilization percentage, spent vs allocated chart
7. **Training Reports** — Training hours per employee, training hours per department, cost analysis, certification compliance rate

### Employee Self-Service (1)

8. **My Training** — Assigned trainings with attendance status, submit feedback after completion, my certifications with expiry dates

---

## API Endpoints (~25)

### Training Programs
```
GET    /api/hr/training/dashboard              — Dashboard stats
GET    /api/hr/training/programs               — List all (filterable)
POST   /api/hr/training/programs               — Create program
GET    /api/hr/training/programs/{id}          — Detail with enrollments + costs
PUT    /api/hr/training/programs/{id}          — Update
DELETE /api/hr/training/programs/{id}          — Delete (planned only)
PATCH  /api/hr/training/programs/{id}/complete — Mark completed
```

### Enrollments
```
GET    /api/hr/training/enrollments            — List all enrollments
POST   /api/hr/training/programs/{id}/enroll   — Enroll employees (array of employee_ids)
PATCH  /api/hr/training/enrollments/{id}       — Update status (attended/absent)
DELETE /api/hr/training/enrollments/{id}       — Cancel enrollment
PUT    /api/hr/training/enrollments/{id}/feedback — Submit feedback
```

### Costs
```
GET    /api/hr/training/programs/{id}/costs    — List costs for program
POST   /api/hr/training/programs/{id}/costs    — Add cost
PUT    /api/hr/training/costs/{id}             — Update cost
DELETE /api/hr/training/costs/{id}             — Delete cost
```

### Certifications
```
GET    /api/hr/training/certifications         — List certification types
POST   /api/hr/training/certifications         — Create
PUT    /api/hr/training/certifications/{id}    — Update
DELETE /api/hr/training/certifications/{id}    — Delete
```

### Employee Certifications
```
GET    /api/hr/training/employee-certifications          — List all (filterable)
POST   /api/hr/training/employee-certifications          — Add cert to employee
PUT    /api/hr/training/employee-certifications/{id}     — Update
DELETE /api/hr/training/employee-certifications/{id}     — Remove
GET    /api/hr/training/employee-certifications/expiring — Certs expiring within N days
```

### Budget
```
GET    /api/hr/training/budgets                — List all (by year)
POST   /api/hr/training/budgets                — Set budget for dept/year
PUT    /api/hr/training/budgets/{id}           — Update budget
```

### Reports
```
GET    /api/hr/training/reports                — Training reports data
```

### Employee Self-Service
```
GET    /api/hr/me/training                     — My trainings + certifications
PUT    /api/hr/me/training/{enrollmentId}/feedback — Submit my feedback
```

---

## Scheduled Jobs

### Certification Expiry Check (Daily)

```
Check employee_certifications where:
  - status = 'active'
  - expiry_date is within 90 days from today

Actions:
  - 90 days: Flag as "expiring_soon" (informational)
  - 60 days: Send notification to HR
  - 30 days: Send urgent notification to HR + employee's manager
  - 0 days (expired): Auto-update status to "expired"
```

---

## Integration Points

| Integration | Direction | Details |
|-------------|-----------|---------|
| Module 1 (Employee) | Read | Employee info, department |
| Module 2 (Departments) | Read | Department for budget allocation |

---

## Future Enhancements (Not in Initial Build)

- Employee self-request for training (with approval workflow)
- E-learning platform integration
- Training effectiveness assessments
- Skills matrix per employee
- Mandatory training compliance tracking per department
- Training certificate auto-generation from completion
