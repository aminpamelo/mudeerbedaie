import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  CalendarDays,
  Check,
  ChevronDown,
  ChevronRight,
  Gauge,
  Layers,
  Loader2,
  MessageSquare,
  MoreHorizontal,
  Pencil,
  Search,
  ShieldAlert,
  UserCog,
  UserPlus,
  Video,
  X,
} from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import DailyComments from '@/livehost/components/mentoring/DailyComments';
import DailyLogModal from '@/livehost/components/mentoring/DailyLogModal';
import DisciplinaryModal, { categoryLabel, severityTone } from '@/livehost/components/mentoring/DisciplinaryModal';
import EnrollMenteeModal from '@/livehost/components/mentoring/EnrollMenteeModal';
import MonthDetailModal from '@/livehost/components/mentoring/MonthDetailModal';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/* ---------------- KPI helpers ---------------- */

function scoreTone(score) {
  if (score === null || score === undefined || score === '') {
    return { bg: 'bg-[#F5F5F5]', text: 'text-[#A3A3A3]' };
  }
  const n = Number(score);
  if (n >= 80) return { bg: 'bg-[#ECFDF5]', text: 'text-[#047857]' };
  if (n >= 60) return { bg: 'bg-[#FEF3C7]', text: 'text-[#B45309]' };
  return { bg: 'bg-[#FEE2E2]', text: 'text-[#B91C1C]' };
}

function salesPct(sales, target) {
  if (sales === '' || sales === null || sales === undefined) return null;
  if (!target || target <= 0) return null;
  const n = Number(sales);
  if (Number.isNaN(n)) return null;
  return Math.min(100, Math.round((n / target) * 100));
}

function overallKpi(attitude, sales, target) {
  const a = attitude === '' || attitude === null || attitude === undefined ? null : Math.max(0, Math.min(100, Number(attitude)));
  const s = salesPct(sales, target);
  const parts = [a, s].filter((v) => v !== null && !Number.isNaN(v));
  if (parts.length === 0) return null;
  return Math.round(parts.reduce((x, y) => x + y, 0) / parts.length);
}

function monthAbbr(mo) {
  return (mo.label || '').split(' ')[0] || String(mo.month);
}

function daysInMonth(mo) {
  return new Date(mo.year, mo.month, 0).getDate();
}

function formatRM(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return '–';
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatRMCompact(n) {
  const num = Number(n);
  if (Number.isNaN(num)) return '–';
  const hasSen = Math.round(num) !== num;
  return `RM ${num.toLocaleString(undefined, { minimumFractionDigits: hasSen ? 2 : 0, maximumFractionDigits: 2 })}`;
}

/** Ultra-compact value for the dense day columns (e.g. 2909 → "2.9k", 420 → "420"). */
function kFormat(n) {
  const num = Number(n);
  if (!num) return '·';
  if (num >= 1000) {
    const k = num / 1000;
    return `${k >= 10 ? Math.round(k) : k.toFixed(1)}k`;
  }
  return String(Math.round(num));
}

/** Which months are expanded into day columns — persisted in the URL so a
 * refresh (or a shared link) reopens the same daily view. */
function readExpandParam() {
  if (typeof window === 'undefined') return new Set();
  const raw = new URLSearchParams(window.location.search).get('perf_expand');
  return new Set(raw ? raw.split(',').filter(Boolean) : []);
}

function writeExpandParam(set) {
  if (typeof window === 'undefined') return;
  const url = new URL(window.location.href);
  const vals = [...set];
  if (vals.length) url.searchParams.set('perf_expand', vals.join(','));
  else url.searchParams.delete('perf_expand');
  window.history.replaceState(window.history.state, '', url);
}

/** Day-cell density for expanded months — 'detailed' (default) or 'dots' (compact). URL-persisted. */
function readViewParam() {
  if (typeof window === 'undefined') return 'detailed';
  return new URLSearchParams(window.location.search).get('perf_view') === 'dots' ? 'dots' : 'detailed';
}

function writeViewParam(v) {
  if (typeof window === 'undefined') return;
  const url = new URL(window.location.href);
  if (v === 'dots') url.searchParams.set('perf_view', 'dots');
  else url.searchParams.delete('perf_view');
  window.history.replaceState(window.history.state, '', url);
}

/* ---------------- Per-program view preferences (localStorage) ----------------
 * The month window lives in the URL so a refresh or shared link reopens the same
 * view, but a fresh navigation to this tab arrives with no query string and the
 * server falls back to its default range. We remember the last-used view per
 * program in localStorage and re-apply it on mount so the PIC doesn't have to
 * re-pick their window (and Group by PIC / day-view) every visit. */
const PREF_PREFIX = 'livehost:mentoring:perf:';

function readPrefs(programId) {
  if (typeof window === 'undefined' || !programId) return null;
  try {
    const raw = window.localStorage.getItem(PREF_PREFIX + programId);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writePrefs(programId, patch) {
  if (typeof window === 'undefined' || !programId) return;
  try {
    const current = readPrefs(programId) ?? {};
    window.localStorage.setItem(PREF_PREFIX + programId, JSON.stringify({ ...current, ...patch }));
  } catch {
    /* storage disabled or over quota — persistence is best-effort */
  }
}

/** Expanded months on mount: URL wins (refresh/share), else the saved preference. */
function initExpanded(programId) {
  const fromUrl = readExpandParam();
  if (fromUrl.size) return fromUrl;
  const prefs = readPrefs(programId);
  return new Set(Array.isArray(prefs?.expand) ? prefs.expand : []);
}

/** Day-cell density on mount: an explicit URL param wins, else the saved preference. */
function initDayView(programId) {
  if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('perf_view')) {
    return readViewParam();
  }
  return readPrefs(programId)?.dayView === 'dots' ? 'dots' : 'detailed';
}

function initGroupByPic(programId) {
  const prefs = readPrefs(programId);
  return typeof prefs?.groupByPic === 'boolean' ? prefs.groupByPic : true;
}

function weekdayShort(mo, day) {
  return new Date(mo.year, mo.month - 1, day).toLocaleDateString(undefined, { weekday: 'short' });
}

/* ---------------- Anchored popover (portal-free, fixed) ---------------- */

function AnchoredPopover({ anchor, width = 224, onClose, children }) {
  const ref = useRef(null);
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    const onDown = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onDown);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onDown);
    };
  }, [onClose]);

  const vw = typeof window !== 'undefined' ? window.innerWidth : 1280;
  const vh = typeof window !== 'undefined' ? window.innerHeight : 800;
  const left = Math.max(12, Math.min(anchor.left, vw - width - 12));
  const estH = 280;
  const top = anchor.bottom + 6 + estH > vh - 12 ? Math.max(12, anchor.top - 6 - estH) : anchor.bottom + 6;

  return (
    <div ref={ref} style={{ position: 'fixed', left, top, width, zIndex: 70 }} className="rounded-[14px] border border-[#EAEAEA] bg-white p-1.5 shadow-[0_16px_48px_rgba(0,0,0,0.16)]">
      {children}
    </div>
  );
}

