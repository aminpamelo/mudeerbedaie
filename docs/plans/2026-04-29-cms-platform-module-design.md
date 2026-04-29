# CMS Platform Module — Design

**Date:** 2026-04-29
**Status:** Approved, ready for implementation plan
**Module location:** `/cms` Inertia React SPA + Laravel API under `app/Http/Controllers/Api/Cms/`

## Problem

Content lifecycle today: created → stages (Idea → Shooting → Editing → Posting → Posted on TikTok) → marked when it converts → flows into the **Ads** module so an `AdCampaign` can be created from it.

Marked content has only one downstream destination today (Ads). The team also wants to **cross-post** that same proven content organically to other platforms (Instagram Reels, Facebook Reels, YouTube Shorts, Threads, X). There is no module to track or coordinate this work.

User mental model:
```
Marked → 1. Ads (paid)
       └ 2. Platform (organic cross-post)   ← NEW
```

## Goals

- Track which platforms a marked content has been cross-posted to, with status, URL, posted date, and per-platform stats.
- Per-platform assignee for accountability ("Lina handles all IG, Hiram handles YT").
- Auto-create a checklist of pending rows the moment content is marked, so nothing falls through the cracks.
- Future-proof the schema so v2 API automation (auto-publish via Meta/YouTube APIs) does not need a migration.

## Non-goals (v1)

- Automated publishing via platform APIs (deferred to v2).
- Caption-variant editing per platform (column reserved, no UI).
- TikTok cross-posting — TikTok is the first-post platform, already tracked via existing `platform_account_id` and `content_stats`.
- Stage refactor — "Cross-Posting" is **not** a 6th stage. It is a post-marking distribution step, parallel to Ads.

## Approach

New top-level sidebar module **Platform** in the `/cms` SPA, sibling to Ads. Mirrors the Ads module architecture (manual workflow, per-content rows, sub-pages for Queue and History). Triggered by the existing `is_marked_for_ads` flag transitioning false → true via a model observer.

## Data model

### `cms_platforms` (seeded reference)

| Column     | Type    | Notes                                |
| ---------- | ------- | ------------------------------------ |
| id         | bigint  | PK                                   |
| key        | string  | unique, e.g. `instagram`             |
| name       | string  | display name                         |
| icon       | string  | nullable, lucide icon name           |
| sort_order | int     | for stable display order             |
| is_enabled | boolean | default true                         |
| timestamps |         |                                      |

Seeded with: `instagram`, `facebook`, `youtube`, `threads`, `x`.
Adding a platform later = a seed entry, no schema change.

### `cms_content_platform_posts` (one row per content × platform)

| Column            | Type      | Notes                                                  |
| ----------------- | --------- | ------------------------------------------------------ |
| id                | bigint    | PK                                                     |
| content_id        | bigint FK | `contents.id`, cascade delete                          |
| platform_id       | bigint FK | `cms_platforms.id`                                     |
| status            | enum      | `pending` (default), `posted`, `skipped`               |
| post_url          | string    | nullable                                               |
| posted_at         | timestamp | nullable                                               |
| assignee_id       | bigint FK | `employees.id`, nullable                               |
| caption_variant   | text      | nullable, **reserved for v2** (no UI in v1)            |
| external_post_id  | string    | nullable, **reserved for v2 API**                      |
| sync_status       | string    | nullable, `manual` / `synced` / `failed` (v2)          |
| stats             | json      | nullable; `{views, likes, comments, last_synced_at}`   |
| timestamps        |           |                                                        |

Constraints:
- `unique(content_id, platform_id)` — guarantees idempotent auto-creation.
- `index(status, assignee_id)` — fast queue filtering.

Migration is plain adds (no enum mutations) — works on MySQL + SQLite without `DB::getDriverName()` branching.

The existing `is_marked_for_ads` boolean on `contents` is **not renamed**. Renaming would touch 5+ files and existing semantics; instead, the field functionally means "marked" and triggers both downstream destinations.

## Backend

### Models

