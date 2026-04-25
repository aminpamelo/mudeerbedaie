import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
  const { days, totalSlots, pendingRecaps, week } = usePage().props;
  const buckets = Array.isArray(days) ? days : [];
  const total = Number.isFinite(totalSlots) ? totalSlots : 0;
  const recaps = Array.isArray(pendingRecaps) ? pendingRecaps : [];
  const weekInfo = week ?? null;

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
          recapsCount={recaps.length}
        />

        {activeTab === 'schedule' ? (
          <div>
            {weekInfo ? <WeekNavigator week={weekInfo} /> : null}
            {buckets.map((bucket) => (
              <DayBucket key={bucket.dayOfWeek} bucket={bucket} />
            ))}
          </div>
        ) : activeTab === 'requests' ? (
          <ActiveRequestsList items={activeRequests} />
        ) : (
          <PendingRecapsList items={recaps} />
        )}

        <FooterHint pendingCount={pendingCount} />
      </div>
    </>
  );
}

function TabStrip({ activeTab, onChange, activeCount, recapsCount }) {
  return (
    <div className="mb-4 grid grid-cols-3 gap-1 rounded-full border border-[var(--hair)] bg-[var(--app-bg-2)] p-1">
      <TabButton active={activeTab === 'schedule'} onClick={() => onChange('schedule')}>
        Jadual
      </TabButton>
      <TabButton
        active={activeTab === 'requests'}
        onClick={() => onChange('requests')}
        count={activeCount}
        countTone="accent"
      >
        Ganti
      </TabButton>
      <TabButton
        active={activeTab === 'recaps'}
        onClick={() => onChange('recaps')}
        count={recapsCount}
        countTone="warm"
      >
        Rekap
      </TabButton>
    </div>
  );
}

