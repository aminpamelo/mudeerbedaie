# Module 7: Recruitment & Onboarding — Detailed Design

**Date:** 2026-03-28
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 4 (Growth)
**Dependencies:** Module 1 (Employee), Module 2 (Departments & Positions)

---

## Key Decisions

- **Full ATS** with multi-stage pipeline (applied → screening → interview → assessment → offer → hired/rejected)
- **Public careers page** (`/careers`) + internal tracking — candidates can apply directly
- **Onboarding checklists** per department — HR ticks off tasks manually
- **Offer letter PDF** generation from templates (shared `letter_templates` table with Module 10)
- **Hired → Employee** conversion — when applicant is marked hired, auto-create employee record

---

## Data Models

### `job_postings` — Open positions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| title | string | Job title |
| department_id | FK → departments | |
| position_id | FK → positions | nullable |
| description | text | Full job description |
| requirements | text | Required qualifications |
| employment_type | enum | full_time, part_time, contract, intern |
| salary_range_min | decimal(10,2) | nullable |
| salary_range_max | decimal(10,2) | nullable |
| show_salary | boolean | Show salary on public page? |
| vacancies | int | Number of openings |
| status | enum | draft, open, closed, filled |
| published_at | timestamp | nullable, when made public |
| closing_date | date | nullable |
| created_by | FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `applicants` — Candidates

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| job_posting_id | FK → job_postings | |
| applicant_number | string (unique) | Auto-generated: APP-YYYYMM-0001 |
| full_name | string | |
| email | string | |
| phone | string | |
| ic_number | string | nullable |
| resume_path | string | Uploaded resume |
| cover_letter | text | nullable |
| source | enum | website, referral, jobstreet, linkedin, walk_in, other |
| current_stage | enum | applied, screening, interview, assessment, offer, hired, rejected, withdrawn |
| rating | int | nullable, 1-5 stars |
| notes | text | nullable, HR notes |
| applied_at | timestamp | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `applicant_stages` — Pipeline tracking history

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| applicant_id | FK → applicants | |
| stage | enum | applied, screening, interview, assessment, offer, hired, rejected, withdrawn |
| notes | text | nullable |
| changed_by | FK → users | |
| created_at | timestamp | When stage changed |

### `interviews` — Scheduled interviews

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| applicant_id | FK → applicants | |
| interviewer_id | FK → employees | |
| interview_date | date | |
| start_time | time | |
| end_time | time | |
| type | enum | phone, video, in_person |
| location | string | nullable |
| status | enum | scheduled, completed, cancelled, no_show |
| feedback | text | nullable, interviewer feedback |
| rating | int | nullable, 1-5 |
| created_at | timestamp | |
| updated_at | timestamp | |

### `offer_letters` — Generated offers

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| applicant_id | FK → applicants | |
| position_id | FK → positions | |
| offered_salary | decimal(10,2) | |
| start_date | date | Proposed start date |
| employment_type | enum | full_time, part_time, contract, intern |
| status | enum | draft, sent, accepted, rejected, expired |
| template_data | json | Placeholder values for PDF |
| pdf_path | string | nullable |
| sent_at | timestamp | nullable |
| responded_at | timestamp | nullable |
| created_by | FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `onboarding_templates` — Checklist templates per department

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "IT Department Onboarding" |
| department_id | FK → departments | nullable (null = default for all) |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `onboarding_template_items` — Tasks within a template

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| onboarding_template_id | FK → onboarding_templates | |
| title | string | e.g., "Setup laptop" |
| description | text | nullable |
| assigned_role | string | nullable, e.g., "IT", "HR", "Manager" |
| due_days | int | Days after start date |
| sort_order | int | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `onboarding_tasks` — Assigned tasks for a specific new hire

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | The new hire |
| template_item_id | FK → onboarding_template_items | nullable |
| title | string | |
| description | text | nullable |
| assigned_to | FK → employees | nullable, person responsible |
| due_date | date | |
| status | enum | pending, in_progress, completed, skipped |
| completed_at | timestamp | nullable |
| completed_by | FK → users | nullable |
| notes | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Screens

### Admin Screens (8)

1. **Recruitment Dashboard** — Stats cards (open positions, active applicants, hired this month, avg time-to-hire), pipeline funnel chart, recent activity
2. **Job Postings** — CRUD table, publish/close actions, vacancy count
3. **Job Posting Detail** — Description, requirements, applicant list for this job
4. **Applicants** — All applicants table + Kanban board view, filterable by job/stage/source/rating
5. **Applicant Detail** — Profile, stage history timeline, interviews, offer, notes, move stage actions
6. **Interviews** — Calendar view of scheduled interviews, schedule new
7. **Onboarding Dashboard** — New hires with checklist progress bars
8. **Onboarding Templates** — CRUD for checklist templates with items

