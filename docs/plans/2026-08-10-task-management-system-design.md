# Task Management System (TMS) — Full Design

**Date:** 2026-08-10
**Status:** Approved
**Goal:** Build a comprehensive task management system (ClickUp-level) for all BeDaie staff — combining Task + Team + Progress + Accountability + Reporting.

## Architecture

**URL:** `/workspace` (new Inertia React SPA)
**Access:** All staff — admin, employee, ceo (permission-scoped)
**Backend:** Laravel 12 controllers + extended Task model
**Frontend:** React 19 + Tailwind v4 (glassmorphism design matching CEO app)
**Root view:** `workspace.app` (custom Inertia middleware: `HandleWorkspaceInertiaRequests`)

### Data Hierarchy

```
Department (Marketing, Sales, HR, Finance, Content, Design, Video, TikTok Live)
  └── Project (Buku Qada Solat, NextDAE, etc.)
       └── Task (Siapkan poster kelas Muharram)
            ├── Subtask (child tasks)
            ├── Checklist items (☐/☑️ simple toggles)
            ├── Comments + @Mentions
            ├── Attachments (PDF, Word, Excel, images, video, audio)
            ├── Time entries (start/pause/stop)
            └── Activity log (audit trail)
```

---

## Database Schema

### Existing Tables (to extend)

#### `tasks` — ADD columns:
```
project_id           FK → tms_projects (nullable, for standalone tasks)
estimated_minutes    INT (nullable, estimated duration)
actual_minutes       INT (nullable, computed from time entries)
start_date           DATE (nullable)
sort_order           INT (for kanban column ordering)
is_recurring         BOOLEAN default false
recurring_config_id  FK → task_recurring_configs (nullable)
approval_status      ENUM: null, pending_review, approved, rejected
approved_by          FK → users (nullable)
approved_at          TIMESTAMP (nullable)
points               INT default 0 (gamification points earned)
```

### New Tables

#### `tms_projects`
```
id, name, description, color, icon
department_id        FK → departments
owner_id             FK → users (project owner)
status               ENUM: active, on_hold, completed, archived
start_date           DATE (nullable)
target_date          DATE (nullable)
sort_order           INT
timestamps, soft_deletes
```

#### `tms_project_members`
```
id, project_id, user_id
role                 ENUM: owner, manager, member, viewer
timestamps
UNIQUE(project_id, user_id)
```

#### `task_checklists`
```
id, task_id
title                VARCHAR
is_completed         BOOLEAN default false
completed_by         FK → users (nullable)
completed_at         TIMESTAMP (nullable)
sort_order           INT
timestamps
```

#### `task_time_entries`
```
id, task_id, user_id
started_at           TIMESTAMP
ended_at             TIMESTAMP (nullable, null = running)
duration_seconds     INT (computed on stop)
description          VARCHAR (nullable, what they worked on)
timestamps
```

#### `task_activity_logs`
```
id, task_id, user_id
action               VARCHAR (created, status_changed, assigned, commented, etc.)
field                VARCHAR (nullable, which field changed)
old_value            TEXT (nullable)
new_value            TEXT (nullable)
timestamps
```

#### `task_watchers`
```
id, task_id, user_id
timestamps
UNIQUE(task_id, user_id)
```

#### `task_dependencies`
```
id
task_id              FK → tasks (the blocked task)
depends_on_task_id   FK → tasks (the blocking task)
type                 ENUM: blocks, blocked_by
timestamps
UNIQUE(task_id, depends_on_task_id)
```

#### `task_recurring_configs`
```
id
task_id              FK → tasks (template task)
frequency            ENUM: daily, weekly, monthly, yearly
day_of_week          INT (nullable, 0=Sun..6=Sat)
day_of_month         INT (nullable, 1-31)
time_of_day          TIME (nullable)
last_generated_at    TIMESTAMP (nullable)
next_due_at          TIMESTAMP
is_active            BOOLEAN default true
timestamps
```

#### `tms_badges`
```
id, name, description, icon, color
criteria_type        ENUM: tasks_completed, streak, speed, first_task, etc.
criteria_value       INT (e.g., 30 for "30 Days Streak")
points               INT (points awarded)
timestamps
```

