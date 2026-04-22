import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowRight, Radio, CalendarClock, Clock } from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import {
  cn,
  formatClockHM,
  formatMinutesHM,
  minutesSince,
} from '@/livehost-pocket/lib/utils';

/**
 * Go Live launch pad — the middle FAB in the Pocket tab bar lands here.
 *
 * One of four mutually-exclusive states, picked server-side by
 * {@link \App\Http\Controllers\LiveHostPocket\GoLiveController}:
 *
 *   - live     : host currently has a session with status='live'
 *   - imminent : next scheduled session is within [-2h, +30min] of now
 *   - upcoming : next scheduled session is further than 30min away
 *   - none     : host has no upcoming scheduled sessions
 *
 * The component is a pure render — the only action available is the
 * existing "End session" POST from the live state. Everything else
 * routes back into the app (Today / Schedule / session detail).
 */
export default function GoLive() {
  const { state, session } = usePage().props;

  // Tick every 30s for the live-state elapsed counter and imminent-state
  // countdown. Fast enough to feel responsive, slow enough to be cheap.
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    if (state !== 'live' && state !== 'imminent') {
      return undefined;
    }
    const id = setInterval(() => setNow(new Date()), 30_000);
    return () => clearInterval(id);
  }, [state]);

  return (
    <>
      <Head title="Go Live" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        {state === 'live' && <LiveState session={session} now={now} />}
        {state === 'imminent' && <ImminentState session={session} now={now} />}
        {state === 'upcoming' && <UpcomingState session={session} />}
        {state === 'none' && <NoneState />}
      </div>
    </>
  );
}

GoLive.layout = (page) => <PocketLayout>{page}</PocketLayout>;

/* ------------------------------------------------------------------ */
/* STATE: live                                                         */
/* ------------------------------------------------------------------ */

function LiveState({ session, now }) {
  const sinceLabel = session?.actualStartAt ? formatClockHM(session.actualStartAt) : '—';
  const elapsed = formatMinutesHM(minutesSince(session?.actualStartAt, now));

  const handleEnd = () => {
    if (!window.confirm('End this live session now?')) {
      return;
    }
    router.post(`/live-host/sessions/${session.id}/end`, {}, {
      preserveScroll: true,
    });
  };

  return (
    <>
      <PretitleBar tone="live">
        <span className="pocket-diode" aria-hidden="true" />
        ON AIR · SINCE {sinceLabel}
      </PretitleBar>

      <div
        className="relative mt-3 overflow-hidden rounded-[22px] border border-[var(--accent)] bg-[var(--app-bg-2)] p-[18px]"
        style={{
          backgroundImage:
            'linear-gradient(160deg, var(--accent-soft), transparent 65%)',
        }}
      >
        <div
          className="pointer-events-none absolute -right-[30%] -top-[45%] h-[230px] w-[230px] rounded-full"
          style={{
            background:
              'radial-gradient(circle, var(--accent-soft), transparent 60%)',
          }}
          aria-hidden="true"
        />

        <div className="relative z-10 mb-2 flex items-center gap-[6px] font-mono text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--fg-2)]">
          <span className="h-1 w-1 bg-[var(--hot)]" aria-hidden="true" />
          {session?.platformAccount ?? session?.platformType ?? 'Platform'}
        </div>
        <h1 className="relative z-10 font-display text-[22px] font-medium leading-[1.1] tracking-[-0.03em] text-[var(--fg)]">
          {session?.title ?? 'Untitled session'}
        </h1>

        <div className="relative z-10 mt-[18px] rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg)] px-[14px] py-[12px]">
          <div className="mb-[3px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Elapsed
          </div>
          <div className="font-mono text-[40px] font-bold leading-none tracking-[-0.04em] tabular-nums text-[var(--accent)]">
            {elapsed}
          </div>
        </div>

        <div className="relative z-10 mt-[14px] flex gap-[8px]">
          <button
            type="button"
            onClick={handleEnd}
            className="flex-1 rounded-[12px] bg-[var(--accent)] px-0 py-[13px] font-sans text-[14px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] transition active:scale-[0.98]"
          >
            End session
          </button>
          <Link
            href={`/live-host/sessions/${session.id}`}
            className="flex flex-1 items-center justify-center rounded-[12px] border border-[var(--hair-2)] bg-transparent px-0 py-[13px] font-sans text-[14px] font-bold text-[var(--fg)] transition active:scale-[0.98]"
          >
            View detail
          </Link>
        </div>
      </div>

      <HintText>
        Tap <strong>End session</strong> when you stop streaming. You can save
        the recap (GMV, notes) straight after.
      </HintText>
    </>
  );
}

