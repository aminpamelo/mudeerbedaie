import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import SlotOverrideModal from '@/livehost/components/session-slots/SlotOverrideModal';
import {
  AlertTriangle,
  CalendarClock,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  Eye,
  EyeOff,
  Grid3x3,
  LayoutGrid,
  List,
  Pencil,
  Plus,
  Radio,
  ShieldAlert,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  Trash2,
  Upload,
  UserPlus,
  XCircle,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import SessionSlotFormModal from '@/livehost/components/SessionSlotFormModal';
import SessionSlotDetailModal from '@/livehost/components/SessionSlotDetailModal';
import LiveSessionModal from '@/livehost/components/LiveSessionModal';
import RegisterCreatorModal from '@/livehost/components/RegisterCreatorModal';

const UNREGISTERED_LANE_ID = '__unregistered__';

// Persisted custom order + hidden set for the account columns/lanes, so the PIC's
// arrangement survives reloads and revisits (per browser).
const ACCOUNT_PREF_KEY = 'livehost:session-slots:calendar:accounts';

function readAccountPrefs() {
  if (typeof window === 'undefined') return { order: [], hidden: [] };
  try {
    const p = JSON.parse(window.localStorage.getItem(ACCOUNT_PREF_KEY) || 'null');
    return {
      order: Array.isArray(p?.order) ? p.order.map(String) : [],
      hidden: Array.isArray(p?.hidden) ? p.hidden.map(String) : [],
    };
  } catch {
    return { order: [], hidden: [] };
  }
}

function writeAccountPrefs(prefs) {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(ACCOUNT_PREF_KEY, JSON.stringify(prefs));
  } catch {
    /* storage disabled or over quota — persistence is best-effort */
  }
}

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

// The lifecycle signal a PIC scans the calendar for: which broadcasts still
// need the host's upload, which are uploaded and waiting on verification, and
// which are already reconciled against the imported TikTok record.
const SESSION_STATE_LEGEND = [
  { color: '#F59E0B', label: 'Needs upload' },
  { color: '#3B82F6', label: 'Needs verify' },
  { color: '#10B981', label: 'Verified' },
  { color: '#EF4444', label: 'Rejected' },
];

function resolveSessionState(session) {
  if (!session) {
    return null;
  }
  const verification = session.verificationStatus ?? 'pending';

  if (session.needsUpload) {
    return {
      key: 'needs_upload',
      overdue: Boolean(session.overdue),
      label: session.overdue ? 'No upload' : 'Upload',
      title: session.overdue
        ? 'Overdue — this slot has passed and the host never went live or uploaded a recap'
        : 'Ended — host still needs to upload proof',
      icon: session.overdue ? AlertTriangle : Upload,
      dot: '#F59E0B',
      bg: '#FEF3C7',
      fg: '#92400E',
    };
  }
  if (verification === 'verified') {
    return {
      key: 'verified',
      label: 'Verified',
      title: 'Verified against the imported TikTok record',
      icon: ShieldCheck,
      dot: '#10B981',
      bg: '#DCFCE7',
      fg: '#166534',
    };
  }
  if (verification === 'rejected') {
    return {
      key: 'rejected',
      label: 'Rejected',
      title: 'Verification was rejected',
      icon: XCircle,
      dot: '#EF4444',
      bg: '#FEE2E2',
      fg: '#991B1B',
    };
  }
  if (session.status === 'live') {
    return {
      key: 'live',
      label: 'Live',
      title: 'Currently live',
      icon: Radio,
      dot: '#10B981',
      bg: '#DCFCE7',
      fg: '#166534',
    };
  }
  if (session.uploaded || session.status === 'ended') {
    return {
      key: 'needs_verify',
      label: 'Verify',
      title: 'Uploaded — awaiting PIC verification',
      icon: ShieldAlert,
      dot: '#3B82F6',
      bg: '#DBEAFE',
      fg: '#1E40AF',
    };
  }
  return {
    key: 'scheduled',
    label: 'Scheduled',
    title: 'Scheduled — not started yet',
    icon: null,
    dot: '#A3A3A3',
    bg: '#F5F5F5',
    fg: '#525252',
  };
}

