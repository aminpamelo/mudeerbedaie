# Teacher Pages — Design Rollout Plan

**Date:** 2026-04-27
**Owner:** Aminpamelo
**Status:** Plan — pending approval
**Reference design:** [resources/views/livewire/teacher/dashboard.blade.php](../../resources/views/livewire/teacher/dashboard.blade.php) ([design doc](2026-04-27-teacher-dashboard-modern-redesign.md))

---

## Goal

Apply the modern colorful (livehost violet identity) design language used in `/teacher/dashboard` to all remaining teacher pages, so the entire teacher experience feels cohesive.

## Scope — 14 Pages

| # | File | Lines | Type | Complexity |
|---|---|---|---|---|
| 1 | `classes-index.blade.php` | 179 | List | **S** |
| 2 | `courses-index.blade.php` | 143 | List | **S** |
| 3 | `payslips-index.blade.php` | 240 | List + filters | **S** |
| 4 | `sessions-index.blade.php` | 589 | List + filters + export | **M** |
| 5 | `students-index.blade.php` | 654 | List + filters + grid/list toggle | **M** |
| 6 | `courses-show.blade.php` | 267 | Detail (2-col) | **S** |
| 7 | `session-show.blade.php` | 413 | Detail + modals + timer | **M** |
| 8 | `students-show.blade.php` | 731 | Detail + tabs | **L** |
| 9 | `classes-show.blade.php` | 2537 | Detail + many tabs | **XL** |
| 10 | `timetable.blade.php` | 955 | Calendar shell + view switcher | **L** |
| 11 | `timetable/day-view.blade.php` | 206 | Sub-view | **S** |
| 12 | `timetable/list-view.blade.php` | 205 | Sub-view | **S** |
| 13 | `timetable/month-view.blade.php` | 118 | Sub-view | **S** |
| 14 | `timetable/week-view.blade.php` | 549 | Sub-view + Alpine | **M** |

**Total ≈ 7,800 LOC of view code.**

---

## Foundation — Already in Place

Reusable in [resources/css/app.css](../../resources/css/app.css):

- **Tokens:** `--color-teacher-{deep,ink,from,base,soft}` (violet ramp), success/warn/danger/info accents
- **Typography:** Plus Jakarta Sans (display) + Inter (body), via `.teacher-display` and `.teacher-num` classes
- **Body scope:** `.teacher-app` class on `<body>` — set in `layouts/teacher.blade.php`
- **Surface:** `.teacher-card`, `.teacher-card-hover`
- **Hero / atmosphere:** `.teacher-hero`, `.teacher-grain`, `.teacher-modal-hero`, `.teacher-modal-stripe`, `.teacher-modal-orb`
- **Stats:** `.teacher-stat` + variants `.teacher-stat-{indigo,emerald,violet,amber}`
- **Indicators:** `.teacher-live-dot`
- **Buttons:** `.teacher-cta` (gradient), `.teacher-cta-ghost`
- **Avatar bubbles:** `.teacher-avatar` + 6 color variants

---

## Phase 0 — Extract Shared Blade Components (BEFORE touching pages)

Six reusable Blade components live in `resources/views/components/teacher/*`. Each captures a pattern that recurs across pages so we don't copy-paste markup.

| Component | Slots / Props | Used by |
|---|---|---|
| `<x-teacher.page-header>` | `title`, `subtitle`, `back?`, action slot | every list + detail page |
| `<x-teacher.stat-card>` | `eyebrow`, `value`, `tone` (indigo/emerald/violet/amber), icon slot, `footer?` slot | every page with stats |
| `<x-teacher.empty-state>` | `icon`, `title`, `message`, action slot | indexes with no data |
| `<x-teacher.status-pill>` | `tone` (scheduled/ongoing/completed/cancelled/no_show/etc.), `label`, `icon?` | sessions, classes, students |
| `<x-teacher.filter-bar>` | content slot — search input + dropdowns | sessions-index, students-index, payslips-index |
| `<x-teacher.tabs>` | `tabs` array, `active`, content slot | students-show, classes-show, timetable |

**Why first:** these absorb 60–70% of repetitive markup. Once they exist, each page redesign becomes a 30–60-line edit instead of a 200–600-line rewrite.

**Estimated effort:** half a day. Add a Pest browser test that mounts each component in isolation to catch regressions early.

---

## Phase 1 — Index / List Pages

Five list pages share the same skeleton: header → stat strip → optional filter bar → list/grid → pagination. Tackle them together for consistency.

| # | Page | What changes | Risk |
|---|---|---|---|
| 1 | `courses-index` | Replace flux:cards with `.teacher-card` grid; gradient stat strip; empty state with gradient icon | Low — small file, pure presentation |
| 2 | `classes-index` | Same as courses-index; class cards get violet status accent stripe | Low |
| 3 | `payslips-index` | Add gradient earnings hero card on top (RM total this year); filter bar component; payslip rows with violet→emerald gradient amount chips | Low |
| 4 | `sessions-index` | New filter bar; status pills via `<x-teacher.status-pill>`; per-row live timer chip when ongoing; gradient export button | Medium — many wire bindings to preserve |
| 5 | `students-index` | New filter bar; grid view = avatar bubble cards (`.teacher-avatar-{1..6}`) with attendance progress bar; list view = compact rows; preserve grid/list toggle | Medium — toggle state, 654 lines |

**Verification per page:**
- Pest feature test continues to pass (`php artisan test --filter Teacher`)
- Manually check filter dropdowns, pagination, search debounce
- Compare row/card density against dashboard so the visual rhythm matches