- `App\Models\CmsPlatform` — `posts(): HasMany`
- `App\Models\CmsContentPlatformPost` — `belongsTo(Content::class)`, `belongsTo(CmsPlatform::class)`, `belongsTo(Employee::class, 'assignee_id')`. Casts: `stats` → array, `posted_at` → datetime.
- `App\Models\Content::platformPosts(): HasMany` — added.

### Auto-creation hook

`App\Observers\ContentObserver::updated()` listens for `is_marked_for_ads` going false → true and dispatches a service call `App\Services\Cms\CreatePlatformPostsForContent` that does `firstOrCreate` of one row per `is_enabled = true` platform.

- Idempotent: re-marking a previously-unmarked-then-marked content does not duplicate rows.
- Unmarking does **not** delete platform posts — preserves any URLs/stats already entered.

### API (under existing `/api/cms` group)

```
GET    /platforms                            list enabled platforms (for dropdowns)
GET    /platform-posts                       index with filters: ?platform_id=&status=&assignee_id=&search=
GET    /platform-posts/{post}                show
PATCH  /platform-posts/{post}                update status, post_url, posted_at, assignee_id
PATCH  /platform-posts/{post}/stats          update stats JSON (merge)
POST   /platform-posts/bulk-assign           batch reassign
```

Controllers in `app/Http/Controllers/Api/Cms/`:
- `CmsPlatformController` (read-only list)
- `CmsContentPlatformPostController` (index/show/update + stats + bulk assign)

Form Requests in `app/Http/Requests/Cms/`:
- `UpdatePlatformPostRequest`
- `UpdatePlatformPostStatsRequest`
- `BulkAssignPlatformPostsRequest`

Authorization reuses whatever middleware the existing Cms controllers use today.

### Tests (Pest)

- Feature: marking a content auto-creates one row per enabled platform
- Feature: unmarking does not delete rows
- Feature: re-marking is idempotent (no duplicate rows)
- Feature: PATCH updates status + url + posted_at, validates URL format
- Feature: stats update merges into JSON correctly
- Feature: bulk-assign updates the right rows
- Unit: observer fires only on false→true transition (not on other field saves)

## Frontend (`resources/js/cms/`)

### Sidebar

New top-level item placed between **Ads** and **Performance**:

```
Platform
 ├─ Cross-Post Queue   (default landing)
 └─ Posted History
```

### New pages

- `pages/PlatformQueue.jsx` — table of pending + posted rows. Filters: platform, status, assignee, search by content title. Click row → inline edit modal for status / URL / posted_at / assignee.
- `pages/PlatformHistory.jsx` — same table filtered to `status = posted`, sorted by `posted_at desc`.

### Content detail integration

`pages/ContentDetail.jsx` gets a new card after the existing **TikTok Stats** card, titled **"Cross-Platform Posts"**, showing this content's 4–5 platform rows with inline status / URL / stats editing. Visual language mirrors the existing Stage Details card.

### Routing inside SPA (`App.jsx`)

- `/cms/platform/queue` → `PlatformQueue`
- `/cms/platform/history` → `PlatformHistory`

### API client (`lib/api.js`)

Adds: `fetchPlatforms`, `fetchPlatformPosts`, `updatePlatformPost`, `updatePlatformPostStats`, `bulkAssignPlatformPosts`.

## UX flow

1. Team marks a TikTok-posted content (existing action).
2. Observer fires → 4–5 pending rows auto-created (IG, FB, YT, Threads, X).
3. Marked content now appears in both **Ads → Marked Posts** (existing) and **Platform → Cross-Post Queue** (new).
4. Assigned employee opens the queue → clicks a row → modal: paste URL, set status = Posted, set posted_at → save.
5. Later, employee returns, opens the row, fills in views / likes / comments stats.
6. Skipped platforms are recorded for audit ("we deliberately did not post this on Threads").

## Rollout

**v1 (this build):** manual tracking, schema future-proofed for API automation.
**v2 (future):** OAuth per platform, queue jobs to publish via API, populate `external_post_id` and `sync_status`. No schema change required — columns already reserved.

## Out of scope

- Inertia paradigm decision: this lives in the existing `/cms` Inertia React SPA. No new top-level paradigm introduced.
- Live Host Pocket and HR SPA are unaffected.
- The existing `is_marked_for_ads` boolean is preserved; no rename.
