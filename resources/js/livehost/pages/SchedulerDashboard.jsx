import { Head, Link, usePage } from '@inertiajs/react';
import { Calendar, Users, Store, AlertCircle, Plus } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

export default function SchedulerDashboard() {
  const { auth, stats, unassignedThisWeek = [], todaySlots = [] } = usePage().props;

  const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';
  const coverage = Number(stats?.coveragePercent ?? 0);
  const unassignedCount = Number(stats?.unassignedCount ?? 0);
  const activeHosts = Number(stats?.activeHosts ?? 0);
  const platformAccounts = Number(stats?.platformAccounts ?? 0);

  const dashboardActions = (
    <Link href="/livehost/session-slots/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New session slot
      </Button>
    </Link>
  );

  return (
    <>
      <Head title="Scheduler Dashboard" />
      <TopBar breadcrumb={['Live Host Desk', 'Scheduler']} actions={dashboardActions} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Hello, {firstName}
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              This week’s coverage and today’s slots.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-12 gap-4">
          <StatCard
            className="col-span-12 md:col-span-6 xl:col-span-3"
            label="This week coverage"
            value={`${coverage}%`}
            icon={Calendar}
            tint="emerald"
          />
          <StatCard
            className="col-span-12 md:col-span-6 xl:col-span-3"
            label="Unassigned slots"
            value={unassignedCount}
            icon={AlertCircle}
            tint="amber"
          />
          <StatCard
            className="col-span-12 md:col-span-6 xl:col-span-3"
            label="Active hosts"
            value={activeHosts}
            icon={Users}
            tint="ink"
          />
          <StatCard
            className="col-span-12 md:col-span-6 xl:col-span-3"
            label="Platform accounts"
            value={platformAccounts}
            icon={Store}
            tint="default"
          />
        </div>

        <div className="grid grid-cols-12 gap-4">
          <UnassignedCard
            className="col-span-12 xl:col-span-7"
            items={unassignedThisWeek}
          />
          <TodayCard
            className="col-span-12 xl:col-span-5"
            items={todaySlots}
          />
        </div>

        <div className="flex flex-wrap gap-2">
          <Link
            href="/livehost/session-slots/create"
            className="inline-flex items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-xs font-medium text-[#0A0A0A] transition-colors hover:bg-[#F5F5F5]"
          >
            <Plus className="h-3.5 w-3.5" strokeWidth={2.5} />
            New session slot
          </Link>
          <Link
            href="/livehost/time-slots"
            className="inline-flex items-center rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-xs font-medium text-[#0A0A0A] transition-colors hover:bg-[#F5F5F5]"
          >
            Manage time slots
          </Link>
          <Link
            href="/livehost/session-slots"
            className="inline-flex items-center rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-xs font-medium text-[#0A0A0A] transition-colors hover:bg-[#F5F5F5]"
          >
            Open calendar
          </Link>
        </div>
      </div>
    </>
  );
}

SchedulerDashboard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function StatCard({ className, label, value, icon: Icon, tint = 'default' }) {
  const tintClasses = {
    emerald: 'bg-[#ECFDF5] text-[#059669]',
    amber: 'bg-[#FFFBEB] text-[#B45309]',
    ink: 'bg-[#F5F5F5] text-[#0A0A0A]',
    default: 'bg-[#F5F5F5] text-[#737373]',
  };
  return (
    <div
      className={`${className ?? ''} rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-[11.5px] font-medium uppercase tracking-wide text-[#737373]">
            {label}
          </div>
          <div className="mt-2 text-2xl font-semibold tracking-[-0.02em] tabular-nums text-[#0A0A0A]">
            {value}
          </div>
        </div>
        <div
          className={`grid h-9 w-9 place-items-center rounded-lg ${tintClasses[tint] ?? tintClasses.default}`}
        >
          <Icon className="h-4 w-4" strokeWidth={2} />
        </div>
      </div>
    </div>
  );
}

function UnassignedCard({ className, items }) {
  return (
    <div
      className={`${className ?? ''} rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}
    >
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2.5 text-[15px] font-semibold tracking-[-0.015em]">
          Unassigned this week
          <span className="rounded-full bg-[#FFFBEB] px-2 py-0.5 text-[11px] font-semibold text-[#B45309]">
            {items.length}
          </span>
        </div>
        <Link
          href="/livehost/session-slots"
          className="text-xs font-medium text-[#737373] hover:text-[#0A0A0A]"
        >
          Open calendar →
        </Link>
      </div>

      {items.length === 0 ? (
        <div className="py-12 text-center text-sm text-[#737373]">
          All slots are assigned.
        </div>
      ) : (
        <div className="flex flex-col">
          {items.map((slot) => (
            <div
              key={slot.id}
              className="flex items-center justify-between border-b border-[#F0F0F0] py-2.5 last:border-b-0"
            >
              <div className="flex items-center gap-3">
                <div className="grid h-8 w-8 place-items-center rounded-md bg-[#F5F5F5] text-[11px] font-semibold text-[#737373]">
                  {initialsFromDate(slot.schedule_date)}
                </div>
                <div>
                  <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                    {formatDate(slot.schedule_date)}
                  </div>
                  <div className="mt-0.5 text-[11.5px] text-[#737373]">
                    Status · {slot.status ?? 'pending'}
                  </div>
                </div>
              </div>
              <Link
                href={`/livehost/session-slots/${slot.id}/edit`}
                className="text-xs font-medium text-[#059669] hover:underline"
              >
                Assign →
              </Link>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function TodayCard({ className, items }) {
  return (
    <div
      className={`${className ?? ''} rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]`}
    >
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2.5 text-[15px] font-semibold tracking-[-0.015em]">
          Today
          <span className="rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[11px] font-semibold text-[#737373]">
            {items.length}
          </span>
        </div>
      </div>

      {items.length === 0 ? (
        <div className="py-12 text-center text-sm text-[#737373]">
          Nothing on today.
        </div>
      ) : (
        <div className="flex flex-col">
          {items.map((slot) => (
            <div
              key={slot.id}
              className="flex items-center justify-between border-b border-[#F0F0F0] py-2.5 last:border-b-0"
            >
              <div className="min-w-0">
                <div className="truncate text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                  {slot.host_name ?? 'Unassigned'}
                </div>
                <div className="mt-0.5 truncate text-[11.5px] text-[#737373]">
                  {slot.platform_account_label ?? '—'}
                </div>
              </div>
              <span className="ml-3 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-[#737373]">
                {slot.status ?? 'pending'}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function formatDate(iso) {
  if (!iso) {
    return '';
  }
  const parts = String(iso).split('-');
  if (parts.length !== 3) {
    return iso;
  }
  const date = new Date(`${iso}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

function initialsFromDate(iso) {
  if (!iso) {
    return '--';
  }
  const date = new Date(`${iso}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return '--';
  }
  return date.toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 2).toUpperCase();
}