/* ------------------------------------------------------------------ */
/* STATE: imminent                                                     */
/* ------------------------------------------------------------------ */

function ImminentState({ session, now }) {
  const start = session?.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  const deltaMinutes = start ? Math.round((start.getTime() - now.getTime()) / 60_000) : 0;
  const { headline, sub } = buildImminentCopy(deltaMinutes);

  return (
    <>
      <PretitleBar>
        <Radio className="h-[11px] w-[11px]" strokeWidth={2.5} />
        GO LIVE · {formatClockHM(now)} MYT
      </PretitleBar>

      <div className="mt-3">
        <div className="font-mono text-[11px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          {headline.label}
        </div>
        <div
          className={cn(
            'mt-[4px] font-display font-medium leading-[0.95] tracking-[-0.045em]',
            headline.late
              ? 'text-[64px] text-[var(--hot)]'
              : 'text-[64px] text-[var(--accent)]'
          )}
        >
          {headline.value}
        </div>
        <p className="mt-[10px] max-w-[30ch] font-display text-[14px] leading-[1.35] text-[var(--fg-2)]">
          {sub}
        </p>
      </div>

      <SessionCard session={session} accent />

      <div className="mt-4 rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[14px] py-[12px]">
        <div className="mb-[4px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          How to go live
        </div>
        <p className="text-[12.5px] leading-[1.45] text-[var(--fg-2)]">
          Open your <strong className="text-[var(--fg)]">
            {session?.platformName ?? session?.platformAccount ?? 'streaming'}
          </strong>{' '}
          app and start the live from there. Once the platform flips to live,
          this session auto-updates. When you&rsquo;re done, come back here to
          end it and save the recap.
        </p>
      </div>

      <div className="mt-4 flex gap-[8px]">
        <Link
          href={`/live-host/sessions/${session.id}`}
          className="flex flex-1 items-center justify-center rounded-[12px] border border-[var(--hair-2)] bg-[var(--app-bg-2)] px-0 py-[12px] font-sans text-[13px] font-bold text-[var(--fg)] transition active:scale-[0.98]"
        >
          Session detail
        </Link>
        <Link
          href="/live-host"
          className="flex flex-1 items-center justify-center gap-[6px] rounded-[12px] bg-[var(--accent)] px-0 py-[12px] font-sans text-[13px] font-bold text-[var(--accent-ink)] transition active:scale-[0.98]"
        >
          Back to Today
          <ArrowRight className="h-[14px] w-[14px]" strokeWidth={2.5} />
        </Link>
      </div>
    </>
  );
}

function buildImminentCopy(deltaMinutes) {
  // Future: "in 14m" / "in 2m" / "NOW"
  // Past:   "5m late" / "42m late"
  if (deltaMinutes >= 2) {
    return {
      headline: { label: 'Going live in', value: `${deltaMinutes}m`, late: false },
      sub: 'Your next session starts soon. Get your platform ready.',
    };
  }
  if (deltaMinutes >= -1) {
    return {
      headline: { label: 'Status', value: 'Now', late: false },
      sub: 'It’s time. Open your platform and hit go live.',
    };
  }
  const late = Math.abs(deltaMinutes);
  return {
    headline: { label: 'Running late', value: `${late}m`, late: true },
    sub: 'You’re past the scheduled start. You can still go live — this session stays open for 2 hours.',
  };
}

/* ------------------------------------------------------------------ */
/* STATE: upcoming                                                     */
/* ------------------------------------------------------------------ */

function UpcomingState({ session }) {
  const startLabel = session?.scheduledStartAt
    ? formatFullWhen(session.scheduledStartAt)
    : 'later';

  return (
    <>
      <PretitleBar>
        <CalendarClock className="h-[11px] w-[11px]" strokeWidth={2.5} />
        UP NEXT
      </PretitleBar>

      <div className="mt-3">
        <h1 className="font-display text-[30px] font-medium leading-[1.05] tracking-[-0.035em] text-[var(--fg)]">
          Nothing imminent.
        </h1>
        <p className="mt-[6px] max-w-[32ch] font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
          Your next stream is <strong className="text-[var(--fg)]">{startLabel}</strong>.
          We&rsquo;ll light up this page 30 minutes before it starts.
        </p>
      </div>

      <SessionCard session={session} />

      <Link
        href="/live-host/schedule"
        className="mt-4 flex items-center justify-between rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-[12px] font-sans text-[13px] font-bold text-[var(--fg)] transition active:scale-[0.98]"
      >
        <span>See full schedule</span>
        <ArrowRight className="h-[14px] w-[14px]" strokeWidth={2.5} />
      </Link>
    </>
  );
}

