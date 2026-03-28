# Module 8: Performance Management — Detailed Design

**Date:** 2026-03-28
**Status:** Approved
**Parent Plan:** [HR System Master Plan](2026-03-26-hr-system-master-plan.md)
**Phase:** 4 (Growth)
**Dependencies:** Module 1 (Employee), Module 2 (Departments & Positions)

---

## Key Decisions

- **Configurable review cycles** — HR can set monthly, quarterly, semi-annual, or annual review periods
- **Manager evaluates** — Direct manager scores KPIs, employee submits self-assessment
- **KPI templates** — Reusable KPIs per position/department, plus custom KPIs per review
- **Rating scale:** 1-5 with configurable labels (Unsatisfactory → Outstanding)
- **PIP tracking** — Formal Performance Improvement Plans with goals, timeline, check-ins, and outcomes
- **Weight-based scoring** — KPIs have percentage weights that sum to 100%

---

## Data Models

### `review_cycles` — Review period configuration

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| name | string | e.g., "Q1 2026 Review", "Annual 2026" |
| type | enum | monthly, quarterly, semi_annual, annual |
| start_date | date | Review period start |
| end_date | date | Review period end |
| submission_deadline | date | When reviews must be completed |
| status | enum | draft, active, in_review, completed, cancelled |
| description | text | nullable |
| created_by | FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `kpi_templates` — Reusable KPI definitions

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| position_id | FK → positions | nullable (null = general KPI) |
| department_id | FK → departments | nullable |
| title | string | e.g., "Customer satisfaction score" |
| description | text | nullable |
| target | string | e.g., "90% satisfaction", "RM500k revenue" |
| weight | decimal(5,2) | Percentage weight |
| category | enum | quantitative, qualitative, behavioral |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

### `performance_reviews` — Individual employee reviews

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| review_cycle_id | FK → review_cycles | |
| employee_id | FK → employees | |
| reviewer_id | FK → employees | Manager/reviewer |
| status | enum | draft, self_assessment, manager_review, completed |
| self_assessment_notes | text | nullable |
| manager_notes | text | nullable, overall feedback |
| overall_rating | decimal(3,1) | nullable, weighted average from KPI scores |
| rating_label | string | nullable, e.g., "Exceeds Expectations" |
| employee_acknowledged | boolean | default false |
| acknowledged_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique:** review_cycle_id + employee_id

### `review_kpis` — KPI assignments for a specific review

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| performance_review_id | FK → performance_reviews | |
| kpi_template_id | FK → kpi_templates | nullable (custom KPIs have no template) |
| title | string | Copied from template or custom |
| target | string | |
| weight | decimal(5,2) | |
| self_score | int | nullable, 1-5, employee self-rating |
| self_comments | text | nullable |
| manager_score | int | nullable, 1-5, manager rating |
| manager_comments | text | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `rating_scales` — Configurable rating labels

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| score | int | 1, 2, 3, 4, 5 |
| label | string | e.g., "Needs Improvement" |
| description | text | nullable |
| color | string | Hex color for display |
| created_at | timestamp | |
| updated_at | timestamp | |

**Default values:**

| Score | Label | Color |
|-------|-------|-------|
| 1 | Unsatisfactory | #EF4444 (red) |
| 2 | Needs Improvement | #F97316 (orange) |
| 3 | Meets Expectations | #EAB308 (yellow) |
| 4 | Exceeds Expectations | #22C55E (green) |
| 5 | Outstanding | #3B82F6 (blue) |

### `performance_improvement_plans` — PIP tracking

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| employee_id | FK → employees | |
| initiated_by | FK → employees | Manager who started PIP |
| performance_review_id | FK → performance_reviews | nullable, linked review |
| reason | text | Why PIP was initiated |
| start_date | date | |
| end_date | date | PIP duration end |
| status | enum | active, extended, completed_improved, completed_not_improved, cancelled |
| outcome_notes | text | nullable |
| completed_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

### `pip_goals` — Goals within a PIP

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | |
| pip_id | FK → performance_improvement_plans | |
| title | string | Specific improvement goal |
| description | text | nullable |
| target_date | date | |
| status | enum | pending, in_progress, achieved, not_achieved |
| check_in_notes | text | nullable, progress notes |
| checked_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Screens

### Admin Screens (8)

1. **Performance Dashboard** — Active cycles count, completion rate progress bar, rating distribution bar chart, department average ratings
2. **Review Cycles** — CRUD table, activate/close/complete actions, employee count per cycle
3. **Review Cycle Detail** — Employee list in this cycle, status per employee, bulk auto-create reviews
4. **KPI Templates** — CRUD table, filterable by position/department/category
5. **Employee Review Detail** — Full review form: KPI table with self + manager scores, self-assessment text, manager notes, overall rating calculation, acknowledge button
6. **PIP Management** — Active PIPs table, status badges, filter by status
7. **PIP Detail** — Goals list with progress, check-in notes, timeline, extend/close actions
8. **Rating Scale Config** — Edit rating labels, descriptions, and colors