/* ---------------- Modal shell ---------------- */

function Modal({ onClose, children, maxWidth = 'max-w-md' }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className={`w-full ${maxWidth} rounded-[16px] bg-white p-5 shadow-[0_20px_60px_rgba(0,0,0,0.18)]`}>{children}</div>
    </div>
  );
}

/* ---------------- Month filter ---------------- */

function MonthFilter({ performance, program }) {
  const { year, range, years, months } = performance;
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const apply = (nextYear, from, to) => {
    writePrefs(program?.id, { year: nextYear, from, to }); // remember this window for next visit
    const data = { tab: 'performance', perf_year: nextYear, perf_from: from, perf_to: to };
    const params = new URLSearchParams(window.location.search);
    const expand = params.get('perf_expand');
    if (expand) data.perf_expand = expand; // keep the daily view open across a filter change
    const view = params.get('perf_view');
    if (view) data.perf_view = view; // keep the day-cell view mode
    router.get(
      window.location.pathname,
      data,
      { only: ['performance'], preserveState: true, preserveScroll: true, replace: true },
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

/* ---------------- Main tab ---------------- */

export default function MonthlyPerformanceTab({ performance, program, board }) {
  const months = performance?.months ?? [];
  const mentees = performance?.mentees ?? [];
  const pics = performance?.pics ?? [];
  const levels = performance?.levels ?? [];

  const [query, setQuery] = useState('');
  const [groupByPic, setGroupByPic] = useState(() => initGroupByPic(program?.id));
  const [expanded, setExpanded] = useState(() => initExpanded(program?.id)); // expanded months: URL, else saved preference
  const [dayView, setDayView] = useState(() => initDayView(program?.id)); // 'dots' | 'detailed' for expanded day cells
  const [matrices, setMatrices] = useState({}); // monthValue -> { loading, byMentee }
  const [popover, setPopover] = useState(null); // { type, menteeId, anchor }
  const [modal, setModal] = useState(null); // { type, mentee, month, day, presetDate }
  const [enrolling, setEnrolling] = useState(false);

  // Enrolling here refreshes the performance grid (new host row) and the board
  // prop that feeds the enrollable-hosts list, so a second enrol excludes them.
  const enrollModal = enrolling && program && (
    <EnrollMenteeModal
      program={program}
      enrollableHosts={board?.enrollableHosts ?? []}
      assignableMentors={board?.assignableMentors ?? []}
      reloadOnly={['performance', 'board']}
      onClose={() => setEnrolling(false)}
    />
  );

  const currentMonthValue = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }, []);
  const currentDay = useMemo(() => new Date().getDate(), []);
  const detailed = dayView === 'detailed';
  const setView = (v) => { setDayView(v); writeViewParam(v); writePrefs(program?.id, { dayView: v }); };
  const toggleGroupByPic = () => setGroupByPic((v) => {
    const next = !v;
    writePrefs(program?.id, { groupByPic: next });
    return next;
  });

  const visibleMentees = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return mentees;
    return mentees.filter((m) => (m.name ?? '').toLowerCase().includes(q));
  }, [mentees, query]);

  const groups = useMemo(() => {
    if (!groupByPic) return [{ key: 'all', pic: null, mentees: visibleMentees }];
    const map = new Map();
    visibleMentees.forEach((m) => {
      const k = m.pic?.id ?? 'none';
      if (!map.has(k)) map.set(k, { key: String(k), pic: m.pic ?? null, mentees: [] });
      map.get(k).mentees.push(m);
    });
    return [...map.values()].sort((a, b) => (a.pic?.name || '~~~').localeCompare(b.pic?.name || '~~~'));
  }, [visibleMentees, groupByPic]);

  // Flat column descriptors so the header and every body row stay perfectly aligned.
  const columns = useMemo(() => {
    const cols = [];
    months.forEach((mo) => {
      if (expanded.has(mo.value)) {
        cols.push({ key: `${mo.value}:sum`, type: 'summary', mo });
        const n = daysInMonth(mo);
        for (let d = 1; d <= n; d++) cols.push({ key: `${mo.value}:${d}`, type: 'day', mo, day: d });
      } else {
        cols.push({ key: mo.value, type: 'month', mo });
      }
    });
    return cols;
  }, [months, expanded]);

  const cellValue = useCallback((m, mo) => {
    const base = m.scores?.[mo.value] ?? {};
    return { attitude: base.attitude ?? null, sales: base.sales ?? null, notes: base.notes ?? null };
  }, []);

  const fetchMatrix = useCallback((mo) => {
    setMatrices((p) => ({ ...p, [mo.value]: { loading: true, byMentee: p[mo.value]?.byMentee ?? {} } }));
    fetch(`/livehost/mentoring/programs/${program.id}/daily-matrix?year=${mo.year}&month=${mo.month}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((data) => setMatrices((p) => ({ ...p, [mo.value]: { loading: false, byMentee: data.by_mentee ?? {} } })))
      .catch(() => setMatrices((p) => ({ ...p, [mo.value]: { loading: false, byMentee: {} } })));
  }, [program.id]);

  const toggleMonth = (mo) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(mo.value)) {
        next.delete(mo.value);
      } else {
        next.add(mo.value);
        if (!matrices[mo.value]) fetchMatrix(mo);
      }
      writeExpandParam(next);
      writePrefs(program?.id, { expand: [...next] });
      return next;
    });
  };

  // Re-fetch matrices for any month that is expanded but not yet loaded — covers
  // a fresh page load (expanded restored from the URL) and filter changes.
  useEffect(() => {
    months.forEach((mo) => {
      if (expanded.has(mo.value) && !matrices[mo.value]) fetchMatrix(mo);
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [months, fetchMatrix]);

  // On a fresh visit (no window params in the URL), re-apply the last-used month
  // window saved for this program so the view isn't reset to the server default.
  const restoredRef = useRef(false);
  useEffect(() => {
    if (restoredRef.current || !program?.id || typeof window === 'undefined') return;
    restoredRef.current = true;

    const params = new URLSearchParams(window.location.search);
    const urlHasWindow = params.has('perf_year') || params.has('perf_from') || params.has('perf_to');
    const prefs = readPrefs(program.id);
    const canRestore = prefs && Number.isInteger(prefs.year) && Number.isInteger(prefs.from) && Number.isInteger(prefs.to);
    const windowDiffers = canRestore
      && (prefs.year !== performance.year || prefs.from !== performance.range.from || prefs.to !== performance.range.to);

    if (!urlHasWindow && windowDiffers) {
      const data = { tab: 'performance', perf_year: prefs.year, perf_from: prefs.from, perf_to: prefs.to };
      if (expanded.size) data.perf_expand = [...expanded].join(',');
      if (dayView === 'dots') data.perf_view = 'dots';
      router.get(window.location.pathname, data, { only: ['performance'], preserveState: true, preserveScroll: true, replace: true });
      return;
    }

    // No server reload needed — mirror any preference-restored client view into the URL.
    if (!params.has('perf_expand') && expanded.size) writeExpandParam(expanded);
    if (!params.has('perf_view') && dayView === 'dots') writeViewParam(dayView);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const reloadPerformance = () => router.reload({ only: ['performance'], preserveScroll: true, preserveState: true });

  const refreshExpandedMatrices = () => {
    months.forEach((mo) => { if (expanded.has(mo.value)) fetchMatrix(mo); });
  };

  if (mentees.length === 0) {
    return (
      <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-4 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="flex flex-col items-center gap-4 py-10 text-center">
          <p className="text-[13px] text-[#737373]">No mentees to evaluate yet. Enrol a host to start tracking their performance.</p>
          <Button type="button" size="sm" onClick={() => setEnrolling(true)} className="h-9 gap-1.5 bg-[#10B981] text-white hover:bg-[#059669]">
            <UserPlus className="h-3.5 w-3.5" strokeWidth={2.25} /> Enrol host
          </Button>
        </div>
        {enrollModal}
      </section>
    );
  }

  return (
    <section className="rounded-[16px] border border-[#EAEAEA] bg-white p-4 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {/* Header */}
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]"><Gauge className="h-4 w-4" strokeWidth={2} /></div>
          <div>
            <h2 className="text-[15px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">Monthly performance</h2>
            <p className="mt-0.5 text-[12px] text-[#737373]">Sales auto-sum from daily live-session GMV. Expand a month <span className="font-medium text-[#525252]">(▸)</span> to see every day; click a day to log its sales &amp; comment. Click a month to set attitude.</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setEnrolling(true)} className="h-9 gap-1.5 rounded-lg border-[#A7F3D0] bg-white text-[#047857] shadow-none hover:bg-[#ECFDF5] focus-visible:ring-2 focus-visible:ring-[#10B981]/20">
            <UserPlus className="h-3.5 w-3.5" strokeWidth={2.25} /> Enrol host
          </Button>
          <Button type="button" size="sm" onClick={() => setModal({ type: 'dailyLog' })} className="h-9 gap-1.5 bg-[#0A0A0A] text-white hover:bg-[#262626]">
            <CalendarDays className="h-3.5 w-3.5" strokeWidth={2.25} /> Daily log
          </Button>
        </div>
      </div>

      {/* Controls */}
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <MonthFilter performance={performance} program={program} />
        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
          {expanded.size > 0 && (
            <div className="hidden h-9 items-center rounded-lg border border-[#EAEAEA] bg-white p-0.5 text-[12px] font-medium lg:inline-flex">
              <button type="button" onClick={() => setView('dots')} className={`rounded-md px-2.5 py-1 transition-colors ${dayView === 'dots' ? 'bg-[#0A0A0A] text-white' : 'text-[#525252] hover:bg-[#F5F5F5]'}`}>Dots</button>
              <button type="button" onClick={() => setView('detailed')} className={`rounded-md px-2.5 py-1 transition-colors ${dayView === 'detailed' ? 'bg-[#0A0A0A] text-white' : 'text-[#525252] hover:bg-[#F5F5F5]'}`}>Detailed</button>
            </div>
          )}
          <button
            type="button"
            onClick={toggleGroupByPic}
            className={`inline-flex h-9 items-center gap-1.5 rounded-lg border px-2.5 text-[12.5px] font-medium transition-colors ${groupByPic ? 'border-[#0A0A0A] bg-[#0A0A0A] text-white' : 'border-[#EAEAEA] bg-white text-[#525252] hover:bg-[#F5F5F5]'}`}
          >
            <Layers className="h-3.5 w-3.5" strokeWidth={2} /> Group by PIC
          </button>
          <div className="relative w-full sm:w-[200px]">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#A3A3A3]" strokeWidth={2} />
            <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search host name…" className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white pl-9 pr-3 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
          </div>
        </div>
      </div>

      {/* Legend */}
      <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-[#737373]">
        <span className="font-medium text-[#525252]">Overall colour:</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#ECFDF5] ring-1 ring-[#A7F3D0]" /> 80–100</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#FEF3C7] ring-1 ring-[#FDE68A]" /> 60–79</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-[#FEE2E2] ring-1 ring-[#FECACA]" /> below 60</span>
        <span className="ml-2 inline-flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-[#10B981]" /> commented</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" /> sales override</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-[#EF4444]" /> disciplinary</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-1.5 w-1.5 rounded-full bg-[#7C3AED]" /> video</span>
      </div>

      {visibleMentees.length === 0 ? (
        <div className="rounded-[12px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-10 text-center text-[12.5px] text-[#A3A3A3]">No host matches “{query.trim()}”.</div>
      ) : (
        <>
        {/* Desktop / tablet: the full host × month matrix */}
        <div className="hidden -mx-2 overflow-x-auto px-2 lg:block">
          <table className="border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className="sticky left-0 z-20 w-[220px] min-w-[200px] border-b border-r border-[#EAEAEA] bg-white px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Host</th>
                {columns.map((col) => {
                  if (col.type === 'day') {
                    const isToday = col.mo.value === currentMonthValue && col.day === currentDay;
                    return (
                      <th key={col.key} className={`${detailed ? 'w-[92px] min-w-[92px]' : 'w-[38px] min-w-[38px]'} border-b px-0 py-2 text-center text-[10px] font-semibold ${isToday ? 'border-[#A7F3D0] bg-[#ECFDF5]' : 'border-[#F0F0F0] text-[#A3A3A3]'}`}>
                        {isToday ? <span className="inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[#10B981] px-1 text-[9px] font-bold text-white">{col.day}</span> : col.day}
                        {detailed && <div className="mt-0.5 text-[8.5px] font-normal uppercase tracking-wide text-[#C4C4C4]">{weekdayShort(col.mo, col.day)}</div>}
                      </th>
                    );
                  }
                  const isCur = col.mo.value === currentMonthValue;
                  const isExpanded = col.type === 'summary';
                  return (
                    <th key={col.key} className={`min-w-[70px] border-b px-1 py-2 text-center ${isExpanded ? 'border-l border-[#EAEAEA] bg-[#FAFAFA]' : 'border-[#EAEAEA]'}`}>
                      <button
                        type="button"
                        onClick={() => toggleMonth(col.mo)}
                        title={isExpanded ? 'Collapse days' : 'Expand into days'}
                        className={`inline-flex items-center gap-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] transition-colors hover:bg-[#F0F0F0] ${isCur ? 'text-[#0A0A0A]' : 'text-[#A3A3A3]'}`}
                      >
                        {monthAbbr(col.mo)}
                        {isExpanded ? <ChevronDown className="h-3 w-3" strokeWidth={2.5} /> : <ChevronRight className="h-3 w-3" strokeWidth={2.5} />}
                      </button>
                    </th>
                  );
                })}
              </tr>
            </thead>
            {groups.map((group) => (
              <tbody key={group.key}>
                {groupByPic && (
                  <tr>
                    <td className="sticky left-0 z-10 border-b border-r border-[#EAEAEA] bg-[#FAFAFA] px-2 py-1.5">
                      <div className="flex items-center gap-1.5 whitespace-nowrap">
                        <span className="grid h-5 w-5 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{group.pic?.initials ?? '—'}</span>
                        <span className="text-[11.5px] font-semibold text-[#0A0A0A]">{group.pic ? group.pic.name : 'No PIC assigned'}</span>
                        <span className="text-[9.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">PIC</span>
                        <span className="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-medium text-[#737373] ring-1 ring-[#EAEAEA]">{group.mentees.length}</span>
                      </div>
                    </td>
                    <td colSpan={columns.length} className="border-b border-[#EAEAEA] bg-[#FAFAFA]" />
                  </tr>
                )}
                {group.mentees.map((m) => (
                  <tr key={m.id} className="group">
                    <td className="sticky left-0 z-10 border-b border-r border-[#F0F0F0] bg-white px-2 py-2 group-hover:bg-[#FAFAFA]">
                      <div className="flex items-center gap-1.5">
                        <span title={m.name} className="max-w-[150px] truncate text-[13px] font-semibold text-[#0A0A0A]">{m.name}</span>
                        {m.level ? (
                          <button type="button" onClick={(e) => setPopover({ type: 'level', menteeId: m.id, anchor: e.currentTarget.getBoundingClientRect() })} className="shrink-0 rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white transition-transform hover:scale-105" style={{ backgroundColor: m.level.color || '#10B981' }} title="Change level">{m.level.name}</button>
                        ) : (
                          <button type="button" onClick={(e) => setPopover({ type: 'level', menteeId: m.id, anchor: e.currentTarget.getBoundingClientRect() })} className="shrink-0 rounded-full border border-dashed border-[#D4D4D4] px-1.5 py-0.5 text-[9px] font-medium text-[#A3A3A3] hover:border-[#10B981] hover:text-[#10B981]">+ level</button>
                        )}
                        {m.status === 'graduated' && <span className="shrink-0 rounded-full bg-[#EEF2FF] px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-[#4338CA]">Grad</span>}
                        {m.disciplinary_count > 0 && (
                          <button type="button" onClick={() => setModal({ type: 'disciplinary', mentee: m })} className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-[#FEF2F2] px-1.5 py-0.5 text-[9px] font-semibold text-[#B91C1C]" title="Disciplinary records">
                            <ShieldAlert className="h-2.5 w-2.5" strokeWidth={2.5} /> {m.disciplinary_count}
                          </button>
                        )}
                        <button type="button" onClick={(e) => setPopover({ type: 'menu', menteeId: m.id, anchor: e.currentTarget.getBoundingClientRect() })} className="ml-0.5 shrink-0 rounded-md p-1 text-[#A3A3A3] opacity-0 transition-opacity hover:bg-[#F0F0F0] hover:text-[#0A0A0A] group-hover:opacity-100" title="Host actions">
                          <MoreHorizontal className="h-3.5 w-3.5" strokeWidth={2} />
                        </button>
                      </div>
                    </td>
                    {columns.map((col) => {
                      if (col.type === 'day') {
                        const matrix = matrices[col.mo.value];
                        const dobj = matrix?.byMentee?.[m.id]?.[col.day - 1];
                        const loaded = matrix && !matrix.loading;
                        const eff = dobj?.effective ?? 0;
                        const isToday = col.mo.value === currentMonthValue && col.day === currentDay;
                        return (
                          <td key={col.key} className={`border-b p-0.5 text-center ${isToday ? 'border-[#D1FAE5] bg-[#F0FDF4]' : 'border-[#F0F0F0]'}`}>
                            <button
                              type="button"
                              disabled={!loaded}
                              onClick={() => loaded && setModal({ type: 'day', mentee: m, month: col.mo, day: dobj })}
                              title={dobj ? `${dobj.date} · ${eff > 0 ? formatRM(eff) : 'no sales'}${dobj.sessions > 0 ? ` · ${dobj.sessions} live` : ''}${dobj.has_comment ? ' · commented' : ''}${dobj.has_video ? ` · ${dobj.video_count} video${dobj.video_count === 1 ? '' : 's'}` : ''}${dobj.has_disciplinary ? ' · disciplinary' : ''}` : ''}
                              className={[
                                'flex flex-col items-center justify-center rounded transition-colors hover:ring-2 hover:ring-[#10B981]/40 disabled:hover:ring-0',
                                detailed ? 'h-[52px] w-[88px] gap-1' : 'h-10 w-[38px] gap-0.5',
                                dobj?.has_disciplinary ? 'bg-[#FEF2F2]' : eff > 0 ? 'bg-[#FAFAFA]' : '',
                              ].join(' ')}
                            >
                              <span className={`font-bold tabular-nums ${detailed ? 'text-[11px]' : 'text-[9px]'} ${eff > 0 ? 'text-[#0A0A0A]' : 'text-[#D4D4D4]'}`}>
                                {loaded ? (eff > 0 ? (detailed ? formatRMCompact(eff) : kFormat(eff)) : (detailed ? '—' : '·')) : '·'}
                              </span>
                              {detailed ? (
                                <span className="flex h-4 items-center gap-1">
                                  {dobj?.sessions > 0 && <span className="text-[8.5px] font-semibold text-[#A3A3A3]">{dobj.sessions}◦</span>}
                                  {dobj?.has_comment && <MessageSquare className="h-3 w-3 text-[#10B981]" strokeWidth={2.25} />}
                                  {dobj?.override != null && <span className="h-2 w-2 rounded-full bg-[#F59E0B]" />}
                                  {dobj?.has_video && (
                                    <span className="inline-flex items-center gap-0.5 text-[#7C3AED]">
                                      <Video className="h-3 w-3" strokeWidth={2.25} />
                                      {dobj.video_count > 1 && <span className="text-[8px] font-bold">{dobj.video_count}</span>}
                                    </span>
                                  )}
                                  {dobj?.has_disciplinary && <ShieldAlert className="h-3 w-3 text-[#EF4444]" strokeWidth={2.25} />}
                                </span>
                              ) : (
                                <span className="flex h-1.5 items-center gap-0.5">
                                  {dobj?.has_comment && <span className="h-1.5 w-1.5 rounded-full bg-[#10B981]" />}
                                  {dobj?.override != null && <span className="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" />}
                                  {dobj?.has_video && <span className="h-1.5 w-1.5 rounded-full bg-[#7C3AED]" />}
                                  {dobj?.has_disciplinary && <span className="h-1.5 w-1.5 rounded-full bg-[#EF4444]" />}
                                </span>
                              )}
                            </button>
                          </td>
                        );
                      }

                      // month (collapsed) or summary (expanded) → the monthly KPI cell
                      const cell = cellValue(m, col.mo);
                      const target = m.sales_target ?? null;
                      const ov = overallKpi(cell.attitude, cell.sales, target);
                      const tone = scoreTone(ov);
                      const hasData = cell.sales !== null || cell.attitude !== null;
                      const isSummary = col.type === 'summary';
                      return (
                        <td key={col.key} className={`border-b border-[#F0F0F0] p-1 text-center ${isSummary ? 'border-l border-[#EAEAEA] bg-[#FAFAFA]' : ''}`}>
                          <button
                            type="button"
                            onClick={() => setModal({ type: 'attitude', mentee: m, month: col.mo })}
                            title={`${m.name} · ${col.mo.label} · Sales ${cell.sales !== null ? formatRM(cell.sales) : '—'} · Attitude ${cell.attitude ?? '—'} · Overall ${ov != null ? `${ov}%` : '—'}`}
                            className={`flex h-10 w-full min-w-[54px] flex-col items-center justify-center rounded-md leading-none transition-all hover:ring-2 hover:ring-[#10B981]/40 ${tone.bg} ${tone.text}`}
                          >
                            {hasData ? (
                              <>
                                <span className="text-[12.5px] font-bold tabular-nums">{cell.sales !== null ? formatRMCompact(cell.sales) : '–'}</span>
                                <span className="mt-[3px] text-[9px] font-semibold uppercase tracking-[0.04em] tabular-nums opacity-70">A {cell.attitude !== null ? cell.attitude : '–'}</span>
                              </>
                            ) : (
                              <span className="text-[12px] font-bold">–</span>
                            )}
                          </button>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            ))}
          </table>
        </div>

        {/* Mobile: one card per host with a horizontal month rail (chosen design) */}
        <div className="space-y-5 lg:hidden">
          {groups.map((group) => (
            <div key={group.key} className="space-y-3">
              {groupByPic && (
                <div className="flex items-center gap-1.5 px-0.5">
                  <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{group.pic?.initials ?? '—'}</span>
                  <span className="truncate text-[12px] font-semibold text-[#0A0A0A]">{group.pic ? group.pic.name : 'No PIC assigned'}</span>
                  <span className="text-[9.5px] font-medium uppercase tracking-wide text-[#A3A3A3]">PIC</span>
                  <span className="ml-auto shrink-0 rounded-full bg-white px-1.5 py-0.5 text-[10px] font-medium text-[#737373] ring-1 ring-[#EAEAEA]">{group.mentees.length}</span>
                </div>
              )}
              {group.mentees.map((m) => (
                <MobileMenteeCard
                  key={m.id}
                  mentee={m}
                  months={months}
                  expanded={expanded}
                  matrices={matrices}
                  currentMonthValue={currentMonthValue}
                  currentDay={currentDay}
                  cellValue={cellValue}
                  onAttitude={(mm, mo) => setModal({ type: 'attitude', mentee: mm, month: mo })}
                  onDay={(mm, mo, dobj) => setModal({ type: 'day', mentee: mm, month: mo, day: dobj })}
                  onToggleMonth={toggleMonth}
                  onLevel={(mm, anchor) => setPopover({ type: 'level', menteeId: mm.id, anchor })}
                  onMenu={(mm, anchor) => setPopover({ type: 'menu', menteeId: mm.id, anchor })}
                  onDisciplinary={(mm) => setModal({ type: 'disciplinary', mentee: mm })}
                />
              ))}
            </div>
          ))}
        </div>
        </>
      )}

      {/* Popovers */}
      {popover?.type === 'menu' && (
        <RowMenu
          anchor={popover.anchor}
          onClose={() => setPopover(null)}
          onRename={() => setPopover({ ...popover, type: 'rename' })}
          onPic={() => setPopover({ ...popover, type: 'pic' })}
          onLevel={() => setPopover({ ...popover, type: 'level' })}
          onDisciplinary={() => { const m = mentees.find((x) => x.id === popover.menteeId); setPopover(null); setModal({ type: 'disciplinary', mentee: m }); }}
        />
      )}
      {popover?.type === 'rename' && <RenamePopover anchor={popover.anchor} mentee={mentees.find((m) => m.id === popover.menteeId)} onClose={() => setPopover(null)} />}
      {popover?.type === 'level' && <LevelPopover anchor={popover.anchor} mentee={mentees.find((m) => m.id === popover.menteeId)} levels={levels} onClose={() => setPopover(null)} />}
      {popover?.type === 'pic' && <PicPopover anchor={popover.anchor} mentee={mentees.find((m) => m.id === popover.menteeId)} pics={pics} leader={performance.leader} onClose={() => setPopover(null)} />}

      {/* Modals */}
      {modal?.type === 'attitude' && (
        <MonthDetailModal
          mentee={modal.mentee}
          month={modal.month}
          target={modal.mentee.sales_target ?? null}
          onSaved={() => { reloadPerformance(); refreshExpandedMatrices(); }}
          onClose={() => setModal(null)}
        />
      )}
      {modal?.type === 'day' && (
        <DayModal
          mentee={modal.mentee}
          month={modal.month}
          day={modal.day}
          onSaved={() => { reloadPerformance(); fetchMatrix(modal.month); }}
          onLogDisciplinary={() => setModal({ type: 'disciplinary', mentee: modal.mentee, presetDate: modal.day.date })}
          onClose={() => setModal(null)}
        />
      )}
      {modal?.type === 'dailyLog' && <DailyLogModal program={program} onClose={() => setModal(null)} />}
      {modal?.type === 'disciplinary' && <DisciplinaryModal mentee={modal.mentee} presetDate={modal.presetDate ?? null} reloadOnly={['performance']} onClose={() => { setModal(null); refreshExpandedMatrices(); }} />}
      {enrollModal}
    </section>
  );
}

/* ---------------- Mobile: card per host + horizontal month rail ---------------- */

function MobileMenteeCard({ mentee: m, months, expanded, matrices, currentMonthValue, currentDay, cellValue, onAttitude, onDay, onToggleMonth, onLevel, onMenu, onDisciplinary }) {
  const expandedMonths = months.filter((mo) => expanded.has(mo.value));

  return (
    <div className="overflow-hidden rounded-[14px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {/* Header */}
      <div className="flex items-center gap-1.5 border-b border-[#F0F0F0] px-3 py-2.5">
        <span title={m.name} className="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-[#0A0A0A]">{m.name}</span>
        {m.level ? (
          <button type="button" onClick={(e) => onLevel(m, e.currentTarget.getBoundingClientRect())} className="shrink-0 rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white" style={{ backgroundColor: m.level.color || '#10B981' }} title="Change level">{m.level.name}</button>
        ) : (
          <button type="button" onClick={(e) => onLevel(m, e.currentTarget.getBoundingClientRect())} className="shrink-0 rounded-full border border-dashed border-[#D4D4D4] px-1.5 py-0.5 text-[9px] font-medium text-[#A3A3A3]">+ level</button>
        )}
        {m.status === 'graduated' && <span className="shrink-0 rounded-full bg-[#EEF2FF] px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-[#4338CA]">Grad</span>}
        {m.disciplinary_count > 0 && (
          <button type="button" onClick={() => onDisciplinary(m)} className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-[#FEF2F2] px-1.5 py-0.5 text-[9px] font-semibold text-[#B91C1C]" title="Disciplinary records">
            <ShieldAlert className="h-2.5 w-2.5" strokeWidth={2.5} /> {m.disciplinary_count}
          </button>
        )}
        <button type="button" onClick={(e) => onMenu(m, e.currentTarget.getBoundingClientRect())} className="shrink-0 rounded-md p-1 text-[#A3A3A3] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]" title="Host actions">
          <MoreHorizontal className="h-4 w-4" strokeWidth={2} />
        </button>
      </div>

      {/* Month rail — tap a month to set attitude, tap "Days" to expand */}
      <div className="flex gap-2 overflow-x-auto px-3 py-3">
        {months.map((mo) => {
          const cell = cellValue(m, mo);
          const ov = overallKpi(cell.attitude, cell.sales, m.sales_target ?? null);
          const tone = scoreTone(ov);
          const hasData = cell.sales !== null || cell.attitude !== null;
          const isCur = mo.value === currentMonthValue;
          const isExp = expanded.has(mo.value);
          return (
            <div key={mo.value} className="flex shrink-0 flex-col items-center gap-1">
              <button
                type="button"
                onClick={() => onAttitude(m, mo)}
                title={`${m.name} · ${mo.label} · Sales ${cell.sales !== null ? formatRM(cell.sales) : '—'} · Attitude ${cell.attitude ?? '—'} · Overall ${ov != null ? `${ov}%` : '—'}`}
                className={`flex w-[80px] flex-col items-center justify-center gap-0.5 rounded-[10px] border border-black/5 px-2 py-2 leading-none transition-transform active:scale-95 ${tone.bg} ${tone.text} ${isCur ? 'ring-2 ring-[#10B981]/40' : ''}`}
              >
                <span className="text-[10px] font-bold uppercase tracking-[0.04em]">{monthAbbr(mo)}</span>
                {hasData ? (
                  <>
                    <span className="mt-1 text-[13px] font-bold tabular-nums">{cell.sales !== null ? formatRMCompact(cell.sales) : '–'}</span>
                    <span className="mt-0.5 text-[9px] font-semibold uppercase tracking-[0.04em] tabular-nums opacity-70">A {cell.attitude ?? '–'}</span>
                  </>
                ) : (
                  <span className="mt-1 text-[13px] font-bold">–</span>
                )}
              </button>
              <button type="button" onClick={() => onToggleMonth(mo)} className="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[9px] font-medium text-[#A3A3A3] hover:bg-[#F5F5F5] hover:text-[#525252]">
                Days {isExp ? <ChevronDown className="h-2.5 w-2.5" strokeWidth={2.5} /> : <ChevronRight className="h-2.5 w-2.5" strokeWidth={2.5} />}
              </button>
            </div>
          );
        })}
      </div>

      {/* Day rails for each expanded month — tap a day to log sales & comment */}
      {expandedMonths.map((mo) => {
        const matrix = matrices[mo.value];
        const loaded = matrix && !matrix.loading;
        const n = daysInMonth(mo);
        return (
          <div key={mo.value} className="border-t border-[#F0F0F0] px-3 py-2.5">
            <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.05em] text-[#A3A3A3]">
              <CalendarDays className="h-3 w-3" strokeWidth={2} /> {mo.label} · days
              {!loaded && <Loader2 className="h-3 w-3 animate-spin" />}
            </div>
            <div className="flex gap-1 overflow-x-auto pb-1">
              {Array.from({ length: n }, (_, i) => i + 1).map((day) => {
                const dobj = matrix?.byMentee?.[m.id]?.[day - 1];
                const eff = dobj?.effective ?? 0;
                const isToday = mo.value === currentMonthValue && day === currentDay;
                return (
                  <button
                    key={day}
                    type="button"
                    disabled={!loaded}
                    onClick={() => loaded && onDay(m, mo, dobj)}
                    title={dobj ? `${dobj.date}${eff > 0 ? ` · ${formatRMCompact(eff)}` : ' · no sales'}${dobj.sessions > 0 ? ` · ${dobj.sessions} live` : ''}${dobj.has_comment ? ' · commented' : ''}${dobj.has_disciplinary ? ' · disciplinary' : ''}` : ''}
                    className={`flex h-14 w-12 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg border ${isToday ? 'border-[#A7F3D0] bg-[#F0FDF4]' : 'border-[#F0F0F0]'} ${dobj?.has_disciplinary ? 'bg-[#FEF2F2]' : eff > 0 ? 'bg-[#FAFAFA]' : ''} transition-colors disabled:opacity-60`}
                  >
                    <span className={`text-[9px] font-semibold ${isToday ? 'text-[#047857]' : 'text-[#A3A3A3]'}`}>{day}</span>
                    <span className={`text-[10px] font-bold tabular-nums ${eff > 0 ? 'text-[#0A0A0A]' : 'text-[#D4D4D4]'}`}>{loaded ? (eff > 0 ? kFormat(eff) : '·') : '·'}</span>
                    <span className="flex h-1.5 items-center gap-0.5">
                      {dobj?.has_comment && <span className="h-1.5 w-1.5 rounded-full bg-[#10B981]" />}
                      {dobj?.override != null && <span className="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" />}
                      {dobj?.has_video && <span className="h-1.5 w-1.5 rounded-full bg-[#7C3AED]" />}
                      {dobj?.has_disciplinary && <span className="h-1.5 w-1.5 rounded-full bg-[#EF4444]" />}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ---------------- Day modal (daily sales + mandatory comment) ---------------- */

function sessionStatusDot(status) {
  return {
    ended: 'bg-[#10B981]', live: 'bg-[#F59E0B]', scheduled: 'bg-[#A3A3A3]', missed: 'bg-[#EF4444]', cancelled: 'bg-[#EF4444]',
  }[status] ?? 'bg-[#A3A3A3]';
}

function DayModal({ mentee, month, day, onSaved, onLogDisciplinary, onClose }) {
  const [comment, setComment] = useState('');
  const [override, setOverride] = useState(day.override != null ? String(day.override) : '');
  const [busy, setBusy] = useState(false);
  const [detail, setDetail] = useState(null);

  const loadDetail = useCallback(() => {
    fetch(`/livehost/mentoring/mentees/${mentee.id}/day-detail?date=${day.date}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((d) => {
        setDetail(d);
        setComment(d.comments?.find((c) => c.is_mine)?.text ?? '');
      })
      .catch(() => setDetail({ sessions: [], disciplinary: [], comments: [] }));
  }, [mentee.id, day.date]);

  useEffect(() => { loadDetail(); }, [loadDetail]);

  const otherComments = (detail?.comments ?? []).filter((c) => !c.is_mine);

  const save = () => {
    if (!comment.trim() && override === '') return;
    setBusy(true);
    router.patch(
      `/livehost/mentoring/mentees/${mentee.id}/daily-metric`,
      { date: day.date, comment, sales_override: override === '' ? null : Number(override) },
      { preserveScroll: true, preserveState: true, only: ['performance'], onSuccess: () => { onSaved(); onClose(); }, onFinish: () => setBusy(false) },
    );
  };

  const dateLabel = new Date(day.date).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
  const nothingHappened = detail && detail.sessions.length === 0 && detail.disciplinary.length === 0 && (detail.videos?.length ?? 0) === 0;

  return (
    <Modal onClose={onClose} maxWidth="max-w-lg">
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <span className="text-[15px] font-semibold text-[#0A0A0A]">{mentee.name}</span>
            {mentee.level && <span className="rounded-full px-1.5 py-0.5 text-[9.5px] font-semibold text-white" style={{ backgroundColor: mentee.level.color || '#10B981' }}>{mentee.level.name}</span>}
          </div>
          <div className="mt-0.5 text-[12px] text-[#737373]">{dateLabel}</div>
        </div>
        <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
      </div>

      <div className="mb-3 flex items-center justify-between rounded-[10px] bg-[#F9F9F9] px-3 py-2 text-[12px]">
        <span className="text-[#737373]">{day.sessions > 0 ? `${day.sessions} live session${day.sessions === 1 ? '' : 's'}` : 'No live session'} · effective <span className="font-bold text-[#0A0A0A]">{formatRMCompact(day.effective)}</span></span>
        <span className="font-medium text-[#525252]">Auto GMV <span className="font-bold tabular-nums text-[#0A0A0A]">{formatRM(day.auto)}</span></span>
      </div>

      {/* What happened this day */}
      <div className="mb-3 overflow-hidden rounded-[10px] border border-[#EAEAEA]">
        <div className="border-b border-[#F0F0F0] bg-[#FAFAFA] px-3 py-1.5 text-[10.5px] font-semibold uppercase tracking-[0.05em] text-[#737373]">What happened</div>
        <div className="max-h-[220px] space-y-2 overflow-y-auto p-2.5">
          {!detail && <div className="grid place-items-center py-4 text-[#A3A3A3]"><Loader2 className="h-4 w-4 animate-spin" /></div>}
          {nothingHappened && <p className="py-3 text-center text-[12px] text-[#A3A3A3]">No live sessions or records logged for this day.</p>}
          {detail?.sessions.map((s) => (
            <div key={s.id} className="flex items-center justify-between gap-2 rounded-lg bg-[#FAFAFA] px-2.5 py-1.5">
              <div className="min-w-0">
                <div className="flex items-center gap-1.5">
                  <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${sessionStatusDot(s.status)}`} />
                  <span className="truncate text-[12.5px] font-medium text-[#0A0A0A]">{s.title || s.account || 'Live session'}</span>
                </div>
                <div className="mt-0.5 text-[10.5px] text-[#A3A3A3]">{[s.start, s.account, s.status, s.duration_minutes ? `${s.duration_minutes}m` : null].filter(Boolean).join(' · ')}</div>
              </div>
              <span className="shrink-0 text-[12px] font-bold tabular-nums text-[#0A0A0A]">{s.gmv != null ? formatRMCompact(s.gmv) : '—'}</span>
            </div>
          ))}
          {detail?.videos?.map((v) => (
            <div key={v.id} className="flex items-center justify-between gap-2 rounded-lg border border-[#E9E3FB] bg-[#F7F5FE] px-2.5 py-1.5">
              <div className="flex min-w-0 items-center gap-1.5">
                <Video className="h-3.5 w-3.5 shrink-0 text-[#7C3AED]" strokeWidth={2.25} />
                <span className="truncate text-[12px] font-medium text-[#0A0A0A]">{v.title}</span>
              </div>
              {v.link && (
                <a href={v.link} target="_blank" rel="noopener noreferrer" className="shrink-0 text-[11px] font-medium text-[#7C3AED] hover:underline">Open</a>
              )}
            </div>
          ))}
          {detail?.disciplinary.map((r) => (
            <div key={r.id} className="rounded-lg border border-[#FBD5D5] bg-[#FEF2F2] px-2.5 py-1.5">
              <div className="flex flex-wrap items-center gap-1.5">
                <ShieldAlert className="h-3 w-3 text-[#B91C1C]" strokeWidth={2.25} />
                <span className={`rounded px-1 py-0.5 text-[9px] font-bold uppercase tracking-wide ${severityTone(r.severity)}`}>{r.severity}</span>
                <span className="text-[12px] font-semibold text-[#0A0A0A]">{categoryLabel(r.category)}</span>
              </div>
              <div className="mt-0.5 whitespace-pre-wrap text-[11.5px] text-[#525252]">{r.description}</div>
              {r.recorded_by && <div className="mt-0.5 text-[10px] text-[#A3A3A3]">by {r.recorded_by}</div>}
            </div>
          ))}
        </div>
      </div>

      <div className="mb-3">
        <div className="mb-1 text-[12.5px] font-medium text-[#525252]">Comments{detail?.comments?.length ? ` (${detail.comments.length})` : ''}</div>
        {detail ? (
          <DailyComments comments={detail.comments} onChanged={loadDetail} reloadOnly={['performance']} emptyLabel="No comments yet — add the first one below." />
        ) : (
          <div className="flex items-center gap-1.5 text-[12px] text-[#A3A3A3]"><Loader2 className="h-3.5 w-3.5 animate-spin" /> Loading…</div>
        )}
      </div>

      <label className="mb-1 block text-[12.5px] font-medium text-[#525252]">
        {otherComments.length > 0 || detail?.comments?.some((c) => c.is_mine) ? 'Your comment' : 'Daily comment'}
        <span className="ml-1 font-normal text-[#A3A3A3]">· saved under your name</span>
      </label>
      <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={3} autoFocus placeholder="How did they do today?" className="mb-3 w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />

      <label className="mb-1 flex items-center justify-between text-[12.5px] font-medium text-[#525252]">
        <span>Sales override <span className="font-normal text-[#A3A3A3]">(optional)</span></span>
      </label>
      <div className="mb-4 flex items-center gap-2">
        <input type="number" min="0" step="0.01" value={override} onChange={(e) => setOverride(e.target.value)} placeholder={String(day.auto)} className="h-9 w-40 rounded-lg border border-[#EAEAEA] bg-white px-3 text-right text-[14px] tabular-nums text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <span className="text-[11px] text-[#A3A3A3]">RM · leave blank to use auto</span>
      </div>

      <div className="flex items-center justify-between border-t border-[#F0F0F0] pt-3">
        <button type="button" onClick={onLogDisciplinary} className="inline-flex items-center gap-1 text-[12px] font-medium text-[#B91C1C] hover:underline">
          <ShieldAlert className="h-3.5 w-3.5" strokeWidth={2} /> Log disciplinary
        </button>
        <div className="flex gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
          <Button type="button" disabled={busy || (!comment.trim() && override === '')} onClick={save} className="bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:opacity-40">{busy ? 'Saving…' : 'Save day'}</Button>
        </div>
      </div>
    </Modal>
  );
}

/* ---------------- Row menu + edit popovers ---------------- */

function MenuItem({ icon: Icon, label, onClick, danger }) {
  return (
    <button type="button" onClick={onClick} className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-[12.5px] font-medium transition-colors ${danger ? 'text-[#B91C1C] hover:bg-[#FEF2F2]' : 'text-[#0A0A0A] hover:bg-[#F5F5F5]'}`}>
      <Icon className="h-3.5 w-3.5" strokeWidth={2} /> {label}
    </button>
  );
}

function RowMenu({ anchor, onClose, onRename, onLevel, onPic, onDisciplinary }) {
  return (
    <AnchoredPopover anchor={anchor} width={200} onClose={onClose}>
      <MenuItem icon={Pencil} label="Rename host" onClick={onRename} />
      <MenuItem icon={Gauge} label="Change level" onClick={onLevel} />
      <MenuItem icon={UserCog} label="Change PIC" onClick={onPic} />
      <div className="my-1 h-px bg-[#F0F0F0]" />
      <MenuItem icon={ShieldAlert} label="Log disciplinary" onClick={onDisciplinary} danger />
    </AnchoredPopover>
  );
}

function RenamePopover({ anchor, mentee, onClose }) {
  const [name, setName] = useState(mentee?.name ?? '');
  const [busy, setBusy] = useState(false);
  const save = () => {
    if (!name.trim()) return;
    setBusy(true);
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/name`, { name }, {
      preserveScroll: true, preserveState: true, only: ['performance'],
      onSuccess: onClose, onFinish: () => setBusy(false),
    });
  };
  return (
    <AnchoredPopover anchor={anchor} width={248} onClose={onClose}>
      <div className="p-1.5">
        <div className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Rename host</div>
        <input value={name} autoFocus onChange={(e) => setName(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') save(); }} className="mb-1 h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[13px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
        <p className="mb-2 text-[10.5px] text-[#A3A3A3]">Edits the account name everywhere it appears.</p>
        <div className="flex justify-end gap-1.5">
          <Button type="button" size="sm" variant="ghost" onClick={onClose} className="h-8">Cancel</Button>
          <Button type="button" size="sm" disabled={busy || !name.trim()} onClick={save} className="h-8 bg-[#0A0A0A] text-white hover:bg-[#262626]">Save</Button>
        </div>
      </div>
    </AnchoredPopover>
  );
}

function LevelPopover({ anchor, mentee, levels, onClose }) {
  const set = (levelId) => {
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/level`, { level_id: levelId, source: 'manual' }, {
      preserveScroll: true, preserveState: true, only: ['performance'], onSuccess: onClose,
    });
  };
  return (
    <AnchoredPopover anchor={anchor} width={216} onClose={onClose}>
      <div className="mb-1 px-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Set level</div>
      <div className="max-h-[240px] overflow-y-auto">
        {levels.map((l) => (
          <button key={l.id} type="button" onClick={() => set(l.id)} className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[12.5px] hover:bg-[#F5F5F5]">
            <span className="h-3 w-3 shrink-0 rounded-full ring-1 ring-black/5" style={{ backgroundColor: l.color || '#A3A3A3' }} />
            <span className={`font-medium ${mentee?.level_id === l.id ? 'text-[#0A0A0A]' : 'text-[#525252]'}`}>{l.name}</span>
            {mentee?.level_id === l.id && <Check className="ml-auto h-3.5 w-3.5 text-[#10B981]" strokeWidth={2.5} />}
          </button>
        ))}
      </div>
      <div className="my-1 h-px bg-[#F0F0F0]" />
      <button type="button" onClick={() => set(null)} className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[12.5px] text-[#A3A3A3] hover:bg-[#F5F5F5]">Clear level</button>
    </AnchoredPopover>
  );
}

function PicPopover({ anchor, mentee, pics, leader, onClose }) {
  const set = (mentorId) => {
    router.patch(`/livehost/mentoring/mentees/${mentee.id}/pic`, { mentor_user_id: mentorId }, {
      preserveScroll: true, preserveState: true, only: ['performance'], onSuccess: onClose,
    });
  };
  return (
    <AnchoredPopover anchor={anchor} width={232} onClose={onClose}>
      <div className="mb-1 px-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Assign PIC</div>
      <button type="button" onClick={() => set(null)} className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[12.5px] hover:bg-[#F5F5F5]">
        <span className="grid h-5 w-5 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{leader?.initials ?? '—'}</span>
        <span className={`${!mentee?.mentor_user_id ? 'font-semibold text-[#0A0A0A]' : 'text-[#525252]'}`}>Program leader{leader ? ` · ${leader.name}` : ''}</span>
        {!mentee?.mentor_user_id && <Check className="ml-auto h-3.5 w-3.5 text-[#10B981]" strokeWidth={2.5} />}
      </button>
      <div className="my-1 h-px bg-[#F0F0F0]" />
      <div className="max-h-[220px] overflow-y-auto">
        {pics.map((p) => (
          <button key={p.id} type="button" onClick={() => set(p.id)} className="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[12.5px] hover:bg-[#F5F5F5]">
            <span className="grid h-5 w-5 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{p.initials}</span>
            <span className={`${mentee?.mentor_user_id === p.id ? 'font-semibold text-[#0A0A0A]' : 'text-[#525252]'}`}>{p.name}</span>
            {mentee?.mentor_user_id === p.id && <Check className="ml-auto h-3.5 w-3.5 text-[#10B981]" strokeWidth={2.5} />}
          </button>
        ))}
      </div>
    </AnchoredPopover>
  );
}