#### `tms_badge_awards`
```
id, badge_id, user_id
awarded_at           TIMESTAMP
timestamps
UNIQUE(badge_id, user_id)
```

#### `tms_user_stats` (cached daily aggregates)
```
id, user_id, date
tasks_completed      INT
tasks_created        INT
tasks_overdue        INT
time_tracked_seconds INT
streak_days          INT
total_points         INT
timestamps
UNIQUE(user_id, date)
```

---

## Module Design (All 28)

### Group A: Core Task System (Modules 4-8)

**Module 4: Task**
- Extend existing Task model with new columns
- Fields: title, description, priority (low/medium/high/urgent), status (todo/in_progress/review/completed/cancelled), due_date, start_date, estimated_duration, tags (JSON array)
- Auto-generate activity log on every change (model observer)

**Module 5: Assign Person**
- Existing `task_assignee` pivot table handles multi-assignee
- Add `role` column to pivot: `leader` or `member`
- Primary `assigned_to` = leader, co-owners = members

**Module 6: Subtask**
- Existing `parent_id` on tasks table — already works
- Subtask inherits project_id from parent
- Completion of all subtasks can auto-complete parent (optional setting)

**Module 7: Checklist**
- New `task_checklists` table — lightweight toggle items
- Drag-sortable, completable inline
- Shows progress bar on task card (3/6 items)

**Module 8: Attachment**
- Existing `task_attachments` table — already works
- Supported: PDF, Word, Excel, Audio, Video, Image
- Storage: `storage/app/public/task-attachments/{task_id}/`
- Max file size: 50MB

### Group B: Views (Modules 1, 12-14, 21-22)

**Module 1: Dashboard** — `/workspace`
- Summary cards: active tasks, completed today, overdue, by department
- My tasks widget (next 5 due)
- Recent activity feed
- Quick-create task button
- Department breakdown chart

**Module 12: Calendar** — `/workspace/calendar`
- Month/week/day views (reuse patterns from student timetable)
- Tasks shown by due_date
- Color-coded by priority
- Click to view/edit task
- Drag to reschedule

**Module 13: Kanban Board** — `/workspace/board`
- Columns: To Do → In Progress → Review → Completed
- Drag-and-drop between columns (react-beautiful-dnd or @dnd-kit)
- Filter by project, assignee, priority, tags
- Task cards show: title, assignee avatar, priority dot, due date, checklist progress, attachment count

**Module 14: Gantt Chart** — `/workspace/gantt`
- Timeline view with start_date → due_date bars
- Group by project or assignee
- Dependencies shown as arrows
- Scrollable date range
- Library: react-gantt-chart or custom SVG

**Module 21: Search** — Global search bar in workspace header
- Full-text search across task title, description, comments
- Filter by: name, staff, tag, department, date range
- Recent searches saved

**Module 22: Filter** — Filter panel on board/list views
- Priority: low/medium/high/urgent
- Status: todo/in_progress/review/completed
- Assignee: multi-select staff
- Department/Project: dropdown
- Date: today, this week, overdue, custom range
- Tags: multi-select
- Persist filter in URL query params

### Group C: Organization (Modules 2-3)

**Module 2: Department/Team**
- Reuse existing `departments` table from HR
- Each department = a workspace section in sidebar
- Department head (from HR) = department manager in TMS

**Module 3: Project**
- New `tms_projects` table
- Projects belong to a department
- Projects have members with roles (owner/manager/member/viewer)
- Project detail page: task list + board + timeline + members
- Project templates (stretch goal)

### Group D: Collaboration (Modules 9-10, 18)

**Module 9: Comment/Discussion**
- Existing `task_comments` table — already works
- Rich text support (or markdown)
- @mentions in comments (Module 10)
- Inline on task detail modal/page

**Module 10: @Mention**
- Parse `@username` in comment text
- Auto-complete dropdown when typing `@`
- Creates notification for mentioned user
- Adds mentioned user as task watcher

**Module 18: Activity Log**
- New `task_activity_logs` table
- Task observer logs every field change automatically
- Shows timeline on task detail: "Mail ubah status kepada Review", "Fatin upload PDF"
- Filterable by action type

