# Teacher Dashboard — Modern Redesign

**Date:** 2026-04-27
**Owner:** Aminpamelo
**Status:** Approved (in implementation)

## Goal

Redesign the teacher experience with a modern, vibrant gradient SaaS aesthetic that's interactive and intuitive. Start with the dashboard as the reference design, then standardize the language across the rest of the teacher pages.

## Decisions

| Topic | Choice |
|---|---|
| Visual flavor | Vibrant gradient SaaS (Linear / modern fintech feel) |
| Rollout | Replace `dashboard.blade.php` directly |
| Chrome scope | Dashboard + sidebar + mobile bottom-nav |
| Color anchor | Indigo → Violet primary, with emerald / amber / rose / sky accents |
| Theme support | Light **and** dark mode from day one |
| Typography | Plus Jakarta Sans (display) + Inter (body), all sans-serif |
| Layout | Action-First Hero — gradient hero card → 4 stat cards → today's timeline → right rail (month earnings, activity, upcoming) |

## Foundation Tokens

```css
/* added to resources/css/app.css under @theme */
--color-teacher-from: #4f46e5;   /* indigo-600 */
--color-teacher-to:   #7c3aed;   /* violet-600 */
--color-teacher-success: #10b981; /* emerald-500 */
--color-teacher-warn:    #f59e0b; /* amber-500 */
--color-teacher-danger:  #f43f5e; /* rose-500 */
--color-teacher-info:    #0ea5e9; /* sky-500 */

--font-display: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
--font-sans:    'Inter', ui-sans-serif, system-ui, sans-serif;
```

Hero gradient: `bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-500`.

Body backgrounds:
- Light: `bg-gradient-to-br from-slate-50 via-white to-indigo-50/40`
- Dark: `bg-gradient-to-br from-zinc-950 via-slate-950 to-indigo-950/40`

## Page Structure

1. **Greeting hero** — gradient card with avatar initial bubble, "Selamat datang, {name}", today's date, weather-style summary ("You have N sessions today, M ongoing"), prominent "Start next session" CTA pulled from the very next slot.
2. **Stat strip** — 4 gradient-tinted cards: Today's Sessions (indigo), Week Earnings (emerald), Active Students (violet), Attendance Rate (amber). Each has icon, value, sub-label, and small accent bar.
3. **Today's Schedule** — vertical timeline. Each item is a colorful card with a left accent stripe (color per status), time block, class info, status pill, and inline action button. Ongoing session shows live timer with pulsing dot.
4. **Right rail** — three stacked cards:
   - This Month earnings (large gradient number + sessions/attendance mini-stats)
   - Recent Activity (icon-led timeline)
   - Coming Up (next 5 days)

## Chrome

- **Sidebar (desktop):** restyled with teacher-themed gradient on the brand mark, indigo-tinted active state pill (no hard `bg-blue-600`), smoother hover states.
- **Bottom nav (mobile):** replaces the spotlight blue circle with a gradient indigo→violet pill for the centered Timetable item. Active items use a subtle gradient tint instead of flat `text-blue-600`.

## Out of Scope (this pass)

- Other teacher pages (classes index, sessions index, etc.) — those follow once the dashboard direction is approved.
- Animation library (Motion/GSAP) — Tailwind transitions + CSS-only is sufficient.
- New backend logic — pure presentation layer change.
