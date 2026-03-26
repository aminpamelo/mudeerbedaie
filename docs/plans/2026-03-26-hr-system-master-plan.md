# HR System — Master Plan

**Date:** 2026-03-26
**Status:** Approved
**Scope:** Internal company staff (excludes teachers, students, live hosts)
**Country:** Malaysia (Employment Act 1955 compliance)

---

## Architecture Overview

```
+---------------------------------------------------+
|            HR Dashboard (React SPA)                |
|          Shadcn/ui + Tailwind CSS v4               |
|     Route: /hr/*  (separate from /admin/*)         |
+---------------------------------------------------+
|               Laravel API Layer                    |
|          /api/hr/* (versioned endpoints)           |
|       Auth: Sanctum (token-based for SPA)          |
+---------------------------------------------------+
|             Laravel Backend                        |
|     Models, Services, Policies, Events             |
|     Shared DB with existing Mudeer system          |
+---------------------------------------------------+
```

### Access Control
- Roles: `hr_admin`, `hr_manager`, or existing `admin` with HR permissions
- Permission-based access to each module
- Self-service portal for employees (view own profile, submit leave, view payslips)

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | React 19 + Shadcn/ui + Tailwind CSS v4 |
| Routing | React Router |
| State | TanStack Query (API cache) + Zustand (UI state) |
| API | Laravel Sanctum + RESTful JSON API |
| Backend | Laravel 12 services, form requests, policies |
| Database | Shared with existing Mudeer system |
| PDF | Laravel DomPDF (payslips, letters) |
| Charts | Recharts (dashboard analytics) |

---

## Module List (10 Modules, 5 Phases)

| # | Module | Phase | Priority | Description |
|---|--------|-------|----------|-------------|
| 1 | Employee Directory & Profiles | 1 | Critical | Employee records, personal info, documents |
| 2 | Organization Structure | 1 | Critical | Departments, positions, reporting lines, org chart |
| 3 | Leave Management | 2 | High | Leave types, requests, approvals, balances |
| 4 | Attendance & Time Tracking | 2 | High | Clock in/out, schedules, overtime |
| 5 | Payroll & Compensation | 3 | High | Salary, statutory deductions (EPF/SOCSO/EIS/PCB), payslips |
| 6 | Claims & Benefits | 3 | Medium | Expense claims, benefits tracking |
| 7 | Recruitment & Onboarding | 4 | Medium | Job posts, applicants, interview, onboarding checklist |
| 8 | Performance Management | 4 | Medium | KPIs, reviews, appraisals |
| 9 | Training & Development | 5 | Low | Training records, certifications |
| 10 | Disciplinary & Offboarding | 5 | Low | Warnings, termination, exit process |

---

## Phase 1 — Foundation

### Module 1: Employee Directory & Profiles

**Employee Profile:**
- Personal info: name, IC number, DOB, gender, marital status, address, phone, email
- Employment details: employee ID, join date, employment type (full-time, part-time, contract), probation end date
- Bank details: bank name, account number (encrypted)
- Emergency contacts: name, relationship, phone
- Profile photo
- Employee status: active, probation, resigned, terminated

**Document Management:**
- Upload & store: IC copy, offer letter, contracts, certificates
- Document categories with expiry tracking

**Employee List:**
- Search by name, IC, employee ID, department
- Filter by department, position, status, employment type
- Export to CSV/Excel

**Data Model:**
- `Employee` model linked to `User` via `user_id`
- `EmployeeDocument` model for file uploads
- `EmployeeEmergencyContact` model

### Module 2: Organization Structure

**Departments:**
- Department CRUD (name, code, description)
- Department head assignment
- Parent-child departments (tree structure)
- Department headcount summary

**Positions/Designations:**
- Position CRUD (title, level, department)
- Position hierarchy levels
- Salary grade/range per position (optional)

**Reporting Lines:**
- Employee → reports to → Manager
- Org chart visualization (React tree component)
- Department org view

---

## Phase 2 — Day-to-Day Operations

### Module 3: Leave Management

**Leave Types (Malaysian):**
- Annual Leave (8/12/16 days based on service years per Employment Act 1955)
- Medical Leave (14/18/22 days based on service years)
- Hospitalization Leave (60 days)
- Maternity Leave (98 days)
- Paternity Leave (7 days)
- Compassionate Leave
- Replacement Leave
- Unpaid Leave

**Features:**
- Leave entitlement rules based on years of service
- Leave request form: date range, type, reason, attachment (MC slip)
- Approval workflow: employee -> manager -> HR
- Leave balance dashboard
- Team leave calendar
- Leave reports (by department, by type, by period)
- Carry forward & forfeiture rules (configurable)
- Pro-rated entitlement for mid-year joiners

**Data Model:**
- `LeaveType` — leave type configuration
- `LeaveEntitlement` — entitlement rules per leave type
- `LeaveBalance` — employee leave balances per year
- `LeaveRequest` — leave applications
- `LeaveApproval` — approval records

### Module 4: Attendance & Time Tracking

**Features:**
- Web-based clock in/out (with IP/location tracking optional)
- Work schedules: office hours, flexible, shifts
- Late arrival / early departure tracking
- Overtime request & approval
- Monthly attendance summary per employee
- Attendance reports & analytics

