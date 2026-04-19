import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import {
  firstNameFrom,
  formatClockHM,
  formatDurationShort,
  formatHoursDecimal,
  formatMinutesHM,
  initialsFrom,
  minutesSince,
  shortGreetingFor,
} from '@/livehost-pocket/lib/utils';

/**
 * Today screen (Batch 2) — host-scoped overview with live-now card, daily
 * tiles, and up-next list. Data comes from
 * {@link \App\Http\Controllers\LiveHostPocket\DashboardController::index}
 * via Inertia props. The visual language mirrors screen 01 of
 * `docs/design-mockups/livehost-mobile-v3-grounded.html`.
 */
export default function Today() {
  const { auth, stats, liveNow, upcoming, features } = usePage().props;
  const user = auth?.user ?? null;
  const firstName = firstNameFrom(user?.name);
  const initials = initialsFrom(user?.name);
  const avatarUrl = user?.avatarUrl ?? null;
  const hasLive = Array.isArray(liveNow) && liveNow.length > 0;
  const allowanceEnabled = Boolean(features?.allowance_enabled);

  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000);
    return () => clearInterval(id);
  }, []);

  const greeting = shortGreetingFor(now);
  const clock = formatClockHM(now);

  return (
    <>
      <Head title="Today" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <Greeting
          firstName={firstName}
          initials={initials}
          avatarUrl={avatarUrl}
          name={user?.name}
          greeting={greeting}
          clock={clock}
          hasLive={hasLive}
          liveCount={liveNow?.length ?? 0}
        />

        {hasLive &&
          liveNow.map((session) => (
            <LiveNowCard key={session.id} session={session} now={now} />
          ))}

        <SectionHeading>Today</SectionHeading>
        <TilesRow
          stats={stats}
          allowanceEnabled={allowanceEnabled}
        />

        <SectionHeading
          link={{ href: '/live-host/schedule', label: 'Schedule \u2192' }}
        >
          Up next
        </SectionHeading>
        <UpNext upcoming={upcoming ?? []} />
      </div>
    </>
  );
}

Today.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function Greeting({ firstName, initials, avatarUrl, name, greeting, clock, hasLive, liveCount }) {
  const pretitleLabel = hasLive ? 'ON AIR' : 'TODAY';
  const subtitle = hasLive
    ? liveCount > 1
      ? `${liveCount} sessions live.`
      : 'One session live.'
    : 'No live sessions right now.';

  return (
    <div className="relative px-1 pt-3 pb-4">
      <div className="mb-1.5 flex items-center gap-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        <span className={hasLive ? 'pocket-diode' : 'hidden'} aria-hidden="true" />
        <span>
          {pretitleLabel} · {clock} MYT
        </span>
      </div>
      <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
        {greeting}, <em className="not-italic text-[var(--accent)]">{firstName}</em>.
        <br />
        {subtitle}
      </h1>
      <div
        className="absolute right-2 top-2 h-[38px] w-[38px] overflow-hidden rounded-full bg-gradient-to-br from-[var(--accent)] to-[var(--hot)]"
        aria-hidden="true"
      >
        {avatarUrl ? (
          <img src={avatarUrl} alt={name ?? ''} className="h-full w-full object-cover" />
        ) : (
          <span className="grid h-full w-full place-items-center font-display text-[12px] font-bold tracking-[-0.04em] text-white">
            {initials}
          </span>
        )}
      </div>
    </div>
  );
}

