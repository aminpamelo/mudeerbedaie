import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  Users,
  Radio,
  Calendar,
  Clock,
  Check,
  Bell,
  Plus,
  UserMinus,
  ChevronRight,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatCard from '@/livehost/components/StatCard';
import LiveSessionRow from '@/livehost/components/LiveSessionRow';
import AgendaRow from '@/livehost/components/AgendaRow';
import ActivityFeedItem from '@/livehost/components/ActivityFeedItem';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';
import { deriveInitials, secondsSince } from '@/livehost/lib/format';

const SESSIONS_TARGET = 14;
const WATCH_HOURS_TARGET = 67;

/**
 * Poll the /livehost/live-now JSON endpoint every 10s. Returns the latest
 * liveNow list and the subset of stats that can change quickly.
 */
function useLiveNow(initialLiveNow, initialStats) {
  const [state, setState] = useState({
    liveNow: initialLiveNow,
    stats: initialStats,
  });

  useEffect(() => {
    let cancelled = false;
    const tick = async () => {
      try {
        const response = await fetch('/livehost/live-now', {
          headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
          return;
        }
        const data = await response.json();
        if (cancelled) {
          return;
        }
        setState((prev) => ({
          liveNow: data.liveNow ?? prev.liveNow,
          stats: { ...prev.stats, ...(data.stats ?? {}) },
        }));
      } catch (_) {
        // Silently ignore polling errors so the dashboard stays mounted.
      }
    };

    const id = setInterval(tick, 10_000);
    return () => {
      cancelled = true;
      clearInterval(id);
    };
  }, []);

  return state;
}

function sumViewers(liveNow) {
  return (liveNow ?? []).reduce((sum, l) => sum + (Number(l.viewers) || 0), 0);
}

function progressPercent(numerator, denominator) {
  if (!denominator) {
    return 0;
  }
  const pct = ((Number(numerator) || 0) / denominator) * 100;
  return Math.max(0, Math.min(100, Math.round(pct)));
}

export default function Dashboard() {
  const {
    auth,
    stats: initialStats,
    liveNow: initialLiveNow,
    upcoming,
    recentActivity,
    topHosts,
    pendingReplacements = 0,
  } = usePage().props;

  const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';
  const { liveNow, stats } = useLiveNow(
    initialLiveNow ?? [],
    initialStats ?? {}
  );

  const totalViewers = sumViewers(liveNow);
  const sessionsPct = progressPercent(stats?.sessionsToday, SESSIONS_TARGET);
  const watchHours = Number(stats?.watchHoursToday ?? 0);
  const watchPct = progressPercent(watchHours, WATCH_HOURS_TARGET);

  const dashboardActions = (
    <Link href="/livehost/session-slots">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New schedule
      </Button>
    </Link>
  );

  return (
    <>
      <Head title="Dashboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Dashboard']} actions={dashboardActions} />

      <div className="space-y-6 p-8">
        <PageHeader
          firstName={firstName}
          liveNowCount={stats?.liveNow ?? 0}
          viewers={totalViewers}
        />

        {pendingReplacements > 0 && (
          <PendingReplacementsBanner count={pendingReplacements} />
        )}

        {/* Bento Row 1: KPIs */}
        <div className="grid grid-cols-12 gap-4">
          <div className="col-span-12 md:col-span-6 xl:col-span-3">
            <StatCard
              variant="hero"
              label="Active hosts"
              value={stats?.activeHosts ?? 0}
              icon={Users}
              iconTint="emerald"
              subtitle={
                <span>
                  of <strong>{stats?.totalHosts ?? 0}</strong> total
                </span>
              }
            />
          </div>
          <div className="col-span-12 md:col-span-6 xl:col-span-3">
            <StatCard
              variant="dark"
              label="Live now"
              value={stats?.liveNow ?? 0}
              icon={Radio}
              subtitle={<span>{totalViewers.toLocaleString()} viewers</span>}
            />
          </div>
          <div className="col-span-12 md:col-span-6 xl:col-span-3">
            <StatCard
              variant="progress"
              label="Sessions today"
              value={stats?.sessionsToday ?? 0}
              valueUnit={`/ ${SESSIONS_TARGET}`}
              icon={Calendar}
              iconTint="amber"
              progressPercent={sessionsPct}
              subtitle={<span>{sessionsPct}% of target</span>}
            />
          </div>
          <div className="col-span-12 md:col-span-6 xl:col-span-3">
            <StatCard
              variant="ring"
              label="Watch hours"
              icon={Clock}
              iconTint="ink"
              ringPercent={watchPct}
              ringValueLabel={`${watchPct}%`}
              ringSideTitle={watchHours.toString()}
              ringSideUnit="h"
              ringSideSubtitle={`weekly target ${WATCH_HOURS_TARGET}h`}
            />
          </div>
        </div>

        {/* Bento Row 2: On Air + Activity */}
        <div className="grid grid-cols-12 gap-4">
          <OnAirCard
            className="col-span-12 xl:col-span-7"
            liveNow={liveNow}
            upcoming={upcoming}
          />
          <ActivityCard
            className="col-span-12 xl:col-span-5"
            activities={recentActivity}
          />
        </div>

        {/* Bento Row 3: Top hosts */}
        <TopHostsCard hosts={topHosts} />
      </div>
    </>
  );
}

Dashboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function PendingReplacementsBanner({ count }) {
  return (
    <Link
      href="/livehost/replacements"
      className="group flex items-center justify-between gap-4 rounded-[16px] border border-[#FDE68A] bg-gradient-to-r from-[#FFFBEB] to-[#FEF3C7] p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:from-[#FEF3C7] hover:to-[#FDE68A]"
    >
      <div className="flex items-center gap-4">
        <div className="grid h-11 w-11 place-items-center rounded-xl bg-[#F59E0B] text-white">
          <UserMinus className="h-5 w-5" strokeWidth={2.25} />
        </div>
        <div>
          <div className="text-[11px] font-medium uppercase tracking-wide text-[#92400E]">
            Tindakan diperlukan
          </div>
          <div className="mt-0.5 text-[15px] font-semibold tracking-[-0.015em] text-[#0A0A0A]">
            Permohonan ganti tertunda
          </div>
        </div>
      </div>
      <div className="flex items-center gap-3">
        <div className="text-3xl font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">
          {count}
        </div>
        <ChevronRight
          className="h-5 w-5 text-[#92400E] transition-transform group-hover:translate-x-0.5"
          strokeWidth={2.25}
        />
      </div>
    </Link>
  );
}

function PageHeader({ firstName, liveNowCount, viewers }) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-8">
      <div>
        <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
          Good afternoon, {firstName}
        </h1>
        <p className="mt-1.5 text-sm text-[#737373]">
          <span className="inline-flex items-center gap-1.5 font-medium text-[#059669]">
            <span className="pulse-dot h-1.5 w-1.5" aria-hidden="true" />
            {liveNowCount} session{liveNowCount === 1 ? '' : 's'} live
          </span>
          <span className="mx-2">·</span>
          {viewers.toLocaleString()} concurrent viewers across all platforms
        </p>
      </div>
      <SegControl items={['Today', 'This week', 'Month', 'All']} activeIndex={1} />
    </div>
  );
}

