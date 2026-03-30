# Content Management System (CMS) Module — Design Document

**Date:** 2026-03-30
**Status:** Approved
**Architecture:** React SPA (same pattern as HR module)

---

## Overview

A Content Management System for tracking content creation workflows and ad campaign management. The system has two pipelines:

1. **Content Pipeline** — Idea → Shooting → Editing → Posting (with TikTok stats)
2. **Ads Pipeline** — Marked posts → Ad Campaigns → Ad Performance stats

### Key Decisions

- **SPA with React** — Same architecture as the HR module
- **Two separate pipelines** (Approach A) — Content creation and ads management are distinct flows with shared data
- **Multiple assignees per stage** — Each workflow stage can have multiple employees assigned
- **Any employee** in the system can be assigned to content tasks
- **TikTok API integration deferred** — System works with manual stats entry; TikTok Video Insights API can be plugged in later
- **Auto-flag + manual mark** — Posts auto-flagged when stats hit thresholds, but any user can manually mark a post for ads
- **Facebook Ads integration deferred** — Future phase; for now, ads are manually linked to posts

---

## Data Model

### Content Pipeline Tables

#### `contents`
The main content item.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK, auto-increment |
| title | string | Content idea title |
| description | text, nullable | Detailed description/brief |
| stage | enum | `idea`, `shooting`, `editing`, `posting`, `posted` |
| due_date | date, nullable | Overall deadline |
| priority | enum | `low`, `medium`, `high`, `urgent` |
| tiktok_url | string, nullable | TikTok post URL (filled at posting stage) |
| tiktok_post_id | string, nullable | TikTok post ID for API stats |
| is_flagged_for_ads | boolean, default false | Auto-flagged by stats threshold |
| is_marked_for_ads | boolean, default false | Manually marked by user |
| marked_by | FK → employees, nullable | Who marked it |
| marked_at | timestamp, nullable | When marked |
| created_by | FK → employees | Who created the idea |
| posted_at | timestamp, nullable | When posted to TikTok |
| timestamps | | created_at, updated_at |
| soft_deletes | | deleted_at |

#### `content_stages`
Tracks each stage's progress.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| content_id | FK → contents | |
| stage | enum | `idea`, `shooting`, `editing`, `posting` |
| status | enum | `pending`, `in_progress`, `completed` |
| due_date | date, nullable | Stage-specific deadline |
| started_at | timestamp, nullable | When work began |
| completed_at | timestamp, nullable | When stage finished |
| notes | text, nullable | Stage notes |
| timestamps | | |

Unique constraint on `(content_id, stage)`.

#### `content_stage_assignees`
Multiple people per stage.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| content_stage_id | FK → content_stages | |
| employee_id | FK → employees | Assigned person |
| role | string, nullable | Optional label (e.g., "Lead Shooter") |
| timestamps | | |

#### `content_stats`
TikTok performance data (manual entry now, API later).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| content_id | FK → contents | |
| views | bigint, default 0 | |
| likes | bigint, default 0 | |
| comments | bigint, default 0 | |
| shares | bigint, default 0 | |
| fetched_at | timestamp | When stats were recorded |
| timestamps | | |

### Ads Pipeline Tables

#### `ad_campaigns`
Ads linked to posted content.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| content_id | FK → contents | Which post this ad is for |
| platform | enum | `facebook`, `tiktok` |
| ad_id | string, nullable | External ad ID (for future API) |
| status | enum | `pending`, `running`, `paused`, `completed` |
| budget | decimal(10,2), nullable | Ad budget |
| start_date | date, nullable | |
| end_date | date, nullable | |
| notes | text, nullable | |
| assigned_by | FK → employees | Who set this up |
| timestamps | | |
| soft_deletes | | |

#### `ad_stats`
Ad performance (manual for now, API later).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| ad_campaign_id | FK → ad_campaigns | |
| impressions | bigint, default 0 | |
| clicks | bigint, default 0 | |
| spend | decimal(10,2), default 0 | |
| conversions | bigint, default 0 | |
| fetched_at | timestamp | |
| timestamps | | |

---

## Pages & UI

### Dashboard (`/cms`)
- **Stats cards**: Total Contents, In Progress, Posted This Month, Flagged for Ads
- **Kanban board**: Columns for each stage with content cards (title, assignees, due date, priority)
- **Content calendar**: Monthly view showing due dates and scheduled shoots
- **Top performing posts**: Best TikTok stats (views, likes, shares)

### Content Pages

| Page | Route | Purpose |
|------|-------|---------|
| Content List | `/cms/contents` | Table view with filters (stage, assignee, priority, date range) + search |
| Content Create | `/cms/contents/create` | Form: title, description, priority, due date, assign people per stage |
| Content Detail | `/cms/contents/:id` | Stage timeline, assignees, TikTok stats chart, mark for ads action |
| Content Edit | `/cms/contents/:id/edit` | Edit content details and stage assignments |
| Kanban Board | `/cms/kanban` | Drag-and-drop kanban view |
| Calendar | `/cms/calendar` | Calendar view of content due dates |

### Content Detail Page Layout

```
┌─────────────────────────────────────────────────┐
│  Content Title                    Priority Badge │
│  Created by: Ahmad  ·  Due: 15 Apr 2026         │
├─────────────────────────────────────────────────┤
│  Stage Timeline (horizontal progress bar)        │
│  [✓ Idea] → [● Shooting] → [○ Editing] → [○ Posting] │
├──────────────────────┬──────────────────────────┤
│  Stage Details       │  TikTok Stats (if posted) │
│  📍 Shooting         │  Views:  45,200           │
│  Status: In Progress │  Likes:   3,100           │
│  Due: 10 Apr 2026    │  Comments:  142           │
│  Assignees:          │  Shares:    89            │
│  - Ali (Lead)        │  [Stats History Chart]    │
│  - Sarah             │                           │
├──────────────────────┴──────────────────────────┤
│  Description / Brief                             │
├─────────────────────────────────────────────────┤
│  [Mark for Ads]  [Move to Next Stage]            │
└─────────────────────────────────────────────────┘
```