### Group E: Workflow (Modules 15-16)

**Module 15: Recurring Tasks**
- `task_recurring_configs` table defines schedule
- Scheduled command `tms:generate-recurring` runs daily
- Creates new task instance from template task
- Supports: daily, weekly (specific day), monthly (specific date), yearly

**Module 16: Approval Workflow**
- Task status flow: Draft → Review → Approved → Completed
- `approval_status` on tasks table
- When staff completes task → status moves to `review`
- Leader/manager clicks "Approve" → `approved`
- Optional: multi-level approval (staff → leader → manager)

### Group F: Tracking (Modules 17, 19-20)

**Module 17: Time Tracking**
- `task_time_entries` table
- Start/Pause/Stop buttons on task detail
- Only one active timer per user at a time
- Running timer shows in workspace header
- Daily/weekly time summary per user

**Module 19: KPI Dashboard** — `/workspace/kpi`
- Per staff: tasks completed, tasks overdue, avg completion time, productivity score
- Per department: completion rate, overdue rate, team velocity
- Period selector: daily/weekly/monthly
- Computed from `tms_user_stats` (cached daily)

**Module 20: Reports** — `/workspace/reports`
- Daily/Weekly/Monthly report generation
- By department, staff, or project
- Metrics: completed tasks, overdue tasks, time spent, avg velocity
- Exportable as PDF/CSV

### Group G: Notifications (Modules 11, 23)

**Module 11: Reminders**
- Scheduled job checks upcoming deadlines daily
- Notifications: 1 day before, same day, overdue
- Channels: in-app (database), email
- WhatsApp/Telegram: stretch goal (reuse existing WABA infrastructure)

**Module 23: Notification Center**
- Bell icon in workspace header with unread count
- Dropdown feed: task assigned, mentioned, comment, deadline, approval
- Mark as read / mark all read
- Click to navigate to task

### Group H: Access Control (Module 24)

