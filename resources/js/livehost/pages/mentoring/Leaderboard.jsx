import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  CalendarDays,
  Trophy,
  Crown,
  Medal,
  Users,
  GraduationCap,
  Radio,
  Search,
  Layers,
  TrendingUp,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const fmtRM = (n) => `RM ${Number(n || 0).toLocaleString('en-MY', { maximumFractionDigits: 0 })}`;
const fmtRMFull = (n) => `RM ${Number(n || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtInt = (n) => Number(n || 0).toLocaleString('en-MY');

/* ---------------- Shared month window (localStorage) ----------------
 * Mirrors the Mentoring Overview: the window lives in the URL for refresh /
 * share, and is remembered across fresh visits so the PIC needn't re-pick it. */
const PREF_KEY = 'livehost:mentoring:leaderboard:window';

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
    /* best-effort */
  }
}

/* Rank medal styling — gold / silver / bronze for the top three. */
const RANK_STYLE = {
  1: { ring: '#F59E0B', bg: '#FEF3C7', ink: '#B45309', label: '#92400E' },
  2: { ring: '#9CA3AF', bg: '#F3F4F6', ink: '#4B5563', label: '#374151' },
  3: { ring: '#D97706', bg: '#FFEDD5', ink: '#C2410C', label: '#9A3412' },
};

function Avatar({ initials, size = 40, ring, tone = '#ECFDF5', ink = '#047857' }) {
  return (
    <div
      className="grid shrink-0 place-items-center rounded-full font-semibold"
      style={{
        width: size,
        height: size,
        fontSize: size * 0.36,
        background: tone,
        color: ink,
        boxShadow: ring ? `0 0 0 2px #fff, 0 0 0 4px ${ring}` : undefined,
      }}
    >
      {initials || '—'}
    </div>
  );
}

function LevelChip({ level }) {
  if (!level) return null;
  const color = level.color || '#A3A3A3';
  return (
    <span className="inline-flex items-center gap-1 rounded-full border border-[#EAEAEA] bg-white px-1.5 py-px text-[10.5px] font-medium text-[#525252]">
      <span className="h-1.5 w-1.5 rounded-full" style={{ background: color }} />
      {level.name}
    </span>
  );
}

