import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  CalendarDays,
  ChevronDown,
  ChevronRight,
  ExternalLink,
  Grid3x3,
  Layers,
  LayoutGrid,
  List,
  Loader2,
  Search,
  ShieldCheck,
  Sparkles,
  Upload,
  X,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import SearchableSelect, { buildAccountOptions, buildHostOptions } from '@/livehost/components/SearchableSelect';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/* The four coverage indicators, in display order. Each cell tallies how much of
 * an account's live activity is still un-settled for that day/month. */
const INDICATORS = [
  { key: 'needs_upload', short: 'U', label: 'Belum upload', hint: 'host kena upload', dot: '#EF4444', text: 'text-[#B91C1C]' },
  { key: 'needs_verify', short: 'V', label: 'Belum verify', hint: 'PIC kena verify', dot: '#F59E0B', text: 'text-[#B45309]' },
  { key: 'verified', short: '✓', label: 'Verified / linked', hint: 'selesai', dot: '#10B981', text: 'text-[#047857]' },
  { key: 'suggestions', short: '◇', label: 'TikTok suggestion', hint: 'live tiada slot', dot: '#7C3AED', text: 'text-[#6D28D9]' },
];

const METRIC_KEYS = ['needs_upload', 'needs_verify', 'verified', 'suggestions'];

function emptyTotals() {
  return { needs_upload: 0, needs_verify: 0, verified: 0, suggestions: 0, total: 0 };
}

/** Fold one cell's metrics into an accumulator (mutates + returns it). */
function addMetrics(acc, m) {
  if (!m) {
    return acc;
  }
  METRIC_KEYS.forEach((k) => { acc[k] += m[k] ?? 0; });
  acc.total += m.total ?? 0;
  return acc;
}

/** Per-account rollup across the visible month window (from the month summaries). */
function accountWindowTotal(account, months) {
  return months.reduce((acc, mo) => addMetrics(acc, account.scores?.[mo.value]), emptyTotals());
}

/** Column total for one month across a set of accounts (from month summaries). */
function monthColumnTotal(accountList, monthValue) {
  return accountList.reduce((acc, a) => addMetrics(acc, a.scores?.[monthValue]), emptyTotals());
}

/** Rollup across every account in the list for the whole window (grand/subtotal). */
function accountsWindowTotal(accountList, months) {
  return accountList.reduce((acc, a) => addMetrics(acc, accountWindowTotal(a, months)), emptyTotals());
}

/* Per-page view preferences (localStorage). The month window + server filters
 * also live in the URL (for refresh/share), but a fresh navigation arrives with
 * no query string — we remember the last-used view here and re-apply it. */
const PREF_KEY = 'livehost:session-coverage:matrix';

function readPrefs() {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(PREF_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writePrefs(patch) {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    window.localStorage.setItem(PREF_KEY, JSON.stringify({ ...(readPrefs() ?? {}), ...patch }));
  } catch {
    /* storage disabled or over quota — persistence is best-effort */
  }
}

function daysInMonth(mo) {
  return new Date(mo.year, mo.month, 0).getDate();
}

function monthAbbr(mo) {
  return (mo.label || '').split(' ')[0] || String(mo.month);
}

function weekdayShort(mo, day) {
  return new Date(mo.year, mo.month - 1, day).toLocaleDateString(undefined, { weekday: 'short' });
}

function isEmptyCell(m) {
  return !m || (m.total === 0 && (m.suggestions ?? 0) === 0);
}

/** Overall tone: the loudest outstanding action wins. Red = host must upload,
 * amber = PIC must verify, purple = TikTok live with no slot, green = all done. */
function cellTone(m) {
  if (isEmptyCell(m)) {
    return 'bg-white';
  }
  if (m.needs_upload > 0) {
    return 'bg-[#FEF2F2]';
  }
  if (m.needs_verify > 0) {
    return 'bg-[#FFFBEB]';
  }
  if ((m.suggestions ?? 0) > 0) {
    return 'bg-[#F7F5FE]';
  }
  if (m.verified > 0) {
    return 'bg-[#ECFDF5]';
  }
  return 'bg-white';
}

/* ---------------- Indicator chips ---------------- */

function CellIndicators({ m, dots }) {
  if (isEmptyCell(m)) {
    return <span className="text-[12px] font-medium text-[#D4D4D4]">–</span>;
  }
  const active = INDICATORS.filter((i) => (m[i.key] ?? 0) > 0);

  if (dots) {
    return (
      <span className="flex flex-wrap items-center justify-center gap-1">
        {active.map((i) => (
          <span key={i.key} className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: i.dot }} title={`${i.label}: ${m[i.key]}`} />
        ))}
      </span>
    );
  }

  return (
    <span className="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-0.5 leading-none">
      {active.map((i) => (
        <span key={i.key} className={`inline-flex items-center gap-0.5 text-[10.5px] font-bold tabular-nums ${i.text}`} title={i.label}>
          <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: i.dot }} />
          {m[i.key]}
        </span>
      ))}
    </span>
  );
}

