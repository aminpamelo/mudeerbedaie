import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CalendarDays, GraduationCap, Layers, Gauge } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import MonthlyPerformanceTab from '@/livehost/components/mentoring/MonthlyPerformanceTab';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/* ---------------- Shared month window (localStorage) ----------------
 * The window lives in the URL so a refresh or shared link reopens the same view,
 * but a fresh navigation arrives with no query string. We remember the last-used
 * window across visits so the PIC doesn't have to re-pick it every time. */
const PREF_KEY = 'livehost:mentoring:overview:window';

function readWindowPref() {
  if (typeof window === 'undefined') return null;
  try {
    const raw = window.localStorage.getItem(PREF_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writeWindowPref(patch) {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(PREF_KEY, JSON.stringify({ ...(readWindowPref() ?? {}), ...patch }));
  } catch {
    /* storage disabled or over quota — persistence is best-effort */
  }
}

/* ---------------- Shared month filter ----------------
 * Reloads the whole `programs` prop (every section) for the chosen window so all
 * programs stay on the same months for comparison. */
function OverviewMonthFilter({ window: win }) {
  const { year, range, years, months } = win;
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const apply = (nextYear, from, to) => {
    writeWindowPref({ year: nextYear, from, to });
    router.get(
      window.location.pathname,
      { perf_year: nextYear, perf_from: from, perf_to: to },
      { only: ['programs', 'window'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const onYear = (y) => {
    const cap = y === currentYear ? currentMonth : 12;
    apply(y, Math.max(1, cap - 5), cap);
  };

  const monthOptions = MONTH_LABELS.map((label, i) => ({ value: i + 1, label }));

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-1 py-1">
        <CalendarDays className="ml-1 h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
        <select value={year} onChange={(e) => onYear(Number(e.target.value))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] font-medium text-[#0A0A0A] focus:outline-none">
          {[...years].sort((a, b) => b - a).map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
        <span className="text-[#D4D4D4]">·</span>
        <select value={range.from} onChange={(e) => apply(year, Number(e.target.value), Math.max(Number(e.target.value), range.to))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] text-[#525252] focus:outline-none">
          {monthOptions.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
        </select>
        <span className="text-[11px] text-[#A3A3A3]">→</span>
        <select value={range.to} onChange={(e) => apply(year, Math.min(range.from, Number(e.target.value)), Number(e.target.value))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] text-[#525252] focus:outline-none">
          {monthOptions.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
        </select>
      </div>
      <div className="flex items-center gap-1">
        <button type="button" onClick={() => { const cap = year === currentYear ? currentMonth : 12; apply(year, Math.max(1, cap - 5), cap); }} className="rounded-md px-2 py-1 text-[11.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">Last 6</button>
        <button type="button" onClick={() => apply(year, 1, 12)} className="rounded-md px-2 py-1 text-[11.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">Full year</button>
      </div>
      <span className="text-[11px] text-[#A3A3A3]">{months.length} month{months.length === 1 ? '' : 's'}</span>
    </div>
  );
}

export default function MentoringOverview() {
  const { programs = [], window: win } = usePage().props;

  const [programFilter, setProgramFilter] = useState('all'); // 'all' | String(programId)
  const [picFilter, setPicFilter] = useState('all'); // 'all' | String(picId) | 'none'

  // Distinct PICs across every program's mentees — the "group" filter options.
  const picOptions = useMemo(() => {
    const map = new Map();
    programs.forEach((p) => (p.performance?.mentees ?? []).forEach((m) => {
      const key = String(m.pic?.id ?? 'none');
      if (!map.has(key)) {
        map.set(key, { key, name: m.pic?.name ?? 'No PIC assigned', initials: m.pic?.initials ?? '—' });
      }
    }));
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
  }, [programs]);

  // A PIC selected earlier can vanish after a reload (reassignment) — fall back to All.
  useEffect(() => {
    if (picFilter !== 'all' && !picOptions.some((o) => o.key === picFilter)) {
      setPicFilter('all');
    }
  }, [picOptions, picFilter]);

  // Likewise a filtered program can leave the active set (paused/completed/deleted
  // elsewhere) after a reload — don't strand the page on an empty filter.
  useEffect(() => {
    if (programFilter !== 'all' && !programs.some((p) => String(p.program.id) === programFilter)) {
      setProgramFilter('all');
    }
  }, [programs, programFilter]);

  // Sections to render: apply the program filter, then (when a PIC is picked)
  // narrow each program's mentees to that PIC and drop programs left empty.
  const visiblePrograms = useMemo(() => {
    return programs
      .filter((p) => programFilter === 'all' || String(p.program.id) === programFilter)
      .map((p) => {
        if (picFilter === 'all') return p;
        const mentees = (p.performance?.mentees ?? []).filter((m) => String(m.pic?.id ?? 'none') === picFilter);
        return { ...p, performance: { ...p.performance, mentees } };
      })
      .filter((p) => picFilter === 'all' || (p.performance?.mentees ?? []).length > 0);
  }, [programs, programFilter, picFilter]);

  // On a fresh visit (no window params in the URL), re-apply the last-used window
  // so the view isn't reset to the server default.
  const restoredRef = useRef(false);
  useEffect(() => {
    if (restoredRef.current || typeof window === 'undefined' || !win) return;
    restoredRef.current = true;

    const params = new URLSearchParams(window.location.search);
    const urlHasWindow = params.has('perf_year') || params.has('perf_from') || params.has('perf_to');
    const prefs = readWindowPref();
    const canRestore = prefs && Number.isInteger(prefs.year) && Number.isInteger(prefs.from) && Number.isInteger(prefs.to);
    const windowDiffers = canRestore
      && (prefs.year !== win.year || prefs.from !== win.range.from || prefs.to !== win.range.to);

    if (!urlHasWindow && windowDiffers) {
      router.get(
        window.location.pathname,
        { perf_year: prefs.year, perf_from: prefs.from, perf_to: prefs.to },
        { only: ['programs', 'window'], preserveState: true, preserveScroll: true, replace: true },
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const selectClass = 'h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[12.5px] font-medium text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20';

  return (
    <>
      <Head title="Mentoring Overview" />
      <TopBar breadcrumb={['Live Host Desk', 'Mentoring', 'Overview']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        {/* Page header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#ECFDF5] text-[#047857]"><Gauge className="h-5 w-5" strokeWidth={2} /></div>
            <div>
              <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A] sm:text-[28px]">Mentoring Overview</h1>
              <p className="mt-1.5 text-sm text-[#737373]">
                Monthly performance across every <span className="font-medium text-[#059669]">active</span> program, in one place. Edit inline — the same grid as each program's Performance tab.
              </p>
            </div>
          </div>
          <Link href="/livehost/mentoring/programs" className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">
            <GraduationCap className="h-3.5 w-3.5" strokeWidth={2} /> All programs
          </Link>
        </div>

        {/* Filters */}
        {programs.length > 0 && (
          <div className="flex flex-wrap items-center gap-2">
            {win && <OverviewMonthFilter window={win} />}
            <div className="hidden h-6 w-px bg-[#EAEAEA] sm:block" />
            <select value={programFilter} onChange={(e) => setProgramFilter(e.target.value)} className={selectClass} title="Filter by program">
              <option value="all">All programs</option>
              {programs.map((p) => <option key={p.program.id} value={String(p.program.id)}>{p.program.title}</option>)}
            </select>
            <div className="relative inline-flex items-center">
              <Layers className="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
              <select value={picFilter} onChange={(e) => setPicFilter(e.target.value)} className={`${selectClass} pl-8`} title="Filter by PIC group">
                <option value="all">All PICs</option>
                {picOptions.map((o) => <option key={o.key} value={o.key}>{o.name}</option>)}
              </select>
            </div>
            <span className="text-[11px] text-[#A3A3A3]">{visiblePrograms.length} of {programs.length} program{programs.length === 1 ? '' : 's'}</span>
          </div>
        )}

        {/* Sections */}
        {programs.length === 0 ? (
          <div className="rounded-[16px] border border-[#EAEAEA] bg-white py-16 text-center shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <GraduationCap className="mx-auto mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
            <div className="text-sm text-[#737373]">No active mentoring programs.</div>
            <Link href="/livehost/mentoring/programs" className="mt-2 inline-block text-sm font-medium text-[#059669] hover:text-[#047857]">
              Activate a program to see it here
            </Link>
          </div>
        ) : visiblePrograms.length === 0 ? (
          <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-12 text-center text-[12.5px] text-[#A3A3A3]">
            No program matches the current filter.
          </div>
        ) : (
          <div className="space-y-6">
            {visiblePrograms.map((p) => (
              <MonthlyPerformanceTab
                key={p.program.id}
                performance={p.performance}
                program={p.program}
                reloadProp="programs"
                showFilter={false}
                persistUrl={false}
                variant="embedded"
                allowEnroll={false}
              />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

MentoringOverview.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