**Phase 1 effort:** ~1.5 days.

---

## Phase 2 — Simple Detail Pages

| # | Page | What changes | Risk |
|---|---|---|---|
| 6 | `courses-show` | Header with gradient backdrop containing course title + stat row; classes list and student sidebar both as `.teacher-card`; gradient student avatars | Low |
| 7 | `session-show` | Apply the same modal-hero treatment as the dashboard (gradient header, status pill); attendance grid uses avatar bubbles + colored status pills; live timer card identical to dashboard's; quick-actions sidebar gets `.teacher-cta` buttons | Medium — share patterns with dashboard modal |

**Phase 2 effort:** ~1 day.

---

## Phase 3 — Tabbed Detail Pages

| # | Page | What changes | Risk |
|---|---|---|---|
| 8 | `students-show` | Use `<x-teacher.tabs>` for the 4 tabs; gradient header banner with student avatar + status badges; per-tab content uses `.teacher-card`; attendance chart with violet accents | Medium — Livewire tab state needs careful preservation |
| 9 | `classes-show` ⚠️ XL | Largest file (2537 lines). Tab interface gets `<x-teacher.tabs>`; each tab redesigned as one or more `.teacher-card`s. Refactor into smaller per-tab partials before/while restyling so the file becomes manageable. Session-management modals match the dashboard's `flux:modal` redesign. | **High** — touch surface huge, easy to break wire actions. Recommend splitting into per-tab partials in a separate prep PR before restyling. |

**Phase 3 effort:** ~3 days (1 for students-show, 2 for classes-show including the partial extraction).

---

## Phase 4 — Timetable Suite

The timetable is the visually richest area and benefits from going last (after the design system has been battle-tested on simpler pages).

| # | Page | What changes | Risk |
|---|---|---|---|
| 10 | `timetable.blade.php` | New view-switcher pill (gradient indicator on active mode); date navigator with violet accents; gradient stat strip identical to dashboard; filter dropdowns inside `<x-teacher.filter-bar>` | Medium |
| 11 | `timetable/day-view` | Time-column timeline with violet hour markers; session blocks use `.teacher-card` with status accent stripe + live timer chip | Low |
| 12 | `timetable/list-view` | Date tile (same component as dashboard's "Coming Up"); session row with avatar bubbles for attendance preview | Low |
| 13 | `timetable/month-view` | Calendar grid: today cell uses `bg-violet-50 ring-violet-500/30`; per-day session-count chip with gradient; mobile card layout uses `.teacher-card` | Low |
| 14 | `timetable/week-view` | Each scrolling day card becomes a small hero (`.teacher-hero` mini-variant); sessions inside use the dashboard's timeline pattern; preserve Alpine scroll-snap | Medium — Alpine state |

**Phase 4 effort:** ~2 days.

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Wire bindings broken during sed-style swaps | Tackle one page at a time, run `php artisan test --filter Teacher<PageName>` after each |
| `classes-show.blade.php` (2537 lines) is too big to safely restyle | Phase 3 splits the file into partials first; restyle only after the split lands |
| Tab state in students-show / classes-show / timetable resets when restyling | Use `wire:key` consistently and verify `setActiveTab` still updates the URL where applicable |
| Visual regressions on mobile | Test every page at 375px viewport (iPhone SE) and 412px (Pixel) — bottom-nav clearance is critical |
| Dark mode parity drift | Each component must be checked in both modes; document any deviation in the component's blade comment |
| Performance — too many gradient layers on long lists | Limit `.teacher-grain` and `radial-gradient` overlays to hero/empty-state surfaces; lists use flat `.teacher-card` |

---

## Out of Scope

- Backend / Livewire component logic changes
- New features (e.g., calendar drag-drop, bulk-edit) — visual layer only
- Admin pages, student pages, live-host pages, HR pages — different design systems / paradigms
- Light-mode-only or dark-mode-only — parity for both stays
- Localization changes — copy stays as-is unless visually broken

---

## Definition of Done

For each page redesign:

1. Markup uses `.teacher-app` scope and the shared Blade components from Phase 0
2. No remaining `bg-blue-*`, `text-blue-*`, `from-indigo-*`, `from-fuchsia-*` classes (replaced with violet ramp + accent palette)
3. Both light + dark modes verified
4. Mobile viewport (375px) renders without horizontal scroll
5. Existing Pest tests pass; no Livewire console warnings
6. Page diff reviewed for accidental wire-binding deletions

---

## Estimated Total Effort

| Phase | Effort |
|---|---|
| 0 — Component extraction | 0.5 day |
| 1 — Index pages (5) | 1.5 days |
| 2 — Simple detail pages (2) | 1 day |
| 3 — Tabbed detail pages (2) | 3 days |
| 4 — Timetable suite (5) | 2 days |
| **Total** | **~8 days** |

Buffer for QA + dark-mode polish: **+2 days** → realistic **~10 working days**.

---

## Recommended Sequence Summary

```
Phase 0 — Components ────────────────────────────────► Day 1 (half)
Phase 1 — Index pages ────────────────────────────────► Days 1–3
Phase 2 — Simple detail ──────────────────────────────► Day 3–4
Phase 3 — Tabs (students, classes) ───────────────────► Days 4–7
Phase 4 — Timetable suite ────────────────────────────► Days 7–9
QA + polish ──────────────────────────────────────────► Days 9–10
```

After approval, the next step is to invoke the implementation cycle starting with Phase 0 (the shared components).