**Data Model:**
- `WorkSchedule` — schedule templates
- `EmployeeSchedule` — schedule assignment per employee
- `AttendanceLog` — daily clock in/out records
- `OvertimeRequest` — overtime applications

---

## Phase 3 — Payroll

### Module 5: Payroll & Compensation

**Salary Structure:**
- Basic salary
- Fixed allowances (housing, transport, phone, etc.)
- Variable allowances (commission, bonus)

**Malaysian Statutory Deductions:**
- EPF (KWSP): employee contribution (11%) + employer contribution (12%/13%)
- SOCSO (PERKESO): employment injury + invalidity scheme
- EIS (SIP): employment insurance system (0.2% each)
- PCB/MTD: monthly tax deduction (based on LHDN tables)

**Payroll Processing:**
- Monthly payroll run
- Overtime pay calculation
- Unpaid leave deductions
- Ad-hoc additions/deductions
- Payslip generation (PDF)
- Salary revision history with effective dates

**Reporting:**
- Bank payment file generation (for bulk transfer)
- Monthly payroll summary
- Yearly payroll report
- EA Form (Borang EA) generation for annual tax

**Data Model:**
- `SalaryStructure` — salary components definition
- `EmployeeSalary` — employee salary details with history
- `PayrollRun` — monthly payroll batch
- `PayrollItem` — individual payroll line items per employee
- `Payslip` — generated payslip records (reuse/extend existing)
- `StatutoryRate` — EPF/SOCSO/EIS rate tables

### Module 6: Claims & Benefits

**Claim Types:**
- Medical, transport, parking, meals, travel, phone, internet, etc.
- Configurable claim limits (monthly/yearly caps per type)

**Features:**
- Claim submission with receipt upload
- Approval workflow (employee -> manager -> HR/Finance)
- Claim status tracking
- Monthly claim reports
- Benefits assignment per employee (insurance, parking pass, etc.)

**Data Model:**
- `ClaimType` — claim category configuration
- `ClaimRequest` — claim submissions
- `ClaimItem` — individual items within a claim
- `EmployeeBenefit` — assigned benefits

---

## Phase 4 — Growth

### Module 7: Recruitment & Onboarding

**Recruitment:**
- Job posting (title, description, department, requirements)
- Applicant tracking pipeline: applied -> screening -> interview -> offer -> hired/rejected
- Interview scheduling
- Offer letter generation (PDF from template)

**Onboarding:**
- Onboarding checklist templates (per department)
- Checklist assignment to new hires
- Task tracking (IT setup, access cards, orientation, etc.)
- Probation tracking & confirmation reminder

**Data Model:**
- `JobPosting` — open positions
- `Applicant` — candidates
- `ApplicantStage` — pipeline tracking
- `Interview` — scheduled interviews
- `OnboardingChecklist` — checklist templates
- `OnboardingTask` — individual tasks

### Module 8: Performance Management

**Features:**
- KPI setting per position/employee
- Self-assessment form
- Manager evaluation form
- Review cycles (quarterly, semi-annual, annual)
- Performance scores & ratings
- Performance history timeline
- Performance Improvement Plans (PIP)

**Data Model:**
- `ReviewCycle` — review period configuration
- `KPI` — key performance indicators
- `PerformanceReview` — review records
- `ReviewScore` — individual KPI scores

---

## Phase 5 — Compliance

### Module 9: Training & Development

**Features:**
- Training records (internal/external)
- Certification tracking with expiry alerts
- Training request & approval
- Training calendar
- Training cost tracking & budget

**Data Model:**
- `Training` — training programs
- `TrainingAttendance` — employee participation
- `EmployeeCertification` — certifications with expiry

### Module 10: Disciplinary & Offboarding

**Disciplinary:**
- Warning letters: verbal, first written, second written, show cause
- Disciplinary action records
- Inquiry/hearing records

**Offboarding:**
- Resignation workflow (notice period calculation, last day)
- Termination workflow
- Exit interview form
- Asset return checklist (laptop, access card, keys, etc.)
- System access revocation checklist
- Final payment calculation (prorated salary, leave encashment, etc.)

**Data Model:**
- `DisciplinaryAction` — warnings and actions
- `ResignationRequest` — resignation applications
- `ExitChecklist` — offboarding tasks
- `ExitInterview` — exit interview responses

---

## HR Dashboard Pages

### Main Navigation
1. **Dashboard** — Overview stats, quick actions, alerts
2. **Employees** — Directory, profiles, org chart
3. **Leave** — Requests, calendar, balances, reports
4. **Attendance** — Clock in/out, summaries, reports
5. **Payroll** — Runs, payslips, statutory, reports
6. **Claims** — Submissions, approvals, reports
7. **Recruitment** — Job posts, applicants, pipeline
8. **Performance** — Reviews, KPIs, cycles
9. **Training** — Programs, certifications
10. **Settings** — Leave types, departments, positions, statutory rates

### Employee Self-Service
- View own profile & update personal info
- Submit leave requests & view balances
- Clock in/out
- Submit expense claims
- View payslips
- View attendance records
