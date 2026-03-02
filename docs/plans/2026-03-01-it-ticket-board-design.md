# IT Ticket Board — Design Document

**Date**: 2026-03-01
**Status**: Approved

## Overview

A new IT Task Management module with a Kanban board for internal development task tracking. Separate from the existing Customer Service ticket system.

## Access Model

- **All authenticated users** can submit IT requests via `/it-request`
- **Admin users** manage the Kanban board at `/admin/it-board`

## Data Model

### `it_tickets` table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| title | string(255) | Ticket title |
| description | text, nullable | Detailed description |
| type | enum | `bug`, `feature`, `task`, `improvement` |
| priority | enum | `low`, `medium`, `high`, `urgent` |
| status | enum | `backlog`, `todo`, `in_progress`, `review`, `testing`, `done` |
| position | integer, default 0 | Sort order within column |
| reporter_id | foreignId → users | Who submitted the ticket |
| assignee_id | foreignId → users, nullable | Who is assigned |
| due_date | date, nullable | Deadline |
| completed_at | timestamp, nullable | When marked done |
| created_at | timestamp | Standard |
| updated_at | timestamp | Standard |

### `it_ticket_comments` table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| it_ticket_id | foreignId → it_tickets, cascade | Parent ticket |
| user_id | foreignId → users | Comment author |
| body | text | Comment content |
| created_at | timestamp | Standard |
| updated_at | timestamp | Standard |

### Models

- `ItTicket` — belongs to `reporter` (User), `assignee` (User), has many `comments`
- `ItTicketComment` — belongs to `ticket` (ItTicket), `user` (User)

## Kanban Board Statuses (6 Columns)

1. **Backlog** — New submissions land here
2. **To Do** — Prioritized and ready to start
3. **In Progress** — Currently being worked on
4. **Review** — Code review stage
5. **Testing** — QA/testing stage
6. **Done** — Completed

## Ticket Properties

### Types
- **Bug** (red badge) — Something broken
- **Feature** (green badge) — New functionality
- **Task** (blue badge) — General work item
- **Improvement** (yellow badge) — Enhance existing feature

### Priority Levels
- **Low** (gray) — No rush
- **Medium** (blue) — Normal priority
- **High** (orange) — Important
- **Urgent** (red) — Needs immediate attention

## Pages & Routes

### Admin Routes (admin middleware)
| Route | Volt Component | Purpose |
|-------|---------------|---------|
| `GET /admin/it-board` | `admin.it-board.index` | Kanban board |
| `GET /admin/it-board/{itTicket}` | `admin.it-board.show` | Ticket detail + comments |
| `GET /admin/it-board/create` | `admin.it-board.create` | Admin create ticket |

### Authenticated User Routes
| Route | Volt Component | Purpose |
|-------|---------------|---------|
| `GET /it-request` | `it-request.create` | Any user submits IT request |

## UI Design

### Kanban Board (`/admin/it-board`)
- Header with title, filters (type, priority, assignee), and create button
- 6 horizontally scrollable columns with sticky headers showing ticket count
- Cards display: title, type badge, priority dot, assignee avatar, due date
- Drag-and-drop via SortableJS + Alpine.js integration
- Livewire handles server-side state persistence on drop
- Quick-create button at top of each column

### Ticket Card
- Title (truncated if long)
- Type badge (color-coded)
- Priority indicator (colored dot)
- Assignee avatar (small)
- Due date (if set, red if overdue)

### Ticket Detail (`/admin/it-board/{ticket}`)
- Full ticket info with inline-editable fields
- Status change dropdown
- Comments thread (chronological, with user avatar/name/timestamp)
- Back link to board

### Submission Form (`/it-request`)
- Simple form: Title, Description, Type, Priority
- Confirmation message after submission
- New tickets auto-land in Backlog

## Navigation

### Admin Sidebar
New item under Administration group:
```
IT Board → /admin/it-board
```

### User Sidebars (teacher, student, etc.)
New item:
```
IT Request → /it-request
```

## Technical Stack

- **Backend**: Livewire Volt (class-based components)
- **Drag & Drop**: SortableJS via npm
- **Styling**: Tailwind CSS v4 + Flux UI Free components
- **Real-time sync**: Livewire wire events for column/position updates

## Dependencies

- `sortablejs` npm package (~10KB)
