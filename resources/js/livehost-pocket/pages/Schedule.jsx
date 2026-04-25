import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';

/**
 * Schedule — weekly read-only roster.
 *
 * Props from {@link \App\Http\Controllers\LiveHostPocket\ScheduleController::index}:
 *   - `days` — array of 7 day buckets (Sunday-first) with nested `schedules`.
 *   - `totalSlots` — total active slots assigned to this host.
 *
 * Each slot may carry a `replacementRequest` payload describing an active
 * (pending/assigned) replacement request. That drives the per-card UI state:
 * a quiet "Mohon ganti" affordance (default), a violet left rule + diode +
 * "Menunggu PIC" with a Tarik balik chip (pending), or a settled card with
 * the replacement's avatar + name (assigned one_date). Permanent assigned
 * slots return null because ownership has transferred.
 */
export default function Schedule() {
  const { days, totalSlots } = usePage().props;
  const buckets = Array.isArray(days) ? days : [];
  const total = Number.isFinite(totalSlots) ? totalSlots : 0;

  const activeRequests = useMemo(() => {
    const list = [];
    buckets.forEach((bucket) => {
      (bucket.schedules ?? []).forEach((slot) => {
        const r = slot.replacementRequest;
        if (!r) return;
        const eligible =
          r.status === 'pending' ||
          (r.status === 'assigned' && r.scope === 'one_date');
        if (!eligible) return;
        list.push({ request: r, slot, dayName: bucket.dayName });
      });
    });
    return list;
  }, [buckets]);

  const pendingCount = useMemo(
    () => activeRequests.filter(({ request }) => request.status === 'pending').length,
    [activeRequests]
  );

  const [activeTab, setActiveTab] = useState('schedule');

  return (
    <>
      <Head title="Jadual" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <Header
          total={total}
          pendingCount={pendingCount}
          onJumpToRequests={() => setActiveTab('requests')}
        />

        <TabStrip
          activeTab={activeTab}
          onChange={setActiveTab}
          activeCount={activeRequests.length}
        />

        {activeTab === 'schedule' ? (
          <div>
            {buckets.map((bucket) => (
              <DayBucket key={bucket.dayOfWeek} bucket={bucket} />
            ))}
          </div>
        ) : (
          <ActiveRequestsList items={activeRequests} />
        )}

        <FooterHint pendingCount={pendingCount} />
      </div>
    </>
  );
}

function TabStrip({ activeTab, onChange, activeCount }) {
  return (
    <div className="mb-4 grid grid-cols-2 gap-1 rounded-full border border-[var(--hair)] bg-[var(--app-bg-2)] p-1">
      <TabButton active={activeTab === 'schedule'} onClick={() => onChange('schedule')}>
        Jadual
      </TabButton>
      <TabButton
        active={activeTab === 'requests'}
        onClick={() => onChange('requests')}
        count={activeCount}
      >
        Permohonan
      </TabButton>
    </div>
  );
}

function TabButton({ active, onClick, children, count }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`inline-flex h-[36px] items-center justify-center gap-[8px] rounded-full px-3 text-[12.5px] font-bold tracking-[-0.005em] transition active:scale-[0.98] ${
        active
          ? 'bg-[var(--fg)] text-[var(--app-bg)] shadow-[0_2px_8px_rgba(20,16,31,0.18)]'
          : 'text-[var(--fg-2)] hover:text-[var(--fg)]'
      }`}
    >
      <span>{children}</span>
      {typeof count === 'number' && count > 0 ? (
        <span
          className={`inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-[6px] font-mono text-[9.5px] font-bold tabular-nums ${
            active
              ? 'bg-white/20 text-[var(--app-bg)]'
              : 'bg-[var(--accent)] text-[var(--accent-ink)]'
          }`}
        >
          {count}
        </span>
      ) : null}
    </button>
  );
}

