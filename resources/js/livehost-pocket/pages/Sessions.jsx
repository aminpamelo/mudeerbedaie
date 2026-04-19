import { Head, Link, router, usePage } from '@inertiajs/react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import {
  formatCompactNumber,
  formatDurationHM,
  formatRinggitInt,
  formatSessionScheduleLine,
  formatShortDateTime,
} from '@/livehost-pocket/lib/format';
import { cn, formatClockHM, minutesSince, formatMinutesHM } from '@/livehost-pocket/lib/utils';

/**
 * Sessions list — screen 02 of livehost-mobile-v3-grounded.html.
 *
 * Props from {@link \App\Http\Controllers\LiveHostPocket\SessionsController::index}:
 *   - `sessions` — paginator (data + links + meta)
 *   - `filter`   — 'upcoming' | 'ended' | 'all'
 */
const FILTER_OPTIONS = [
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'ended', label: 'Ended' },
  { key: 'all', label: 'All' },
];

export default function Sessions() {
  const { sessions, filter } = usePage().props;
  const items = sessions?.data ?? [];
  const links = sessions?.links ?? [];
  const meta = sessions?.meta ?? sessions ?? {};

  const setFilter = (next) => {
    if (next === filter) {
      return;
    }
    router.get('/live-host/sessions', { filter: next }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <>
      <Head title="Sessions" />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <div className="px-1 pt-3 pb-4">
          <div className="mb-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            Sessions
          </div>
          <h1 className="font-display text-[22px] font-medium leading-[1.08] tracking-[-0.03em] text-[var(--fg)]">
            Your sessions
          </h1>
        </div>

        <Segmented value={filter} onChange={setFilter} />

        {items.length === 0 ? (
          <EmptyState filter={filter} />
        ) : (
          <div>
            {items.map((session) => (
              <SessionCard key={session.id} session={session} />
            ))}
          </div>
        )}

        {items.length > 0 && links.length > 3 ? (
          <Pagination
            links={links}
            from={meta.from ?? sessions?.from}
            to={meta.to ?? sessions?.to}
            total={meta.total ?? sessions?.total}
          />
        ) : null}
      </div>
    </>
  );
}

Sessions.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function Segmented({ value, onChange }) {
  return (
    <div
      role="tablist"
      aria-label="Sessions filter"
      className="mb-3 flex rounded-[11px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[3px]"
    >
      {FILTER_OPTIONS.map((opt) => {
        const active = value === opt.key;
        return (
          <button
            key={opt.key}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onChange(opt.key)}
            className={cn(
              'flex-1 rounded-[8px] py-[7px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] transition',
              active
                ? 'bg-[var(--fg)] text-[var(--app-bg)]'
                : 'text-[var(--fg-3)] hover:text-[var(--fg-2)]'
            )}
          >
            {opt.label}
          </button>
        );
      })}
    </div>
  );
}

function SessionCard({ session }) {
  const status = session.status;
  const isLive = status === 'live';
  const isEnded = status === 'ended';
  const isCancelled = status === 'cancelled';
  const isScheduled = status === 'scheduled';

  return (
    <div
      className={cn(
        'mb-[10px] rounded-[16px] border p-[14px]',
        isLive
          ? 'border-[var(--accent)]'
          : 'border-[var(--hair)] bg-[var(--app-bg-2)]'
      )}
      style={
        isLive
          ? {
              backgroundImage:
                'linear-gradient(160deg, var(--accent-soft), transparent 65%)',
              backgroundColor: 'var(--app-bg-2)',
            }
          : undefined
      }
    >
      <div className="mb-2 flex items-center justify-between">
        <PlatformLabel
          name={session.platformAccount}
          platformType={session.platformType}
        />
        <StatusChip status={status} />
      </div>

      <div className="mb-1 text-[14.5px] font-bold leading-tight tracking-[-0.01em] text-[var(--fg)]">
        {session.title}
      </div>

      <ScheduleLine session={session} isLive={isLive} />

      {isEnded && session.analytics ? <MetricsStrip analytics={session.analytics} /> : null}

      {(isEnded || isLive) ? (
        <Link
          href={isLive ? '/live-host' : `/live-host/sessions/${session.id}`}
          className="mt-[10px] block border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]"
        >
          {isLive ? 'Manage session \u2192' : 'Open recap & upload \u2192'}
        </Link>
      ) : null}

      {isCancelled ? (
        <div className="mt-[10px] border-t border-[var(--hair)] pt-[10px] text-center font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          Session cancelled
        </div>
      ) : null}

      {isScheduled ? <ScheduledFooter session={session} /> : null}
    </div>
  );
}

function ScheduleLine({ session, isLive }) {
  if (isLive && session.actualStartAt) {
    const started = formatClockHM(session.actualStartAt);
    const elapsed = formatMinutesHM(minutesSince(session.actualStartAt));
    return (
      <div className="mb-[10px] font-mono text-[10.5px] tracking-[0.02em] text-[var(--fg-2)]">
        Started <strong className="font-bold text-[var(--fg)]">{started}</strong>
        {' '}&middot; elapsed{' '}
        <strong className="font-bold text-[var(--fg)]">{elapsed}</strong>
      </div>
    );
  }

  if (session.status === 'ended' && session.actualStartAt) {
    const line = formatSessionScheduleLine({
      start: session.actualStartAt,
      end: session.actualEndAt,
      durationMinutes: session.durationMinutes,
    });
    return (
      <div className="mb-[10px] font-mono text-[10.5px] tracking-[0.02em] text-[var(--fg-2)]">
        {line}
      </div>
    );
  }

  if (session.scheduledStartAt) {
    const label = formatShortDateTime(session.scheduledStartAt);
    const duration = formatDurationHM(session.durationMinutes);
    return (
      <div className="mb-[10px] font-mono text-[10.5px] tracking-[0.02em] text-[var(--fg-2)]">
        {label}
        {duration !== '—' ? (
          <>
            {' '}&middot;{' '}
            <strong className="font-bold text-[var(--fg)]">{duration}</strong>
          </>
        ) : null}
      </div>
    );
  }

  return null;
}