function LiveNowCard({ session, now }) {
  const sinceLabel = session.actualStartAt ? formatClockHM(session.actualStartAt) : '—';
  const scheduledLabel = formatSchedRange(session.scheduledStartAt, session.scheduledEndAt);
  const elapsed = formatMinutesHM(minutesSince(session.actualStartAt, now));

  const handleEnd = () => {
    if (!window.confirm('End this live session now?')) {
      return;
    }
    router.post(`/live-host/sessions/${session.id}/end`, {}, {
      preserveScroll: true,
    });
  };

  return (
    <div className="relative mb-3 overflow-hidden rounded-[20px] border border-[var(--accent)] bg-[var(--app-bg-2)] p-4 shadow-[var(--shadow-pocket-card)]"
      style={{
        backgroundImage:
          'linear-gradient(160deg, var(--accent-soft), transparent 60%)',
      }}
    >
      <div
        className="pointer-events-none absolute -right-[30%] -top-[50%] h-[220px] w-[220px] rounded-full"
        style={{
          background:
            'radial-gradient(circle, var(--accent-soft), transparent 60%)',
        }}
        aria-hidden="true"
      />
      <div className="relative z-10 flex items-center justify-between">
        <span className="inline-flex items-center gap-[5px] rounded-full border border-[var(--accent)] bg-[var(--accent-soft)] px-[9px] py-[3px] font-mono text-[9.5px] font-extrabold uppercase tracking-[0.2em] text-[var(--accent)]">
          <span className="pocket-diode h-[5px] w-[5px]" aria-hidden="true" />
          LIVE NOW
        </span>
        <span className="font-mono text-[12px] font-bold text-[var(--fg)]">
          <span className="mr-[5px] text-[9px] uppercase tracking-[0.12em] text-[var(--fg-3)]">
            SINCE
          </span>
          {sinceLabel}
        </span>
      </div>

      <div className="relative z-10 mt-4 flex items-center gap-[6px] font-mono text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--fg-2)]">
        <span className="h-1 w-1 bg-[var(--hot)]" aria-hidden="true" />
        {session.platformAccount ?? session.platformType ?? 'Platform'}
      </div>
      <h2 className="relative z-10 mt-1 font-display text-[20px] font-medium leading-[1.12] tracking-[-0.03em] text-[var(--fg)]">
        {session.title}
      </h2>

      <div className="relative z-10 mt-[14px] flex gap-[18px] border-t border-[var(--hair)] pt-3">
        <div>
          <div className="mb-1 font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Scheduled
          </div>
          <div className="font-mono text-[13px] font-semibold text-[var(--fg)]">
            {scheduledLabel}
          </div>
        </div>
        <div>
          <div className="mb-1 font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Elapsed
          </div>
          <div className="font-mono text-[13px] font-semibold text-[var(--fg)]">
            {elapsed}
          </div>
        </div>
      </div>

      <div className="relative z-10 mt-[14px] flex gap-2">
        <button
          type="button"
          onClick={handleEnd}
          className="flex-1 rounded-[11px] bg-[var(--accent)] px-0 py-[11px] font-sans text-[13px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] transition active:scale-[0.98]"
        >
          End session
        </button>
        <Link
          href={`/live-host/sessions/${session.id}`}
          className="flex flex-1 items-center justify-center rounded-[11px] border border-[var(--hair-2)] bg-transparent px-0 py-[11px] font-sans text-[13px] font-bold text-[var(--fg)] transition active:scale-[0.98]"
        >
          View detail
        </Link>
      </div>
    </div>
  );
}

function SectionHeading({ children, link }) {
  return (
    <div className="mt-2 mb-2 flex items-baseline justify-between px-1">
      <h3 className="font-display text-[13px] font-medium tracking-[-0.015em] text-[var(--fg)]">
        {children}
      </h3>
      {link ? (
        <Link
          href={link.href}
          className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] transition hover:text-[var(--fg)]"
        >
          {link.label}
        </Link>
      ) : null}
    </div>
  );
}