function SegControl({ items, activeIndex }) {
  // Display-only indicator: signals which window is being summarised.
  // Will become interactive once the backend supports range filtering.
  const activeLabel = items[activeIndex] ?? items[0];
  return (
    <div
      className="inline-flex items-center gap-2 rounded-lg border border-[#EAEAEA] bg-white px-3 py-1.5"
      aria-label="Current range"
      title="Showing stats for this range"
    >
      <span className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
        Range
      </span>
      <span className="text-xs font-semibold text-[#0A0A0A]">{activeLabel}</span>
    </div>
  );
}

function OnAirCard({ className, liveNow, upcoming }) {
  const upcomingList = upcoming ?? [];

  return (
    <div
      className={`${className ?? ''} rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}
    >
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2.5 text-[15px] font-semibold tracking-[-0.015em]">
          On Air now
          <span className="rounded-full bg-[#ECFDF5] px-2 py-0.5 text-[11px] font-semibold text-[#059669]">
            {liveNow.length} live
          </span>
        </div>
        <a
          href="/livehost/sessions"
          className="text-xs font-medium text-[#737373] hover:text-[#0A0A0A]"
        >
          View all sessions →
        </a>
      </div>

      {liveNow.length === 0 ? (
        <div className="py-12 text-center text-sm text-[#737373]">
          No live sessions right now.
        </div>
      ) : (
        <div className="flex flex-col gap-2.5">
          {liveNow.map((session, index) => (
            <LiveSessionRow
              key={session.id}
              hostName={session.hostName}
              initials={session.initials ?? deriveInitials(session.hostName)}
              platformAccount={session.platformAccount}
              platformType={session.platformType}
              sessionId={session.sessionId}
              viewers={session.viewers}
              durationSeconds={secondsSince(session.startedAt) ?? 0}
              thumbColor={((index % 5) + 1)}
            />
          ))}
        </div>
      )}

      <div className="mt-6 mb-2 flex items-center justify-between">
        <div className="text-[15px] font-semibold tracking-[-0.015em]">Next up</div>
        <a
          href="/livehost/schedules"
          className="text-xs font-medium text-[#737373] hover:text-[#0A0A0A]"
        >
          Full schedule →
        </a>
      </div>
      {upcomingList.length === 0 ? (
        <div className="py-6 text-sm text-[#737373]">No upcoming schedules.</div>
      ) : (
        <div className="flex flex-col">
          {upcomingList.map((u) => (
            <AgendaRow
              key={u.id}
              startTime={`${(u.dayName ?? '').slice(0, 3)} ${formatTime(u.startTime)}`.trim()}
              durationLabel={computeDurationLabel(u.startTime, u.endTime)}
              hostName={u.hostName}
              meta={[u.platformAccount, u.platformType].filter(Boolean).join(' · ')}
              status="scheduled"
            />
          ))}
        </div>
      )}
    </div>
  );
}

function formatTime(value) {
  if (!value) {
    return '';
  }
  const str = String(value);
  const match = str.match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return str;
  }
  return `${match[1].padStart(2, '0')}:${match[2]}`;
}

function computeDurationLabel(startTime, endTime) {
  if (!startTime || !endTime) {
    return '';
  }
  const parse = (t) => {
    const m = String(t).match(/^(\d{1,2}):(\d{2})/);
    if (!m) {
      return null;
    }
    return Number(m[1]) * 60 + Number(m[2]);
  };
  const startMins = parse(startTime);
  const endMins = parse(endTime);
  if (startMins === null || endMins === null) {
    return '';
  }
  const diff = endMins - startMins;
  if (diff <= 0) {
    return '';
  }
  if (diff >= 60) {
    const hours = diff / 60;
    const rounded = Number.isInteger(hours) ? hours.toString() : hours.toFixed(1);
    return `${rounded}h`;
  }
  return `${diff}m`;
}

function ActivityCard({ className, activities }) {
  const list = activities ?? [];

  return (
    <div
      className={`${className ?? ''} rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}
    >
      <div className="mb-4 flex items-center justify-between">
        <div className="text-[15px] font-semibold tracking-[-0.015em]">
          Recent activity
        </div>
        <a
          href="/livehost/sessions"
          className="text-xs font-medium text-[#737373] hover:text-[#0A0A0A]"
        >
          See all →
        </a>
      </div>

      {list.length === 0 ? (
        <div className="py-12 text-center text-sm text-[#737373]">
          Activity will appear here as sessions start and end.
        </div>
      ) : (
        <div className="flex flex-col">
          {list.map((activity) => (
            <ActivityFeedItem
              key={activity.id}
              icon={iconForKind(activity.kind)}
              iconTint={tintForKind(activity.kind)}
              timeLabel={relativeTime(activity.at)}
            >
              {bodyForKind(activity)}
            </ActivityFeedItem>
          ))}
        </div>
      )}
    </div>
  );
}

