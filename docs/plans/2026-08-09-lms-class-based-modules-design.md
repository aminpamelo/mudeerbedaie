# LMS Class-Based Modules Design

**Date:** 2026-08-09
**Status:** Approved
**Goal:** Student engagement + business growth for Islamic studies LMS

## Core Principle

Everything attaches to the **Class**, not the Course. The Course is a billing/product wrapper. The Class is where learning happens.

## Current State

The system has a solid foundation (~60-70% of a class-based LMS):
- Course & class management, enrollment with Stripe subscriptions
- Session scheduling from timetables, attendance tracking
- Certificate issuance (visual builder + PDF + email/WhatsApp delivery)
- Notification system (email + WhatsApp reminders/follow-ups)
- Teacher payslips & commissions
- Multi-role access (student, teacher, admin, class_admin)
- Storefront with course listing & detail pages

**Gaps:** No content library, no progress tracking, no announcements, no gamification, no discussion, no homework, storefront is course-centric (not class-centric).

---

## Phase 1 — Foundation (Engagement Core)

### Module 1: Class Storefront Revamp

**Problem:** Storefront sells Courses but students join Classes. The public page doesn't show teacher, schedule, capacity, or syllabus.

**Design:**
- `/courses` stays as a category discovery page listing Course types (Tajwid, Fiqh, etc.)
- `/course/{slug}` becomes a **Class picker** page:
  - Course overview at top (description, thumbnail)
  - Grid of available Classes under this course, each card showing:
    - Teacher name + avatar
    - Schedule (e.g. "Isnin & Rabu, 8:30 PM")
    - Class type (Individual / Group)
    - Capacity (e.g. "12/20 pelajar")
    - Monthly fee
    - Syllabus summary (first 3 topics)
    - "Daftar" button → enrollment flow
- Enrollment flow: student selects a specific Class, Enrollment links to Course (billing), ClassStudent links to chosen Class

**Schema changes:**
- Add `show_on_storefront` boolean to `classes` table
- Add `storefront_description` text to `classes` table

---

### Module 2: Content Library (Per-Class)

**Problem:** Teachers share materials via WhatsApp — unstructured, gets lost, no tracking.

**New model: `ClassResource`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| class_id | FK → classes | |
| session_id | FK → class_sessions | nullable, attach to specific session |
| uploaded_by | FK → users | teacher or admin |
| title | string | |
| type | enum | recording, pdf, audio, image, link, note |
| file_path | string | nullable, for uploaded files |
| url | string | nullable, for external links (YouTube, etc.) |
| content | text | nullable, markdown for notes |
| sort_order | int | |
| is_published | boolean | teacher controls visibility |
| published_at | datetime | nullable, for scheduled release |
| timestamps | | |

**New model: `ClassResourceView`** (tracks who viewed what)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| class_resource_id | FK | |
| student_id | FK | |
| first_viewed_at | datetime | |
| last_viewed_at | datetime | |
| view_count | int | |
| timestamps | | |

**UI locations:**
- **Teacher:** New "Bahan" tab in class-show → upload files, add links, write notes. Can attach to specific session or leave general.
- **Student:** New "Bahan" tab in their class page → browse/download/view. Organized by session or general.
- **Admin:** Resource stats (uploads per class, student engagement).

**Behaviors:**
- Scheduled release (publish recording after session ends)
- `is_published = false` hides from students (draft mode)
- Files stored at `storage/app/public/class-resources/{class_id}/`
- Supports: PDF, MP3/audio, MP4/video, images, external URLs, text notes
- View tracking automatic on open/download

---

### Module 3: Student Progress Dashboard (Per-Class)

**Problem:** Students have no visibility into their own progress.

**Computed from existing data (no new tables for core metrics):**
- Attendance rate: `ClassAttendance` (present / total completed sessions)
- Sessions completed: `ClassSession` status = completed
- Syllabus progress: `ClassSyllabus` items covered in sessions
- Streak: consecutive sessions attended

**New model: `StudentMilestone`** (custom progress markers)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| class_student_id | FK → class_students | |
| title | string | e.g. "Khatam Juz 1", "Hafal 10 Surah" |
| achieved_at | datetime | |
| awarded_by | FK → users | nullable, teacher or system |
| type | enum | attendance, syllabus, custom |
| timestamps | | |

**Student dashboard shows (per class):**
- Attendance ring chart (e.g. 85% — 17/20 sessions)
- Current streak (e.g. "5 sesi berturut-turut")
- Sessions timeline (past = green checkmarks, upcoming = grey)
- Milestones earned
- Syllabus progress bar (topics covered / total)
- Next session countdown

**UI locations:**
- **Student `/my/classes/{class}`** — new "Kemajuan" tab
- **Teacher class-show** — per-student progress view in Students tab
- Summary card on student dashboard home

---

### Module 4: Class Announcements

**Problem:** Teachers use WhatsApp for announcements — no record, late joiners miss posts, admin can't see communications.

**New model: `ClassAnnouncement`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| class_id | FK → classes | |
| author_id | FK → users | teacher or admin |
| title | string | |
| body | text | markdown |
| is_pinned | boolean | |
| published_at | datetime | |
| timestamps | | |

**New model: `ClassAnnouncementRead`** (read tracking)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| announcement_id | FK | |
| student_id | FK | |
| read_at | datetime | |
| timestamps | | |

**UI locations:**
- **Teacher:** New "Pengumuman" tab in class-show → compose, pin, view read receipts
- **Student:** Announcements feed on class page + unread badge on dashboard
- **Admin:** See all announcements across classes

**Behaviors:**
- Pinned announcements stick to top
- Unread count badge on student dashboard
- Optional: trigger WhatsApp/email notification (reuses existing notification system)
- Markdown support for formatting
- Auto-tracked when student views

---

## Phase 2 — Engagement & Retention (Future)

| # | Module | Description |
|---|--------|-------------|
| 5 | Gamification System | Points, badges, streaks, leaderboard per class |
| 6 | Discussion Board | Per-class threads, Q&A, pinned posts |
| 7 | Homework/Tasks | Teacher assigns per-session tasks, student marks done, teacher reviews |
| 8 | Student Reviews | Rate completed classes, show on storefront as social proof |

## Phase 3 — Growth & Scale (Future)

| # | Module | Description |
|---|--------|-------------|
| 9 | Learning Path | Link classes in sequence, auto-suggest next on completion |
| 10 | Free Trial/Preview | 1 free session or preview content before enrollment |
| 11 | Student Analytics Dashboard | Admin engagement metrics, completion rates, revenue per class |
| 12 | Achievement Certificates | Auto-issue at milestones (not just class completion) |

---

## Architecture Notes

- All modules use **Livewire Volt** (consistent with existing education pages)
- All new tables follow existing patterns (Eloquent models, factories, migrations)
- File uploads use Laravel Storage (public disk)
- Notifications reuse existing `ClassNotificationSetting` + job infrastructure
- Student views at `/my/classes/{class}` with new tabs
- Teacher views in existing `class-show` with new tabs
