# Master Timetable Session Modal — Upsell Section

Date: 2026-04-27

## Problem

The session detail modal in `/admin/master-timetable` shows session info, attendance, timeline, and teacher activity, but exposes no upsell information even though `class_sessions` already stores `upsell_funnel_ids`, `upsell_pic_user_ids`, `upsell_teacher_ids`, and `upsell_teacher_commission_rate`, and a session links to `funnelOrders` / `funnelSessions`. Admins viewing a session in the timetable have to context-switch to `/admin/upsell-dashboard` to see whether upsell is configured or how it is performing.

## Goal

Surface per-session upsell configuration and live performance inside the existing modal so admins can answer "is upsell wired up here, and is it working?" without leaving the timetable.

## Design

### Placement

A full-width card inside the modal's left column (`lg:col-span-2`), inserted between the Attendance Summary card and the Teacher Notes block in [resources/views/livewire/admin/master-timetable.blade.php](resources/views/livewire/admin/master-timetable.blade.php).

### Data wiring (Volt PHP block)

- Extend the eager-load list in `selectSession(int $sessionId)` with `funnelOrders` and `funnelSessions` to avoid N+1 when the modal opens.
- Add a public helper method `getUpsellStats(ClassSession $session): array` that returns:
  - `configured` (bool) — true if `upsell_funnel_ids` is non-empty
  - `funnels` — collection from existing `upsellFunnels()` accessor
  - `pics` — collection from existing `upsellPics()` accessor
  - `teachers` — collection from existing `upsellTeachers()` accessor
  - `commission_rate` — `upsell_teacher_commission_rate`
  - `visitors` — `funnelSessions->count()`
  - `conversions` — `funnelOrders->count()`
  - `conversion_rate` — visitors > 0 ? round(conversions / visitors * 100, 1) : 0
  - `revenue` — `funnelOrders->sum('funnel_revenue')`

### UI structure

1. **Header row** — "Upsell" `flux:heading size="sm"` + small badge ("Configured" green / "Not Configured" gray).
2. **Configured branch:**
   - 4 stat tiles in a `grid grid-cols-4 gap-4`, same look/spacing as the Attendance Summary tiles: Visitors (gray), Conversions (blue), Conversion Rate (purple), Revenue (green).
   - Two-column grid below stats:
     - Left: "Funnels" label + `flux:badge` per funnel (joined by Tailwind gap).
     - Right: "Commission Rate" label + value formatted as `XX.XX%`.
   - Two more labelled rows (or a 2-col block): "Upsell PICs" and "Upsell Teachers", each rendering names joined by `, ` (matching the existing PIC row in the Session Info card).
   - Footer: right-aligned `flux:button variant="ghost" size="sm"` linking to `route('admin.upsell-dashboard')` with an `arrow-top-right-on-square` icon and label "View in Upsell Dashboard".
3. **Not configured branch:** centered empty state mirroring the Attendance empty state — `flux:icon name="megaphone"` (or similar) + `No upsell configured for this session`.

### Dark mode

Reuse exact Tailwind class patterns from sibling cards: `bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4`.

### Out of scope

- Editing upsell config from within this modal.
- Per-funnel breakdown of revenue inside the modal (admins go to the dashboard for that — covered by the link).
- Changes to `/livehost/*` or `/live-host/*` (Inertia paradigms not affected).

### Testing

- Visual verification via Playwright on `/admin/master-timetable` for both branches (with upsell configured and without).
- No new model behavior or routes — existing tests under `tests/Feature/Livewire/Admin/ClassUpsellTest.php` already cover the data layer.