function iconForKind(kind) {
  if (kind === 'live') {
    return Radio;
  }
  if (kind === 'ended') {
    return Check;
  }
  if (kind === 'cancelled') {
    return Bell;
  }
  return Calendar;
}

function tintForKind(kind) {
  if (kind === 'live' || kind === 'ended') {
    return 'emerald';
  }
  if (kind === 'cancelled') {
    return 'rose';
  }
  return 'default';
}

function bodyForKind(activity) {
  const host = activity.hostName ?? 'A host';
  const account = activity.platformAccount ?? 'a platform';
  switch (activity.kind) {
    case 'live':
      return (
        <>
          <strong>{host}</strong> started a session on <strong>{account}</strong>.
        </>
      );
    case 'ended':
      return (
        <>
          <strong>{host}</strong> ended a session on <strong>{account}</strong>.
        </>
      );
    case 'cancelled':
      return (
        <>
          <strong>{host}</strong>'s session on <strong>{account}</strong> was cancelled.
        </>
      );
    default:
      return (
        <>
          Session on <strong>{account}</strong> scheduled for <strong>{host}</strong>.
        </>
      );
  }
}

function relativeTime(iso) {
  if (!iso) {
    return '';
  }
  const then = Date.parse(iso);
  if (Number.isNaN(then)) {
    return '';
  }
  const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
  if (seconds < 60) {
    return `${seconds}s ago`;
  }
  if (seconds < 3600) {
    return `${Math.round(seconds / 60)}m ago`;
  }
  if (seconds < 86400) {
    return `${Math.round(seconds / 3600)}h ago`;
  }
  return `${Math.round(seconds / 86400)}d ago`;
}