function ScheduledFooter({ session }) {
  if (!session.scheduledStartAt) {
    return null;
  }
  return (
    <div className="mt-[6px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
      Awaiting start
    </div>
  );
}

function PlatformLabel({ name, platformType }) {
  const label = name ?? platformType ?? 'Platform';
  const dotColor =
    platformType === 'tiktok'
      ? 'var(--fg-1)'
      : platformType === 'facebook'
        ? 'var(--cool)'
        : 'var(--hot)';

  return (
    <span className="inline-flex items-center gap-[5px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
      <span
        className="h-1 w-1"
        style={{ backgroundColor: dotColor }}
        aria-hidden="true"
      />
      {label}
    </span>
  );
}

function StatusChip({ status }) {
  const base = 'inline-flex items-center rounded-full px-[7px] py-[3px] font-mono text-[8.5px] font-extrabold uppercase tracking-[0.14em]';
  if (status === 'live') {
    return (
      <span
        className={cn(base, 'border border-[var(--accent)] text-[var(--accent)]')}
        style={{ backgroundColor: 'var(--accent-soft)' }}
      >
        <span
          className="pocket-diode mr-[5px]"
          style={{ width: 5, height: 5 }}
          aria-hidden="true"
        />
        LIVE
      </span>
    );
  }
  if (status === 'scheduled') {
    return (
      <span
        className={cn(base, 'text-[var(--cool)]')}
        style={{ backgroundColor: 'rgba(37,99,235,0.1)' }}
      >
        SCHED
      </span>
    );
  }
  if (status === 'cancelled') {
    return (
      <span
        className={cn(base, 'text-[var(--hot)]')}
        style={{ backgroundColor: 'rgba(225,29,72,0.1)' }}
      >
        CANCELLED
      </span>
    );
  }
  // ended (default)
  return (
    <span
      className={cn(base, 'text-[var(--fg-2)]')}
      style={{ backgroundColor: 'var(--hair)' }}
    >
      ENDED
    </span>
  );
}

function MetricsStrip({ analytics }) {
  return (
    <div className="grid grid-cols-3 border-t border-[var(--hair)] pt-[10px]">
      <Metric label="Peak" value={formatCompactNumber(analytics.viewersPeak)} />
      <Metric label="Likes" value={formatCompactNumber(analytics.totalLikes)} withBorder />
      <Metric
        label="Gifts"
        withBorder
        value={
          <>
            <span className="mr-[2px] font-mono text-[9px] font-medium text-[var(--fg-3)]">
              RM
            </span>
            {formatRinggitInt(analytics.giftsValue)}
          </>
        }
      />
    </div>
  );
}

function Metric({ label, value, withBorder = false }) {
  return (
    <div
      className={cn(
        'pr-[10px]',
        withBorder && 'border-l border-[var(--hair)] pl-[12px]'
      )}
    >
      <div className="font-display text-[15px] font-medium leading-none tracking-[-0.03em] tabular-nums text-[var(--fg)]">
        {value}
      </div>
      <div className="mt-1 font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {label}
      </div>
    </div>
  );
}

function EmptyState({ filter }) {
  const copy =
    filter === 'upcoming'
      ? 'No upcoming sessions yet.'
      : filter === 'ended'
        ? 'Nothing ended to review — go make one great.'
        : 'No sessions yet. They will appear here once scheduled.';

  return (
    <div className="mb-[10px] rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center text-[12px] text-[var(--fg-3)]">
      {copy}
    </div>
  );
}

function Pagination({ links, from, to, total }) {
  const usable = (links ?? []).filter((l) => l.label && !l.label.includes('...'));
  if (usable.length === 0) {
    return null;
  }

  return (
    <div className="mt-3 flex flex-col items-center gap-2 pt-2">
      {from && to && total ? (
        <div className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          {from}&ndash;{to} of {total}
        </div>
      ) : null}
      <div className="flex flex-wrap items-center justify-center gap-1">
        {usable.map((link, idx) => {
          const isPrev = /previous/i.test(link.label);
          const isNext = /next/i.test(link.label);
          const label = isPrev ? '\u2190' : isNext ? '\u2192' : link.label.replace(/&laquo;|&raquo;/g, '').trim();
          if (!link.url) {
            return (
              <span
                key={`${idx}-${link.label}`}
                className="inline-flex min-w-[32px] items-center justify-center rounded-[8px] border border-[var(--hair)] px-2 py-1 font-mono text-[10px] font-bold text-[var(--fg-3)]"
                aria-disabled="true"
              >
                {label}
              </span>
            );
          }
          return (
            <Link
              key={`${idx}-${link.label}`}
              href={link.url}
              preserveScroll
              preserveState
              replace
              className={cn(
                'inline-flex min-w-[32px] items-center justify-center rounded-[8px] border px-2 py-1 font-mono text-[10px] font-bold transition',
                link.active
                  ? 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent)]'
                  : 'border-[var(--hair)] text-[var(--fg-2)] hover:border-[var(--hair-2)] hover:text-[var(--fg)]'
              )}
            >
              {label}
            </Link>
          );
        })}
      </div>
    </div>
  );
}
