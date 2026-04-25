import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Radio, CalendarClock, Battery, Wifi, Lightbulb, ListChecks, Package } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { cn, formatClockHM, formatMinutesHM, minutesSince } from '@/livehost-pocket/lib/utils';

/**
 * Go Live — pre-flight briefing.
 *
 * Four mutually-exclusive states from
 * {@link \App\Http\Controllers\LiveHostPocket\GoLiveController}:
 *
 *   - live     : host is currently streaming (elapsed counter, end-stream CTA)
 *   - imminent : start time within [-2h, +30min] (countdown, prep checklist, Mula siaran CTA)
 *   - upcoming : start time further than 30 min ahead (countdown OR date mode, CTA warming up)
 *   - none     : no upcoming sessions (empty state)
 *
 * Copy is Malay throughout; design tokens come from pocket.css
 * (--accent, --hot, --cool, --warm, --hair, --fg-*).
 */
export default function GoLive() {
  const { state, session } = usePage().props;

  // Tick every 1s in live/imminent so the clock feels alive; 60s otherwise
  // to save battery on phones that idle on this page for hours.
  const fast = state === 'live' || state === 'imminent';
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    if (state === 'none') return undefined;
    const id = setInterval(() => setNow(new Date()), fast ? 1000 : 60_000);
    return () => clearInterval(id);
  }, [state, fast]);

  const headTitle =
    state === 'live'
      ? 'Sedang Siaran'
      : state === 'imminent'
        ? 'Bersedia Mula'
        : state === 'upcoming'
          ? 'Jadual Seterusnya'
          : 'Mula Siaran';

  return (
    <>
      <Head title={headTitle} />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        {state === 'live' && <LiveState session={session} now={now} />}
        {state === 'imminent' && <ImminentState session={session} now={now} />}
        {state === 'upcoming' && <UpcomingState session={session} now={now} />}
        {state === 'none' && <NoneState />}
      </div>
    </>
  );
}

GoLive.layout = (page) => <PocketLayout>{page}</PocketLayout>;

/* ------------------------------------------------------------------ */
/* Constants                                                           */
/* ------------------------------------------------------------------ */

const IMMINENT_LEAD_SECONDS = 30 * 60; // matches GoLiveController::IMMINENT_LEAD_MINUTES

const PREP_ITEMS = [
  { key: 'bateri', label: 'Bateri penuh', icon: Battery },
  { key: 'internet', label: 'Internet stabil', icon: Wifi },
  { key: 'cahaya', label: 'Pencahayaan baik', icon: Lightbulb },
  { key: 'nota', label: 'Skrip dan nota sedia', icon: ListChecks },
  { key: 'produk', label: 'Produk live siap', icon: Package },
];

const DAY_NAMES_MS = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];
const MONTH_NAMES_MS = [
  'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun',
  'Jul', 'Ogos', 'Sep', 'Okt', 'Nov', 'Dis',
];

/* ------------------------------------------------------------------ */
/* STATE: live                                                         */
/* ------------------------------------------------------------------ */

