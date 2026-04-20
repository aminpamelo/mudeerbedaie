import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, LayoutGrid, List, Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import SessionSlotFormModal from '@/livehost/components/SessionSlotFormModal';
import SessionSlotDetailModal from '@/livehost/components/SessionSlotDetailModal';

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAY_NAMES_FULL = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
];

const HOUR_PX = 72;
const DEFAULT_HOUR_START = 8;
const DEFAULT_HOUR_END = 24;

const STATUS_OPTIONS = [
  { value: '', label: 'Any status' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];

const PLATFORM_STYLES = {
  'tiktok-shop': {
    bar: 'bg-[#EC4899]',
    tint: 'from-[#FDF2F8]',
    text: 'text-[#9D174D]',
    label: 'TTS',
  },
  tiktok: {
    bar: 'bg-[#EC4899]',
    tint: 'from-[#FDF2F8]',
    text: 'text-[#9D174D]',
    label: 'TT',
  },
  'facebook-shop': {
    bar: 'bg-[#3B82F6]',
    tint: 'from-[#EFF6FF]',
    text: 'text-[#1D4ED8]',
    label: 'FB',
  },
  facebook: {
    bar: 'bg-[#3B82F6]',
    tint: 'from-[#EFF6FF]',
    text: 'text-[#1D4ED8]',
    label: 'FB',
  },
  shopee: {
    bar: 'bg-[#F97316]',
    tint: 'from-[#FFF7ED]',
    text: 'text-[#B45309]',
    label: 'SPE',
  },
};

const DEFAULT_STYLE = {
  bar: 'bg-[#A3A3A3]',
  tint: 'from-[#F5F5F5]',
  text: 'text-[#525252]',
  label: 'OTH',
};

// Per-account color palette for swim lanes. Each active account is assigned a
// stable slot by sort order so colors don't shift across re-renders.
const ACCOUNT_PALETTE = [
  { dot: '#EC4899', soft: '#FDF2F8', tint: '#FCE7F3', border: '#F9A8D4', text: '#9D174D' }, // rose
  { dot: '#8B5CF6', soft: '#F5F3FF', tint: '#EDE9FE', border: '#C4B5FD', text: '#5B21B6' }, // violet
  { dot: '#F59E0B', soft: '#FFFBEB', tint: '#FEF3C7', border: '#FCD34D', text: '#92400E' }, // amber
  { dot: '#10B981', soft: '#ECFDF5', tint: '#D1FAE5', border: '#6EE7B7', text: '#065F46' }, // emerald
  { dot: '#0EA5E9', soft: '#F0F9FF', tint: '#E0F2FE', border: '#7DD3FC', text: '#075985' }, // sky
  { dot: '#64748B', soft: '#F8FAFC', tint: '#F1F5F9', border: '#CBD5E1', text: '#334155' }, // slate
];

const FALLBACK_ACCOUNT_COLOR = {
  dot: '#A3A3A3',
  soft: '#FAFAFA',
  tint: '#F5F5F5',
  border: '#E5E5E5',
  text: '#525252',
};

const LANE_MIN_WIDTH = 96;

function platformStyle(slug) {
  return PLATFORM_STYLES[slug] ?? DEFAULT_STYLE;
}

function parseHM(time) {
  if (!time) {
    return { h: 0, m: 0 };
  }
  const [h, m] = time.split(':').map((v) => Number(v));
  return { h: Number.isFinite(h) ? h : 0, m: Number.isFinite(m) ? m : 0 };
}

function formatHour(h) {
  if (h === 0 || h === 24) {
    return '12 AM';
  }
  if (h === 12) {
    return '12 PM';
  }
  return h > 12 ? `${h - 12} PM` : `${h} AM`;
}

function formatTimeLabel(time) {
  const { h, m } = parseHM(time);
  const suffix = h >= 12 ? 'PM' : 'AM';
  const display = h === 0 ? 12 : h > 12 ? h - 12 : h;
  return `${display}:${String(m).padStart(2, '0')} ${suffix}`;
}

function formatWeekLabel(weekStart, weekEnd) {
  const start = new Date(weekStart);
  const end = new Date(weekEnd);
  const fmt = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });
  const year = start.getFullYear();
  return `${fmt.format(start)} – ${fmt.format(end)}, ${year}`;
}

