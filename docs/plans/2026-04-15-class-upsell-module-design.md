# Class Schedule Upsell Module — Design Document

**Date**: 2026-04-15
**Status**: Approved

## Overview

Add an upsell module to the class management system that allows admins to assign funnels and PICs (Person In Charge) to timetable slots. When sessions are generated from the timetable, they inherit the upsell config. The PIC shares the funnel link during class, and conversions are tracked back to the specific session for reporting.

## Architecture: Approach A — New `class_timetable_upsells` Table

Chosen over alternatives (extending class_sessions directly, or JSON config) for clean separation, referential integrity, and easy reporting.

## Data Model

### New Table: `class_timetable_upsells`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| class_timetable_id | FK → class_timetables | Which timetable |
| day_of_week | string | Day: "monday", "tuesday", etc. |
| time_slot | string | Time: "09:00", "14:00", etc. |
| funnel_id | FK → funnels | Which funnel to share |
| pic_user_id | FK → users | Staff member in charge |
| is_active | boolean (default: true) | Toggle on/off |
| notes | text, nullable | Admin notes for PIC |
| timestamps | | created_at, updated_at |

**Unique constraint**: `(class_timetable_id, day_of_week, time_slot)`

### Extended: `class_sessions` table (2 new nullable columns)

| Column | Type | Description |
|--------|------|-------------|
| upsell_funnel_id | FK → funnels, nullable | Inherited from timetable upsell |
| upsell_pic_user_id | FK → users, nullable | Inherited PIC |

### Extended: `funnel_orders` table (1 new nullable column)

| Column | Type | Description |
|--------|------|-------------|
| class_session_id | FK → class_sessions, nullable | Links conversion back to session |

### New Model: `ClassTimetableUpsell`

**Relationships:**
- `belongsTo(ClassTimetable)` — timetable
- `belongsTo(Funnel)` — funnel to share
- `belongsTo(User)` — PIC staff member

**ClassTimetable** gains:
- `hasMany(ClassTimetableUpsell)` — upsell configs

**ClassSession** gains:
- `belongsTo(Funnel, 'upsell_funnel_id')` — inherited funnel
- `belongsTo(User, 'upsell_pic_user_id')` — inherited PIC

**FunnelOrder** gains:
- `belongsTo(ClassSession, 'class_session_id')` — session that sourced the conversion

## UI Design

### Upsell Tab on Class Detail Page

Located as a new tab alongside Overview, Students, Timetable, Certificates, Payments, PIC, Notifikasi.

#### Section A: Upsell Configuration (top)

Table showing each timetable slot with its upsell config:

| Day | Time | Funnel | PIC | Status | Actions |
|-----|------|--------|-----|--------|---------|
| Monday | 09:00 AM | Seerah Bundle | Ahmad N. | Active | Edit / Delete |
| Wednesday | 09:00 AM | Seerah Bundle | Sarah M. | Active | Edit / Delete |
| Friday | 02:00 PM | — | — | None | + Add |

- **+ Add Upsell** button opens modal with: slot selector (day+time from timetable), funnel dropdown, PIC dropdown, notes field
- Toggle active/inactive per slot
- Empty state: "No Timetable Configured" if class has no timetable

#### Section B: Conversion Report (bottom)

**Summary cards:**
- Total Offers (sessions with upsell)
- Conversions (funnel orders linked to sessions)
- Revenue (sum of funnel_orders.funnel_revenue)
- Conversion Rate (conversions / offers)

**Detail table:**

| Session | Date | PIC | Funnel | Orders | Revenue |
|---------|------|-----|--------|--------|---------|
| Mon 9AM | Apr 7 | Ahmad N. | Seerah | 3 | RM 900 |
| Wed 9AM | Apr 9 | Sarah M. | Seerah | 2 | RM 600 |

- Filterable by date range, PIC, funnel

## End-to-End Workflow

1. **Admin configures upsell** — On Upsell tab, selects timetable slot, assigns funnel + PIC
2. **Sessions inherit config** — `ClassTimetable::generateSessions()` populates `upsell_funnel_id` + `upsell_pic_user_id` on new sessions. "Sync to Sessions" action backfills existing sessions.
3. **PIC sees assignment** — Session detail view shows funnel link. PIC copies URL to share with students.
4. **Funnel URL includes tracking** — `?ref=session-{session_id}` parameter for attribution
5. **Student purchases via funnel** — `FunnelOrder` created with `class_session_id` from ref param
6. **Reporting** — Upsell tab shows conversion stats per session, PIC, and funnel

## Technical Notes

- Migration must support both MySQL and SQLite (dual-driver pattern per CLAUDE.md)
- PIC dropdown includes users with roles: admin, class_admin, sales, employee
- Funnel dropdown shows only published funnels
- Session generation hook: modify `ClassTimetable::generateSessions()` to check for matching upsell config
- Funnel checkout: modify funnel order creation to capture `ref` parameter as `class_session_id`