### Public Page (1)

9. **Careers Page** (`/careers`) — Open positions list, company info, apply form with resume upload

### Employee Self-Service (1)

10. **My Onboarding** — New hire sees their assigned checklist with progress

---

## API Endpoints (~30)

### Job Postings
```
GET    /api/hr/recruitment/dashboard          — Dashboard stats
GET    /api/hr/recruitment/postings           — List all (admin, filterable)
POST   /api/hr/recruitment/postings           — Create job posting
GET    /api/hr/recruitment/postings/{id}      — Detail with applicants
PUT    /api/hr/recruitment/postings/{id}      — Update
DELETE /api/hr/recruitment/postings/{id}      — Delete (draft only)
PATCH  /api/hr/recruitment/postings/{id}/publish — Publish to careers page
PATCH  /api/hr/recruitment/postings/{id}/close   — Close posting
```

### Applicants
```
GET    /api/hr/recruitment/applicants         — List all (filterable)
POST   /api/hr/recruitment/applicants         — Add applicant manually
GET    /api/hr/recruitment/applicants/{id}    — Detail with stages/interviews
PUT    /api/hr/recruitment/applicants/{id}    — Update
PATCH  /api/hr/recruitment/applicants/{id}/stage — Move to next stage
POST   /api/hr/recruitment/applicants/{id}/hire  — Convert to employee
```

### Interviews
```
GET    /api/hr/recruitment/interviews         — List all (calendar data)
POST   /api/hr/recruitment/interviews         — Schedule interview
PUT    /api/hr/recruitment/interviews/{id}    — Update/reschedule
DELETE /api/hr/recruitment/interviews/{id}    — Cancel
PUT    /api/hr/recruitment/interviews/{id}/feedback — Submit feedback
```

### Offer Letters
```
POST   /api/hr/recruitment/offers             — Create offer
GET    /api/hr/recruitment/offers/{id}        — Detail
PUT    /api/hr/recruitment/offers/{id}        — Update draft
POST   /api/hr/recruitment/offers/{id}/send   — Mark as sent
PATCH  /api/hr/recruitment/offers/{id}/respond — Accept/reject
GET    /api/hr/recruitment/offers/{id}/pdf    — Download PDF
```

### Public Careers
```
GET    /api/careers                           — Public: list open positions
GET    /api/careers/{id}                      — Public: job detail
POST   /api/careers/{id}/apply                — Public: submit application
```

### Onboarding
```
GET    /api/hr/onboarding/dashboard           — Onboarding progress overview
GET    /api/hr/onboarding/templates           — List templates
POST   /api/hr/onboarding/templates           — Create template
PUT    /api/hr/onboarding/templates/{id}      — Update template + items
DELETE /api/hr/onboarding/templates/{id}      — Delete template
POST   /api/hr/onboarding/assign/{employeeId} — Assign checklist to new hire
GET    /api/hr/onboarding/tasks/{employeeId}  — Get employee's onboarding tasks
PATCH  /api/hr/onboarding/tasks/{taskId}      — Update task status
GET    /api/hr/me/onboarding                  — My onboarding tasks (employee)
```

---

## Workflows

### Recruitment Pipeline

```
HR creates job posting → status: draft
  → HR publishes → status: open, visible on /careers
    → Candidate applies (or HR adds manually) → stage: applied
      → HR screens resume → stage: screening
        → Schedule interview → stage: interview
          → Interviewer submits feedback + rating
            → Assessment/test (optional) → stage: assessment
              → HR creates offer letter → stage: offer
                → Generate PDF, mark sent
                  → Candidate accepts → stage: hired
                    → Auto-create employee record
                    → Auto-assign onboarding checklist
                  → Candidate rejects → stage: rejected
```

### Onboarding

```
Employee hired (from recruitment or manually)
  → System finds matching onboarding template (by department, or default)
  → Creates onboarding_tasks from template items
  → HR/assigned people complete tasks
  → Employee sees progress on self-service
  → All tasks completed → onboarding complete
```

---

## Integration Points

| Integration | Direction | Details |
|-------------|-----------|---------|
| Module 1 (Employee) | Write | Auto-create employee record when applicant hired |
| Module 2 (Departments) | Read | Department/position for job postings |
| Module 10 (Letters) | Shared | `letter_templates` table for offer letters |

---

## Future Enhancements (Not in Initial Build)

- Email notifications to applicants (stage updates, interview reminders)
- Applicant self-service portal to check status
- Bulk import applicants from CSV
- Interview scorecards with weighted criteria
- Job board API integrations (JobStreet, LinkedIn)