### Employee Self-Service (2)

9. **My Reviews** — List of my reviews, current cycle self-assessment form, view completed reviews with ratings
10. **My PIP** — If on active PIP: view goals, progress, timeline

---

## API Endpoints (~28)

### Review Cycles
```
GET    /api/hr/performance/dashboard           — Dashboard stats
GET    /api/hr/performance/cycles              — List cycles
POST   /api/hr/performance/cycles              — Create cycle
GET    /api/hr/performance/cycles/{id}         — Detail with reviews
PUT    /api/hr/performance/cycles/{id}         — Update
PATCH  /api/hr/performance/cycles/{id}/activate — Activate + auto-create reviews
PATCH  /api/hr/performance/cycles/{id}/complete — Mark completed
DELETE /api/hr/performance/cycles/{id}         — Delete (draft only)
```

### KPI Templates
```
GET    /api/hr/performance/kpis               — List templates
POST   /api/hr/performance/kpis               — Create
PUT    /api/hr/performance/kpis/{id}          — Update
DELETE /api/hr/performance/kpis/{id}          — Delete
```

### Performance Reviews
```
GET    /api/hr/performance/reviews             — List all (filterable)
GET    /api/hr/performance/reviews/{id}        — Detail with KPIs
POST   /api/hr/performance/reviews/{id}/kpis   — Add KPI to review
PUT    /api/hr/performance/reviews/{id}/self-assessment — Submit self-assessment
PUT    /api/hr/performance/reviews/{id}/manager-review  — Submit manager scores
PATCH  /api/hr/performance/reviews/{id}/complete        — Finalize review
PATCH  /api/hr/performance/reviews/{id}/acknowledge     — Employee acknowledges
```

### PIP
```
GET    /api/hr/performance/pips               — List all PIPs
POST   /api/hr/performance/pips               — Create PIP
GET    /api/hr/performance/pips/{id}          — Detail with goals
PUT    /api/hr/performance/pips/{id}          — Update
PATCH  /api/hr/performance/pips/{id}/extend   — Extend timeline
PATCH  /api/hr/performance/pips/{id}/complete — Complete with outcome
POST   /api/hr/performance/pips/{id}/goals    — Add goal
PUT    /api/hr/performance/pips/{id}/goals/{goalId} — Update goal progress
```

### Rating Scales
```
GET    /api/hr/performance/rating-scales      — List all
PUT    /api/hr/performance/rating-scales      — Bulk update
```

### Employee Self-Service
```
GET    /api/hr/me/reviews                     — My reviews
GET    /api/hr/me/reviews/{id}                — My review detail
PUT    /api/hr/me/reviews/{id}/self-assessment — Submit self-assessment
GET    /api/hr/me/pip                         — My active PIP (if any)
```

---

## Workflows

### Review Cycle Flow

```
HR creates review cycle → status: draft
  → HR activates cycle
    → System auto-creates performance_reviews for all active employees
    → Auto-assigns KPIs from templates (matching position/department)
  → Employees submit self-assessment → review status: self_assessment
    → Manager reviews + scores each KPI → review status: manager_review
      → Manager submits overall rating → review status: completed
        → System calculates weighted average: Σ(manager_score × weight) / Σ(weight)
        → Maps to rating_label from rating_scales
          → Employee acknowledges review
```

### PIP Flow

```
Manager identifies underperformer (from review or observation)
  → Creates PIP with reason, start/end date
  → Adds specific improvement goals with target dates
    → Regular check-ins: update goal progress + notes
      → At end date:
        → Goals achieved → status: completed_improved → back to normal
        → Goals not achieved → status: completed_not_improved
          → May lead to disciplinary action (Module 10)
        → Need more time → status: extended → new end date
```

### Overall Rating Calculation

```
For each review_kpi:
  weighted_score = manager_score × (weight / 100)

overall_rating = Σ(weighted_score) / Σ(weight/100)
  (effectively the weighted average of manager scores, on a 1-5 scale)

rating_label = lookup from rating_scales where score = round(overall_rating)
```

---

## Integration Points

| Integration | Direction | Details |
|-------------|-----------|---------|
| Module 1 (Employee) | Read | Employee info, department, position |
| Module 2 (Positions) | Read | KPI templates linked to positions |
| Module 10 (Disciplinary) | Output | PIP outcome may trigger disciplinary action |

---

## Future Enhancements (Not in Initial Build)

- 360-degree feedback (peer + subordinate reviews)
- Goal setting module (OKRs)
- Performance-linked salary increment recommendations
- Automated review reminders via notification
- Performance comparison across departments