function ActiveRequestsList({ items }) {
  if (items.length === 0) {
    return (
      <div className="rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-4 py-8 text-center">
        <div className="mx-auto mb-2 grid h-[40px] w-[40px] place-items-center rounded-full"
          style={{ backgroundColor: 'var(--accent-soft)' }}
          aria-hidden="true"
        >
          <SwapIcon className="h-[18px] w-[18px] text-[var(--accent)]" />
        </div>
        <div className="font-display text-[14px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          Tiada permohonan ganti aktif.
        </div>
        <p className="mt-[6px] text-[11.5px] leading-relaxed text-[var(--fg-3)]">
          Tekan butang <span className="font-medium text-[var(--fg-2)]">Mohon ganti</span> pada mana-mana slot di tab <em className="not-italic font-medium text-[var(--fg-2)]">Jadual</em> untuk hantar permohonan.
        </p>
      </div>
    );
  }

  return (
    <div>
      {items.map(({ request, slot, dayName }) => (
        <ActiveRequestRow
          key={request.id}
          request={request}
          slot={slot}
          dayName={dayName}
        />
      ))}
    </div>
  );
}

Schedule.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function Header({ total, pendingCount, onJumpToRequests }) {
  return (
    <div className="px-1 pt-3 pb-4">
      <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Roster mingguan
      </div>
      <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
        Jadual anda.
        {pendingCount > 0 ? (
          <>
            {' '}
            <button
              type="button"
              onClick={onJumpToRequests}
              className="not-italic text-[var(--accent)] underline-offset-[3px] decoration-[var(--accent-soft)] hover:underline active:opacity-70"
              style={{ textDecorationThickness: '2px' }}
            >
              {pendingCount} menunggu pengganti.
            </button>
          </>
        ) : null}
      </h1>
      <div className="mt-2 flex items-center gap-[10px] font-mono text-[11px] tracking-[0.02em] text-[var(--fg-2)]">
        <span>{total} slot ditugaskan</span>
        {pendingCount > 0 ? (
          <>
            <span className="text-[var(--fg-4)]" aria-hidden="true">
              ·
            </span>
            <button
              type="button"
              onClick={onJumpToRequests}
              className="inline-flex items-center gap-[6px] active:opacity-70"
            >
              <span className="pocket-diode h-[5px] w-[5px]" aria-hidden="true" />
              <span className="font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
                {pendingCount} tertunda
              </span>
            </button>
          </>
        ) : null}
      </div>
    </div>
  );
}

// Curated palette so each day of the week carries a tiny color personality
// without overwhelming the page. Order matches Sunday → Saturday.
const DAY_DOT_COLORS = [
  'var(--hot)',            // Sun
  '#F59E0B',               // Mon — warm amber
  '#10B981',               // Tue — emerald
  '#06B6D4',               // Wed — cyan
  '#6366F1',               // Thu — indigo
  'var(--accent)',         // Fri — house violet
  '#EC4899',               // Sat — pink
];

function DayBucket({ bucket }) {
  const hasSchedules = (bucket.schedules?.length ?? 0) > 0;
  const dayColor = DAY_DOT_COLORS[bucket.dayOfWeek] ?? 'var(--fg-3)';
  const dayNameMs = DAY_NAMES_MS[bucket.dayName] ?? bucket.dayName;
  const dayShortMs = DAY_SHORT_MS[bucket.dayShort] ?? bucket.dayShort;

  return (
    <section className="mb-5">
      <div className="mb-2 flex items-center gap-[10px] px-1">
        <span
          className="h-[10px] w-[3px] flex-none rounded-full"
          style={{ backgroundColor: dayColor }}
          aria-hidden="true"
        />
        <span className="font-display text-[14px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          {dayNameMs}
        </span>
        <span
          className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
          style={{ backgroundColor: 'var(--hair)' }}
        >
          {dayShortMs}
        </span>
        <span
          className="ml-1 h-px flex-1"
          style={{ backgroundColor: 'var(--hair)' }}
          aria-hidden="true"
        />
        {hasSchedules ? (
          <span className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] tabular-nums">
            {bucket.schedules.length}
          </span>
        ) : null}
      </div>

      {hasSchedules ? (
        <div>
          {bucket.schedules.map((slot) => (
            <SlotCard key={slot.id} slot={slot} dayName={bucket.dayName} />
          ))}
        </div>
      ) : (
        <div className="rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-4 text-center font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          Tiada slot
        </div>
      )}
    </section>
  );
}

const DAY_NAMES_MS = {
  Sunday: 'Ahad',
  Monday: 'Isnin',
  Tuesday: 'Selasa',
  Wednesday: 'Rabu',
  Thursday: 'Khamis',
  Friday: 'Jumaat',
  Saturday: 'Sabtu',
};