function LiveState({ session, now }) {
  const sinceLabel = session?.actualStartAt ? formatClockHM(session.actualStartAt) : '—';
  const elapsedMins = minutesSince(session?.actualStartAt, now);
  const elapsed = formatMinutesHM(elapsedMins);
  const accent = platformColor(session?.platformType);

  const handleEnd = () => {
    if (!window.confirm('Tamatkan sesi siaran sekarang?')) return;
    router.post(`/live-host/sessions/${session.id}/end`, {}, { preserveScroll: true });
  };

  return (
    <div className="flex min-h-[calc(100vh-180px)] flex-col">
      <PretitleBar tone="live">
        <span className="pocket-diode" aria-hidden="true" />
        SEDANG SIARAN · MULA {sinceLabel}
      </PretitleBar>

      <CountdownHero
        label="Berjalan"
        value={elapsed}
        subUnits={['JAM', 'MINIT']}
        progress={Math.min(1, (elapsedMins ?? 0) / 120)}
        accent={accent}
        glow
      />

      <div className="mt-2 text-center">
        <p className="font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
          Anda kini di udara. Tamatkan apabila selesai untuk simpan rekap.
        </p>
      </div>

      <SessionPreview session={session} accent={accent} />

      <div className="flex-1" />

      <StickyActions>
        <Link
          href={`/live-host/sessions/${session.id}`}
          className="flex h-[48px] flex-1 items-center justify-center rounded-[14px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] text-[14px] font-bold tracking-[-0.005em] text-[var(--fg)] transition active:scale-[0.98]"
        >
          Lihat butiran
        </Link>
        <button
          type="button"
          onClick={handleEnd}
          className="flex h-[48px] flex-1 items-center justify-center rounded-[14px] bg-[var(--hot)] text-[14px] font-bold tracking-[-0.005em] text-white shadow-[0_8px_22px_-8px_rgba(225,29,72,0.55)] transition active:scale-[0.98]"
        >
          Tamatkan siaran
        </button>
      </StickyActions>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* STATE: imminent                                                     */
/* ------------------------------------------------------------------ */

function ImminentState({ session, now }) {
  const start = session?.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  const deltaSec = start ? Math.round((start.getTime() - now.getTime()) / 1000) : 0;
  const accent = platformColor(session?.platformType);
  const isLate = deltaSec < -60;
  const isNow = deltaSec >= -60 && deltaSec <= 60;

  // Progress fills from 0 (T-30min) → 1 (T-0). Clamps below 0 / above 1.
  const progress = clamp(1 - deltaSec / IMMINENT_LEAD_SECONDS, 0, 1);

  const label = isLate
    ? 'Lewat'
    : isNow
      ? 'Sekarang'
      : 'Mula dalam';

  const value = isLate
    ? formatCountdownAbs(-deltaSec)
    : isNow
      ? '00:00'
      : formatCountdownAbs(deltaSec);

  const subUnits = isLate || isNow
    ? ['LEWAT', '']
    : deltaSec >= 3600
      ? ['JAM', 'MINIT', 'SAAT']
      : ['MINIT', 'SAAT'];

  const sub = isLate
    ? 'Anda lewat dari masa dijadualkan. Slot ini masih boleh dimulakan dalam 2 jam.'
    : isNow
      ? 'Sudah masanya. Mulakan siaran sekarang.'
      : `Sesi anda bermula pada ${formatClockHM(start)} hari ini.`;

  const handleStart = () => {
    if (!session) return;
    router.post(
      '/live-host/go-live/start',
      { live_schedule_assignment_id: session.scheduleAssignmentId ?? null },
      { preserveScroll: true }
    );
  };

  const cta = (
    <button
      type="button"
      onClick={handleStart}
      className={cn(
        'flex h-[52px] w-full items-center justify-center gap-[8px] rounded-[14px] text-[15px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] shadow-[0_10px_28px_-8px_rgba(124,58,237,0.55)] transition active:scale-[0.98]',
        isLate ? 'bg-[var(--hot)]' : 'bg-[var(--accent)]'
      )}
    >
      <Radio className="h-[16px] w-[16px]" strokeWidth={2.5} />
      <span>Mula siaran</span>
    </button>
  );

  return (
    <div className="flex min-h-[calc(100vh-180px)] flex-col">
      <PretitleBar>
        <Radio className="h-[11px] w-[11px]" strokeWidth={2.5} />
        BERSEDIA MULA · {formatClockHM(now)} MYT
      </PretitleBar>

      <CountdownHero
        label={label}
        value={value}
        subUnits={subUnits}
        progress={progress}
        accent={isLate ? 'var(--hot)' : 'var(--accent)'}
        glow
      />

      <p className="mt-2 max-w-[34ch] self-center text-center font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
        {sub}
      </p>

      <SessionPreview session={session} accent={accent} />

      <PrepChecklist sessionId={session?.id} />

      <div className="flex-1" />

      <StickyActions>
        <Link
          href={`/live-host/sessions/${session.id}`}
          className="flex h-[52px] w-[52px] flex-none items-center justify-center rounded-[14px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] text-[var(--fg)] transition active:scale-[0.98]"
          aria-label="Lihat butiran sesi"
        >
          <ArrowRight className="h-[18px] w-[18px] -rotate-45" strokeWidth={2.5} />
        </Link>
        {cta}
      </StickyActions>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* STATE: upcoming                                                     */
/* ------------------------------------------------------------------ */

function UpcomingState({ session, now }) {
  const start = session?.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  const deltaSec = start ? Math.round((start.getTime() - now.getTime()) / 1000) : 0;
  const accent = platformColor(session?.platformType);

  // Same-day (≤24h): countdown clock. Otherwise: date mode.
  const showCountdown = deltaSec > 0 && deltaSec < 24 * 3600;

  const minutesToImminent = Math.max(0, deltaSec - IMMINENT_LEAD_SECONDS);
  const enableLabel = formatMinutesShort(minutesToImminent);

  return (
    <div className="flex min-h-[calc(100vh-180px)] flex-col">
      <PretitleBar>
        <CalendarClock className="h-[11px] w-[11px]" strokeWidth={2.5} />
        JADUAL SETERUSNYA · {formatClockHM(now)} MYT
      </PretitleBar>

      {showCountdown ? (
        <CountdownHero
          label="Mula dalam"
          value={formatCountdownAbs(deltaSec)}
          subUnits={deltaSec >= 3600 ? ['JAM', 'MINIT', 'SAAT'] : ['MINIT', 'SAAT']}
          progress={0}
          accent="var(--fg-3)"
          dim
        />
      ) : (
        <DateHero start={start} />
      )}

      <p className="mt-2 max-w-[34ch] self-center text-center font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
        {showCountdown
          ? `Bersedia! Butang Mula akan aktif ${enableLabel} sebelum mula.`
          : `Sesi seterusnya pada ${formatFullWhenMs(start)}.`}
      </p>

      <SessionPreview session={session} accent={accent} />

      <PrepChecklist sessionId={session?.id} />

      <div className="flex-1" />

      <StickyActions>
        <Link
          href="/live-host/schedule"
          className="flex h-[52px] flex-1 items-center justify-center rounded-[14px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] text-[14px] font-bold tracking-[-0.005em] text-[var(--fg)] transition active:scale-[0.98]"
        >
          Buka jadual
        </Link>
        <DisabledStartButton enableLabel={enableLabel} />
      </StickyActions>
    </div>
  );
}

function DateHero({ start }) {
  const day = start ? DAY_NAMES_MS[start.getDay()] : '';
  const dateLabel = start ? `${start.getDate()} ${MONTH_NAMES_MS[start.getMonth()]}` : '—';
  const timeLabel = start ? formatClockHM(start) : '—';

  return (
    <div className="mt-4 grid place-items-center">
      <div
        className="grid h-[220px] w-[220px] place-items-center rounded-full"
        style={{
          background:
            'radial-gradient(circle at 35% 30%, rgba(124,58,237,0.10), transparent 65%), var(--app-bg-2)',
          border: '1px solid var(--hair)',
        }}
      >
        <div className="text-center">
          <div className="font-mono text-[10.5px] font-bold uppercase tracking-[0.16em] text-[var(--fg-3)]">
            {day}
          </div>
          <div className="mt-[6px] font-display text-[28px] font-medium leading-none tracking-[-0.03em] text-[var(--fg)] tabular-nums">
            {dateLabel}
          </div>
          <div className="mt-[10px] font-mono text-[22px] font-bold tabular-nums tracking-[-0.02em] text-[var(--accent)]">
            {timeLabel}
          </div>
        </div>
      </div>
    </div>
  );
}

function DisabledStartButton({ enableLabel }) {
  return (
    <div className="flex h-[52px] flex-1 items-center justify-center gap-[6px] rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 text-[var(--fg-3)]">
      <Radio className="h-[14px] w-[14px]" strokeWidth={2.5} />
      <span className="font-mono text-[10px] font-bold uppercase tracking-[0.14em]">
        Aktif {enableLabel} sebelum mula
      </span>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* STATE: none                                                         */
/* ------------------------------------------------------------------ */

function NoneState() {
  return (
    <div className="flex min-h-[calc(100vh-180px)] flex-col">
      <PretitleBar>
        <Radio className="h-[11px] w-[11px]" strokeWidth={2.5} />
        LAPANG
      </PretitleBar>

      <div className="mt-3">
        <h1 className="font-display text-[30px] font-medium leading-[1.05] tracking-[-0.035em] text-[var(--fg)]">
          Anda tiada penugasan.
        </h1>
        <p className="mt-[8px] max-w-[34ch] font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
          Apabila PIC tetapkan slot baharu, ia akan muncul di sini dengan kira detik mula.
        </p>
      </div>

      <div
        className="mt-6 grid place-items-center rounded-[18px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-4 py-[44px]"
        style={{
          backgroundImage:
            'radial-gradient(circle at 50% 35%, rgba(124,58,237,0.06), transparent 65%)',
        }}
      >
        <div
          className="grid h-[64px] w-[64px] place-items-center rounded-full"
          style={{ backgroundColor: 'var(--accent-soft)' }}
        >
          <Radio className="h-[26px] w-[26px] text-[var(--accent)]" strokeWidth={2} />
        </div>
        <div className="mt-3 max-w-[26ch] text-center font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          Menunggu penugasan baharu
        </div>
      </div>

      <div className="flex-1" />

      <StickyActions>
        <Link
          href="/live-host/schedule"
          className="flex h-[52px] w-full items-center justify-center gap-[8px] rounded-[14px] bg-[var(--fg)] px-4 text-[14px] font-bold tracking-[-0.005em] text-[var(--app-bg)] transition active:scale-[0.98]"
        >
          Buka jadual
          <ArrowRight className="h-[14px] w-[14px]" strokeWidth={2.5} />
        </Link>
      </StickyActions>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* CountdownHero — ring + numerals                                     */
/* ------------------------------------------------------------------ */

function CountdownHero({ label, value, subUnits, progress, accent = 'var(--accent)', glow = false, dim = false }) {
  const size = 240;
  const stroke = 6;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c * (1 - clamp(progress, 0, 1));

  const parts = String(value).split(':');

  return (
    <div className="relative mt-4 grid place-items-center">
      {glow ? (
        <div
          className="pointer-events-none absolute h-[280px] w-[280px] rounded-full blur-[40px]"
          style={{
            background: `radial-gradient(circle, ${withAlpha(accent, 0.16)}, transparent 65%)`,
          }}
          aria-hidden="true"
        />
      ) : null}

      <div
        className="relative grid place-items-center rounded-full"
        style={{ width: size, height: size }}
      >
        <svg
          width={size}
          height={size}
          className="absolute inset-0 -rotate-90"
          aria-hidden="true"
        >
          <circle
            cx={size / 2}
            cy={size / 2}
            r={r}
            fill="none"
            stroke="var(--hair)"
            strokeWidth={stroke}
          />
          {progress > 0 ? (
            <circle
              cx={size / 2}
              cy={size / 2}
              r={r}
              fill="none"
              stroke={accent}
              strokeWidth={stroke}
              strokeLinecap="round"
              strokeDasharray={c}
              strokeDashoffset={offset}
              style={{
                transition: 'stroke-dashoffset 900ms cubic-bezier(0.2, 0.8, 0.2, 1)',
              }}
            />
          ) : null}
        </svg>

        <div className="relative z-10 text-center">
          <div className="mb-[10px] font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--fg-3)]">
            {label}
          </div>
          <div className="flex items-baseline justify-center gap-[2px]">
            {parts.map((part, i) => (
              <span
                key={i}
                className="contents"
              >
                {i > 0 ? (
                  <span
                    className="px-[2px] font-mono text-[44px] font-bold leading-none tracking-[-0.03em] text-[var(--fg-4)]"
                    aria-hidden="true"
                  >
                    :
                  </span>
                ) : null}
                <span
                  className="font-mono text-[52px] font-bold leading-none tracking-[-0.04em] tabular-nums"
                  style={{ color: dim ? 'var(--fg-2)' : accent }}
                >
                  {part}
                </span>
              </span>
            ))}
          </div>
          {subUnits.length > 0 ? (
            <div className="mt-[10px] flex items-center justify-center gap-[14px] font-mono text-[8.5px] font-bold uppercase tracking-[0.18em] text-[var(--fg-3)]">
              {subUnits.filter(Boolean).map((u, i) => (
                <span key={i}>{u}</span>
              ))}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* SessionPreview — platform-painted card                              */
/* ------------------------------------------------------------------ */

function SessionPreview({ session, accent }) {
  if (!session) return null;
  const start = session.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  const tint = withAlpha(accent, 0.07);

  return (
    <div
      className="relative mt-5 overflow-hidden rounded-[16px] border border-[var(--hair)] pl-[14px] pr-3 py-[12px]"
      style={{
        background: `linear-gradient(95deg, ${tint}, var(--app-bg-2) 60%)`,
      }}
    >
      <span
        className="absolute left-0 top-0 bottom-0 w-[4px]"
        style={{ backgroundColor: accent }}
        aria-hidden="true"
      />

      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-[6px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
          <span
            className="h-[6px] w-[6px] rounded-full"
            style={{
              backgroundColor: accent,
              boxShadow: `0 0 0 2px ${tint}`,
            }}
            aria-hidden="true"
          />
          {session.platformAccount ?? session.platformType ?? 'Platform'}
        </span>
        <span className="font-mono text-[9.5px] tracking-[0.04em] text-[var(--fg-3)]">
          LS-{String(session.id).padStart(5, '0')}
        </span>
      </div>

      <h3 className="mt-[6px] font-display text-[16px] font-medium leading-[1.2] tracking-[-0.02em] text-[var(--fg)]">
        {session.title ?? 'Sesi tanpa tajuk'}
      </h3>

      <div className="mt-[10px] flex items-baseline gap-[16px] border-t border-[var(--hair)] pt-[8px]">
        <Stat label="Dijadualkan" value={start ? formatClockHM(start) : '—'} />
        {session.durationMinutes ? (
          <Stat label="Tempoh" value={formatMinutesShort(session.durationMinutes * 60)} />
        ) : null}
      </div>
    </div>
  );
}

function Stat({ label, value }) {
  return (
    <div>
      <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {label}
      </div>
      <div className="mt-[2px] font-mono text-[13px] font-bold tabular-nums text-[var(--fg)]">
        {value}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* PrepChecklist — tappable rows persisted to localStorage             */
/* ------------------------------------------------------------------ */

function PrepChecklist({ sessionId }) {
  const storageKey = sessionId ? `pocket.golive.prep.${sessionId}` : null;

  const [checked, setChecked] = useState(() => {
    if (typeof window === 'undefined' || !storageKey) return new Set();
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) return new Set();
      const arr = JSON.parse(raw);
      return new Set(Array.isArray(arr) ? arr : []);
    } catch {
      return new Set();
    }
  });

  // Reset when the session id changes.
  useEffect(() => {
    if (typeof window === 'undefined' || !storageKey) return;
    try {
      const raw = window.localStorage.getItem(storageKey);
      const arr = raw ? JSON.parse(raw) : [];
      setChecked(new Set(Array.isArray(arr) ? arr : []));
    } catch {
      setChecked(new Set());
    }
  }, [storageKey]);

  const toggle = (key) => {
    setChecked((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      if (storageKey && typeof window !== 'undefined') {
        try {
          window.localStorage.setItem(storageKey, JSON.stringify(Array.from(next)));
        } catch {
          /* localStorage may be disabled — silently ignore */
        }
      }
      return next;
    });
  };

  const total = PREP_ITEMS.length;
  const done = checked.size;
  const ratio = total > 0 ? done / total : 0;

  return (
    <section className="mt-5">
      <div className="mb-2 flex items-center gap-[10px] px-1">
        <span className="font-display text-[13px] font-medium tracking-[-0.015em] text-[var(--fg)]">
          Daftar semak
        </span>
        <span className="ml-1 h-px flex-1" style={{ backgroundColor: 'var(--hair)' }} aria-hidden="true" />
        <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] tabular-nums">
          {done}/{total} sedia
        </span>
      </div>

      {/* Progress bar */}
      <div className="mb-3 h-[3px] w-full overflow-hidden rounded-full" style={{ backgroundColor: 'var(--hair)' }}>
        <div
          className="h-full rounded-full"
          style={{
            width: `${ratio * 100}%`,
            backgroundColor: 'var(--accent)',
            transition: 'width 280ms cubic-bezier(0.2, 0.8, 0.2, 1)',
          }}
        />
      </div>

      <div className="grid grid-cols-1 gap-[6px]">
        {PREP_ITEMS.map((item) => {
          const Icon = item.icon;
          const active = checked.has(item.key);
          return (
            <button
              key={item.key}
              type="button"
              onClick={() => toggle(item.key)}
              aria-pressed={active}
              className={cn(
                'flex items-center gap-[10px] rounded-[12px] border px-3 py-[10px] text-left transition active:scale-[0.99]',
                active
                  ? 'border-[var(--accent)] bg-[var(--accent-soft)]'
                  : 'border-[var(--hair)] bg-[var(--app-bg-2)] hover:border-[var(--hair-2)]'
              )}
            >
              <span
                className={cn(
                  'grid h-[28px] w-[28px] flex-none place-items-center rounded-[8px] transition',
                  active ? 'bg-[var(--accent)] text-[var(--accent-ink)]' : 'bg-[var(--hair)] text-[var(--fg-2)]'
                )}
              >
                <Icon className="h-[14px] w-[14px]" strokeWidth={2.2} />
              </span>
              <span
                className={cn(
                  'flex-1 text-[13px] font-medium tracking-[-0.005em]',
                  active ? 'text-[var(--accent-ink-strong,var(--accent))] line-through decoration-[1.5px] decoration-[var(--accent)]' : 'text-[var(--fg)]'
                )}
                style={active ? { color: 'var(--accent)' } : undefined}
              >
                {item.label}
              </span>
              <span
                className={cn(
                  'grid h-[20px] w-[20px] flex-none place-items-center rounded-full border-[1.5px] transition',
                  active
                    ? 'border-[var(--accent)] bg-[var(--accent)] text-[var(--accent-ink)]'
                    : 'border-[var(--hair-2)] bg-[var(--app-bg-2)] text-transparent'
                )}
              >
                <CheckGlyph className="h-[10px] w-[10px]" />
              </span>
            </button>
          );
        })}
      </div>
    </section>
  );
}

function CheckGlyph({ className = '' }) {
  return (
    <svg
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.4"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d="M3 8.5l3 3 7-7.5" />
    </svg>
  );
}

/* ------------------------------------------------------------------ */
/* Pretitle + sticky actions                                           */
/* ------------------------------------------------------------------ */

function PretitleBar({ children, tone = 'default' }) {
  return (
    <div
      className={cn(
        'flex items-center gap-[6px] px-1 pt-3 font-mono text-[10px] font-bold uppercase tracking-[0.14em]',
        tone === 'live' ? 'text-[var(--accent)]' : 'text-[var(--fg-3)]'
      )}
    >
      {children}
    </div>
  );
}

function StickyActions({ children }) {
  return (
    <div className="sticky bottom-0 -mx-4 mt-4 border-t border-[var(--hair)] bg-[var(--app-bg)]/95 px-4 pt-3 pb-[max(env(safe-area-inset-bottom),12px)] backdrop-blur-[6px]">
      <div className="flex items-center gap-[8px]">{children}</div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function platformColor(type) {
  if (type === 'tiktok') return '#E11D48';
  if (type === 'facebook') return '#2563EB';
  if (type === 'shopee') return '#F97316';
  if (type === 'instagram') return '#D946EF';
  return '#7C3AED';
}

function clamp(value, min, max) {
  if (Number.isNaN(value)) return min;
  return Math.min(max, Math.max(min, value));
}

function withAlpha(color, alpha) {
  // Accepts hex (#RRGGBB) or "var(--accent)" — for vars we fall back to
  // the soft accent token name so callers still get a reasonable wash.
  if (typeof color === 'string' && color.startsWith('#')) {
    const hex = color.slice(1);
    const bigint = parseInt(
      hex.length === 3
        ? hex.split('').map((c) => c + c).join('')
        : hex,
      16
    );
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }
  return 'var(--accent-soft)';
}

function formatCountdownAbs(seconds) {
  const s = Math.max(0, Math.abs(Math.round(seconds)));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  if (h > 0) {
    return `${pad(h)}:${pad(m)}:${pad(sec)}`;
  }
  return `${pad(m)}:${pad(sec)}`;
}

function pad(n) {
  return String(n).padStart(2, '0');
}

function formatMinutesShort(seconds) {
  const total = Math.max(0, Math.round(seconds / 60));
  if (total >= 60) {
    const h = Math.floor(total / 60);
    const m = total % 60;
    return m > 0 ? `${h}j ${m}m` : `${h} jam`;
  }
  return `${total} minit`;
}

function formatFullWhenMs(date) {
  if (!date || Number.isNaN(date.getTime())) return '—';
  const now = new Date();
  const sameDay =
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate();
  if (sameDay) {
    return `${formatClockHM(date)} hari ini`;
  }
  const tomorrow = new Date(now);
  tomorrow.setDate(now.getDate() + 1);
  const isTomorrow =
    date.getFullYear() === tomorrow.getFullYear() &&
    date.getMonth() === tomorrow.getMonth() &&
    date.getDate() === tomorrow.getDate();
  if (isTomorrow) {
    return `${formatClockHM(date)} esok`;
  }
  const day = DAY_NAMES_MS[date.getDay()];
  const dm = `${date.getDate()} ${MONTH_NAMES_MS[date.getMonth()]}`;
  return `${day}, ${dm} · ${formatClockHM(date)}`;
}