/* ------------------------------------------------------------------ */
/* STATE: none                                                         */
/* ------------------------------------------------------------------ */

function NoneState() {
  return (
    <>
      <PretitleBar>
        <Clock className="h-[11px] w-[11px]" strokeWidth={2.5} />
        ALL CLEAR
      </PretitleBar>

      <div className="mt-3">
        <h1 className="font-display text-[30px] font-medium leading-[1.05] tracking-[-0.035em] text-[var(--fg)]">
          You&rsquo;re all clear.
        </h1>
        <p className="mt-[6px] max-w-[32ch] font-display text-[14px] leading-[1.4] text-[var(--fg-2)]">
          No scheduled sessions on your dock. When PIC adds one, it&rsquo;ll
          show up here with a countdown.
        </p>
      </div>

      <div
        className="mt-6 grid place-items-center rounded-[18px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-4 py-[44px]"
      >
        <div className="grid h-[56px] w-[56px] place-items-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]">
          <Radio className="h-[22px] w-[22px]" strokeWidth={2} />
        </div>
        <div className="mt-3 max-w-[24ch] text-center font-mono text-[10.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          Waiting for your next assignment
        </div>
      </div>

      <Link
        href="/live-host/schedule"
        className="mt-4 flex items-center justify-between rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[14px] py-[12px] font-sans text-[13px] font-bold text-[var(--fg)] transition active:scale-[0.98]"
      >
        <span>Open schedule</span>
        <ArrowRight className="h-[14px] w-[14px]" strokeWidth={2.5} />
      </Link>
    </>
  );
}

/* ------------------------------------------------------------------ */
/* Small shared pieces                                                 */
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

function SessionCard({ session, accent = false }) {
  if (!session) {
    return null;
  }
  const start = session.scheduledStartAt ? new Date(session.scheduledStartAt) : null;
  const timeLabel = start ? formatClockHM(start) : '—';
  const dayLabel = start ? formatDayShort(start) : '';

  return (
    <div
      className={cn(
        'mt-4 rounded-[16px] border bg-[var(--app-bg-2)] p-[14px]',
        accent ? 'border-[var(--accent)]' : 'border-[var(--hair)]'
      )}
    >
      <div className="mb-[6px] flex items-center justify-between">
        <div className="inline-flex items-center gap-[5px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
          <span className="h-1 w-1 bg-[var(--fg-1)]" aria-hidden="true" />
          {session.platformAccount ?? session.platformType ?? 'Platform'}
        </div>
        <div className="text-right font-mono text-[10px] text-[var(--fg-3)]">
          LS-{String(session.id).padStart(5, '0')}
        </div>
      </div>
      <h3 className="font-display text-[17px] font-medium leading-[1.15] tracking-[-0.025em] text-[var(--fg)]">
        {session.title ?? 'Untitled session'}
      </h3>
      <div className="mt-[10px] flex items-baseline gap-[12px] border-t border-[var(--hair)] pt-[10px]">
        <div>
          <div className="mb-[2px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Scheduled
          </div>
          <div className="font-mono text-[13px] font-semibold text-[var(--fg)]">
            {timeLabel}
            {dayLabel ? <span className="ml-[5px] text-[var(--fg-3)]">{dayLabel}</span> : null}
          </div>
        </div>
        {session.durationMinutes ? (
          <div>
            <div className="mb-[2px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
              Duration
            </div>
            <div className="font-mono text-[13px] font-semibold text-[var(--fg)]">
              {session.durationMinutes}m
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function HintText({ children }) {
  return (
    <p className="mt-3 px-1 text-[11.5px] leading-[1.5] text-[var(--fg-2)]">
      {children}
    </p>
  );
}

/**
 * "Today 18:30" / "Tomorrow 06:30" / "Wed 23 Apr" — used on the upcoming
 * session card so the host has a date anchor even when the stream is days
 * out.
 */
function formatFullWhen(iso) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  const time = formatClockHM(date);
  const now = new Date();
  const sameDay =
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate();
  if (sameDay) {
    return `today at ${time}`;
  }
  const tomorrow = new Date(now);
  tomorrow.setDate(now.getDate() + 1);
  const isTomorrow =
    date.getFullYear() === tomorrow.getFullYear() &&
    date.getMonth() === tomorrow.getMonth() &&
    date.getDate() === tomorrow.getDate();
  if (isTomorrow) {
    return `tomorrow at ${time}`;
  }
  const weekday = date.toLocaleDateString('en-GB', { weekday: 'short' });
  const dm = date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
  return `${weekday} ${dm} · ${time}`;
}

function formatDayShort(date) {
  const now = new Date();
  const sameDay =
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate();
  if (sameDay) {
    return 'today';
  }
  return date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
}