const DAY_SHORT_MS = {
  Sun: 'AHD',
  Mon: 'ISN',
  Tue: 'SEL',
  Wed: 'RAB',
  Thu: 'KHA',
  Fri: 'JUM',
  Sat: 'SAB',
};

const MONTH_NAMES_MS = [
  'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun',
  'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember',
];

const REASON_OPTIONS = [
  { value: 'sick', label: 'Sakit' },
  { value: 'family', label: 'Keluarga' },
  { value: 'personal', label: 'Peribadi' },
  { value: 'other', label: 'Lain-lain' },
];

function nextOccurrenceIso(dayOfWeek, startTime) {
  if (typeof dayOfWeek !== 'number' || dayOfWeek < 0 || dayOfWeek > 6) {
    return '';
  }
  const now = new Date();
  let delta = (dayOfWeek - now.getDay() + 7) % 7;
  if (delta === 0) {
    // Slot is today: use it only if its start time is still in the future.
    const [h = 0, m = 0] = String(startTime ?? '00:00')
      .split(':')
      .map((n) => parseInt(n, 10));
    const slotStart = new Date(now);
    slotStart.setHours(h, m, 0, 0);
    delta = slotStart > now ? 0 : 7;
  }
  const target = new Date(now.getFullYear(), now.getMonth(), now.getDate() + delta);
  const y = target.getFullYear();
  const mo = String(target.getMonth() + 1).padStart(2, '0');
  const d = String(target.getDate()).padStart(2, '0');
  return `${y}-${mo}-${d}`;
}

function formatTargetDate(iso, { withYear = false } = {}) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-').map((s) => parseInt(s, 10));
  if (!y || !m || !d) return iso;
  const base = `${d} ${MONTH_NAMES_MS[m - 1] ?? ''}`.trim();
  return withYear ? `${base} ${y}` : base;
}

function initialsFrom(name) {
  if (!name) return '··';
  const parts = String(name).trim().split(/\s+/);
  if (parts.length === 0) return '··';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function platformColor(platformType) {
  if (platformType === 'tiktok') return '#E11D48';   // hot rose
  if (platformType === 'facebook') return '#2563EB'; // cool blue
  if (platformType === 'shopee') return '#F97316';   // orange
  if (platformType === 'instagram') return '#D946EF';// fuchsia
  return 'var(--accent)';                            // violet fallback
}

function platformTint(platformType) {
  if (platformType === 'tiktok') return 'rgba(225,29,72,0.06)';
  if (platformType === 'facebook') return 'rgba(37,99,235,0.06)';
  if (platformType === 'shopee') return 'rgba(249,115,22,0.06)';
  if (platformType === 'instagram') return 'rgba(217,70,239,0.06)';
  return 'rgba(124,58,237,0.06)';
}

function durationLabel(start, end) {
  if (!start || !end) return '';
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins < 0) mins += 24 * 60;
  if (mins <= 0) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h && m) return `${h}j ${m}m`;
  if (h) return `${h} jam`;
  return `${m} min`;
}