**Module 24: Roles & Permissions**
- 4 TMS roles: Admin, Manager, Leader, Staff
- Mapped from existing user roles:
  - `admin` / `ceo` → TMS Admin (all access)
  - Department head → Manager (their department)
  - Task leader assignee → Leader (their team's tasks)
  - `employee` → Staff (own tasks only)
- Project-level permissions via `tms_project_members` role
- Middleware: `HandleWorkspaceInertiaRequests` shares user's TMS permissions

### Group I: Gamification (Module 26)

**Module 26: Gamification**
- Points: earned for completing tasks, streaks, fast completion
- Badges: seeded set (Top Performer, 30 Days Streak, Fast Completion, etc.)
- Leaderboard: `/workspace/leaderboard` — ranked by monthly points
- Badge display on user profile/avatar
- Daily points calculation job

### Group J: AI Assistant (Module 25)

**Module 25: AI Assistant**
- Task decomposition: "Buku 40 Hadis" → AI suggests subtasks
- Daily summary: "3 task overdue, 5 task siap hari ini"
- Weekly report generation: "Marketing menyiapkan 42 task"
- Priority suggestion: AI ranks which task to do first
- Uses OpenAI API (OPENAI_API_KEY already in system for AI Sales Pages)
- Accessible via chat widget in workspace sidebar

### Group K: Integration & Mobile (Modules 27-28)

**Module 27: Integration**
- Google Calendar sync: push task deadlines as calendar events (OAuth2)
- Stretch: Telegram bot, Slack webhook
- Internal: link tasks to existing modules (HR, LiveHost, etc.)

**Module 28: Mobile / PWA**
- Workspace already responsive (React + Tailwind)
- Add PWA manifest + service worker for install-to-homescreen
- Push notifications via web push API
- Offline task viewing (stretch)

---

## React Page Structure

```
resources/js/workspace/
├── app.jsx                    # Inertia app entry
├── styles/workspace.css       # Design tokens + glassmorphism
├── layouts/
│   └── WorkspaceLayout.jsx    # Sidebar + header + notification bell
├── pages/
│   ├── Dashboard.jsx          # Module 1
│   ├── Board.jsx              # Module 13 (Kanban)
│   ├── Calendar.jsx           # Module 12
│   ├── Gantt.jsx              # Module 14
│   ├── Projects.jsx           # Module 3 (list)
│   ├── ProjectDetail.jsx      # Project tasks + board + timeline
│   ├── TaskDetail.jsx         # Full task page (subtasks, comments, time, log)
│   ├── MyTasks.jsx            # Personal task list
│   ├── Kpi.jsx                # Module 19
│   ├── Reports.jsx            # Module 20
│   ├── Leaderboard.jsx        # Module 26
│   └── Settings.jsx           # Workspace settings, categories, badges
├── components/
│   ├── TaskCard.jsx           # Kanban card
│   ├── TaskModal.jsx          # Quick view/edit modal
│   ├── TaskForm.jsx           # Create/edit form
│   ├── CommentThread.jsx      # Module 9
│   ├── MentionInput.jsx       # Module 10 (@autocomplete)
│   ├── ChecklistEditor.jsx    # Module 7
│   ├── TimeTracker.jsx        # Module 17 (start/stop widget)
│   ├── ActivityTimeline.jsx   # Module 18
│   ├── FilterPanel.jsx        # Module 22
│   ├── NotificationBell.jsx   # Module 23
│   ├── GanttChart.jsx         # Module 14
│   ├── ApprovalActions.jsx    # Module 16
│   ├── RecurringConfig.jsx    # Module 15
│   ├── AiAssistant.jsx        # Module 25
│   └── BadgeDisplay.jsx       # Module 26
└── lib/
    ├── api.js                 # API helpers
    ├── permissions.js         # Role/permission checks
    └── utils.js               # Formatters, constants
```

## Backend Controller Structure

```
app/Http/Controllers/Workspace/
├── DashboardController.php    # Summary stats + widgets
├── ProjectController.php      # CRUD + members
├── TaskController.php         # CRUD + status + assign + bulk ops
├── SubtaskController.php      # CRUD under parent task
├── ChecklistController.php    # CRUD checklist items
├── CommentController.php      # CRUD + @mention parsing
├── AttachmentController.php   # Upload/download/delete
├── TimeEntryController.php    # Start/stop/list
├── ActivityLogController.php  # Read-only feed
├── KpiController.php          # Aggregate queries
├── ReportController.php       # Generate + export
├── BoardController.php        # Kanban reorder endpoint
├── CalendarController.php     # Date-range task query
├── GanttController.php        # Timeline data
├── RecurringController.php    # Manage recurring configs
├── NotificationController.php # In-app notifications
├── LeaderboardController.php  # Points + badges
└── AiController.php           # AI task decomposition + summaries
```

## Implementation Phases

Given all 28 modules, execution order for maximum velocity:

| Phase | Modules | What | Est. Tasks |
|-------|---------|------|-----------|
| **1. Foundation** | Schema + Models + Auth | All migrations, models, factories, middleware, route group, layout | ~15 |
| **2. Core CRUD** | 4,5,6,7,8 | Task CRUD, subtasks, checklists, attachments, assign | ~12 |
| **3. Views** | 1,13,12 | Dashboard, Kanban board (drag-drop), Calendar | ~10 |
| **4. Organization** | 2,3 | Department workspaces, Projects with members | ~8 |
| **5. Collaboration** | 9,10,18 | Comments, @mentions, activity log | ~8 |
| **6. Workflow** | 15,16 | Recurring tasks, approval workflow | ~6 |
| **7. Tracking** | 17,19,20 | Time tracking, KPI dashboard, reports | ~10 |
| **8. Notifications** | 11,23 | Reminders, notification center | ~6 |
| **9. Advanced Views** | 14,21,22 | Gantt chart, search, advanced filters | ~8 |
| **10. Access** | 24 | Role-based permissions matrix | ~4 |
| **11. Gamification** | 26 | Points, badges, leaderboard | ~6 |
| **12. AI** | 25 | Task decomposition, daily summaries | ~5 |
| **13. Integration** | 27,28 | Google Calendar, PWA | ~6 |

**Total: ~104 implementation tasks across 13 phases**