function addDays(iso, days) {
  const d = new Date(iso);
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

function todayIso() {
  const now = new Date();
  const tzOffset = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - tzOffset).toISOString().slice(0, 10);
}

function dayIndexOf(iso) {
  return new Date(iso).getDay();
}

export default function SessionSlotsCalendar() {
  const {
    sessionSlots,
    weekStart,
    weekEnd,
    filters,
    hosts,
    platformAccounts,
    timeSlots,
    hostPlatformPivots,
    flash,
  } = usePage().props;

  const [host, setHost] = useState(filters?.host ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');
  const [mode, setMode] = useState(filters?.mode ?? '');

  const [createOpen, setCreateOpen] = useState(false);
  const [createPrefill, setCreatePrefill] = useState(null);
  const [editTarget, setEditTarget] = useState(null);
  const [detailTarget, setDetailTarget] = useState(null);

  const { hourStart, hourEnd, totalHeight } = useMemo(() => {
    const times = [];
    const push = (t) => {
      if (!t) {
        return;
      }
      const { h, m } = parseHM(t);
      times.push(h + m / 60);
    };

    for (const ts of timeSlots) {
      push(ts.startTime);
      push(ts.endTime);
    }
    for (const s of sessionSlots) {
      push(s.startTime);
      push(s.endTime);
    }

    let start = DEFAULT_HOUR_START;
    let end = DEFAULT_HOUR_END;
    if (times.length > 0) {
      start = Math.min(DEFAULT_HOUR_START, Math.floor(Math.min(...times)));
      end = Math.max(DEFAULT_HOUR_END, Math.ceil(Math.max(...times)));
    }
    start = Math.max(0, start);
    end = Math.min(24, end);

    return { hourStart: start, hourEnd: end, totalHeight: (end - start) * HOUR_PX };
  }, [timeSlots, sessionSlots]);

  // Resolve the set of accounts that should render as swim lanes. When the
  // platform filter is set, lanes collapse to just that account (matches
  // single-column behaviour). Otherwise, collect every account referenced by
  // a session or a time slot for the current week so empty lanes aren't shown.
  const activeAccounts = useMemo(() => {
    if (platformAccount) {
      const id = Number(platformAccount);
      const meta = platformAccounts.find((pa) => Number(pa.id) === id);
      return meta ? [meta] : [];
    }

    const ids = new Set();
    for (const s of sessionSlots) {
      if (s.platformAccountId) {
        ids.add(Number(s.platformAccountId));
      }
    }
    for (const ts of timeSlots) {
      if (ts.platformAccountId) {
        ids.add(Number(ts.platformAccountId));
      }
    }

    return Array.from(ids)
      .map((id) => platformAccounts.find((pa) => Number(pa.id) === id))
      .filter(Boolean)
      .sort((a, b) => String(a.name).localeCompare(String(b.name)));
  }, [platformAccount, sessionSlots, timeSlots, platformAccounts]);

  const accountColorMap = useMemo(() => {
    const map = new Map();
    activeAccounts.forEach((account, i) => {
      map.set(Number(account.id), ACCOUNT_PALETTE[i % ACCOUNT_PALETTE.length]);
    });
    return map;
  }, [activeAccounts]);

  const laneCount = Math.max(1, activeAccounts.length);
  const showLanes = activeAccounts.length > 1;
  const minGridWidth = 64 + 7 * laneCount * LANE_MIN_WIDTH;

  const colorForAccount = (accountId) =>
    accountColorMap.get(Number(accountId)) ?? FALLBACK_ACCOUNT_COLOR;

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.host ?? '') === host &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.status ?? '') === status &&
      (initial.mode ?? '') === mode
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/session-slots/calendar',
        {
          host: host || undefined,
          platform_account: platformAccount || undefined,
          status: status || undefined,
          mode: mode || undefined,
          week_of: weekStart,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [host, platformAccount, status, mode, filters, weekStart]);

  const goToWeek = (isoDate) => {
    router.get(
      '/livehost/session-slots/calendar',
      {
        host: host || undefined,
        platform_account: platformAccount || undefined,
        status: status || undefined,
        mode: mode || undefined,
        week_of: isoDate,
      },
      { preserveScroll: true }
    );
  };

  const todayDow = dayIndexOf(todayIso());
  const isCurrentWeek = todayIso() >= weekStart && todayIso() <= weekEnd;

  const slotsByDay = useMemo(() => {
    const groups = Array.from({ length: 7 }, () => []);

    for (const slot of sessionSlots) {
      const dow = Number(slot.dayOfWeek);
      if (dow >= 0 && dow <= 6) {
        groups[dow].push(slot);
      }
    }

    return groups;
  }, [sessionSlots]);

  const dateForDow = (dow) => addDays(weekStart, dow);

  const handleScaffoldClick = (dow, timeSlot, laneAccount = null) => {
    setCreatePrefill({
      dayOfWeek: dow,
      timeSlotId: timeSlot?.id ?? null,
      platformAccountId: timeSlot?.platformAccountId ?? laneAccount?.id ?? null,
      scheduleDate: addDays(weekStart, dow),
    });
    setCreateOpen(true);
  };

  const openCreateModal = () => {
    setCreatePrefill(null);
    setCreateOpen(true);
  };

  const newSlotAction = (
    <Button
      size="sm"
      onClick={openCreateModal}
      className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]"
    >
      <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
      New session slot
    </Button>
  );

  const handleEdit = (slot) => {
    setEditTarget(slot);
  };

  const handleDelete = (slot) => {
    if (
      window.confirm(
        `Delete the ${DAY_NAMES_FULL[slot.dayOfWeek]} ${slot.timeSlotLabel} session slot?`
      )
    ) {
      router.delete(`/livehost/session-slots/${slot.id}`, {
        preserveScroll: true,
      });
    }
  };

  const nowPosition = useMemo(() => {
    if (!isCurrentWeek) {
      return null;
    }
    const now = new Date();
    const minutes = (now.getHours() - hourStart) * 60 + now.getMinutes();
    if (minutes < 0 || minutes >= (hourEnd - hourStart) * 60) {
      return null;
    }
    return (minutes / 60) * HOUR_PX;
  }, [isCurrentWeek, hourStart, hourEnd]);

  return (
    <>
      <Head title="Session Slots · Calendar" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Session Slots', 'Calendar']}
        actions={newSlotAction}
      />

      <div className="space-y-6 p-8">
        {/* Header */}
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Session Calendar
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              Weekly time grid — click an empty slot to assign, or a block to manage.
            </p>
          </div>

          {/* View toggle */}
          <div className="flex items-center rounded-full border border-[#EAEAEA] bg-white p-1 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <Link
              href="/livehost/session-slots/table"
              className="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]"
            >
              <List className="h-[13px] w-[13px]" strokeWidth={2} />
              Table
            </Link>
            <span className="flex items-center gap-1.5 rounded-full bg-ink px-3.5 py-1.5 text-[12px] font-medium text-white">
              <LayoutGrid className="h-[13px] w-[13px]" strokeWidth={2} />
              Calendar
            </span>
          </div>
        </div>

        {flash?.error && (
          <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {flash.error}
          </div>
        )}
        {flash?.success && (
          <div className="rounded-[12px] border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#065F46]">
            {flash.success}
          </div>
        )}

        {/* Filters + week nav */}
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex flex-wrap items-center gap-3">
            <select
              value={host}
              onChange={(e) => setHost(e.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">All hosts</option>
              <option value="unassigned">Unassigned only</option>
              {hosts.map((h) => (
                <option key={h.id} value={h.id}>
                  {h.name}
                </option>
              ))}
            </select>
            <select
              value={platformAccount}
              onChange={(e) => setPlatformAccount(e.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">All TikTok accounts</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name} {pa.platform ? `· ${pa.platform}` : ''}
                </option>
              ))}
            </select>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            <select
              value={mode}
              onChange={(e) => setMode(e.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">Template & dated</option>
              <option value="template">Weekly template</option>
              <option value="dated">Dated only</option>
            </select>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => goToWeek(addDays(weekStart, -7))}
              className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#EAEAEA] text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
              title="Previous week"
            >
              <ChevronLeft className="h-4 w-4" strokeWidth={2} />
            </button>
            <button
              type="button"
              onClick={() => goToWeek(todayIso())}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3.5 text-[13px] font-medium tabular-nums text-[#0A0A0A] hover:bg-[#F5F5F5]"
            >
              {formatWeekLabel(weekStart, weekEnd)}
            </button>
            <button
              type="button"
              onClick={() => goToWeek(addDays(weekStart, 7))}
              className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#EAEAEA] text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
              title="Next week"
            >
              <ChevronRight className="h-4 w-4" strokeWidth={2} />
            </button>
          </div>
        </div>

        {/* Legend — one chip per active account */}
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px]">
          <span className="font-mono text-[10px] uppercase tracking-wider text-[#A3A3A3]">
            Accounts
          </span>
          {activeAccounts.length === 0 ? (
            <span className="italic text-[#A3A3A3]">No active accounts this week</span>
          ) : (
            activeAccounts.map((account) => {
              const color = colorForAccount(account.id);
              return (
                <div key={account.id} className="flex items-center gap-1.5">
                  <span
                    className="h-2 w-2 rounded-sm"
                    style={{ backgroundColor: color.dot }}
                  ></span>
                  <span className="font-medium text-[#0A0A0A]">{account.name}</span>
                  {account.platform && (
                    <span className="font-mono text-[10px] uppercase tracking-wide text-[#A3A3A3]">
                      · {account.platform}
                    </span>
                  )}
                </div>
              );
            })
          )}
        </div>

        {timeSlots.length === 0 && (
          <div className="rounded-[16px] border border-dashed border-[#EAEAEA] bg-white p-8 text-center shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <h3 className="text-[15px] font-semibold text-[#0A0A0A]">No time slots defined yet</h3>
            <p className="mt-1 text-[13px] text-[#737373]">
              Define reusable time windows first, then assign hosts to them from this calendar.
            </p>
            <Link
              href="/livehost/time-slots/create"
              className="mt-4 inline-flex h-9 items-center gap-1.5 rounded-lg bg-ink px-3.5 text-[13px] font-medium text-white hover:bg-[#262626]"
            >
              <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
              New time slot
            </Link>
          </div>
        )}

        {/* Grid */}
        <div className="overflow-x-auto rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {/* Header row */}
          <div
            className="sticky top-0 z-30 grid border-b border-[#EAEAEA] bg-white/90 backdrop-blur"
            style={{
              gridTemplateColumns: '64px repeat(7, 1fr)',
              minWidth: `${minGridWidth}px`,
            }}
          >
            <div className="border-r border-[#EAEAEA] px-2 py-3"></div>
            {DAY_NAMES.map((dayShort, dow) => {
              const dayDate = dateForDow(dow);
              const isToday = isCurrentWeek && dow === todayDow;
              return (
                <div
                  key={dayShort}
                  className={`relative border-r last:border-r-0 ${
                    isToday ? 'bg-[#FFFBEB]' : ''
                  } border-[#EAEAEA]`}
                >
                  {isToday && (
                    <div className="absolute left-0 right-0 top-0 z-10 h-[2px] bg-[#F59E0B]"></div>
                  )}
                  <div className="flex items-center justify-between px-3 py-3">
                    <div className="flex flex-col">
                      <span
                        className={`font-mono text-[10px] uppercase tracking-wider ${
                          isToday ? 'text-[#B45309]' : 'text-[#A3A3A3]'
                        }`}
                      >
                        {dayShort}
                      </span>
                      <span
                        className={`text-[15px] font-semibold leading-tight ${
                          isToday ? 'text-[#B45309]' : 'text-[#0A0A0A]'
                        }`}
                      >
                        {DAY_NAMES_FULL[dow]}
                      </span>
                      <span className="mt-0.5 font-mono text-[10px] text-[#A3A3A3] tabular-nums">
                        {dayDate.slice(5).replace('-', '/')}
                      </span>
                    </div>
                    <span className="font-mono text-[11px] font-semibold tabular-nums text-[#525252]">
                      {String(slotsByDay[dow].length).padStart(2, '0')}
                    </span>
                  </div>

                  {/* Swim lane sub-header — only when >1 account is active */}
                  {showLanes && (
                    <div
                      className="grid border-t border-[#F5F5F5]"
                      style={{ gridTemplateColumns: `repeat(${laneCount}, minmax(0, 1fr))` }}
                    >
                      {activeAccounts.map((account) => {
                        const color = colorForAccount(account.id);
                        return (
                          <div
                            key={account.id}
                            className="flex items-center gap-1 border-r border-[#F5F5F5] px-1.5 py-1 last:border-r-0"
                            title={account.name}
                          >
                            <span
                              className="h-1.5 w-1.5 shrink-0 rounded-[2px]"
                              style={{ backgroundColor: color.dot }}
                            ></span>
                            <span
                              className="truncate font-mono text-[9px] font-semibold uppercase tracking-wide"
                              style={{ color: color.text }}
                            >
                              {account.name}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          {/* Body */}
          <div
            className="relative grid overflow-y-auto"
            style={{
              gridTemplateColumns: '64px repeat(7, 1fr)',
              maxHeight: 'calc(100vh - 360px)',
              minHeight: '540px',
              minWidth: `${minGridWidth}px`,
            }}
          >
            {/* Hour axis */}
            <div
              className="relative border-r border-[#EAEAEA]"
              style={{ height: `${totalHeight}px` }}
            >
              {Array.from({ length: hourEnd - hourStart }, (_, i) => hourStart + i).map((h) => {
                const top = (h - hourStart) * HOUR_PX;
                return (
                  <div
                    key={h}
                    className="absolute left-0 right-0 pr-2 pt-0.5 text-right"
                    style={{ top: `${top}px` }}
                  >
                    <span className="font-mono text-[10px] font-medium tracking-tight text-[#A3A3A3] tabular-nums">
                      {formatHour(h)}
                    </span>
                  </div>
                );
              })}
            </div>

            {/* Day columns — each day is split into one swim lane per active account */}
            {DAY_NAMES.map((_, dow) => {
              const isToday = isCurrentWeek && dow === todayDow;
              const slots = slotsByDay[dow];

              const lanes = activeAccounts.length > 0 ? activeAccounts : [null];

              return (
                <div
                  key={dow}
                  className={`relative grid border-r border-[#EAEAEA] last:border-r-0 ${
                    isToday ? 'bg-[#FFFBEB]/40' : ''
                  }`}
                  style={{
                    height: `${totalHeight}px`,
                    gridTemplateColumns: `repeat(${laneCount}, minmax(0, 1fr))`,
                  }}
                >
                  {/* Today line (spans all lanes in this day) */}
                  {isToday && nowPosition !== null && (
                    <div
                      className="pointer-events-none absolute left-0 right-0 z-20 flex items-center"
                      style={{ top: `${nowPosition}px` }}
                    >
                      <div className="-ml-[3px] h-1.5 w-1.5 rounded-full bg-[#F59E0B]"></div>
                      <div className="h-[1.5px] flex-1 bg-gradient-to-r from-[#F59E0B] via-[#F59E0B]/70 to-transparent"></div>
                    </div>
                  )}

                  {lanes.map((account, laneIndex) => {
                    const accountId = account ? Number(account.id) : null;
                    const color = account ? colorForAccount(accountId) : FALLBACK_ACCOUNT_COLOR;

                    const laneScaffolds = timeSlots.filter((ts) => {
                      const matchesDay =
                        ts.dayOfWeek === null || ts.dayOfWeek === undefined || ts.dayOfWeek === dow;
                      if (!matchesDay) {
                        return false;
                      }
                      if (accountId === null) {
                        return true;
                      }
                      // Global time slots (no account attached) show in every lane
                      if (!ts.platformAccountId) {
                        return true;
                      }
                      return Number(ts.platformAccountId) === accountId;
                    });

                    const laneSlots = slots.filter((slot) => {
                      if (accountId === null) {
                        return true;
                      }
                      return Number(slot.platformAccountId) === accountId;
                    });

                    return (
                      <div
                        key={account?.id ?? `lane-${laneIndex}`}
                        className="relative border-r border-[#F5F5F5] last:border-r-0"
                        style={{
                          backgroundImage:
                            'linear-gradient(to bottom, rgba(0,0,0,0.04) 1px, transparent 1px)',
                          backgroundSize: `100% ${HOUR_PX}px`,
                        }}
                      >
                        {/* Scaffolds for this lane's account */}
                        {laneScaffolds.map((ts) => {
                          const hasAssignment = laneSlots.some((s) => s.timeSlotId === ts.id);
                          if (hasAssignment) {
                            return null;
                          }
                          const { h: sh, m: sm } = parseHM(ts.startTime);
                          const { h: eh, m: em } = parseHM(ts.endTime);
                          const startMin = sh * 60 + sm;
                          const endMin = eh * 60 + em;
                          const duration = Math.max(30, endMin - startMin);
                          if (startMin < hourStart * 60 || startMin >= hourEnd * 60) {
                            return null;
                          }
                          const topPx = ((startMin - hourStart * 60) / 60) * HOUR_PX;
                          const heightPx = (duration / 60) * HOUR_PX;

                          return (
                            <button
                              key={`scaffold-${ts.id}-${account?.id ?? 'any'}`}
                              type="button"
                              onClick={() => handleScaffoldClick(dow, ts, account)}
                              className="group/scaffold absolute left-0.5 right-0.5 flex flex-col items-center justify-center overflow-hidden rounded-md border border-dashed border-[#D4D4D4] bg-white/50 px-0.5 text-[#A3A3A3] transition-all hover:border-[#10B981] hover:bg-[#10B981]/[0.04] hover:text-[#10B981]"
                              style={{ top: `${topPx}px`, height: `${heightPx}px` }}
                              title={`${formatTimeLabel(ts.startTime)} – ${formatTimeLabel(ts.endTime)}`}
                            >
                              <span className="truncate font-mono text-[9px] font-medium tabular-nums">
                                {formatTimeLabel(ts.startTime)}
                              </span>
                              <span className="mt-0.5 truncate text-[9px] font-medium uppercase tracking-wide opacity-0 group-hover/scaffold:opacity-100">
                                + Assign
                              </span>
                            </button>
                          );
                        })}

                        {/* Slot blocks for this lane's account */}
                        {laneSlots.map((slot) => {
                          const { h: sh, m: sm } = parseHM(slot.startTime);
                          const { h: eh, m: em } = parseHM(slot.endTime);
                          const startMin = sh * 60 + sm;
                          const endMin = eh * 60 + em;
                          const duration = Math.max(30, endMin - startMin);

                          if (startMin < hourStart * 60 || startMin >= hourEnd * 60) {
                            return null;
                          }

                          const topPx = ((startMin - hourStart * 60) / 60) * HOUR_PX;
                          const heightPx = (duration / 60) * HOUR_PX;
                          const pc = platformStyle(slot.platformType);
                          const slotColor = colorForAccount(slot.platformAccountId);
                          const isCompact = heightPx < 60;

                          return (
                            <div
                              key={slot.id}
                              className="group/block absolute left-0.5 right-0.5 z-10 overflow-hidden rounded-md"
                              style={{ top: `${topPx}px`, height: `${heightPx}px` }}
                            >
                              <button
                                type="button"
                                onClick={() => setDetailTarget(slot)}
                                className="relative flex h-full w-full flex-col rounded-md border text-left transition-all hover:shadow-[0_4px_12px_rgba(0,0,0,0.08)]"
                                style={{
                                  background: `linear-gradient(135deg, ${slotColor.tint} 0%, ${slotColor.soft} 50%, #FFFFFF 100%)`,
                                  borderColor: slotColor.border,
                                }}
                              >
                                {/* Account color bar */}
                                <div
                                  className="absolute bottom-0 left-0 top-0 w-[3px] rounded-l-md"
                                  style={{ backgroundColor: slotColor.dot }}
                                ></div>

                                <div className="relative flex h-full min-w-0 flex-col p-1.5 pl-2.5">
                                  <div className="flex items-start justify-between gap-1">
                                    <div className="min-w-0">
                                      <div className="font-mono text-[11px] font-semibold leading-none tabular-nums text-[#0A0A0A]">
                                        {formatTimeLabel(slot.startTime)}
                                      </div>
                                      {!isCompact && (
                                        <div className="mt-0.5 truncate font-mono text-[9px] leading-none tabular-nums text-[#A3A3A3]">
                                          {duration}min
                                        </div>
                                      )}
                                    </div>
                                    {slot.isTemplate && (
                                      <span className="shrink-0 rounded bg-white/70 px-1 py-px font-mono text-[8px] font-semibold uppercase tracking-wide text-[#5B21B6] backdrop-blur">
                                        W
                                      </span>
                                    )}
                                  </div>

                                  {heightPx >= 56 && (
                                    <div className="mt-auto min-w-0">
                                      {slot.hostName ? (
                                        <div className="flex items-center gap-1">
                                          <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-[#10B981]"></span>
                                          <span className="truncate text-[10px] font-medium text-[#0A0A0A]">
                                            {slot.hostName}
                                          </span>
                                        </div>
                                      ) : (
                                        <div className="flex items-center gap-1 text-[#A3A3A3]">
                                          <span className="h-1.5 w-1.5 shrink-0 rounded-full border border-current"></span>
                                          <span className="truncate text-[10px] italic">
                                            Unassigned
                                          </span>
                                        </div>
                                      )}
                                      {heightPx >= 88 && (
                                        <div
                                          className="mt-0.5 truncate font-mono text-[9px] uppercase tracking-wide"
                                          style={{ color: slotColor.text }}
                                        >
                                          {pc.label}
                                        </div>
                                      )}
                                    </div>
                                  )}
                                </div>
                              </button>

                              {/* Hover actions */}
                              <div className="absolute bottom-1 right-1 flex items-center gap-0.5 opacity-0 transition-opacity group-hover/block:opacity-100">
                                <button
                                  type="button"
                                  onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleEdit(slot);
                                  }}
                                  className="inline-flex h-5 w-5 items-center justify-center rounded border border-[#EAEAEA] bg-white/95 text-[#525252] backdrop-blur hover:border-[#3B82F6]/50 hover:text-[#1D4ED8]"
                                  title="Edit"
                                >
                                  <Pencil className="h-2.5 w-2.5" strokeWidth={2} />
                                </button>
                                <button
                                  type="button"
                                  onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    handleDelete(slot);
                                  }}
                                  className="inline-flex h-5 w-5 items-center justify-center rounded border border-[#EAEAEA] bg-white/95 text-[#525252] backdrop-blur hover:border-[#F43F5E]/50 hover:text-[#F43F5E]"
                                  title="Delete"
                                >
                                  <Trash2 className="h-2.5 w-2.5" strokeWidth={2} />
                                </button>
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </div>
      </div>

      <SessionSlotFormModal
        open={createOpen}
        onOpenChange={setCreateOpen}
        mode="create"
        prefill={createPrefill}
        hosts={hosts}
        platformAccounts={platformAccounts}
        timeSlots={timeSlots}
        hostPlatformPivots={hostPlatformPivots ?? []}
        returnTo="calendar"
        weekOf={weekStart}
      />

      <SessionSlotFormModal
        open={editTarget !== null}
        onOpenChange={(next) => {
          if (!next) {
            setEditTarget(null);
          }
        }}
        mode="edit"
        sessionSlot={editTarget}
        hosts={hosts}
        platformAccounts={platformAccounts}
        timeSlots={timeSlots}
        hostPlatformPivots={hostPlatformPivots ?? []}
        returnTo="calendar"
        weekOf={weekStart}
        onSuccess={() => setEditTarget(null)}
      />

      <SessionSlotDetailModal
        open={detailTarget !== null}
        onOpenChange={(next) => {
          if (!next) {
            setDetailTarget(null);
          }
        }}
        sessionSlot={detailTarget}
        onEdit={(slot) => {
          setDetailTarget(null);
          setEditTarget(slot);
        }}
        onDeleted={() => setDetailTarget(null)}
      />
    </>
  );
}

SessionSlotsCalendar.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