function SlotCard({ slot, dayName }) {
  const [modalOpen, setModalOpen] = useState(false);
  const withdraw = useForm({});

  const request = slot.replacementRequest;

  // Assigned permanent: ownership has transferred. Hide for snappy UX.
  if (request && request.status === 'assigned' && request.scope === 'permanent') {
    return null;
  }

  const platform = slot.platformAccount ?? slot.platformType ?? 'Platform';
  const accent = platformColor(slot.platformType);
  const tint = platformTint(slot.platformType);
  const duration = durationLabel(slot.startTime, slot.endTime);

  const isPending = request && request.status === 'pending';
  const isAssignedOneDate =
    request && request.status === 'assigned' && request.scope === 'one_date';

  // State-driven left rail color: pending = violet, assigned = cool blue,
  // default = platform brand color.
  const railColor = isPending
    ? 'var(--accent)'
    : isAssignedOneDate
      ? 'var(--cool)'
      : accent;

  // Subtle painted background on the card: a faint tint pulled from the rail
  // color, fading to white. Adds color presence without overwhelming.
  const cardBackground = isAssignedOneDate
    ? 'var(--app-bg-3)'
    : isPending
      ? 'linear-gradient(95deg, rgba(124,58,237,0.07), var(--app-bg-2) 55%)'
      : `linear-gradient(95deg, ${tint}, var(--app-bg-2) 55%)`;

  const handleWithdraw = () => {
    if (!request) return;
    const ok = window.confirm('Tarik balik permohonan ganti slot ini?');
    if (!ok) return;
    withdraw.delete(`/live-host/replacement-requests/${request.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <div
        className="relative mb-[8px] overflow-hidden rounded-[14px] border border-[var(--hair)] pl-[14px] pr-3 py-[12px]"
        style={{ background: cardBackground }}
      >
        {/* Colored left rail. */}
        <span
          className="absolute left-0 top-0 bottom-0 w-[4px]"
          style={{ backgroundColor: railColor }}
          aria-hidden="true"
        />

        {/* Header row: platform name + state pills. */}
        <div className="flex items-center justify-between gap-2">
          <span className="inline-flex items-center gap-[6px] font-mono text-[9px] font-bold uppercase tracking-[0.14em]">
            <span
              className="h-[6px] w-[6px] rounded-full"
              style={{
                backgroundColor: accent,
                boxShadow: `0 0 0 2px ${tint}`,
              }}
              aria-hidden="true"
            />
            <span className="text-[var(--fg-2)]">{platform}</span>
          </span>
          <div className="flex items-center gap-[5px]">
            {slot.isRecurring ? (
              <span
                className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
                style={{ backgroundColor: 'var(--hair)' }}
              >
                MINGGUAN
              </span>
            ) : null}
            {isAssignedOneDate ? (
              <span
                className="inline-flex items-center gap-[5px] rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--cool)]"
                style={{ backgroundColor: 'rgba(37,99,235,0.12)' }}
              >
                <CheckIcon className="h-[8px] w-[8px]" />
                TELAH DIGANTI
              </span>
            ) : null}
          </div>
        </div>

        {/* Time block — anchor of the card. */}
        <div className="mt-[6px] flex items-baseline justify-between gap-2">
          <div
            className={`font-mono text-[17px] font-bold leading-none tabular-nums tracking-[-0.01em] ${
              isAssignedOneDate ? 'text-[var(--fg-2)]' : 'text-[var(--fg)]'
            }`}
          >
            {slot.startTime}
            <span
              className="mx-[6px] text-[var(--fg-4)]"
              aria-hidden="true"
            >
              –
            </span>
            {slot.endTime}
          </div>
          {duration ? (
            <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
              {duration}
            </span>
          ) : null}
        </div>

        {slot.remarks && !isPending && !isAssignedOneDate ? (
          <div className="mt-[8px] text-[11.5px] leading-snug text-[var(--fg-2)]">
            {slot.remarks}
          </div>
        ) : null}

        {/* Pending: violet diode + label + Tarik balik chip. */}
        {isPending ? (
          <div className="mt-[10px] flex items-center justify-between gap-2 border-t border-[var(--hair)] pt-[8px]">
            <span className="inline-flex items-center gap-[6px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
              <span className="pocket-diode h-[5px] w-[5px]" aria-hidden="true" />
              MENUNGGU PIC
            </span>
            <button
              type="button"
              onClick={handleWithdraw}
              disabled={withdraw.processing}
              className="inline-flex h-[28px] items-center rounded-full border border-[var(--hot)] px-[12px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--hot)] transition active:opacity-60 disabled:opacity-50"
              style={{ backgroundColor: 'rgba(225,29,72,0.06)' }}
            >
              Tarik balik
            </button>
          </div>
        ) : null}

        {/* Assigned (one_date): avatar + name + date moment. */}
        {isAssignedOneDate ? (
          <div className="mt-[10px] flex items-center gap-[10px] border-t border-[var(--hair)] pt-[8px]">
            <span
              className="grid h-[26px] w-[26px] flex-none place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] font-display text-[10px] font-bold tracking-[-0.04em] text-white shadow-[0_2px_6px_rgba(124,58,237,0.35)]"
              aria-hidden="true"
            >
              {initialsFrom(request.replacementHostName)}
            </span>
            <div className="text-[12px] leading-snug text-[var(--fg-2)]">
              Diganti oleh{' '}
              <span className="font-medium text-[var(--fg)]">
                {request.replacementHostName ?? '—'}
              </span>
              {request.targetDate ? (
                <>
                  <span className="px-[6px] text-[var(--fg-4)]">·</span>
                  <span className="font-mono tabular-nums text-[var(--fg-3)]">
                    {formatTargetDate(request.targetDate)}
                  </span>
                </>
              ) : null}
            </div>
          </div>
        ) : null}

        {/* Default state: prominent violet "Mohon ganti" pill. */}
        {!request ? (
          <div className="mt-[10px] flex items-center justify-end border-t border-[var(--hair)] pt-[8px]">
            <button
              type="button"
              onClick={() => setModalOpen(true)}
              className="group inline-flex h-[30px] items-center gap-[7px] rounded-full px-[12px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--accent-ink)] shadow-[0_4px_12px_-4px_rgba(124,58,237,0.45)] transition active:scale-[0.97]"
              style={{ backgroundColor: 'var(--accent)' }}
              aria-label={`Mohon ganti slot ${dayName} ${slot.startTime}–${slot.endTime}`}
            >
              <SwapIcon className="h-[11px] w-[11px]" />
              Mohon ganti
            </button>
          </div>
        ) : null}
      </div>

      {modalOpen ? (
        <RequestModal
          slot={slot}
          dayName={dayName}
          onClose={() => setModalOpen(false)}
        />
      ) : null}
    </>
  );
}

const REASON_LABEL_BY_VALUE = REASON_OPTIONS.reduce((acc, opt) => {
  acc[opt.value] = opt.label;
  return acc;
}, {});

function timeAgoMs(iso) {
  if (!iso) return '';
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '';
  const seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
  if (seconds < 60) return 'baru sahaja';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} minit lalu`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} jam lalu`;
  const days = Math.floor(hours / 24);
  return `${days} hari lalu`;
}

function ActiveRequestRow({ request, slot, dayName }) {
  const withdraw = useForm({});
  const dayMs = DAY_NAMES_MS[dayName] ?? dayName;
  const isPending = request.status === 'pending';
  const reasonLabel = REASON_LABEL_BY_VALUE[request.reasonCategory] ?? '';

  // Live-updating time-ago label.
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (!isPending) return undefined;
    const id = setInterval(() => setNow(Date.now()), 30_000);
    return () => clearInterval(id);
  }, [isPending]);
  // `now` participates so the label re-computes; eslint may want this referenced.
  void now;

  const handleWithdraw = () => {
    const ok = window.confirm('Tarik balik permohonan ganti slot ini?');
    if (!ok) return;
    withdraw.delete(`/live-host/replacement-requests/${request.id}`, {
      preserveScroll: true,
    });
  };

  const accentColor = isPending ? 'var(--accent)' : 'var(--cool)';
  const accentTint = isPending ? 'rgba(124,58,237,0.07)' : 'rgba(37,99,235,0.07)';

  return (
    <div
      className="relative mb-[8px] overflow-hidden rounded-[14px] border border-[var(--hair)] pl-[14px] pr-3 py-[12px]"
      style={{
        background: `linear-gradient(95deg, ${accentTint}, var(--app-bg-2) 60%)`,
      }}
    >
      <span
        className="absolute left-0 top-0 bottom-0 w-[4px]"
        style={{ backgroundColor: accentColor }}
        aria-hidden="true"
      />

      <div className="flex items-center justify-between gap-2">
        {isPending ? (
          <span className="inline-flex items-center gap-[6px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
            <span className="pocket-diode h-[5px] w-[5px]" aria-hidden="true" />
            MENUNGGU PIC
          </span>
        ) : (
          <span
            className="inline-flex items-center gap-[5px] rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--cool)]"
            style={{ backgroundColor: 'rgba(37,99,235,0.12)' }}
          >
            <CheckIcon className="h-[8px] w-[8px]" />
            TELAH DIGANTI
          </span>
        )}
        <span className="font-mono text-[9.5px] tracking-[0.04em] text-[var(--fg-3)]">
          {isPending && request.requestedAt
            ? timeAgoMs(request.requestedAt)
            : null}
        </span>
      </div>

      <div className="mt-[6px] font-display text-[15px] font-medium leading-tight tracking-[-0.01em] text-[var(--fg)]">
        {dayMs}
        {request.targetDate ? (
          <>
            ,{' '}
            <span className="font-mono tabular-nums">
              {formatTargetDate(request.targetDate, { withYear: true })}
            </span>
          </>
        ) : null}
      </div>
      <div className="mt-[2px] font-mono text-[10.5px] tabular-nums tracking-[0.04em] text-[var(--fg-3)]">
        {slot.startTime} – {slot.endTime}
        {reasonLabel ? (
          <>
            <span className="px-[6px] text-[var(--fg-4)]">·</span>
            <span>Sebab: <span className="text-[var(--fg-2)]">{reasonLabel}</span></span>
          </>
        ) : null}
      </div>

      {!isPending && request.replacementHostName ? (
        <div className="mt-[8px] flex items-center gap-[10px] border-t border-[var(--hair)] pt-[8px]">
          <span
            className="grid h-[24px] w-[24px] flex-none place-items-center rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)] font-display text-[10px] font-bold tracking-[-0.04em] text-white shadow-[0_2px_6px_rgba(124,58,237,0.35)]"
            aria-hidden="true"
          >
            {initialsFrom(request.replacementHostName)}
          </span>
          <div className="text-[11.5px] leading-snug text-[var(--fg-2)]">
            Diganti oleh{' '}
            <span className="font-medium text-[var(--fg)]">
              {request.replacementHostName}
            </span>
          </div>
        </div>
      ) : null}

      {isPending ? (
        <div className="mt-[10px] flex items-center justify-end border-t border-[var(--hair)] pt-[8px]">
          <button
            type="button"
            onClick={handleWithdraw}
            disabled={withdraw.processing}
            className="inline-flex h-[28px] items-center rounded-full border border-[var(--hot)] px-[12px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--hot)] transition active:opacity-60 disabled:opacity-50"
            style={{ backgroundColor: 'rgba(225,29,72,0.06)' }}
          >
            Tarik balik
          </button>
        </div>
      ) : null}
    </div>
  );
}

function FooterHint({ pendingCount }) {
  return (
    <div className="mt-3 px-2 pb-2 text-center">
      <p className="text-[11px] leading-relaxed text-[var(--fg-3)]">
        Tekan{' '}
        <span className="inline-flex items-center gap-[4px] align-baseline font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
          <SwapIcon className="h-[10px] w-[10px]" />
          Mohon ganti
        </span>{' '}
        untuk minta pengganti hadir.{' '}
        <em className="not-italic text-[var(--fg-2)]">
          Untuk pertukaran kekal, hubungi PIC anda.
        </em>
      </p>
      {pendingCount > 0 ? (
        <p className="mt-[6px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
          {pendingCount} permohonan dihantar · menunggu PIC
        </p>
      ) : null}
    </div>
  );
}

/**
 * RequestModal — host submits a replacement request for a given slot.
 *
 * Bottom-sheet treatment with a slot-preview header, segmented scope control,
 * reason chips, and an editorial commission note. POSTs to
 * `/live-host/replacement-requests` (named `live-host.replacement-requests.store`).
 * Uses literal URLs because this pocket app does not expose Ziggy's `route()`
 * helper on the JS side (other pocket pages use literal URL strings too).
 */
function RequestModal({ slot, dayName, onClose }) {
  // The target date is fully implied by the slot — a host's emergency on a
  // recurring Sunday slot is always for the next upcoming Sunday. Compute it
  // on mount so the host doesn't have to pick anything.
  const computedTargetDate = useMemo(
    () => nextOccurrenceIso(slot.dayOfWeek, slot.startTime),
    [slot.dayOfWeek, slot.startTime]
  );

  const form = useForm({
    live_schedule_assignment_id: slot.id,
    scope: 'one_date',
    target_date: computedTargetDate,
    reason_category: 'sick',
    reason_note: '',
  });

  const dayMs = DAY_NAMES_MS[dayName] ?? dayName;
  const timeRange = `${slot.startTime} – ${slot.endTime}`;
  const platform = slot.platformAccount ?? slot.platformType ?? 'Platform';
  const slotDuration = durationLabel(slot.startTime, slot.endTime);

  // Lock body scroll while modal is open.
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, []);

  // Close on Escape key.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const submit = (e) => {
    e.preventDefault();
    const payload = { ...form.data };
    if (payload.scope !== 'one_date') {
      payload.target_date = '';
    }
    form.transform(() => payload);
    form.post('/live-host/replacement-requests', {
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const errors = form.errors ?? {};
  const reasonLabel =
    REASON_OPTIONS.find((opt) => opt.value === form.data.reason_category)?.label ??
    '';

  // Editorial summary line.
  const dateLabel = form.data.target_date
    ? `${dayMs}, ${formatTargetDate(form.data.target_date, { withYear: true })}`
    : `hari ${dayMs}`;
  const summary = `Mohon pengganti untuk ${dateLabel} · ${timeRange}.`;

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-[2px] sm:items-center sm:px-4 sm:py-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="replacement-modal-title"
      onClick={onClose}
    >
      <div
        className="w-full max-w-[480px] overflow-hidden rounded-t-[22px] border border-[var(--hair)] bg-[var(--app-bg)] shadow-[0_-12px_40px_rgba(20,16,31,0.18)] sm:rounded-[22px]"
        onClick={(e) => e.stopPropagation()}
      >
        <form onSubmit={submit}>
          {/* Grab handle (mobile bottom-sheet affordance). */}
          <div className="flex justify-center pt-[8px] pb-[2px] sm:hidden">
            <span
              className="h-[4px] w-[36px] rounded-full bg-[var(--hair-2)]"
              aria-hidden="true"
            />
          </div>

          {/* Header — pretitle + display title + editorial subtitle. */}
          <div className="px-5 pt-3 pb-4">
            <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
              Permohonan ganti · {dayMs}
            </div>
            <h2
              id="replacement-modal-title"
              className="font-display text-[22px] font-medium leading-[1.06] tracking-[-0.03em] text-[var(--fg)]"
            >
              Mohon Ganti Slot.
            </h2>
            <p className="mt-[6px] text-[12px] italic leading-relaxed text-[var(--fg-2)]">
              Komisen sesi ini akan diberi kepada pengganti, bukan anda.
            </p>
          </div>

          {/* Platform label — small ID line so the host knows which slot
              they're acting on. The full slot details (day + date + time)
              live in the Tarikh slot block below. */}
          <div className="mx-5 mb-4 flex items-center gap-[8px]">
            <span
              className="h-[6px] w-[6px] rounded-full"
              style={{
                backgroundColor: platformColor(slot.platformType),
                boxShadow: `0 0 0 2px ${platformTint(slot.platformType)}`,
              }}
              aria-hidden="true"
            />
            <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
              {platform}
            </span>
            {slot.isRecurring ? (
              <span
                className="ml-auto inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
                style={{ backgroundColor: 'var(--hair)' }}
              >
                MINGGUAN
              </span>
            ) : null}
          </div>

          <div className="max-h-[60vh] overflow-y-auto px-5">
            {/* Scope is locked to "one_date" for the host-side flow; permanent
                replacements are handled by the PIC out-of-band. The segmented
                control is intentionally hidden so there is nothing to choose. */}

            {/* Target date — auto-resolved to the next occurrence of this
                slot. Read-only; no picker, no decision. */}
            <div className="mb-5">
              <div className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
                Tarikh slot
              </div>
              <div className="flex items-center gap-[12px] rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[12px]">
                <span
                  className="grid h-[36px] w-[36px] flex-none place-items-center rounded-[10px]"
                  style={{ backgroundColor: 'var(--accent-soft)' }}
                  aria-hidden="true"
                >
                  <CalendarIcon className="h-[18px] w-[18px] text-[var(--accent)]" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="font-display text-[15px] font-medium leading-tight tracking-[-0.01em] text-[var(--fg)]">
                    {dayMs}
                    {form.data.target_date ? (
                      <>
                        ,{' '}
                        <span className="font-mono tabular-nums">
                          {formatTargetDate(form.data.target_date, { withYear: true })}
                        </span>
                      </>
                    ) : null}
                  </div>
                  <div className="mt-[2px] font-mono text-[10.5px] tracking-[0.04em] text-[var(--fg-3)]">
                    {timeRange}
                    {slotDuration ? (
                      <>
                        <span className="px-[5px] text-[var(--fg-4)]">·</span>
                        <span>{slotDuration}</span>
                      </>
                    ) : null}
                  </div>
                </div>
              </div>
              {errors.target_date ? (
                <p className="mt-1 text-[11px] text-[var(--hot)]">
                  {errors.target_date}
                </p>
              ) : null}
            </div>

            {/* Reason — chip row. */}
            <div className="mb-5">
              <div className="mb-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
                Sebab
              </div>
              <div className="flex flex-wrap gap-[6px]">
                {REASON_OPTIONS.map((opt) => {
                  const active = form.data.reason_category === opt.value;
                  return (
                    <button
                      key={opt.value}
                      type="button"
                      onClick={() => form.setData('reason_category', opt.value)}
                      className={`inline-flex h-[34px] items-center rounded-full border px-[14px] text-[12.5px] font-medium tracking-[-0.005em] transition active:scale-[0.97] ${
                        active
                          ? 'border-[var(--accent)] bg-[var(--accent)] text-[var(--accent-ink)]'
                          : 'border-[var(--hair-2)] bg-[var(--app-bg-2)] text-[var(--fg-2)] hover:border-[var(--fg-3)] hover:text-[var(--fg)]'
                      }`}
                      aria-pressed={active}
                    >
                      {opt.label}
                    </button>
                  );
                })}
              </div>
              {errors.reason_category ? (
                <p className="mt-2 text-[11px] text-[var(--hot)]">
                  {errors.reason_category}
                </p>
              ) : null}
            </div>

            {/* Note (optional). */}
            <div className="mb-5">
              <label
                htmlFor="rr-note"
                className="mb-2 block font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]"
              >
                Catatan{' '}
                <span className="font-normal lowercase tracking-[0] text-[var(--fg-3)]">
                  (pilihan)
                </span>
              </label>
              <textarea
                id="rr-note"
                maxLength={500}
                rows={3}
                value={form.data.reason_note}
                onChange={(e) => form.setData('reason_note', e.target.value)}
                placeholder="Beritahu PIC anda perincian, jika ada."
                className="w-full rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px] text-[13.5px] leading-snug text-[var(--fg)] outline-none transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent-soft)]"
              />
              {errors.reason_note ? (
                <p className="mt-1 text-[11px] text-[var(--hot)]">
                  {errors.reason_note}
                </p>
              ) : null}
            </div>

            {/* Editorial summary recap — only when something to summarize. */}
            <div className="mb-4 border-t border-[var(--hair)] pt-3">
              <p className="text-[12px] leading-relaxed text-[var(--fg-2)]">
                <em className="not-italic font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
                  Ringkasan ·{' '}
                </em>
                {summary}{' '}
                {reasonLabel ? (
                  <span>
                    Sebab: <span className="text-[var(--fg)]">{reasonLabel}</span>.
                  </span>
                ) : null}
              </p>
            </div>

            {errors.live_schedule_assignment_id ? (
              <p className="mb-3 text-[11px] text-[var(--hot)]">
                {errors.live_schedule_assignment_id}
              </p>
            ) : null}
          </div>

          {/* Footer actions. */}
          <div className="sticky bottom-0 flex items-center gap-[8px] border-t border-[var(--hair)] bg-[var(--app-bg)] px-5 py-3 pb-[max(env(safe-area-inset-bottom),12px)]">
            <button
              type="button"
              onClick={onClose}
              disabled={form.processing}
              className="inline-flex h-[44px] flex-none items-center rounded-[12px] px-4 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)] transition hover:text-[var(--fg)] active:opacity-60 disabled:opacity-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={form.processing}
              className="ml-auto inline-flex h-[44px] flex-1 items-center justify-center rounded-[12px] bg-[var(--accent)] px-4 text-[13px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] shadow-[0_8px_22px_-8px_rgba(124,58,237,0.55)] transition active:scale-[0.98] disabled:opacity-50"
            >
              {form.processing ? 'Menghantar…' : 'Hantar permohonan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function SwapIcon({ className = '' }) {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M3 6h9M9 3l3 3-3 3" />
      <path d="M13 10H4M7 13l-3-3 3-3" />
    </svg>
  );
}

function CheckIcon({ className = '' }) {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M3 8.5l3 3 7-7.5" />
    </svg>
  );
}

function CalendarIcon({ className = '' }) {
  return (
    <svg
      viewBox="0 0 20 20"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <rect x="3" y="4.5" width="14" height="12.5" rx="2" />
      <path d="M3 8.5h14" />
      <path d="M7 3v3M13 3v3" />
    </svg>
  );
}