/* ---------------- Month filter (server reload of hosts + window) ---------------- */
function MonthFilter({ window: win, scope }) {
  const { year, range, years, months } = win;
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const apply = (nextYear, from, to) => {
    writeWindowPref({ year: nextYear, from, to });
    router.get(
      window.location.pathname,
      { scope, perf_year: nextYear, perf_from: from, perf_to: to },
      { only: ['hosts', 'window'], preserveState: true, preserveScroll: true, replace: true },
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

/* Two-option segmented control. */
function Segmented({ value, options, onChange }) {
  return (
    <div className="inline-flex items-center rounded-lg border border-[#EAEAEA] bg-white p-0.5">
      {options.map((o) => {
        const active = value === o.value;
        return (
          <button
            key={o.value}
            type="button"
            onClick={() => onChange(o.value)}
            className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12px] font-medium transition-colors ${active ? 'bg-[#0A0A0A] text-white' : 'text-[#525252] hover:bg-[#F5F5F5]'}`}
          >
            {o.icon}
            {o.label}
          </button>
        );
      })}
    </div>
  );
}

/* ---------------- Overall podium ---------------- */
function PodiumCard({ host, place }) {
  const s = RANK_STYLE[place];
  const isFirst = place === 1;
  return (
    <div
      className={`relative flex flex-col items-center rounded-[16px] border bg-white px-4 text-center shadow-[0_1px_2px_rgba(0,0,0,0.04)] ${isFirst ? 'pb-6 pt-8' : 'pb-5 pt-6'}`}
      style={{ borderColor: isFirst ? s.ring : '#EAEAEA' }}
    >
      <div
        className="absolute -top-3 grid h-7 w-7 place-items-center rounded-full text-[12px] font-bold text-white"
        style={{ background: s.ring }}
      >
        {place}
      </div>
      {isFirst && <Crown className="mb-1 h-5 w-5" style={{ color: s.ring }} strokeWidth={2} />}
      <Avatar initials={host.initials} size={isFirst ? 60 : 48} ring={s.ring} tone={s.bg} ink={s.ink} />
      <div className="mt-2.5 line-clamp-2 text-[13.5px] font-semibold leading-tight text-[#0A0A0A]">{host.name}</div>
      {host.pic && <div className="mt-0.5 text-[11px] text-[#A3A3A3]">PIC · {host.pic.name}</div>}
      <div className={`mt-2 font-bold tracking-tight text-[#0A0A0A] ${isFirst ? 'text-[22px]' : 'text-[18px]'}`}>{fmtRM(host.sales)}</div>
      <div className="mt-0.5 inline-flex items-center gap-1 text-[11px] text-[#737373]">
        <Radio className="h-3 w-3" strokeWidth={2} /> {fmtInt(host.sessions)} live
      </div>
    </div>
  );
}

function Podium({ top }) {
  if (top.length === 0) return null;
  // Visual order: 2nd, 1st, 3rd — the classic podium.
  const order = [top[1], top[0], top[2]].filter(Boolean);
  return (
    <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-3">
      {order.map((h) => <PodiumCard key={h.host_id} host={h} place={h.rank} />)}
    </div>
  );
}

/* ---------------- Grouped ranking ---------------- */
function RankBadge({ rank }) {
  const s = RANK_STYLE[rank];
  if (s) {
    return (
      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full" style={{ background: s.bg }}>
        <Medal className="h-3.5 w-3.5" style={{ color: s.ink }} strokeWidth={2.2} />
      </span>
    );
  }
  return (
    <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#F5F5F5] text-[11px] font-semibold tabular-nums text-[#737373]">
      {rank}
    </span>
  );
}

function HostRow({ host, groupBy, maxSales }) {
  const share = maxSales > 0 ? Math.max(3, Math.round((host.sales / maxSales) * 100)) : 0;
  const secondary = groupBy === 'pic'
    ? host.program?.title
    : (host.pic ? `PIC · ${host.pic.name}` : null);

  return (
    <div className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-[#FAFAFA]">
      <RankBadge rank={host.rank} />
      <Avatar initials={host.initials} size={32} />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="truncate text-[13px] font-medium text-[#0A0A0A]">{host.name}</span>
          <LevelChip level={host.level} />
          {!host.is_mentee && (
            <span className="rounded-full bg-[#F5F5F5] px-1.5 py-px text-[10px] font-medium text-[#A3A3A3]">Bukan mentee</span>
          )}
        </div>
        <div className="mt-1 flex items-center gap-2">
          <div className="h-1.5 w-full max-w-[220px] overflow-hidden rounded-full bg-[#F0F0F0]">
            <div className="h-full rounded-full bg-gradient-to-r from-[#34D399] to-[#10B981]" style={{ width: `${share}%` }} />
          </div>
          {secondary && <span className="hidden truncate text-[11px] text-[#A3A3A3] sm:block">{secondary}</span>}
        </div>
      </div>
      <div className="shrink-0 text-right">
        <div className="text-[13.5px] font-semibold tabular-nums text-[#0A0A0A]" title={fmtRMFull(host.sales)}>{fmtRM(host.sales)}</div>
        <div className="text-[11px] tabular-nums text-[#A3A3A3]">{fmtInt(host.sessions)} live</div>
      </div>
    </div>
  );
}

function GroupCard({ group, groupBy }) {
  const maxSales = group.hosts[0]?.sales ?? 0;
  return (
    <section className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <header className="flex items-center gap-3 border-b border-[#F0F0F0] bg-[#FCFCFC] px-4 py-3">
        {groupBy === 'pic' ? (
          <Avatar initials={group.initials} size={34} tone="#EEF2FF" ink="#4338CA" />
        ) : (
          <div className="grid h-[34px] w-[34px] place-items-center rounded-full bg-[#ECFDF5] text-[#047857]"><GraduationCap className="h-4 w-4" strokeWidth={2} /></div>
        )}
        <div className="min-w-0 flex-1">
          <div className="truncate text-[14px] font-semibold text-[#0A0A0A]">{group.label}</div>
          <div className="text-[11.5px] text-[#A3A3A3]">{group.count} host{group.count === 1 ? '' : 's'} · {fmtInt(group.sessions)} live</div>
        </div>
        <div className="shrink-0 text-right">
          <div className="text-[15px] font-bold tracking-tight text-[#0A0A0A]" title={fmtRMFull(group.total)}>{fmtRM(group.total)}</div>
          <div className="text-[10.5px] uppercase tracking-wide text-[#A3A3A3]">Jumlah sales</div>
        </div>
      </header>
      <div className="divide-y divide-[#F5F5F5]">
        {group.hosts.map((h) => <HostRow key={h.host_id} host={h} groupBy={groupBy} maxSales={maxSales} />)}
      </div>
    </section>
  );
}

function StatChip({ icon, label, value }) {
  return (
    <div className="flex items-center gap-2.5 rounded-[12px] border border-[#EAEAEA] bg-white px-3.5 py-2.5">
      <div className="grid h-8 w-8 place-items-center rounded-lg bg-[#F5F5F5] text-[#525252]">{icon}</div>
      <div>
        <div className="text-[15px] font-bold leading-none tracking-tight text-[#0A0A0A]">{value}</div>
        <div className="mt-1 text-[11px] text-[#A3A3A3]">{label}</div>
      </div>
    </div>
  );
}

export default function Leaderboard() {
  const { hosts = [], programs = [], scope = 'mentees', window: win } = usePage().props;

  const [groupBy, setGroupBy] = useState('program'); // 'pic' | 'program' — default to program view
  const [programFilter, setProgramFilter] = useState('all'); // 'all' | String(programId)
  const [search, setSearch] = useState('');

  const changeScope = (next) => {
    if (next === scope) return;
    router.get(
      window.location.pathname,
      { scope: next, perf_year: win.year, perf_from: win.range.from, perf_to: win.range.to },
      { only: ['hosts', 'scope'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  // A program filter can leave the active set after a reload — reset gracefully.
  useEffect(() => {
    if (programFilter !== 'all' && !programs.some((p) => String(p.id) === programFilter)) {
      setProgramFilter('all');
    }
  }, [programs, programFilter]);

  // Restore last-used window on a fresh visit (no window params in the URL).
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
        { scope, perf_year: prefs.year, perf_from: prefs.from, perf_to: prefs.to },
        { only: ['hosts', 'window'], preserveState: true, preserveScroll: true, replace: true },
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filteredHosts = useMemo(() => {
    const q = search.trim().toLowerCase();
    return hosts.filter((h) => {
      if (programFilter !== 'all' && String(h.program?.id ?? '') !== programFilter) return false;
      if (q && !(h.name || '').toLowerCase().includes(q)) return false;
      return true;
    });
  }, [hosts, programFilter, search]);

  const ranked = useMemo(
    () => [...filteredHosts].sort((a, b) => b.sales - a.sales).map((h, i) => ({ ...h, rank: i + 1 })),
    [filteredHosts],
  );

  const totals = useMemo(() => {
    const sales = ranked.reduce((s, h) => s + h.sales, 0);
    const sessions = ranked.reduce((s, h) => s + h.sessions, 0);
    return { sales, sessions, count: ranked.length, avg: ranked.length ? sales / ranked.length : 0 };
  }, [ranked]);

  const groups = useMemo(() => {
    const map = new Map();
    for (const h of ranked) {
      let key;
      let label;
      let initials = null;
      if (groupBy === 'program') {
        key = h.program ? `p-${h.program.id}` : 'p-none';
        label = h.program?.title ?? 'Tiada program';
      } else {
        key = h.pic ? `pic-${h.pic.id}` : 'pic-none';
        label = h.pic?.name ?? 'Tiada PIC';
        initials = h.pic?.initials ?? null;
      }
      if (!map.has(key)) map.set(key, { key, label, initials, hosts: [] });
      map.get(key).hosts.push(h);
    }
    return [...map.values()]
      .map((g) => {
        const sorted = [...g.hosts].sort((a, b) => b.sales - a.sales).map((h, i) => ({ ...h, rank: i + 1 }));
        return {
          ...g,
          hosts: sorted,
          total: sorted.reduce((s, h) => s + h.sales, 0),
          sessions: sorted.reduce((s, h) => s + h.sessions, 0),
          count: sorted.length,
        };
      })
      .sort((a, b) => b.total - a.total);
  }, [ranked, groupBy]);

  const selectClass = 'h-9 rounded-lg border border-[#EAEAEA] bg-white px-2.5 text-[12.5px] font-medium text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20';

  return (
    <>
      <Head title="Sales Leaderboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Mentoring', 'Leaderboard']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        {/* Page header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#FEF3C7] text-[#B45309]"><Trophy className="h-5 w-5" strokeWidth={2} /></div>
            <div>
              <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A] sm:text-[28px]">Sales Leaderboard</h1>
              <p className="mt-1.5 text-sm text-[#737373]">
                Ranking sales setiap host — Net GMV live session <span className="font-medium text-[#059669]">+ override PIC</span>, dijumlahkan untuk tempoh yang dipilih.
              </p>
            </div>
          </div>
          <Link href="/livehost/mentoring/overview" className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#525252] hover:bg-[#F5F5F5]">
            <TrendingUp className="h-3.5 w-3.5" strokeWidth={2} /> Mentoring Overview
          </Link>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-2">
          {win && <MonthFilter window={win} scope={scope} />}
          <div className="hidden h-6 w-px bg-[#EAEAEA] sm:block" />
          <Segmented
            value={scope}
            onChange={changeScope}
            options={[
              { value: 'mentees', label: 'Mentees', icon: <GraduationCap className="h-3.5 w-3.5" strokeWidth={2} /> },
              { value: 'all', label: 'Semua host', icon: <Users className="h-3.5 w-3.5" strokeWidth={2} /> },
            ]}
          />
          <Segmented
            value={groupBy}
            onChange={setGroupBy}
            options={[
              { value: 'pic', label: 'Ikut PIC', icon: <Layers className="h-3.5 w-3.5" strokeWidth={2} /> },
              { value: 'program', label: 'Ikut Program', icon: <GraduationCap className="h-3.5 w-3.5" strokeWidth={2} /> },
            ]}
          />
          <select value={programFilter} onChange={(e) => setProgramFilter(e.target.value)} className={selectClass} title="Tapis ikut program">
            <option value="all">Semua program</option>
            {programs.map((p) => <option key={p.id} value={String(p.id)}>{p.title}</option>)}
          </select>
          <div className="relative inline-flex items-center">
            <Search className="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-[#A3A3A3]" strokeWidth={2} />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Cari nama host…"
              className={`${selectClass} w-[180px] pl-8 font-normal`}
            />
          </div>
        </div>

        {ranked.length === 0 ? (
          <div className="rounded-[16px] border border-[#EAEAEA] bg-white py-16 text-center shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <Trophy className="mx-auto mb-3 h-10 w-10 text-[#D4D4D4]" strokeWidth={1.5} />
            <div className="text-sm text-[#737373]">
              {scope === 'mentees' ? 'Tiada mentee dalam program aktif untuk tempoh ini.' : 'Tiada data sales untuk tempoh ini.'}
            </div>
            {scope === 'mentees' && (
              <button type="button" onClick={() => changeScope('all')} className="mt-2 inline-block text-sm font-medium text-[#059669] hover:text-[#047857]">
                Tunjuk semua live host →
              </button>
            )}
          </div>
        ) : (
          <>
            {/* KPI strip */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <StatChip icon={<TrendingUp className="h-4 w-4" strokeWidth={2} />} label="Jumlah sales" value={fmtRM(totals.sales)} />
              <StatChip icon={<Users className="h-4 w-4" strokeWidth={2} />} label={scope === 'mentees' ? 'Mentee' : 'Live host'} value={fmtInt(totals.count)} />
              <StatChip icon={<Radio className="h-4 w-4" strokeWidth={2} />} label="Jumlah live" value={fmtInt(totals.sessions)} />
              <StatChip icon={<Trophy className="h-4 w-4" strokeWidth={2} />} label="Purata / host" value={fmtRM(totals.avg)} />
            </div>

            {/* Overall podium */}
            <Podium top={ranked.slice(0, 3)} />

            {/* Grouped rankings */}
            <div className="space-y-4">
              <div className="flex items-center gap-2 text-[12px] font-medium uppercase tracking-wide text-[#A3A3A3]">
                <span>Ranking {groupBy === 'pic' ? 'ikut PIC' : 'ikut program'}</span>
                <span className="h-px flex-1 bg-[#EEEEEE]" />
                <span className="normal-case tracking-normal">{groups.length} kumpulan</span>
              </div>
              {groups.map((g) => <GroupCard key={g.key} group={g} groupBy={groupBy} />)}
            </div>
          </>
        )}
      </div>
    </>
  );
}

Leaderboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