function TilesRow({ stats, allowanceEnabled }) {
  const done = stats?.sessionsDoneToday ?? 0;
  const total = stats?.sessionsToday ?? 0;
  const remaining = Math.max(0, total - done);
  const watchMinutes = stats?.watchMinutesToday ?? 0;

  return (
    <div className="mb-3 grid grid-cols-2 gap-2">
      <Tile
        accent
        label="Sessions done"
        value={
          <>
            {done}
            <span className="ml-[3px] font-mono text-[11px] font-medium text-[var(--fg-3)]">
              / {total}
            </span>
          </>
        }
        sub={
          total === 0
            ? 'Nothing scheduled today'
            : remaining === 0
              ? 'All done for today'
              : `${remaining} more to go today`
        }
      />

      {allowanceEnabled ? (
        <Tile
          label="Allowance today"
          value={
            <>
              <span className="mr-[3px] font-mono text-[11px] font-medium text-[var(--fg-3)]">
                RM
              </span>
              0
              <span className="ml-[3px] font-mono text-[11px] font-medium text-[var(--fg-3)]">
                .00
              </span>
            </>
          }
          sub={`From ${done} ended ${done === 1 ? 'session' : 'sessions'}`}
        />
      ) : (
        <Tile
          label="Watch time today"
          value={formatHoursDecimal(watchMinutes)}
          sub={`From ${done} ${done === 1 ? 'session' : 'sessions'}`}
        />
      )}
    </div>
  );
}

function Tile({ label, value, sub, accent = false }) {
  return (
    <div
      className={`flex min-h-[84px] flex-col justify-between rounded-[16px] border px-[14px] py-3 ${
        accent
          ? 'border-[var(--accent)]'
          : 'border-[var(--hair)] bg-[var(--app-bg-2)]'
      }`}
      style={
        accent
          ? {
              backgroundImage:
                'linear-gradient(165deg, var(--accent-soft), transparent 60%)',
            }
          : undefined
      }
    >
      <div>
        <div className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          {label}
        </div>
        <div
          className={`mt-[6px] font-display text-[24px] font-medium leading-none tracking-[-0.04em] tabular-nums ${
            accent ? 'text-[var(--accent)]' : 'text-[var(--fg)]'
          }`}
        >
          {value}
        </div>
      </div>
      <div className="mt-[6px] text-[10.5px] text-[var(--fg-2)]">{sub}</div>
    </div>
  );
}

function UpNext({ upcoming }) {
  if (!upcoming || upcoming.length === 0) {
    return (
      <div className="rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-4 text-center text-[12px] text-[var(--fg-3)]">
        No upcoming sessions scheduled.
      </div>
    );
  }

  return (
    <div>
      {upcoming.map((session) => (
        <UpcomingRow key={session.id} session={session} />
      ))}
    </div>
  );
}

function UpcomingRow({ session }) {
  const time = session.scheduledStartAt ? formatClockHM(session.scheduledStartAt) : '—';
  const duration = formatDurationShort(session.durationMinutes);
  const meta = [session.platformAccount, 'scheduled']
    .filter(Boolean)
    .join(' · ')
    .toLowerCase();

  return (
    <div
      className="mb-[6px] grid grid-cols-[48px_1fr_auto] items-center gap-[10px] rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-[10px]"
    >
      <div className="text-center">
        <div className="font-mono text-[13px] font-bold tracking-[-0.01em] text-[var(--fg)]">
          {time}
        </div>
        <div className="mt-[2px] font-mono text-[8.5px] font-bold uppercase tracking-[0.1em] text-[var(--fg-3)]">
          {duration}
        </div>
      </div>
      <div>
        <div className="text-[12.5px] font-bold tracking-[-0.005em] text-[var(--fg)]">
          {session.title}
        </div>
        <div className="mt-[2px] font-mono text-[9.5px] tracking-[0.02em] text-[var(--fg-3)]">
          {meta}
        </div>
      </div>
      <span className="rounded-full bg-[rgba(37,99,235,0.1)] px-[7px] py-[3px] font-mono text-[8.5px] font-extrabold uppercase tracking-[0.14em] text-[var(--cool)]">
        SCHED
      </span>
    </div>
  );
}

function formatSchedRange(startIso, endIso) {
  if (!startIso) {
    return '—';
  }
  const start = formatClockHM(startIso);
  if (!endIso) {
    return start;
  }
  const end = formatClockHM(endIso);
  return `${start} \u2013 ${end}`;
}