### Ads Pages

| Page | Route | Purpose |
|------|-------|---------|
| Ads Dashboard | `/cms/ads` | List of marked posts with ad status, performance overview |
| Ad Campaign Detail | `/cms/ads/:id` | Linked content info, ad stats, platform details |

### Sidebar Navigation

```
CMS Module
├── Dashboard
├── Contents
│   ├── All Contents
│   ├── Kanban Board
│   └── Calendar
├── Ads
│   ├── Marked Posts
│   └── Campaigns
└── Settings
    └── TikTok Integration (future)
```

---

## API Endpoints

### Content Pipeline

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/cms/contents` | List with filters (stage, assignee, priority, date) |
| POST | `/api/cms/contents` | Create content with stage assignments |
| GET | `/api/cms/contents/{id}` | Detail with stages, assignees, stats |
| PUT | `/api/cms/contents/{id}` | Update content |
| DELETE | `/api/cms/contents/{id}` | Soft delete |
| PATCH | `/api/cms/contents/{id}/stage` | Move to next/specific stage |
| POST | `/api/cms/contents/{id}/stats` | Manually add TikTok stats |
| PATCH | `/api/cms/contents/{id}/mark-for-ads` | Mark/unmark for ads |
| GET | `/api/cms/contents/kanban` | Grouped by stage for kanban view |
| GET | `/api/cms/contents/calendar` | Date-based for calendar view |

### Stage Assignments

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/cms/contents/{id}/stages/{stage}/assignees` | Add assignee to stage |
| DELETE | `/api/cms/contents/{id}/stages/{stage}/assignees/{employeeId}` | Remove assignee |

### Ads Pipeline

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/cms/ads` | List ad campaigns |
| POST | `/api/cms/ads` | Create campaign linked to content |
| GET | `/api/cms/ads/{id}` | Campaign detail with stats |
| PUT | `/api/cms/ads/{id}` | Update campaign |
| POST | `/api/cms/ads/{id}/stats` | Add ad performance stats |

### Dashboard

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/cms/dashboard/stats` | Summary cards |
| GET | `/api/cms/dashboard/top-posts` | Top performing posted content |

---

## Backend Structure

```
app/
├── Http/Controllers/Api/Cms/
│   ├── CmsDashboardController.php
│   ├── CmsContentController.php
│   ├── CmsContentStageController.php
│   ├── CmsAdCampaignController.php
│   └── CmsAdStatsController.php
├── Models/
│   ├── Content.php
│   ├── ContentStage.php
│   ├── ContentStageAssignee.php
│   ├── ContentStat.php
│   ├── AdCampaign.php
│   └── AdStat.php
├── Http/Requests/Cms/
│   ├── StoreContentRequest.php
│   ├── UpdateContentRequest.php
│   └── StoreAdCampaignRequest.php
└── Jobs/
    └── FetchTikTokContentStats.php  (future phase)
```

## Frontend Structure

```
resources/js/cms/
├── main.jsx                   # Entry point
├── App.jsx                    # Router + React Query
├── layouts/
│   └── CmsLayout.jsx          # Sidebar + header layout
├── pages/
│   ├── Dashboard.jsx
│   ├── ContentList.jsx
│   ├── ContentCreate.jsx
│   ├── ContentDetail.jsx
│   ├── ContentEdit.jsx
│   ├── KanbanBoard.jsx
│   ├── ContentCalendar.jsx
│   ├── AdsList.jsx
│   └── AdCampaignDetail.jsx
├── components/
│   ├── ui/                    # Shared from HR (Radix UI + Tailwind)
│   ├── KanbanColumn.jsx
│   ├── KanbanCard.jsx
│   ├── StageTimeline.jsx
│   ├── StatsCard.jsx
│   └── AssigneePicker.jsx
├── lib/
│   ├── api.js                 # Axios instance + CMS API functions
│   └── utils.js
└── styles/
    └── cms.css
```

---

## TikTok Integration (Future Phase)

The system is designed to work without TikTok API. When ready:

- Extend the existing `TikTokAuthService` pattern for Video Insights API scopes
- Create `TikTokContentStatsService` to fetch video performance data
- Scheduled `FetchTikTokContentStats` job pulls stats periodically
- Auto-flag logic runs after stats fetch, checking configurable thresholds
- Settings page for managing TikTok connection and threshold configuration

### Existing TikTok Infrastructure to Reuse
- OAuth flow pattern from `TikTokAuthService`
- `PlatformAccount` / `PlatformApiCredential` models for token storage
- Job scheduling patterns from `SyncTikTokOrders`
- `TikTokClientFactory` approach (extended for content API)

---

## Ads Integration (Future Phase)

- Facebook Ads API integration for automatic performance tracking
- Manual assignment: user selects which Facebook ad corresponds to which post
- `ad_stats` table designed to store data from either manual entry or API

---

## Auto-Flag Configuration

Default thresholds (configurable in future settings page):
- Views > 10,000
- Likes > 1,000
- Engagement rate > 5%

When thresholds are met, `is_flagged_for_ads` is set to `true`. Any user can then manually `is_marked_for_ads` to confirm it should enter the ads pipeline.