function TabButton({ active, onClick, children, count, countTone = 'accent' }) {
  const inactiveBadgeClass =
    countTone === 'warm'
      ? 'bg-[var(--warm)] text-white'
      : 'bg-[var(--accent)] text-[var(--accent-ink)]';
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`inline-flex h-[36px] items-center justify-center gap-[6px] rounded-full px-2 text-[12px] font-bold tracking-[-0.005em] transition active:scale-[0.98] ${
        active
          ? 'bg-[var(--fg)] text-[var(--app-bg)] shadow-[0_2px_8px_rgba(20,16,31,0.18)]'
          : 'text-[var(--fg-2)] hover:text-[var(--fg)]'
      }`}
    >
      <span>{children}</span>
      {typeof count === 'number' && count > 0 ? (
        <span
          className={`inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-[6px] font-mono text-[9.5px] font-bold tabular-nums ${
            active ? 'bg-white/20 text-[var(--app-bg)]' : inactiveBadgeClass
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

function PendingRecapsList({ items }) {
  if (items.length === 0) {
    return (
      <div className="rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-4 py-8 text-center">
        <div
          className="mx-auto mb-2 grid h-[40px] w-[40px] place-items-center rounded-full"
          style={{ backgroundColor: 'rgba(245,158,11,0.14)' }}
          aria-hidden="true"
        >
          <CheckIcon className="h-[18px] w-[18px] text-[var(--warm)]" />
        </div>
        <div className="font-display text-[14px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          Tiada rekap menunggu.
        </div>
        <p className="mt-[6px] text-[11.5px] leading-relaxed text-[var(--fg-3)]">
          Semua sesi terdahulu sudah dikemas kini. Bagus!
        </p>
      </div>
    );
  }

  return (
    <div>
      {items.map((session) => (
        <PendingRecapRow key={session.id} session={session} />
      ))}
    </div>
  );
}

function PendingRecapRow({ session }) {
  const platform = session.platformAccount ?? session.platformType ?? 'Platform';
  const platformAccent = platformColor(session.platformType);
  const platformTintBg = platformTint(session.platformType);
  const needsUpload = Boolean(session.needsUpload);
  const startSource = session.actualStartAt ?? session.scheduledStartAt;
  const startDate = startSource ? new Date(startSource) : null;
  const endDate = session.actualEndAt ? new Date(session.actualEndAt) : null;
  const startLabel = startDate ? formatTwoLineWhen(startDate) : null;
  const timeRange = startDate
    ? `${pad2(startDate.getHours())}:${pad2(startDate.getMinutes())}${
        endDate
          ? ` – ${pad2(endDate.getHours())}:${pad2(endDate.getMinutes())}`
          : ''
      }`
    : '—';
  const duration = session.durationMinutes
    ? formatRecapDuration(session.durationMinutes)
    : null;

  return (
    <a
      href={`/live-host/sessions/${session.id}${needsUpload ? '' : '?recap=yes'}`}
      className="relative mb-[8px] block overflow-hidden rounded-[14px] border border-[var(--hair)] pl-[14px] pr-3 py-[12px] transition active:scale-[0.99]"
      style={{
        background: 'linear-gradient(95deg, rgba(245,158,11,0.07), var(--app-bg-2) 60%)',
      }}
    >
      <span
        className="absolute left-0 top-0 bottom-0 w-[4px]"
        style={{ backgroundColor: 'var(--warm)' }}
        aria-hidden="true"
      />

      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-[6px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
          <span
            className="h-[6px] w-[6px] rounded-full"
            style={{
              backgroundColor: platformAccent,
              boxShadow: `0 0 0 2px ${platformTintBg}`,
            }}
            aria-hidden="true"
          />
          {platform}
        </span>
        <span
          className="inline-flex items-center gap-[5px] rounded-full px-[7px] py-[3px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--warm)]"
          style={{ backgroundColor: 'rgba(245,158,11,0.14)' }}
        >
          <span
            className="h-[5px] w-[5px] rounded-full"
            style={{
              backgroundColor: 'var(--warm)',
              boxShadow: '0 0 0 2px rgba(245,158,11,0.18)',
            }}
            aria-hidden="true"
          />
          {needsUpload ? 'PERLU UPLOAD' : 'REKAP TERTUNDA'}
        </span>
      </div>

      <div className="mt-[6px] font-display text-[15px] font-medium leading-tight tracking-[-0.015em] text-[var(--fg)]">
        {session.title || 'Sesi tanpa tajuk'}
      </div>
      {startLabel ? (
        <div className="mt-[2px] font-mono text-[10.5px] tabular-nums tracking-[0.04em] text-[var(--fg-3)]">
          {startLabel}
          <span className="px-[6px] text-[var(--fg-4)]">·</span>
          <span className="text-[var(--fg-2)]">{timeRange}</span>
          {duration ? (
            <>
              <span className="px-[6px] text-[var(--fg-4)]">·</span>
              <span>{duration}</span>
            </>
          ) : null}
        </div>
      ) : null}

      <div className="mt-[10px] flex items-center justify-between border-t border-[var(--hair)] pt-[8px]">
        <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--warm)]">
          {needsUpload ? 'Muat naik bukti' : 'Hantar rekap'}
        </span>
        <span
          className="grid h-[24px] w-[24px] place-items-center rounded-full"
          style={{ backgroundColor: 'rgba(245,158,11,0.14)' }}
          aria-hidden="true"
        >
          <ArrowGlyph className="h-[10px] w-[10px] text-[var(--warm)]" />
        </span>
      </div>
    </a>
  );
}

function ArrowGlyph({ className = '' }) {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M3 8h10M9 4l4 4-4 4" />
    </svg>
  );
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function formatTwoLineWhen(date) {
  const now = new Date();
  const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  const dayDiff = Math.round((startOfDay(date) - startOfDay(now)) / 86_400_000);
  if (dayDiff === 0) return 'Hari ini';
  if (dayDiff === -1) return 'Semalam';
  if (Math.abs(dayDiff) < 7) {
    const short = ['Ahd', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab'][date.getDay()];
    return short;
  }
  const months = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
  return `${date.getDate()} ${months[date.getMonth()]}`;
}

function formatRecapDuration(minutes) {
  const m = Number(minutes);
  if (!Number.isFinite(m) || m <= 0) return null;
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  const rest = m % 60;
  return rest > 0 ? `${h}j ${rest}m` : `${h} jam`;
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
  const isPast = Boolean(bucket.isPast);
  const isToday = Boolean(bucket.isToday);
  const dateLabel = bucket.date ? formatBucketDate(bucket.date) : null;

  return (
    <section className={`mb-5 ${isPast ? 'opacity-70' : ''}`}>
      <div className="mb-2 flex items-center gap-[10px] px-1">
        <span
          className="h-[10px] w-[3px] flex-none rounded-full"
          style={{ backgroundColor: dayColor }}
          aria-hidden="true"
        />
        <span
          className={`font-display text-[14px] tracking-[-0.015em] ${
            isToday
              ? 'font-bold text-[var(--accent)]'
              : 'font-medium text-[var(--fg)]'
          }`}
        >
          {dayNameMs}
        </span>
        {dateLabel ? (
          <span className="font-mono text-[10px] tabular-nums text-[var(--fg-3)]">
            {dateLabel}
          </span>
        ) : null}
        {isToday ? (
          <span
            className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent-ink)]"
            style={{ backgroundColor: 'var(--accent)' }}
          >
            HARI INI
          </span>
        ) : (
          <span
            className="inline-flex items-center rounded-full px-[7px] py-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]"
            style={{ backgroundColor: 'var(--hair)' }}
          >
            {dayShortMs}
          </span>
        )}
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
            <SlotCard
              key={slot.id}
              slot={slot}
              dayName={bucket.dayName}
              bucketDate={bucket.date}
              isPast={isPast}
              isToday={isToday}
            />
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

function WeekNavigator({ week }) {
  const isCurrent = Boolean(week.isCurrent);

  return (
    <div className="mb-4 flex items-center gap-[8px] rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[10px] py-[8px]">
      <Link
        href={`/live-host/schedule?week=${encodeURIComponent(week.prev)}`}
        preserveScroll
        aria-label="Minggu sebelum"
        className="grid h-[32px] w-[32px] flex-none place-items-center rounded-full border border-[var(--hair)] bg-[var(--app-bg)] text-[var(--fg-2)] transition active:scale-[0.95] hover:text-[var(--fg)]"
      >
        <ChevronIcon className="h-[12px] w-[12px]" direction="left" />
      </Link>

      <div className="min-w-0 flex-1 text-center">
        <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          {isCurrent ? 'Minggu ini' : 'Minggu'}
        </div>
        <div className="mt-[1px] truncate font-display text-[13px] font-medium tracking-[-0.01em] text-[var(--fg)] tabular-nums">
          {week.label}
        </div>
      </div>

      <Link
        href={`/live-host/schedule?week=${encodeURIComponent(week.next)}`}
        preserveScroll
        aria-label="Minggu seterusnya"
        className="grid h-[32px] w-[32px] flex-none place-items-center rounded-full border border-[var(--hair)] bg-[var(--app-bg)] text-[var(--fg-2)] transition active:scale-[0.95] hover:text-[var(--fg)]"
      >
        <ChevronIcon className="h-[12px] w-[12px]" direction="right" />
      </Link>

      {isCurrent ? (
        <span
          className="ml-[2px] inline-flex h-[32px] flex-none items-center rounded-full px-[12px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent-ink)] shadow-[0_4px_10px_-4px_rgba(124,58,237,0.45)]"
          style={{ backgroundColor: 'var(--accent)' }}
          aria-label="Minggu ini mengandungi hari ini"
        >
          Hari ini
        </span>
      ) : (
        <Link
          href={`/live-host/schedule?week=${encodeURIComponent(week.today)}`}
          preserveScroll
          aria-label="Pulang ke minggu hari ini"
          className="ml-[2px] inline-flex h-[32px] flex-none items-center gap-[5px] rounded-full border border-[var(--hair)] bg-[var(--app-bg)] px-[10px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)] transition active:scale-[0.97] hover:text-[var(--fg)]"
        >
          <ChevronIcon className="h-[10px] w-[10px]" direction="left" />
          Pulang
        </Link>
      )}
    </div>
  );
}

function ChevronIcon({ className = '', direction = 'right' }) {
  const path = direction === 'left' ? 'M10 4l-4 4 4 4' : 'M6 4l4 4-4 4';
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d={path} />
    </svg>
  );
}

function formatBucketDate(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-').map((s) => parseInt(s, 10));
  if (!y || !m || !d) return '';
  const months = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
  return `${d} ${months[m - 1] ?? ''}`.trim();
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

function hasStartTimePassed(startTime) {
  if (!startTime) return false;
  const [h = 0, m = 0] = String(startTime).split(':').map((n) => parseInt(n, 10));
  const now = new Date();
  const slotStart = new Date(now);
  slotStart.setHours(h, m, 0, 0);
  return slotStart <= now;
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

function SlotCard({ slot, dayName, bucketDate, isPast = false, isToday = false }) {
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

  // The request affordance is only meaningful when the slot's actual date
  // is still in the future. Hide it for past dates and for today when the
  // start time has already passed — server-side validation rejects past
  // target_date anyway.
  const slotStartPassed = isToday && hasStartTimePassed(slot.startTime);
  const canRequestReplacement = !isPast && !slotStartPassed;

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

        {/* Default state: prominent violet "Mohon ganti" pill (hidden on
            past dates / passed time slots). */}
        {!request && canRequestReplacement ? (
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

        {/* Recap action: surface "Muat naik bukti" / "Rekap tertunda" when a
            linked session is owed work. Hidden while a replacement state is
            showing (request UI takes priority). */}
        {!request && slot.recapAction && !canRequestReplacement ? (
          <RecapActionLink action={slot.recapAction} />
        ) : null}
      </div>

      {modalOpen ? (
        <RequestModal
          slot={slot}
          dayName={dayName}
          targetDate={bucketDate}
          onClose={() => setModalOpen(false)}
        />
      ) : null}
    </>
  );
}

function RecapActionLink({ action, defaultWentLive = null }) {
  const [open, setOpen] = useState(false);
  const submitted = action?.state === 'submitted' || action?.submitted === true;
  const needsUpload = Boolean(action?.needsUpload);
  const sessionId = action?.sessionId;
  if (!sessionId) return null;

  const label = submitted
    ? 'Lihat rekap'
    : needsUpload
      ? 'Muat naik bukti'
      : 'Hantar rekap';
  const pillLabel = submitted
    ? 'BUKTI DIHANTAR'
    : needsUpload
      ? 'PERLU UPLOAD'
      : 'REKAP TERTUNDA';

  // Submitted state uses emerald to read as "done"; pending/upload stays
  // amber so the eye is drawn to the slots that still need work.
  const tone = submitted
    ? { fg: '#10B981', bg: 'rgba(16,185,129,0.14)', ring: 'rgba(16,185,129,0.18)' }
    : { fg: 'var(--warm)', bg: 'rgba(245,158,11,0.14)', ring: 'rgba(245,158,11,0.18)' };

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="mt-[10px] flex w-full items-center justify-between gap-2 border-t border-[var(--hair)] pt-[8px] active:opacity-70"
        aria-label={`${label} untuk slot ini`}
      >
        <span
          className="inline-flex items-center gap-[5px] rounded-full px-[7px] py-[3px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em]"
          style={{ backgroundColor: tone.bg, color: tone.fg }}
        >
          <span
            className="h-[5px] w-[5px] rounded-full"
            style={{
              backgroundColor: tone.fg,
              boxShadow: `0 0 0 2px ${tone.ring}`,
            }}
            aria-hidden="true"
          />
          {pillLabel}
        </span>
        <span
          className="inline-flex items-center gap-[6px] font-mono text-[10px] font-bold uppercase tracking-[0.14em]"
          style={{ color: tone.fg }}
        >
          {label}
          <span
            className="grid h-[22px] w-[22px] place-items-center rounded-full"
            style={{ backgroundColor: tone.bg }}
            aria-hidden="true"
          >
            <ArrowGlyph className="h-[10px] w-[10px]" />
          </span>
        </span>
      </button>

      {open ? (
        <RecapModal
          action={action}
          defaultWentLive={defaultWentLive ?? (needsUpload ? true : null)}
          onClose={() => setOpen(false)}
        />
      ) : null}
    </>
  );
}

const MISSED_REASONS = [
  { code: 'tech_issue', label: 'Tech / connection issue' },
  { code: 'sick', label: 'Sick' },
  { code: 'account_issue', label: 'Platform account issue' },
  { code: 'schedule_conflict', label: 'Schedule conflict' },
  { code: 'other', label: 'Other' },
];

function RecapModal({ action, defaultWentLive, onClose }) {
  const session = action?.session ?? null;
  const sessionId = action?.sessionId;
  const initialAttachments = Array.isArray(action?.attachments) ? action.attachments : [];

  const [attachments, setAttachments] = useState(initialAttachments);
  const fileInputRef = useRef(null);
  const [uploadError, setUploadError] = useState('');

  // Lock body scroll while open + close on Escape.
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKey);
    };
  }, [onClose]);

  const recap = useForm({
    went_live: deriveDefaultWentLive(session, defaultWentLive),
    actual_start_at: toLocalDatetime(session?.actualStartAt ?? session?.scheduledStartAt),
    actual_end_at: toLocalDatetime(session?.actualEndAt),
    remarks: session?.remarks ?? '',
    missed_reason_code: session?.missedReasonCode ?? '',
    missed_reason_note: session?.missedReasonNote ?? '',
  });

  const hasVisualProof = attachments.some(
    (a) => a.fileType?.startsWith('image/') || a.fileType?.startsWith('video/')
  );

  const handlePickFile = () => fileInputRef.current?.click();

  const handleFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploadError('');
    const data = new FormData();
    data.append('file', file);
    router.post(`/live-host/sessions/${sessionId}/attachments`, data, {
      preserveScroll: true,
      preserveState: true,
      forceFormData: true,
      onSuccess: () => {
        // Refresh schedule props (which carry the updated attachments) and
        // sync local state from the latest snapshot.
        router.reload({
          only: ['days'],
          preserveScroll: true,
          preserveState: true,
          onSuccess: (page) => {
            const fresh = findRecapAction(page.props.days, sessionId);
            if (fresh?.attachments) setAttachments(fresh.attachments);
          },
        });
      },
      onError: (errs) => {
        setUploadError(errs?.file ?? 'Upload gagal — sila cuba lagi.');
      },
      onFinish: () => {
        if (fileInputRef.current) fileInputRef.current.value = '';
      },
    });
  };

  const handleDeleteAttachment = (attachmentId) => {
    if (!window.confirm('Buang fail ini?')) return;
    router.delete(`/live-host/sessions/${sessionId}/attachments/${attachmentId}`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        router.reload({
          only: ['days'],
          preserveScroll: true,
          preserveState: true,
          onSuccess: (page) => {
            const fresh = findRecapAction(page.props.days, sessionId);
            setAttachments(fresh?.attachments ?? []);
          },
        });
      },
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    recap.post(`/live-host/sessions/${sessionId}/recap`, {
      preserveScroll: true,
      onSuccess: () => {
        onClose();
        router.reload({ only: ['days'], preserveScroll: true });
      },
    });
  };

  const wentLive = recap.data.went_live;
  const errors = recap.errors ?? {};

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-[2px] sm:items-center sm:px-4 sm:py-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="recap-modal-title"
      onClick={onClose}
    >
      <div
        className="flex max-h-[92vh] w-full max-w-[480px] flex-col overflow-hidden rounded-t-[22px] border border-[var(--hair)] bg-[var(--app-bg)] shadow-[0_-12px_40px_rgba(20,16,31,0.18)] sm:rounded-[22px]"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex justify-center pt-[8px] pb-[2px] sm:hidden">
          <span className="h-[4px] w-[36px] rounded-full bg-[var(--hair-2)]" aria-hidden="true" />
        </div>

        <div className="px-5 pt-3 pb-3">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Rekap sesi · LS-{String(sessionId).padStart(5, '0')}
          </div>
          <h2
            id="recap-modal-title"
            className="font-display text-[20px] font-medium leading-[1.1] tracking-[-0.03em] text-[var(--fg)]"
          >
            {session?.title ?? 'Rekap sesi'}
          </h2>
          {session?.scheduledStartAt ? (
            <div className="mt-[4px] font-mono text-[11px] tracking-[0.02em] text-[var(--fg-2)]">
              {formatModalScheduleLine(session)}
            </div>
          ) : null}
        </div>

        <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
          <div className="min-h-0 flex-1 overflow-y-auto px-5 pb-3">
            <RecapPathSwitch
              value={wentLive}
              onChange={(next) => {
                if (next === wentLive) return;
                recap.setData('went_live', next);
                recap.clearErrors();
              }}
            />

            {wentLive === true ? (
              <RecapWentLiveBlock
                attachments={attachments}
                hasVisualProof={hasVisualProof}
                onPick={handlePickFile}
                onDelete={handleDeleteAttachment}
                fileInputRef={fileInputRef}
                onFileChange={handleFileChange}
                uploadError={uploadError}
                actualStartAt={recap.data.actual_start_at}
                onActualStartChange={(v) => recap.setData('actual_start_at', v)}
                actualEndAt={recap.data.actual_end_at}
                onActualEndChange={(v) => recap.setData('actual_end_at', v)}
                remarks={recap.data.remarks}
                onRemarksChange={(v) => recap.setData('remarks', v)}
                errors={errors}
              />
            ) : null}

            {wentLive === false ? (
              <RecapMissedBlock
                reasonCode={recap.data.missed_reason_code}
                onReasonChange={(v) => recap.setData('missed_reason_code', v)}
                note={recap.data.missed_reason_note}
                onNoteChange={(v) => recap.setData('missed_reason_note', v)}
                errors={errors}
              />
            ) : null}

            {wentLive === null ? (
              <div className="mb-2 rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-5 text-center text-[12px] text-[var(--fg-3)]">
                Pilih sama ada anda menyiarkan langsung untuk teruskan.
              </div>
            ) : null}
          </div>

          <div className="sticky bottom-0 flex items-center gap-[8px] border-t border-[var(--hair)] bg-[var(--app-bg)] px-5 py-3 pb-[max(env(safe-area-inset-bottom),12px)]">
            <button
              type="button"
              onClick={onClose}
              disabled={recap.processing}
              className="inline-flex h-[44px] flex-none items-center rounded-[12px] px-4 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)] transition hover:text-[var(--fg)] active:opacity-60 disabled:opacity-50"
            >
              Tutup
            </button>
            {wentLive === true ? (
              <button
                type="submit"
                disabled={recap.processing || !hasVisualProof}
                className="ml-auto inline-flex h-[44px] flex-1 items-center justify-center rounded-[12px] bg-[var(--accent)] px-4 text-[13px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] shadow-[0_8px_22px_-8px_rgba(124,58,237,0.55)] transition active:scale-[0.98] disabled:opacity-50"
              >
                {recap.processing ? 'Menyimpan…' : 'Simpan rekap'}
              </button>
            ) : null}
            {wentLive === false ? (
              <button
                type="submit"
                disabled={recap.processing || !recap.data.missed_reason_code}
                className="ml-auto inline-flex h-[44px] flex-1 items-center justify-center rounded-[12px] bg-[var(--hot)] px-4 text-[13px] font-bold tracking-[-0.005em] text-white transition active:scale-[0.98] disabled:opacity-50"
              >
                {recap.processing ? 'Menyimpan…' : 'Tandakan terlepas'}
              </button>
            ) : null}
          </div>
        </form>
      </div>
    </div>
  );
}

function RecapPathSwitch({ value, onChange }) {
  return (
    <div className="mb-3">
      <div className="mb-2 font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
        Anda menyiarkan langsung?
      </div>
      <div className="grid grid-cols-2 gap-[8px]">
        <button
          type="button"
          onClick={() => onChange(true)}
          aria-pressed={value === true}
          className={`rounded-[12px] border px-[12px] py-[12px] text-left font-display text-[13px] font-medium tracking-[-0.01em] transition ${
            value === true
              ? 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent)]'
              : 'border-[var(--hair)] bg-[var(--app-bg-2)] text-[var(--fg-2)]'
          }`}
        >
          Ya, saya live
        </button>
        <button
          type="button"
          onClick={() => onChange(false)}
          aria-pressed={value === false}
          className={`rounded-[12px] border px-[12px] py-[12px] text-left font-display text-[13px] font-medium tracking-[-0.01em] transition ${
            value === false
              ? 'border-[var(--hot)] bg-[rgba(225,29,72,0.08)] text-[var(--hot)]'
              : 'border-[var(--hair)] bg-[var(--app-bg-2)] text-[var(--fg-2)]'
          }`}
        >
          Tidak, terlepas
        </button>
      </div>
    </div>
  );
}

function RecapWentLiveBlock({
  attachments,
  hasVisualProof,
  onPick,
  onDelete,
  fileInputRef,
  onFileChange,
  uploadError,
  actualStartAt,
  onActualStartChange,
  actualEndAt,
  onActualEndChange,
  remarks,
  onRemarksChange,
  errors,
}) {
  return (
    <>
      <div className="mb-3">
        <div className="mb-2 flex items-baseline justify-between">
          <span className="font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
            Bukti live
          </span>
          <span className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            wajib
          </span>
        </div>

        {!hasVisualProof ? (
          <button
            type="button"
            onClick={onPick}
            className="flex aspect-[5/3] w-full flex-col items-center justify-center gap-[10px] rounded-[16px] border-2 border-dashed border-[var(--accent)] bg-[var(--accent-soft)] px-4 text-center transition active:scale-[0.99]"
          >
            <span className="grid h-[44px] w-[44px] place-items-center rounded-full bg-[var(--accent)] text-[var(--accent-ink)] shadow-sm">
              <UploadIcon className="h-[20px] w-[20px]" />
            </span>
            <div>
              <div className="font-display text-[14px] font-semibold tracking-[-0.01em] text-[var(--accent)]">
                Muat naik ringkasan live
              </div>
              <p className="mt-[4px] text-[11px] leading-snug text-[var(--fg-2)]">
                Tangkap layar viewers, likes, hadiah dari platform.
              </p>
            </div>
            <span className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
              Imej atau video
            </span>
          </button>
        ) : (
          <div className="space-y-[8px]">
            {attachments.map((a) => (
              <RecapAttachmentRow key={a.id} attachment={a} onDelete={() => onDelete(a.id)} />
            ))}
            <button
              type="button"
              onClick={onPick}
              className="flex w-full items-center justify-center gap-[8px] rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-[10px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
            >
              + Tambah fail lagi
            </button>
          </div>
        )}

        <input
          ref={fileInputRef}
          type="file"
          accept="image/*,video/*,application/pdf"
          className="hidden"
          onChange={onFileChange}
        />

        {uploadError ? (
          <div className="mt-[8px] rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-[8px] text-[11.5px] text-[var(--hot)]">
            {uploadError}
          </div>
        ) : null}

        {!hasVisualProof ? (
          <div className="mt-[8px] rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-[8px] text-[11.5px] leading-snug text-[var(--hot)]">
            Tambah satu imej atau video sebagai bukti sebelum simpan.
          </div>
        ) : null}
      </div>

      <div className="mb-3 grid grid-cols-1 gap-[8px]">
        <RecapDateTimeField
          label="Mula sebenar"
          value={actualStartAt}
          onChange={onActualStartChange}
          error={errors.actual_start_at}
        />
        <RecapDateTimeField
          label="Tamat sebenar"
          value={actualEndAt}
          onChange={onActualEndChange}
          error={errors.actual_end_at}
        />
      </div>

      <div className="mb-3">
        <div className="mb-2 font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Catatan{' '}
          <span className="font-normal text-[var(--fg-3)]">(pilihan)</span>
        </div>
        <textarea
          value={remarks ?? ''}
          onChange={(e) => onRemarksChange(e.target.value)}
          placeholder="Bagaimana sesi tadi?"
          rows={3}
          className="w-full resize-none rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px] text-[13px] leading-snug text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--accent)] focus:outline-none"
        />
        {errors.remarks ? (
          <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.remarks}</p>
        ) : null}
      </div>
    </>
  );
}

function RecapMissedBlock({ reasonCode, onReasonChange, note, onNoteChange, errors }) {
  return (
    <>
      <div className="mb-3">
        <div className="mb-2 font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Kenapa anda tidak live?
        </div>
        <div role="radiogroup" className="space-y-[6px]">
          {MISSED_REASONS.map((r) => (
            <label
              key={r.code}
              className={`flex cursor-pointer items-center gap-[10px] rounded-[10px] border bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px] ${
                reasonCode === r.code ? 'border-[var(--hot)]' : 'border-[var(--hair)]'
              }`}
            >
              <input
                type="radio"
                name="recap_missed_reason"
                value={r.code}
                checked={reasonCode === r.code}
                onChange={() => onReasonChange(r.code)}
                className="h-[14px] w-[14px] accent-[var(--hot)]"
              />
              <span className="text-[var(--fg)]">{r.label}</span>
            </label>
          ))}
        </div>
        {errors.missed_reason_code ? (
          <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.missed_reason_code}</p>
        ) : null}
      </div>
      <div className="mb-3">
        <div className="mb-2 font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Nota{' '}
          <span className="font-normal text-[var(--fg-3)]">(pilihan)</span>
        </div>
        <textarea
          value={note ?? ''}
          onChange={(e) => onNoteChange(e.target.value)}
          placeholder="Konteks tambahan untuk admin"
          rows={3}
          maxLength={500}
          className="w-full resize-none rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px] text-[13px] leading-snug text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--hot)] focus:outline-none"
        />
        {errors.missed_reason_note ? (
          <p className="mt-1 text-[11px] text-[var(--hot)]">{errors.missed_reason_note}</p>
        ) : null}
      </div>
    </>
  );
}

function RecapDateTimeField({ label, value, onChange, error }) {
  return (
    <div className="rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px]">
      <div className="mb-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {label}
      </div>
      <input
        type="datetime-local"
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        className="w-full bg-transparent font-mono text-[13px] text-[var(--fg)] focus:outline-none"
      />
      {error ? <p className="mt-1 text-[11px] text-[var(--hot)]">{error}</p> : null}
    </div>
  );
}

function RecapAttachmentRow({ attachment, onDelete }) {
  const isImage = attachment.fileType?.startsWith('image/');
  const isVideo = attachment.fileType?.startsWith('video/');
  const hasPreview = isImage || isVideo;
  return (
    <div className="overflow-hidden rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
      {hasPreview ? (
        <a
          href={attachment.fileUrl}
          target="_blank"
          rel="noreferrer"
          className="block aspect-video w-full bg-[var(--app-bg)]"
        >
          {isImage ? (
            <img
              src={attachment.fileUrl}
              alt={attachment.fileName}
              className="h-full w-full object-cover"
              loading="lazy"
            />
          ) : (
            <video
              src={attachment.fileUrl}
              className="h-full w-full object-cover"
              controls
              preload="metadata"
            />
          )}
        </a>
      ) : null}
      <div className="flex items-center gap-[10px] px-3 py-[8px]">
        <div className="min-w-0 flex-1">
          <div className="truncate text-[12.5px] font-medium text-[var(--fg)]">
            {attachment.fileName}
          </div>
          <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            {formatRecapBytes(attachment.fileSize)}
          </div>
        </div>
        <button
          type="button"
          onClick={onDelete}
          className="grid h-[26px] w-[26px] place-items-center rounded-full border border-[var(--hair)] text-[var(--fg-3)] transition hover:border-[var(--hot)] hover:text-[var(--hot)]"
          aria-label={`Buang ${attachment.fileName}`}
        >
          <span className="text-[14px] leading-none">×</span>
        </button>
      </div>
    </div>
  );
}

function deriveDefaultWentLive(session, override) {
  if (override === true || override === false) return override;
  if (!session) return null;
  if (session.status === 'missed') return false;
  if (session.status === 'ended' || session.status === 'live') return true;
  return null;
}

function findRecapAction(days, sessionId) {
  if (!Array.isArray(days)) return null;
  for (const day of days) {
    for (const slot of day.schedules ?? []) {
      if (slot?.recapAction?.sessionId === sessionId) return slot.recapAction;
    }
  }
  return null;
}

function formatModalScheduleLine(session) {
  const start = session?.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  if (!start) return '';
  const months = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
  const datePart = `${start.getDate()} ${months[start.getMonth()]}`;
  const time = `${pad2(start.getHours())}:${pad2(start.getMinutes())}`;
  return `${datePart} · ${time}`;
}

function toLocalDatetime(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}T${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

function formatRecapBytes(bytes) {
  const b = Number(bytes);
  if (!Number.isFinite(b) || b <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let v = b;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i += 1;
  }
  return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function UploadIcon({ className = '' }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <polyline points="17 8 12 3 7 8" />
      <line x1="12" y1="3" x2="12" y2="15" />
    </svg>
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
function RequestModal({ slot, dayName, targetDate, onClose }) {
  // Target date is the date of the bucket the host is acting on (i.e. the
  // exact week being viewed). Falls back to the slot's stored `date` if
  // bucketDate isn't passed for some reason.
  const resolvedTargetDate = targetDate || slot.date || '';

  const form = useForm({
    live_schedule_assignment_id: slot.id,
    scope: 'one_date',
    target_date: resolvedTargetDate,
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