function TopHostsCard({ hosts }) {
  if (!hosts || hosts.length === 0) {
    return (
      <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div className="flex items-center justify-between border-b border-[#F0F0F0] p-4">
          <div className="text-[15px] font-semibold tracking-[-0.015em]">
            Top performing hosts
          </div>
        </div>
        <div className="py-12 text-center text-sm text-[#737373]">
          No hosts yet — create your first live host to see rankings here.
        </div>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-center justify-between border-b border-[#F0F0F0] p-4">
        <div className="text-[15px] font-semibold tracking-[-0.015em]">
          Top performing hosts
          <span className="ml-2 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[11px] text-[#737373]">
            This week
          </span>
        </div>
        <a
          href="/livehost/hosts"
          className="text-xs font-medium text-[#737373] hover:text-[#0A0A0A]"
        >
          View all →
        </a>
      </div>
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
            <th className="px-5 py-2.5 text-left">Host</th>
            <th className="px-5 py-2.5 text-center">Accounts</th>
            <th className="px-5 py-2.5 text-right">Sessions</th>
            <th className="px-5 py-2.5 text-left">Status</th>
          </tr>
        </thead>
        <tbody>
          {hosts.map((host) => (
            <tr
              key={host.id}
              className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
            >
              <td className="px-5 py-3.5">
                <div className="flex items-center gap-3">
                  <div className="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-[#10B981] to-[#059669] text-xs font-semibold text-white">
                    {host.initials ?? deriveInitials(host.name)}
                  </div>
                  <div>
                    <div className="text-[13.5px] font-semibold tracking-[-0.01em]">
                      {host.name}
                    </div>
                    <div className="mt-0.5 text-[11.5px] text-[#737373]">
                      ID · {host.id}
                    </div>
                  </div>
                </div>
              </td>
              <td className="px-5 py-3.5 text-center tabular-nums">
                {host.accounts ?? 0}
              </td>
              <td className="px-5 py-3.5 text-right font-semibold tabular-nums">
                {host.sessions ?? 0}
              </td>
              <td className="px-5 py-3.5">
                <StatusChip variant={statusVariant(host.status)} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function statusVariant(status) {
  if (status === 'active') {
    return 'active';
  }
  if (status === 'suspended') {
    return 'suspended';
  }
  return 'inactive';
}