function formatGmv(value) {
  const num = Number(value);
  if (!Number.isFinite(num) || num === 0) {
    return null;
  }
  const hasSen = num % 1 !== 0;
  return `RM ${num.toLocaleString(undefined, {
    minimumFractionDigits: hasSen ? 2 : 0,
    maximumFractionDigits: 2,
  })}`;
}

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
    suggestions = [],
    weekStart,
    weekEnd,
    filters,
    hosts,
    platformAccounts,
    liveAccounts = [],
    timeSlots,
    slotOverrides = [],
    hostPlatformPivots,
    flash,
  } = usePage().props;

  const [host, setHost] = useState(filters?.host ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [liveAccount, setLiveAccount] = useState(filters?.live_account ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');
  const [mode, setMode] = useState(filters?.mode ?? '');
  const [showSuggestions, setShowSuggestions] = useState((filters?.show_suggestions ?? '1') !== '0');

  const [createOpen, setCreateOpen] = useState(false);
  const [createPrefill, setCreatePrefill] = useState(null);
  const [editTarget, setEditTarget] = useState(null);
  const [detailTarget, setDetailTarget] = useState(null);
  const [sessionTarget, setSessionTarget] = useState(null);
  const [registerTarget, setRegisterTarget] = useState(null);
  const [overrideAccount, setOverrideAccount] = useState(null);

  const openSession = (session) => {
    if (session) {
      setSessionTarget(session);
    }
  };

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
    for (const ov of slotOverrides) {
      for (const s of ov.slots ?? []) {
        push(s.startTime);
        push(s.endTime);
      }
    }
    for (const s of sessionSlots) {
      push(s.startTime);
      push(s.endTime);
    }
    if (showSuggestions) {
      for (const s of suggestions) {
        push(s.startTime);
        push(s.endTime);
      }
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
  }, [timeSlots, slotOverrides, sessionSlots, suggestions, showSuggestions]);

  // Suggestions actually rendered this session — respects the toggle. Kept as a
  // separate memo so lane/grouping logic can depend on it cleanly.
  const visibleSuggestions = useMemo(
    () => (showSuggestions ? suggestions : []),
    [showSuggestions, suggestions]
  );

  const unregisteredSuggestions = useMemo(
    () => visibleSuggestions.filter((s) => !s.isRegistered),
    [visibleSuggestions]
  );

  // Resolve the set of CREATOR ACCOUNTS (the punca kuasa) that render as swim
  // lanes. When the account filter is set, lanes collapse to just that account.
  // Otherwise, collect every live account referenced by a session this week,
  // plus an "Unassigned" lane for legacy/unresolved slots. The shop is no
  // longer the lane axis — many accounts can be live for one shop at once.
  const [accountPrefs, setAccountPrefs] = useState(() => readAccountPrefs());
  const [accountPanelOpen, setAccountPanelOpen] = useState(false);
  const accountPanelRef = useRef(null);

  // Every account/lane referenced this week, alphabetical (the raw set before the
  // user's custom order / hidden preferences are applied).
  const allAccounts = useMemo(() => {
    const lookup = (id) => liveAccounts.find((a) => Number(a.id) === Number(id));

    if (liveAccount) {
      const meta = lookup(liveAccount);
      return meta ? [meta] : [];
    }

    const ids = new Set();
    let hasUnassigned = false;
    for (const s of sessionSlots) {
      if (s.liveAccountId) {
        ids.add(Number(s.liveAccountId));
      } else {
        hasUnassigned = true;
      }
    }
    // A creator with only TikTok suggestions (no assignment yet) still earns a
    // lane, so the ghost has somewhere to render.
    let hasUnregistered = false;
    for (const s of visibleSuggestions) {
      if (s.isRegistered && s.liveAccountId) {
        ids.add(Number(s.liveAccountId));
      } else if (!s.isRegistered) {
        hasUnregistered = true;
      }
    }

    const accounts = Array.from(ids)
      .map((id) => lookup(id) ?? { id, label: `Account ${id}`, shops: [] })
      .sort((a, b) => String(a.label).localeCompare(String(b.label)));

    if (hasUnassigned) {
      accounts.push({ id: '__none__', label: 'Unassigned', shops: [], isNone: true });
    }
    if (hasUnregistered) {
      accounts.push({
        id: UNREGISTERED_LANE_ID,
        label: 'Unregistered',
        shops: [],
        isUnregistered: true,
      });
    }

    return accounts;
  }, [liveAccount, sessionSlots, liveAccounts, visibleSuggestions]);

  // Colors are keyed to the alphabetical index so custom ordering / hiding never
  // reshuffles which color an account has.
  const accountColorMap = useMemo(() => {
    const map = new Map();
    allAccounts.forEach((account, i) => {
      map.set(String(account.id), ACCOUNT_PALETTE[i % ACCOUNT_PALETTE.length]);
    });
    return map;
  }, [allAccounts]);

  const colorForAccount = (accountId) =>
    accountColorMap.get(String(accountId ?? '__none__')) ?? FALLBACK_ACCOUNT_COLOR;

  const isManageableAccount = (a) => !a.isNone && !a.isUnregistered && a.id !== '__none__';

  // Real accounts in the user's custom order (persisted ids first, the rest
  // alphabetical) — drives the Sort & hide panel (includes hidden ones).
  const orderedManageable = useMemo(() => {
    const orderIndex = new Map((accountPrefs.order ?? []).map((id, i) => [String(id), i]));
    return allAccounts
      .filter((a) => !a.isNone && !a.isUnregistered && a.id !== '__none__')
      .sort((a, b) => {
        const ai = orderIndex.has(String(a.id)) ? orderIndex.get(String(a.id)) : Infinity;
        const bi = orderIndex.has(String(b.id)) ? orderIndex.get(String(b.id)) : Infinity;
        if (ai !== bi) return ai - bi;
        return String(a.label).localeCompare(String(b.label));
      });
  }, [allAccounts, accountPrefs.order]);

  // The lanes actually rendered: custom-ordered visible accounts, then the
  // synthetic Unassigned / Unregistered lanes.
  const activeAccounts = useMemo(() => {
    const hidden = new Set((accountPrefs.hidden ?? []).map(String));
    const visible = orderedManageable.filter((a) => !hidden.has(String(a.id)));
    const synthetic = allAccounts.filter((a) => a.isNone || a.isUnregistered);
    return [...visible, ...synthetic];
  }, [orderedManageable, allAccounts, accountPrefs.hidden]);

  const laneCount = Math.max(1, activeAccounts.length);
  const showLanes = activeAccounts.length > 1;
  const minGridWidth = 64 + 7 * laneCount * LANE_MIN_WIDTH;

  useEffect(() => {
    writeAccountPrefs(accountPrefs);
  }, [accountPrefs]);

  useEffect(() => {
    if (!accountPanelOpen) return undefined;
    const onClick = (e) => {
      if (accountPanelRef.current && !accountPanelRef.current.contains(e.target)) {
        setAccountPanelOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [accountPanelOpen]);

  const moveAccount = (id, dir) => {
    const ids = orderedManageable.map((a) => String(a.id));
    const i = ids.indexOf(String(id));
    const j = dir === 'up' ? i - 1 : i + 1;
    if (i < 0 || j < 0 || j >= ids.length) return;
    [ids[i], ids[j]] = [ids[j], ids[i]];
    setAccountPrefs((p) => ({ ...p, order: ids }));
  };

  const toggleAccountHidden = (id) => {
    setAccountPrefs((p) => {
      const hidden = new Set((p.hidden ?? []).map(String));
      if (hidden.has(String(id))) {
        hidden.delete(String(id));
      } else {
        hidden.add(String(id));
      }
      return { ...p, hidden: [...hidden] };
    });
  };

  const resetAccountPrefs = () => setAccountPrefs({ order: [], hidden: [] });

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.host ?? '') === host &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.live_account ?? '') === liveAccount &&
      (initial.status ?? '') === status &&
      (initial.mode ?? '') === mode &&
      ((initial.show_suggestions ?? '1') !== '0') === showSuggestions
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/session-slots/calendar',
        {
          host: host || undefined,
          platform_account: platformAccount || undefined,
          live_account: liveAccount || undefined,
          status: status || undefined,
          mode: mode || undefined,
          show_suggestions: showSuggestions ? undefined : '0',
          week_of: weekStart,
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [host, platformAccount, liveAccount, status, mode, showSuggestions, filters, weekStart]);

  const goToWeek = (isoDate) => {
    router.get(
      '/livehost/session-slots/calendar',
      {
        host: host || undefined,
        platform_account: platformAccount || undefined,
        live_account: liveAccount || undefined,
        status: status || undefined,
        mode: mode || undefined,
        show_suggestions: showSuggestions ? undefined : '0',
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

  const suggestionsByDay = useMemo(() => {
    const groups = Array.from({ length: 7 }, () => []);

    for (const suggestion of visibleSuggestions) {
      const dow = Number(suggestion.dayOfWeek);
      if (dow >= 0 && dow <= 6) {
        groups[dow].push(suggestion);
      }
    }

    return groups;
  }, [visibleSuggestions]);

  const dateForDow = (dow) => addDays(weekStart, dow);

  // An account's normal (perpetual) slots, flattened to per-day rows — offered as
  // the starting suggestion when creating a new override. Global (all-day) slots
  // expand across every weekday.
  const suggestedSlotsFor = (account) => {
    if (!account) {
      return [];
    }
    const shopIds = (account.shops ?? []).map((s) => Number(s.id));
    const matching = timeSlots.filter((ts) => !ts.platformAccountId || shopIds.includes(Number(ts.platformAccountId)));
    const out = [];
    for (const ts of matching) {
      if (ts.dayOfWeek === null || ts.dayOfWeek === undefined) {
        for (let d = 0; d < 7; d += 1) {
          out.push({ day_of_week: d, start_time: ts.startTime, end_time: ts.endTime });
        }
      } else {
        out.push({ day_of_week: ts.dayOfWeek, start_time: ts.startTime, end_time: ts.endTime });
      }
    }
    return out;
  };

  // The active per-creator slot override (if any) for a live account on a given
  // ISO date — its slots replace the account's normal scaffolds in that window.
  const overrideFor = (accountId, isoDate) => {
    if (accountId == null) {
      return null;
    }
    return slotOverrides.find(
      (o) => Number(o.liveAccountId) === Number(accountId)
        && o.from <= isoDate
        && (o.until == null || o.until >= isoDate)
    ) ?? null;
  };

  const handleScaffoldClick = (dow, timeSlot, laneAccount = null) => {
    const isReal = laneAccount && !laneAccount.isNone;
    const primaryShop = isReal
      ? (laneAccount.shops?.find((s) => s.isPrimary) ?? laneAccount.shops?.[0])
      : null;
    // If this account is under an active override for the date, its slots come
    // from the override — hand them to the form so the clicked slot is selectable
    // and pre-selected (the normal weekly slots don't include override slots).
    const activeOverride = isReal ? overrideFor(laneAccount.id, addDays(weekStart, dow)) : null;
    setCreatePrefill({
      dayOfWeek: dow,
      timeSlotId: timeSlot?.id ?? null,
      liveAccountId: isReal ? laneAccount.id : null,
      platformAccountId: timeSlot?.platformAccountId ?? primaryShop?.id ?? null,
      scheduleDate: addDays(weekStart, dow),
      overrideTimeSlots: activeOverride ? activeOverride.slots : null,
    });
    setCreateOpen(true);
  };

  const handleSuggestionClick = (suggestion) => {
    if (!suggestion.isRegistered) {
      const sameHandle = unregisteredSuggestions.filter(
        (s) => (s.creatorHandle ?? '') === (suggestion.creatorHandle ?? '')
      );
      setRegisterTarget({
        creatorHandle: suggestion.creatorHandle,
        creatorUserId: suggestion.creatorUserId,
        platformAccountId: suggestion.platformAccountId,
        platformAccount: suggestion.platformAccount,
        count: sameHandle.length || 1,
      });
      return;
    }

    setCreatePrefill({
      dayOfWeek: suggestion.dayOfWeek,
      timeSlotId: suggestion.suggestedTimeSlotId ?? null,
      liveAccountId: suggestion.liveAccountId,
      platformAccountId: suggestion.platformAccountId,
      scheduleDate: suggestion.scheduleDate,
      suggestionId: suggestion.id,
      suggestion,
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

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        {/* Header */}
        <div className="flex flex-wrap items-end justify-between gap-4 sm:gap-8">
          <div>
            <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A] sm:text-3xl">
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
            <Link
              href="/livehost/session-slots/matrix"
              className="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]"
            >
              <Grid3x3 className="h-[13px] w-[13px]" strokeWidth={2} />
              Matrix
            </Link>
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
        <div className="flex flex-col gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
          <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:gap-3">
            <select
              value={host}
              onChange={(e) => setHost(e.target.value)}
              className="h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
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
              value={liveAccount}
              onChange={(e) => setLiveAccount(e.target.value)}
              className="h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
            >
              <option value="">All accounts</option>
              {liveAccounts.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.label}
                </option>
              ))}
            </select>
            <select
              value={platformAccount}
              onChange={(e) => setPlatformAccount(e.target.value)}
              className="h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
            >
              <option value="">All shops</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name} {pa.platform ? `· ${pa.platform}` : ''}
                </option>
              ))}
            </select>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
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
              className="h-9 w-full min-w-0 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
            >
              <option value="">Template & dated</option>
              <option value="template">Weekly template</option>
              <option value="dated">Dated only</option>
            </select>
            <button
              type="button"
              onClick={() => setShowSuggestions((v) => !v)}
              aria-pressed={showSuggestions}
              title="Show TikTok lives that have no session slot yet"
              className={`inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border px-3 text-sm font-medium transition-colors sm:w-auto ${
                showSuggestions
                  ? 'border-[#F5D0E4] bg-[#FDF2F8] text-[#9D174D]'
                  : 'border-[#EAEAEA] bg-white text-[#737373] hover:text-[#0A0A0A]'
              }`}
            >
              <Sparkles className="h-[13px] w-[13px]" strokeWidth={2.2} />
              TikTok suggestions
              {suggestions.length > 0 && (
                <span
                  className={`rounded-full px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums ${
                    showSuggestions ? 'bg-white/70 text-[#9D174D]' : 'bg-[#F5F5F5] text-[#737373]'
                  }`}
                >
                  {suggestions.length}
                </span>
              )}
            </button>
          </div>

          <div className="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-start">
            <button
              type="button"
              onClick={() => goToWeek(addDays(weekStart, -7))}
              className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#EAEAEA] text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
              title="Previous week"
            >
              <ChevronLeft className="h-4 w-4" strokeWidth={2} />
            </button>
            <button
              type="button"
              onClick={() => goToWeek(todayIso())}
              className="h-9 flex-1 rounded-lg border border-[#EAEAEA] bg-white px-3.5 text-[13px] font-medium tabular-nums text-[#0A0A0A] hover:bg-[#F5F5F5] sm:flex-none"
            >
              {formatWeekLabel(weekStart, weekEnd)}
            </button>
            <button
              type="button"
              onClick={() => goToWeek(addDays(weekStart, 7))}
              className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#EAEAEA] text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
              title="Next week"
            >
              <ChevronRight className="h-4 w-4" strokeWidth={2} />
            </button>
          </div>
        </div>

        {/* Legend — one chip per active creator account (the punca kuasa) */}
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px]">
          <span className="font-mono text-[10px] uppercase tracking-wider text-[#A3A3A3]">
            Accounts
          </span>

          {orderedManageable.length > 0 && (
            <div className="relative z-40" ref={accountPanelRef}>
              <button
                type="button"
                onClick={() => setAccountPanelOpen((o) => !o)}
                className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] px-2 py-0.5 text-[10px] font-medium text-[#525252] transition-colors hover:bg-[#F5F5F5]"
              >
                <SlidersHorizontal className="h-3 w-3" strokeWidth={2} />
                Sort &amp; hide
                {(accountPrefs.hidden ?? []).length > 0 && (
                  <span className="rounded-full bg-[#F0F0F0] px-1 text-[9px] font-semibold text-[#737373]">
                    {(accountPrefs.hidden ?? []).length} hidden
                  </span>
                )}
              </button>
              {accountPanelOpen && (
                <div className="absolute left-0 top-full z-50 mt-1.5 w-72 rounded-xl border border-[#EAEAEA] bg-white p-1.5 shadow-xl">
                  <div className="flex items-center justify-between px-1.5 py-1">
                    <span className="text-[11px] font-semibold text-[#0A0A0A]">Account columns</span>
                    <button
                      type="button"
                      onClick={resetAccountPrefs}
                      className="text-[10.5px] font-medium text-[#737373] transition-colors hover:text-[#0A0A0A]"
                    >
                      Reset
                    </button>
                  </div>
                  <div className="max-h-72 space-y-0.5 overflow-y-auto">
                    {orderedManageable.map((account, i) => {
                      const isHidden = (accountPrefs.hidden ?? []).map(String).includes(String(account.id));
                      const color = colorForAccount(account.id);
                      return (
                        <div key={account.id} className="flex items-center gap-1.5 rounded-lg px-1 py-1 hover:bg-[#FAFAFA]">
                          <div className="flex flex-col text-[#A3A3A3]">
                            <button
                              type="button"
                              onClick={() => moveAccount(account.id, 'up')}
                              disabled={i === 0}
                              aria-label="Move up"
                              className="leading-none transition-colors hover:text-[#0A0A0A] disabled:opacity-20"
                            >
                              <ChevronUp className="h-3.5 w-3.5" strokeWidth={2.5} />
                            </button>
                            <button
                              type="button"
                              onClick={() => moveAccount(account.id, 'down')}
                              disabled={i === orderedManageable.length - 1}
                              aria-label="Move down"
                              className="leading-none transition-colors hover:text-[#0A0A0A] disabled:opacity-20"
                            >
                              <ChevronDown className="h-3.5 w-3.5" strokeWidth={2.5} />
                            </button>
                          </div>
                          <span className="h-2 w-2 flex-shrink-0 rounded-sm" style={{ backgroundColor: color.dot }} />
                          <span className={`flex-1 truncate text-[12px] ${isHidden ? 'text-[#B0B0B0] line-through' : 'text-[#0A0A0A]'}`}>
                            {account.label}
                          </span>
                          <button
                            type="button"
                            onClick={() => toggleAccountHidden(account.id)}
                            aria-label={isHidden ? 'Show account' : 'Hide account'}
                            className="rounded p-1 text-[#737373] transition-colors hover:bg-[#F0F0F0]"
                          >
                            {isHidden ? <EyeOff className="h-3.5 w-3.5" strokeWidth={2} /> : <Eye className="h-3.5 w-3.5" strokeWidth={2} />}
                          </button>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          )}

          {activeAccounts.length === 0 ? (
            <span className="italic text-[#A3A3A3]">No active accounts this week</span>
          ) : (
            activeAccounts.map((account) => {
              const color = colorForAccount(account.id);
              const shopLabel = (account.shops ?? []).map((s) => s.name).filter(Boolean).join(', ');
              const manageable = !account.isNone && !account.isUnregistered && account.id !== '__none__';
              const inner = (
                <>
                  <span className="h-2 w-2 rounded-sm" style={{ backgroundColor: color.dot }}></span>
                  <span className="font-medium text-[#0A0A0A]">{account.label}</span>
                  {shopLabel && (
                    <span className="max-w-[160px] truncate font-mono text-[10px] uppercase tracking-wide text-[#A3A3A3]">
                      · {shopLabel}
                    </span>
                  )}
                </>
              );
              return manageable ? (
                <button
                  key={account.id}
                  type="button"
                  onClick={() => setOverrideAccount(account)}
                  title={`${shopLabel ? `${shopLabel} · ` : ''}Set slot override for ${account.label}`}
                  className="flex items-center gap-1.5 rounded-md px-1.5 py-0.5 transition-colors hover:bg-[#EEF2FF]"
                >
                  {inner}
                  <CalendarClock className="h-3 w-3 text-[#A3A3A3]" strokeWidth={2} />
                </button>
              ) : (
                <div key={account.id} className="flex items-center gap-1.5" title={shopLabel}>
                  {inner}
                </div>
              );
            })
          )}
        </div>

        {/* Session-state legend — the upload → verify pipeline at a glance */}
        <div className="-mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px]">
          <span className="font-mono text-[10px] uppercase tracking-wider text-[#A3A3A3]">
            Session
          </span>
          {SESSION_STATE_LEGEND.map((item) => (
            <div key={item.label} className="flex items-center gap-1.5">
              <span
                className="h-2 w-2 rounded-sm"
                style={{ backgroundColor: item.color }}
              ></span>
              <span className="text-[#525252]">{item.label}</span>
            </div>
          ))}
          {showSuggestions && suggestions.length > 0 && (
            <div className="flex items-center gap-1.5" title="A TikTok live with no session slot yet">
              <span className="h-2 w-2 rounded-sm border border-dashed border-[#EC4899] bg-[#FDF2F8]"></span>
              <span className="text-[#525252]">TikTok suggestion</span>
            </div>
          )}
        </div>

        {/* Unregistered-creator guide — these lives can't be assigned until the
            creator account exists in the system. */}
        {showSuggestions && unregisteredSuggestions.length > 0 && (
          <div className="-mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-[12px] border border-[#FDE68A] bg-[#FFFBEB] px-4 py-2.5 text-[12.5px] text-[#92400E]">
            <AlertTriangle className="h-4 w-4 shrink-0 text-[#B45309]" strokeWidth={2.2} />
            <span>
              <span className="font-semibold">{unregisteredSuggestions.length} TikTok live
              {unregisteredSuggestions.length === 1 ? '' : 's'}</span> {unregisteredSuggestions.length === 1 ? 'is' : 'are'} on
              creator accounts not registered in your system yet — see the{' '}
              <span className="font-semibold">Unregistered</span> lane. Register a creator to be able
              to assign its sessions.
            </span>
          </div>
        )}

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
              const needsUploadCount = slotsByDay[dow].filter(
                (s) => s.session?.needsUpload
              ).length;
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
                    <div className="flex items-center gap-1.5">
                      {needsUploadCount > 0 && (
                        <span
                          title={`${needsUploadCount} session${needsUploadCount === 1 ? '' : 's'} awaiting upload`}
                          className="inline-flex items-center gap-0.5 rounded-full bg-[#FEF3C7] px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums text-[#92400E] ring-1 ring-[#FCD34D]"
                        >
                          <Upload className="h-2.5 w-2.5" strokeWidth={2.5} />
                          {needsUploadCount}
                        </span>
                      )}
                      <span className="font-mono text-[11px] font-semibold tabular-nums text-[#525252]">
                        {String(slotsByDay[dow].length).padStart(2, '0')}
                      </span>
                    </div>
                  </div>

                  {/* Swim lane sub-header — only when >1 account is active */}
                  {showLanes && (
                    <div
                      className="grid border-t border-[#F5F5F5]"
                      style={{ gridTemplateColumns: `repeat(${laneCount}, minmax(0, 1fr))` }}
                    >
                      {activeAccounts.map((account) => {
                        const color = colorForAccount(account.id);
                        if (account.isUnregistered) {
                          return (
                            <div
                              key={account.id}
                              className="flex items-center gap-1 border-r border-[#F5F5F5] px-1.5 py-1 last:border-r-0"
                              title="Creators not registered in your system yet"
                            >
                              <AlertTriangle
                                className="h-2 w-2 shrink-0 text-[#B45309]"
                                strokeWidth={2.5}
                              />
                              <span className="truncate font-mono text-[9px] font-semibold uppercase tracking-wide text-[#B45309]">
                                {account.label}
                              </span>
                            </div>
                          );
                        }
                        return (
                          <div
                            key={account.id}
                            className="flex items-center gap-1 border-r border-[#F5F5F5] px-1.5 py-1 last:border-r-0"
                            title={account.label}
                          >
                            <span
                              className="h-1.5 w-1.5 shrink-0 rounded-[2px]"
                              style={{ backgroundColor: color.dot }}
                            ></span>
                            <span
                              className="truncate font-mono text-[9px] font-semibold uppercase tracking-wide"
                              style={{ color: color.text }}
                            >
                              {account.label}
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
                    const isFallback = account === null;
                    const isNone = Boolean(account?.isNone);
                    const isUnregistered = Boolean(account?.isUnregistered);
                    const color = account ? colorForAccount(account.id) : FALLBACK_ACCOUNT_COLOR;
                    const accountShopIds = (account?.shops ?? []).map((s) => Number(s.id));

                    // A per-creator slot override in effect for this date replaces
                    // the account's normal scaffolds for that day.
                    const activeOverride = (!isFallback && !isNone && !isUnregistered && account)
                      ? overrideFor(account.id, dateForDow(dow))
                      : null;

                    const laneScaffolds = activeOverride
                      ? activeOverride.slots.filter(
                          (ts) => ts.dayOfWeek === null || ts.dayOfWeek === undefined || ts.dayOfWeek === dow
                        )
                      : timeSlots.filter((ts) => {
                        const matchesDay =
                          ts.dayOfWeek === null || ts.dayOfWeek === undefined || ts.dayOfWeek === dow;
                        if (!matchesDay) {
                          return false;
                        }
                        // Single fallback lane (no accounts yet) shows every scaffold.
                        if (isFallback) {
                          return true;
                        }
                        // The Unassigned / Unregistered lanes never show scaffolds —
                        // they only surface legacy slots or TikTok suggestions.
                        if (isNone || isUnregistered) {
                          return false;
                        }
                        // Global time slots (no shop attached) show in every account lane.
                        if (!ts.platformAccountId) {
                          return true;
                        }
                        // Otherwise show the scaffold only in lanes whose account is
                        // affiliated with the time slot's shop.
                        return accountShopIds.includes(Number(ts.platformAccountId));
                      });

                    const laneSlots = slots.filter((slot) => {
                      if (isFallback) {
                        return true;
                      }
                      if (isNone) {
                        return slot.liveAccountId == null;
                      }
                      if (isUnregistered) {
                        return false;
                      }
                      return Number(slot.liveAccountId) === Number(account.id);
                    });

                    const laneSuggestions = suggestionsByDay[dow].filter((sg) => {
                      if (isUnregistered) {
                        return !sg.isRegistered;
                      }
                      if (isNone) {
                        return false;
                      }
                      if (isFallback) {
                        return sg.isRegistered;
                      }
                      return sg.isRegistered && Number(sg.liveAccountId) === Number(account.id);
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

                        {/* TikTok suggestion ghosts — lives with no session slot yet */}
                        {laneSuggestions.map((sg) => {
                          const { h: sh, m: sm } = parseHM(sg.startTime);
                          const { h: eh, m: em } = parseHM(sg.endTime);
                          const startMin = sh * 60 + sm;
                          const endMin = eh * 60 + em;
                          const duration = Math.max(30, endMin - startMin);
                          if (startMin < hourStart * 60 || startMin >= hourEnd * 60) {
                            return null;
                          }
                          const topPx = ((startMin - hourStart * 60) / 60) * HOUR_PX;
                          const heightPx = (duration / 60) * HOUR_PX;
                          const gmvLabel = formatGmv(sg.gmv);
                          const isCompact = heightPx < 46;

                          return (
                            <button
                              key={`suggestion-${sg.id}`}
                              type="button"
                              onClick={() => handleSuggestionClick(sg)}
                              className="group/sg absolute left-0.5 right-0.5 z-[5] flex flex-col overflow-hidden rounded-md border border-dashed border-[#F0ABCE] bg-[#FDF2F8]/85 px-1.5 py-1 text-left text-[#9D174D] backdrop-blur-[1px] transition-all hover:border-[#EC4899] hover:bg-[#FCE7F3] hover:shadow-[0_4px_12px_rgba(236,72,153,0.12)]"
                              style={{ top: `${topPx}px`, height: `${heightPx}px` }}
                              title={`TikTok live ${sg.creatorHandle ? `@${sg.creatorHandle} ` : ''}· ${formatTimeLabel(sg.startTime)} – ${formatTimeLabel(sg.endTime)}${gmvLabel ? ` · ${gmvLabel}` : ''} · ${sg.isRegistered ? 'click to assign' : 'creator not registered — click to register'}`}
                            >
                              <div className="flex items-center gap-1">
                                <Radio className="h-2.5 w-2.5 shrink-0" strokeWidth={2.4} />
                                <span className="truncate font-mono text-[10px] font-semibold leading-none tabular-nums">
                                  {formatTimeLabel(sg.startTime)}
                                </span>
                                {sg.matchType === 'near_slot' && !isCompact && (
                                  <span className="ml-auto rounded bg-white/70 px-1 py-px font-mono text-[7.5px] font-bold uppercase tracking-wide">
                                    near slot
                                  </span>
                                )}
                              </div>
                              {!isCompact && (
                                <div className="mt-auto min-w-0">
                                  {sg.creatorHandle && (
                                    <div className="truncate font-mono text-[9px] leading-none opacity-80">
                                      @{sg.creatorHandle}
                                    </div>
                                  )}
                                  {gmvLabel && (
                                    <div className="mt-0.5 font-mono text-[9.5px] font-semibold leading-none tabular-nums">
                                      {gmvLabel}
                                    </div>
                                  )}
                                  <div className="mt-0.5 inline-flex items-center gap-0.5 text-[8.5px] font-semibold uppercase tracking-wide opacity-0 transition-opacity group-hover/sg:opacity-100">
                                    {sg.isRegistered ? (
                                      <>
                                        <Plus className="h-2 w-2" strokeWidth={2.6} /> Assign
                                      </>
                                    ) : (
                                      <>
                                        <UserPlus className="h-2 w-2" strokeWidth={2.6} /> Register
                                      </>
                                    )}
                                  </div>
                                </div>
                              )}
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
                          const slotColor = color;
                          const isCompact = heightPx < 60;
                          const sessionState = resolveSessionState(slot.session);
                          const SessionStateIcon = sessionState?.icon;
                          const gmvLabel = formatGmv(slot.session?.gmvNet);
                          const needsUpload = sessionState?.key === 'needs_upload';
                          const isOverdue = Boolean(sessionState?.overdue);
                          const blockBackground = needsUpload
                            ? 'linear-gradient(135deg, #FDE68A 0%, #FEF3C7 45%, #FFFDF5 100%)'
                            : `linear-gradient(135deg, ${slotColor.tint} 0%, ${slotColor.soft} 50%, #FFFFFF 100%)`;
                          const blockBorderColor = needsUpload ? '#F59E0B' : slotColor.border;
                          const barColor = needsUpload ? '#F59E0B' : slotColor.dot;

                          return (
                            <div
                              key={slot.id}
                              className="group/block absolute left-0.5 right-0.5 z-10 overflow-hidden rounded-md"
                              style={{ top: `${topPx}px`, height: `${heightPx}px` }}
                            >
                              <button
                                type="button"
                                onClick={() => setDetailTarget(slot)}
                                className={`relative flex h-full w-full flex-col rounded-md border text-left transition-all hover:shadow-[0_4px_12px_rgba(0,0,0,0.08)] ${
                                  needsUpload ? 'border-2 shadow-[0_0_0_1px_rgba(245,158,11,0.25)]' : ''
                                }`}
                                style={{
                                  background: blockBackground,
                                  borderColor: blockBorderColor,
                                }}
                              >
                                {/* Left accent bar — account colour, or amber when an upload is outstanding */}
                                <div
                                  className={`absolute bottom-0 left-0 top-0 rounded-l-md ${needsUpload ? 'w-1' : 'w-[3px]'}`}
                                  style={{ backgroundColor: barColor }}
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
                                    <div className="flex shrink-0 items-center gap-1">
                                      {sessionState && (
                                        <button
                                          type="button"
                                          onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            openSession(slot.session);
                                          }}
                                          title={sessionState.title}
                                          className={`inline-flex items-center gap-0.5 rounded px-1 py-px text-[8px] font-bold uppercase tracking-wide backdrop-blur transition-transform hover:scale-105 ${
                                            isOverdue ? 'animate-pulse ring-1 ring-[#F59E0B]' : ''
                                          }`}
                                          style={{ backgroundColor: sessionState.bg, color: sessionState.fg }}
                                        >
                                          {SessionStateIcon ? (
                                            <SessionStateIcon className="h-2.5 w-2.5" strokeWidth={2.5} />
                                          ) : (
                                            <span
                                              className="h-1.5 w-1.5 rounded-full"
                                              style={{ backgroundColor: sessionState.dot }}
                                            ></span>
                                          )}
                                          {!isCompact && <span>{sessionState.label}</span>}
                                        </button>
                                      )}
                                      {slot.isTemplate && (
                                        <span className="rounded bg-white/70 px-1 py-px font-mono text-[8px] font-semibold uppercase tracking-wide text-[#5B21B6] backdrop-blur">
                                          W
                                        </span>
                                      )}
                                    </div>
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
                                          className="mt-0.5 flex items-center gap-1 truncate font-mono text-[9px] uppercase tracking-wide"
                                          style={{ color: slotColor.text }}
                                          title={slot.platformAccount ?? pc.label}
                                        >
                                          <span className="shrink-0">{pc.label}</span>
                                          {slot.platformAccount && (
                                            <span className="truncate normal-case opacity-80">
                                              {slot.platformAccount}
                                            </span>
                                          )}
                                        </div>
                                      )}
                                      {gmvLabel && (
                                        <div className="mt-0.5 font-mono text-[9.5px] font-semibold leading-none tabular-nums text-[#0A0A0A]">
                                          {gmvLabel}
                                        </div>
                                      )}
                                      {needsUpload && (
                                        <div className="mt-1 inline-flex items-center gap-0.5 rounded bg-[#F59E0B] px-1 py-px text-[8.5px] font-bold uppercase tracking-wide text-white shadow-sm">
                                          <Upload className="h-2.5 w-2.5" strokeWidth={2.6} />
                                          {isOverdue ? 'No upload yet' : 'Needs upload'}
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
        liveAccounts={liveAccounts}
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
        liveAccounts={liveAccounts}
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
        onOpenSession={(session) => {
          setDetailTarget(null);
          openSession(session);
        }}
        onDeleted={() => setDetailTarget(null)}
      />

      <LiveSessionModal
        open={sessionTarget !== null}
        onOpenChange={(next) => {
          if (!next) {
            setSessionTarget(null);
          }
        }}
        session={sessionTarget}
        hosts={hosts}
        platformAccounts={platformAccounts}
      />

      <RegisterCreatorModal
        open={registerTarget !== null}
        onOpenChange={(next) => {
          if (!next) {
            setRegisterTarget(null);
          }
        }}
        creator={registerTarget}
        onRegistered={() => setRegisterTarget(null)}
      />

      {overrideAccount && (
        <SlotOverrideModal
          account={overrideAccount}
          suggestedSlots={suggestedSlotsFor(overrideAccount)}
          onClose={() => setOverrideAccount(null)}
          onSaved={() => router.reload({ only: ['slotOverrides', 'sessionSlots'], preserveScroll: true })}
        />
      )}
    </>
  );
}

SessionSlotsCalendar.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