/* ---------------- Totals cell (row / group / grand margins) ---------------- */

function TotalCell({ m, tone = 'bg-[#FAFAFA]', muted = false }) {
  return (
    <td className={`border-b border-l border-[#EAEAEA] px-1.5 py-1 text-center ${tone}`}>
      {muted ? (
        <span className="text-[11px] text-[#D4D4D4]">·</span>
      ) : (
        <div className="flex min-h-[34px] items-center justify-center">
          <CellIndicators m={m} dots={false} />
        </div>
      )}
    </td>
  );
}

/* ---------------- Month window filter ---------------- */

function MonthFilter({ coverage, reload }) {
  const { year, range, years, months } = coverage;
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const apply = (nextYear, from, to) => reload({ year: nextYear, from, to });
  const onYear = (y) => {
    const cap = y === currentYear ? currentMonth : 12;
    apply(y, Math.max(1, cap - 5), cap);
  };

  const opts = MONTH_LABELS.map((label, i) => ({ value: i + 1, label }));

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-1 py-1">
        <CalendarDays className="ml-1 h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
        <select value={year} onChange={(e) => onYear(Number(e.target.value))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] font-medium text-[#0A0A0A] focus:outline-none">
          {[...years].sort((a, b) => b - a).map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
        <span className="text-[#D4D4D4]">·</span>
        <select value={range.from} onChange={(e) => apply(year, Number(e.target.value), Math.max(Number(e.target.value), range.to))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] text-[#525252] focus:outline-none">
          {opts.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
        </select>
        <span className="text-[11px] text-[#A3A3A3]">→</span>
        <select value={range.to} onChange={(e) => apply(year, Math.min(range.from, Number(e.target.value)), Number(e.target.value))} className="h-7 rounded-md bg-transparent px-1 text-[12.5px] text-[#525252] focus:outline-none">
          {opts.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
        </select>
      </div>
      <button type="button" onClick={() => { const cap = year === currentYear ? currentMonth : 12; apply(year, Math.max(1, cap - 5), cap); }} className="rounded-md px-2 py-1 text-[11.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">Last 6</button>
      <button type="button" onClick={() => apply(year, 1, 12)} className="rounded-md px-2 py-1 text-[11.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">Full year</button>
      <span className="text-[11px] text-[#A3A3A3]">{months.length} month{months.length === 1 ? '' : 's'}</span>
    </div>
  );
}

/* ---------------- Day drill-in modal ---------------- */

const BUCKET_BADGE = {
  needs_upload: { label: 'Belum upload', cls: 'bg-[#FEF2F2] text-[#B91C1C]' },
  needs_verify: { label: 'Belum verify', cls: 'bg-[#FFFBEB] text-[#B45309]' },
  verified: { label: 'Verified', cls: 'bg-[#ECFDF5] text-[#047857]' },
  rejected: { label: 'Rejected', cls: 'bg-[#FEF2F2] text-[#B91C1C]' },
  other: { label: 'Scheduled', cls: 'bg-[#F5F5F5] text-[#525252]' },
};

function DayDrillModal({ account, date, filterQuery, onClose }) {
  const [data, setData] = useState(null);

  useEffect(() => {
    const q = new URLSearchParams(filterQuery);
    q.set('account', String(account.id));
    q.set('date', date);
    fetch(`/livehost/session-slots/coverage/day?${q.toString()}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => r.json())
      .then(setData)
      .catch(() => setData({ sessions: [], suggestionCount: 0 }));
  }, [account.id, date, filterQuery]);

  const dateLabel = new Date(date).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-lg rounded-[16px] bg-white p-5 shadow-[0_20px_60px_rgba(0,0,0,0.18)]">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <div className="text-[15px] font-semibold text-[#0A0A0A]">{account.label}</div>
            <div className="mt-0.5 text-[12px] text-[#737373]">{dateLabel}</div>
          </div>
          <button type="button" onClick={onClose} className="rounded-md p-1 text-[#737373] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"><X className="h-4 w-4" strokeWidth={2} /></button>
        </div>

        {!data && <div className="grid place-items-center py-8 text-[#A3A3A3]"><Loader2 className="h-5 w-5 animate-spin" /></div>}

        {data && (
          <>
            <div className="mb-3 space-y-1.5">
              <div className="text-[10.5px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Sessions</div>
              {data.sessions.length === 0 && <p className="rounded-lg bg-[#FAFAFA] py-3 text-center text-[12px] text-[#A3A3A3]">Tiada session hari ini.</p>}
              {data.sessions.map((s) => {
                const badge = BUCKET_BADGE[s.bucket] ?? BUCKET_BADGE.other;
                return (
                  <a key={s.id} href={s.url} className="group flex items-center justify-between gap-2 rounded-lg border border-[#EEEEEE] bg-white px-2.5 py-2 hover:border-[#D4D4D4] hover:bg-[#FAFAFA]">
                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5">
                        <span className={`rounded px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide ${badge.cls}`}>{badge.label}</span>
                        <span className="truncate text-[12.5px] font-medium text-[#0A0A0A]">{s.title || s.shop || 'Live session'}</span>
                      </div>
                      <div className="mt-0.5 text-[10.5px] text-[#A3A3A3]">{[s.startTime, s.hostName, s.gmvNet > 0 ? `RM ${s.gmvNet.toLocaleString()}` : null].filter(Boolean).join(' · ')}</div>
                    </div>
                    <ExternalLink className="h-3.5 w-3.5 shrink-0 text-[#C4C4C4] group-hover:text-[#0A0A0A]" strokeWidth={2} />
                  </a>
                );
              })}
            </div>

            {data.suggestionCount > 0 && (
              <a href="/livehost/session-slots/calendar" className="flex items-center justify-between gap-2 rounded-lg border border-[#E9E3FB] bg-[#F7F5FE] px-2.5 py-2 hover:bg-[#F1ECFB]">
                <span className="flex items-center gap-1.5 text-[12px] font-medium text-[#6D28D9]">
                  <Sparkles className="h-3.5 w-3.5" strokeWidth={2.2} />
                  {data.suggestionCount} TikTok live belum ada slot
                </span>
                <span className="text-[11px] font-medium text-[#7C3AED]">Assign →</span>
              </a>
            )}
          </>
        )}
      </div>
    </div>
  );
}

/* ---------------- Main page ---------------- */

export default function SessionCoverageMatrix() {
  const { props } = usePage();
  const coverage = props.coverage ?? { year: new Date().getFullYear(), range: { from: 1, to: 12 }, years: [], months: [], accounts: [] };
  const propFilters = props.filters ?? {};
  const hosts = props.hosts ?? [];
  const liveAccounts = props.liveAccounts ?? [];
  const platformAccounts = props.platformAccounts ?? [];

  const months = coverage.months ?? [];
  const accounts = coverage.accounts ?? [];

  const [host, setHost] = useState(propFilters.host ?? '');
  const [platformAccount, setPlatformAccount] = useState(propFilters.platform_account ?? '');
  const [liveAccount, setLiveAccount] = useState(propFilters.live_account ?? '');
  const [includeUnlinked, setIncludeUnlinked] = useState(Boolean(propFilters.include_unlinked));

  // Searchable filter options so a specific host/account can be typed instead of
  // hunted for in a long native <select>.
  const hostFilterOptions = useMemo(() => buildHostOptions(hosts), [hosts]);
  const accountFilterOptions = useMemo(() => buildAccountOptions(liveAccounts), [liveAccounts]);

  const [query, setQuery] = useState('');
  const [groupByHost, setGroupByHost] = useState(() => { const p = readPrefs(); return typeof p?.groupByHost === 'boolean' ? p.groupByHost : true; });
  const [dayView, setDayView] = useState(() => (readPrefs()?.dayView === 'dots' ? 'dots' : 'detailed')); // 'detailed' | 'dots'
  const [expanded, setExpanded] = useState(() => { const p = readPrefs(); return new Set(Array.isArray(p?.expanded) ? p.expanded : []); });
  const [matrices, setMatrices] = useState({}); // monthValue -> { loading, byAccount }
  const [drill, setDrill] = useState(null); // { account, date }

  const detailed = dayView === 'detailed';
  const dots = !detailed;

  const currentMonthValue = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }, []);
  const currentDay = useMemo(() => new Date().getDate(), []);

  const filterQuery = useCallback(() => {
    const p = new URLSearchParams();
    if (host) p.set('host', host);
    if (platformAccount) p.set('platform_account', platformAccount);
    if (liveAccount) p.set('live_account', liveAccount);
    if (includeUnlinked) p.set('include_unlinked', '1');
    return p.toString();
  }, [host, platformAccount, liveAccount, includeUnlinked]);

  const reload = useCallback((overrides = {}) => {
    const params = {
      year: coverage.year,
      from: coverage.range.from,
      to: coverage.range.to,
      ...(host ? { host } : {}),
      ...(platformAccount ? { platform_account: platformAccount } : {}),
      ...(liveAccount ? { live_account: liveAccount } : {}),
      ...(includeUnlinked ? { include_unlinked: 1 } : {}),
      ...overrides,
    };
    writePrefs({ server: { // remember the window + filters for a fresh visit
      year: params.year,
      from: params.from,
      to: params.to,
      host: params.host ?? '',
      platform_account: params.platform_account ?? '',
      live_account: params.live_account ?? '',
      include_unlinked: params.include_unlinked ? 1 : 0,
    } });
    router.get('/livehost/session-slots/matrix', params, { only: ['coverage', 'filters'], preserveState: true, preserveScroll: true, replace: true });
  }, [coverage.year, coverage.range.from, coverage.range.to, host, platformAccount, liveAccount, includeUnlinked]);

  // Reload from the server when a server-driven filter changes (after mount).
  const mountedRef = useRef(false);
  const suppressReloadRef = useRef(false); // set by the restore effect so its own setState doesn't double-reload
  useEffect(() => {
    if (!mountedRef.current) { mountedRef.current = true; return; }
    if (suppressReloadRef.current) { suppressReloadRef.current = false; return; }
    reload();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [host, platformAccount, liveAccount, includeUnlinked]);

  // On a fresh visit (no window/filter params in the URL), re-apply the last-used
  // window + filters so the view isn't reset to the server default.
  const restoredRef = useRef(false);
  useEffect(() => {
    if (restoredRef.current || typeof window === 'undefined') { return; }
    restoredRef.current = true;
    const s = readPrefs()?.server;
    if (!s) { return; }
    const url = new URLSearchParams(window.location.search);
    const urlHasParams = ['year', 'from', 'to', 'host', 'platform_account', 'live_account', 'include_unlinked'].some((k) => url.has(k));
    if (urlHasParams) { return; } // URL wins (refresh / shared link)
    const windowDiffers = Number.isInteger(s.year) && (s.year !== coverage.year || s.from !== coverage.range.from || s.to !== coverage.range.to);
    const filtersDiffer = (s.host ?? '') !== host || (s.platform_account ?? '') !== platformAccount || (s.live_account ?? '') !== liveAccount || Boolean(s.include_unlinked) !== includeUnlinked;
    if (!windowDiffers && !filtersDiffer) { return; }
    if (filtersDiffer) { // reflect in the select UI; suppress the resulting auto-reload
      suppressReloadRef.current = true;
      setHost(s.host ?? '');
      setPlatformAccount(s.platform_account ?? '');
      setLiveAccount(s.live_account ?? '');
      setIncludeUnlinked(Boolean(s.include_unlinked));
    }
    router.get('/livehost/session-slots/matrix', {
      year: s.year ?? coverage.year,
      from: s.from ?? coverage.range.from,
      to: s.to ?? coverage.range.to,
      ...(s.host ? { host: s.host } : {}),
      ...(s.platform_account ? { platform_account: s.platform_account } : {}),
      ...(s.live_account ? { live_account: s.live_account } : {}),
      ...(s.include_unlinked ? { include_unlinked: 1 } : {}),
    }, { only: ['coverage', 'filters'], preserveState: true, preserveScroll: true, replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchDaily = useCallback((mo) => {
    setMatrices((p) => ({ ...p, [mo.value]: { loading: true, byAccount: p[mo.value]?.byAccount ?? {} } }));
    const q = new URLSearchParams(filterQuery());
    q.set('year', String(mo.year));
    q.set('month', String(mo.month));
    fetch(`/livehost/session-slots/coverage/daily?${q.toString()}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => setMatrices((p) => ({ ...p, [mo.value]: { loading: false, byAccount: data.by_account ?? {} } })))
      .catch(() => setMatrices((p) => ({ ...p, [mo.value]: { loading: false, byAccount: {} } })));
  }, [filterQuery]);

  const toggleMonth = (mo) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(mo.value)) {
        next.delete(mo.value);
      } else {
        next.add(mo.value);
        if (!matrices[mo.value]) fetchDaily(mo);
      }
      writePrefs({ expanded: [...next] });
      return next;
    });
  };

  const changeDayView = (v) => { setDayView(v); writePrefs({ dayView: v }); };
  const toggleGroupByHost = () => setGroupByHost((v) => { const next = !v; writePrefs({ groupByHost: next }); return next; });

  // Re-fetch expanded months whenever the filter set changes (matrices reset).
  useEffect(() => {
    setMatrices({});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterQuery]);
  useEffect(() => {
    months.forEach((mo) => { if (expanded.has(mo.value) && !matrices[mo.value]) fetchDaily(mo); });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [months, matrices, expanded, fetchDaily]);

  const visibleAccounts = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return accounts;
    return accounts.filter((a) => (a.label ?? '').toLowerCase().includes(q));
  }, [accounts, query]);

  const groups = useMemo(() => {
    if (!groupByHost) return [{ key: 'all', host: null, accounts: visibleAccounts }];
    const map = new Map();
    visibleAccounts.forEach((a) => {
      const k = a.host?.id ?? 'none';
      if (!map.has(k)) map.set(k, { key: String(k), host: a.host ?? null, accounts: [] });
      map.get(k).accounts.push(a);
    });
    return [...map.values()].sort((x, y) => (x.host?.name || '~~~').localeCompare(y.host?.name || '~~~'));
  }, [visibleAccounts, groupByHost]);

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

  const dayMetrics = (accountId, mo, day) => {
    const arr = matrices[mo.value]?.byAccount?.[accountId];
    return arr ? arr[day - 1] : null;
  };

  const select = 'h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto';

  return (
    <>
      <Head title="Session Slots · Coverage matrix" />
      <TopBar breadcrumb={['Live Host Desk', 'Session Slots', 'Matrix']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        {/* Header + view toggle */}
        <div className="flex flex-wrap items-end justify-between gap-4 sm:gap-8">
          <div>
            <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A] sm:text-3xl">Coverage Matrix</h1>
            <p className="mt-1.5 text-sm text-[#737373]">Every account by month — expand a month to see each day. Each cell shows what's still un-settled.</p>
          </div>
          <div className="flex items-center rounded-full border border-[#EAEAEA] bg-white p-1 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <Link href="/livehost/session-slots/table" className="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]">
              <List className="h-[13px] w-[13px]" strokeWidth={2} /> Table
            </Link>
            <Link href="/livehost/session-slots/calendar" className="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]">
              <LayoutGrid className="h-[13px] w-[13px]" strokeWidth={2} /> Calendar
            </Link>
            <span className="flex items-center gap-1.5 rounded-full bg-ink px-3.5 py-1.5 text-[12px] font-medium text-white">
              <Grid3x3 className="h-[13px] w-[13px]" strokeWidth={2} /> Matrix
            </span>
          </div>
        </div>

        {/* Controls */}
        <div className="flex flex-col gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <MonthFilter coverage={coverage} reload={reload} />
            <div className="flex flex-wrap items-center gap-2">
              {expanded.size > 0 && (
                <div className="inline-flex h-9 items-center rounded-lg border border-[#EAEAEA] bg-white p-0.5 text-[12px] font-medium">
                  <button type="button" onClick={() => changeDayView('dots')} className={`rounded-md px-2.5 py-1 transition-colors ${dots ? 'bg-[#0A0A0A] text-white' : 'text-[#525252] hover:bg-[#F5F5F5]'}`}>Dots</button>
                  <button type="button" onClick={() => changeDayView('detailed')} className={`rounded-md px-2.5 py-1 transition-colors ${detailed ? 'bg-[#0A0A0A] text-white' : 'text-[#525252] hover:bg-[#F5F5F5]'}`}>Detailed</button>
                </div>
              )}
              <button type="button" onClick={toggleGroupByHost} className={`inline-flex h-9 items-center gap-1.5 rounded-lg border px-2.5 text-[12.5px] font-medium transition-colors ${groupByHost ? 'border-[#0A0A0A] bg-[#0A0A0A] text-white' : 'border-[#EAEAEA] bg-white text-[#525252] hover:bg-[#F5F5F5]'}`}>
                <Layers className="h-3.5 w-3.5" strokeWidth={2} /> Group by host
              </button>
              <div className="relative w-full sm:w-[200px]">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#A3A3A3]" strokeWidth={2} />
                <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search account…" className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white pl-9 pr-3 text-[13px] text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20" />
              </div>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2 border-t border-[#F0F0F0] pt-3">
            <SearchableSelect
              value={host}
              onChange={setHost}
              options={hostFilterOptions}
              placeholder="All hosts"
              searchPlaceholder="Search host by name…"
              emptyLabel="No host found"
              allowClear
              className="w-full min-w-0 sm:w-48"
            />
            <SearchableSelect
              value={liveAccount}
              onChange={setLiveAccount}
              options={accountFilterOptions}
              placeholder="All accounts"
              searchPlaceholder="Search nickname, handle or shop…"
              emptyLabel="No account found"
              allowClear
              className="w-full min-w-0 sm:w-52"
            />
            <select value={platformAccount} onChange={(e) => setPlatformAccount(e.target.value)} className={select}>
              <option value="">All shops</option>
              {platformAccounts.map((pa) => <option key={pa.id} value={pa.id}>{pa.name}{pa.platform ? ` · ${pa.platform}` : ''}</option>)}
            </select>
            <button type="button" onClick={() => setIncludeUnlinked((v) => !v)} aria-pressed={includeUnlinked} className={`inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium transition-colors ${includeUnlinked ? 'border-[#F5D0E4] bg-[#FDF2F8] text-[#9D174D]' : 'border-[#EAEAEA] bg-white text-[#737373] hover:text-[#0A0A0A]'}`}>
              <Sparkles className="h-[13px] w-[13px]" strokeWidth={2.2} /> Include unlinked lives
            </button>
          </div>
        </div>

        {/* Legend — each dot's meaning + whose action it is */}
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[#737373]">
          <span className="font-medium text-[#525252]">Setiap dot:</span>
          {INDICATORS.map((i) => (
            <span key={i.key} className="inline-flex items-center gap-1.5">
              <span className="h-2.5 w-2.5 rounded-full ring-1 ring-black/5" style={{ backgroundColor: i.dot }} />
              <span className="font-medium text-[#525252]">{i.label}</span>
              <span className="text-[#A3A3A3]">· {i.hint}</span>
            </span>
          ))}
        </div>

        {/* Matrix */}
        {visibleAccounts.length === 0 ? (
          <div className="rounded-[16px] border border-dashed border-[#E5E5E5] bg-[#FAFAFA] py-16 text-center text-[13px] text-[#A3A3A3]">
            {query.trim() ? `Tiada account padan "${query.trim()}".` : 'Tiada account untuk dipaparkan.'}
          </div>
        ) : (
          <div className="-mx-2 overflow-x-auto px-2">
            <table className="border-separate border-spacing-0 text-sm">
              <thead>
                <tr>
                  <th className="sticky left-0 z-20 w-[220px] min-w-[200px] border-b border-r border-[#EAEAEA] bg-white px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-[#737373]">Account</th>
                  {columns.map((col) => {
                    if (col.type === 'day') {
                      const isToday = col.mo.value === currentMonthValue && col.day === currentDay;
                      return (
                        <th key={col.key} className={`${detailed ? 'w-[64px] min-w-[64px]' : 'w-[34px] min-w-[34px]'} border-b px-0 py-2 text-center text-[10px] font-semibold ${isToday ? 'border-[#A7F3D0] bg-[#ECFDF5]' : 'border-[#F0F0F0] text-[#A3A3A3]'}`}>
                          {isToday ? <span className="inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[#10B981] px-1 text-[9px] font-bold text-white">{col.day}</span> : col.day}
                          {detailed && <div className="mt-0.5 text-[8.5px] font-normal uppercase tracking-wide text-[#C4C4C4]">{weekdayShort(col.mo, col.day)}</div>}
                        </th>
                      );
                    }
                    const isCur = col.mo.value === currentMonthValue;
                    const isSummary = col.type === 'summary';
                    return (
                      <th key={col.key} className={`min-w-[76px] border-b px-1 py-2 text-center ${isSummary ? 'border-l border-[#EAEAEA] bg-[#FAFAFA]' : 'border-[#EAEAEA]'}`}>
                        <button type="button" onClick={() => toggleMonth(col.mo)} title={isSummary ? 'Collapse days' : 'Expand into days'} className={`inline-flex items-center gap-0.5 whitespace-nowrap rounded-md px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] transition-colors hover:bg-[#F0F0F0] ${isCur ? 'text-[#0A0A0A]' : 'text-[#A3A3A3]'}`}>
                          {monthAbbr(col.mo)}
                          {isSummary ? <ChevronDown className="h-3 w-3" strokeWidth={2.5} /> : <ChevronRight className="h-3 w-3" strokeWidth={2.5} />}
                        </button>
                      </th>
                    );
                  })}
                  <th className="min-w-[104px] border-b border-l border-[#EAEAEA] bg-[#FAFAFA] px-1.5 py-2 text-center text-[11px] font-semibold uppercase tracking-[0.05em] text-[#525252]">Σ Total</th>
                </tr>
              </thead>
              {groups.map((group) => (
                <tbody key={group.key}>
                  {groupByHost && (
                    <tr>
                      <td className="sticky left-0 z-10 border-b border-r border-[#EAEAEA] bg-[#FAFAFA] px-2 py-1.5">
                        <div className="flex items-center gap-1.5 whitespace-nowrap">
                          <span className="grid h-5 w-5 place-items-center rounded-full bg-[#E5E7EB] text-[9px] font-bold text-[#525252]">{group.host?.initials ?? '—'}</span>
                          <span className="text-[11.5px] font-semibold text-[#0A0A0A]">{group.host ? group.host.name : 'No host assigned'}</span>
                          <span className="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-medium text-[#737373] ring-1 ring-[#EAEAEA]">{group.accounts.length}</span>
                        </div>
                      </td>
                      <td colSpan={columns.length} className="border-b border-[#EAEAEA] bg-[#FAFAFA]" />
                      <TotalCell m={accountsWindowTotal(group.accounts, months)} tone="bg-[#F5F5F5]" />
                    </tr>
                  )}
                  {group.accounts.map((a) => (
                    <tr key={a.id} className="group">
                      <td className="sticky left-0 z-10 border-b border-r border-[#F0F0F0] bg-white px-2 py-2 group-hover:bg-[#FAFAFA]">
                        <div className="flex items-center gap-1.5">
                          <span title={a.label} className="max-w-[160px] truncate text-[13px] font-semibold text-[#0A0A0A]">{a.label}</span>
                          {a.needsReview && <span className="shrink-0 rounded-full bg-[#FEF3C7] px-1.5 py-0.5 text-[9px] font-medium text-[#B45309]">review</span>}
                        </div>
                      </td>
                      {columns.map((col) => {
                        if (col.type === 'day') {
                          const m = dayMetrics(a.id, col.mo, col.day);
                          const loaded = matrices[col.mo.value] && !matrices[col.mo.value].loading;
                          const isToday = col.mo.value === currentMonthValue && col.day === currentDay;
                          return (
                            <td key={col.key} className={`border-b p-0.5 text-center ${isToday ? 'border-[#D1FAE5] bg-[#F0FDF4]' : 'border-[#F0F0F0]'}`}>
                              <button type="button" disabled={!loaded} onClick={() => setDrill({ account: a, date: m?.date })} className={`flex min-h-[38px] w-full flex-col items-center justify-center rounded transition-all hover:ring-2 hover:ring-[#10B981]/40 disabled:hover:ring-0 ${detailed ? 'px-0.5 py-1' : 'p-0.5'} ${loaded ? cellTone(m) : ''}`}>
                                {loaded ? <CellIndicators m={m} dots={dots} /> : <span className="text-[10px] text-[#D4D4D4]">·</span>}
                              </button>
                            </td>
                          );
                        }
                        const m = a.scores?.[col.mo.value];
                        const isSummary = col.type === 'summary';
                        return (
                          <td key={col.key} className={`border-b border-[#F0F0F0] p-1 text-center ${isSummary ? 'border-l border-[#EAEAEA] bg-[#FAFAFA]' : ''}`}>
                            <button type="button" onClick={() => toggleMonth(col.mo)} title="Expand into days" className={`flex min-h-[40px] w-full min-w-[64px] flex-col items-center justify-center rounded-md px-1 py-1 transition-all hover:ring-2 hover:ring-[#10B981]/30 ${cellTone(m)}`}>
                              <CellIndicators m={m} dots={false} />
                            </button>
                          </td>
                        );
                      })}
                      <TotalCell m={accountWindowTotal(a, months)} tone="bg-[#FAFAFA] group-hover:bg-[#F5F5F5]" />
                    </tr>
                  ))}
                </tbody>
              ))}
              <tbody>
                <tr>
                  <td className="sticky left-0 z-10 border-t-2 border-r border-[#E5E5E5] bg-white px-2 py-2.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#0A0A0A]">Grand total</td>
                  {columns.map((col) => {
                    if (col.type === 'day') {
                      const byAcc = matrices[col.mo.value]?.byAccount;
                      const loaded = matrices[col.mo.value] && !matrices[col.mo.value].loading;
                      if (!loaded || !byAcc) {
                        return <td key={col.key} className="border-t-2 border-[#E5E5E5] px-0 py-2 text-center text-[10px] text-[#D4D4D4]">·</td>;
                      }
                      const t = visibleAccounts.reduce((acc, a) => addMetrics(acc, byAcc[a.id]?.[col.day - 1]), emptyTotals());
                      return (
                        <td key={col.key} className="border-t-2 border-[#E5E5E5] px-0.5 py-2 text-center">
                          <CellIndicators m={t} dots={dots} />
                        </td>
                      );
                    }
                    const isSummary = col.type === 'summary';
                    return (
                      <td key={col.key} className={`border-t-2 border-[#E5E5E5] px-1 py-2 text-center ${isSummary ? 'border-l bg-[#FAFAFA]' : ''}`}>
                        <CellIndicators m={monthColumnTotal(visibleAccounts, col.mo.value)} dots={false} />
                      </td>
                    );
                  })}
                  <td className="border-l border-t-2 border-[#E5E5E5] bg-[#F5F5F5] px-1.5 py-2 text-center">
                    <div className="flex min-h-[34px] items-center justify-center">
                      <CellIndicators m={accountsWindowTotal(visibleAccounts, months)} dots={false} />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>

      {drill?.date && <DayDrillModal account={drill.account} date={drill.date} filterQuery={filterQuery()} onClose={() => setDrill(null)} />}
    </>
  );
}

SessionCoverageMatrix.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
