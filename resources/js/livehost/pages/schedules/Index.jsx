import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CalendarDays, Eye, List, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import WeeklyCalendar from '@/livehost/components/WeeklyCalendar';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAY_COLORS = [
  'bg-[#FFF7ED] text-[#B45309]',
  'bg-[#F5F5F5] text-[#404040]',
  'bg-[#FDF2F8] text-[#9D174D]',
  'bg-[#ECFDF5] text-[#065F46]',
  'bg-[#FFFBEB] text-[#92400E]',
  'bg-[#EFF6FF] text-[#1D4ED8]',
  'bg-[#F5F3FF] text-[#5B21B6]',
];

function formatTimeRange(startTime, endTime) {
  if (!startTime || !endTime) {
    return '—';
  }
  return `${startTime} – ${endTime}`;
}

export default function SchedulesIndex() {
  const { schedules, filters, hosts, platformAccounts, viewMode } = usePage().props;
  const currentView = viewMode === 'calendar' ? 'calendar' : 'list';
  const scheduleArray = Array.isArray(schedules) ? schedules : schedules.data;
  const scheduleTotal = Array.isArray(schedules) ? schedules.length : schedules.total;

  const [search, setSearch] = useState(filters?.search ?? '');
  const [host, setHost] = useState(filters?.host ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [dayOfWeek, setDayOfWeek] = useState(filters?.day_of_week ?? '');
  const [active, setActive] = useState(filters?.active ?? '');

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.search ?? '') === search &&
      (initial.host ?? '') === host &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.day_of_week ?? '') === dayOfWeek &&
      (initial.active ?? '') === active
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/schedules',
        {
          search: search || undefined,
          host: host || undefined,
          platform_account: platformAccount || undefined,
          day_of_week: dayOfWeek !== '' ? dayOfWeek : undefined,
          active: active || undefined,
          view: currentView === 'calendar' ? 'calendar' : undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [search, host, platformAccount, dayOfWeek, active, filters, currentView]);

  const clearFilters = () => {
    setSearch('');
    setHost('');
    setPlatformAccount('');
    setDayOfWeek('');
    setActive('');
  };

  const switchView = (nextView) => {
    if (nextView === currentView) {
      return;
    }
    router.get(
      '/livehost/schedules',
      {
        search: search || undefined,
        host: host || undefined,
        platform_account: platformAccount || undefined,
        day_of_week: dayOfWeek !== '' ? dayOfWeek : undefined,
        active: active || undefined,
        view: nextView === 'calendar' ? 'calendar' : undefined,
      },
      {
        preserveState: false,
        preserveScroll: true,
      }
    );
  };

  const newScheduleAction = (
    <Link href="/livehost/schedules/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New schedule
      </Button>
    </Link>
  );

  const hasFilters = Boolean(search || host || platformAccount || dayOfWeek !== '' || active);

  return (
    <>
      <Head title="Schedules" />
      <TopBar breadcrumb={['Live Host Desk', 'Schedules']} actions={newScheduleAction} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Schedules
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {scheduleTotal} weekly recurring slot{scheduleTotal === 1 ? '' : 's'}
            </p>
          </div>

          {/* View toggle */}
          <div
            role="tablist"
            aria-label="View mode"
            className="inline-flex items-center rounded-lg border border-[#EAEAEA] bg-white p-0.5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
          >
            <button
              type="button"
              role="tab"
              aria-selected={currentView === 'list'}
              onClick={() => switchView('list')}
              className={[
                'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[12.5px] font-medium transition-colors',
                currentView === 'list'
                  ? 'bg-[#0A0A0A] text-white'
                  : 'text-[#525252] hover:bg-[#F5F5F5]',
              ].join(' ')}
            >
              <List className="h-3.5 w-3.5" strokeWidth={2} />
              List
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={currentView === 'calendar'}
              onClick={() => switchView('calendar')}
              className={[
                'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[12.5px] font-medium transition-colors',
                currentView === 'calendar'
                  ? 'bg-[#0A0A0A] text-white'
                  : 'text-[#525252] hover:bg-[#F5F5F5]',
              ].join(' ')}
            >
              <CalendarDays className="h-3.5 w-3.5" strokeWidth={2} />
              Calendar
            </button>
          </div>
        </div>

        {/* Filter bar */}
        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative min-w-[220px] flex-1">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search by remarks…"
              className="border-[#EAEAEA] bg-[#FAFAFA] pl-9"
            />
          </div>
          <select
            value={host}
            onChange={(event) => setHost(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All hosts</option>
            {hosts.map((h) => (
              <option key={h.id} value={h.id}>
                {h.name}
              </option>
            ))}
          </select>
          <select
            value={platformAccount}
            onChange={(event) => setPlatformAccount(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All platform accounts</option>
            {platformAccounts.map((pa) => (
              <option key={pa.id} value={pa.id}>
                {pa.name} {pa.platform ? `· ${pa.platform}` : ''}
              </option>
            ))}
          </select>
          <select
            value={dayOfWeek}
            onChange={(event) => setDayOfWeek(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All days</option>
            {DAY_NAMES.map((d, idx) => (
              <option key={d} value={idx}>
                {d}
              </option>
            ))}
          </select>
          <select
            value={active}
            onChange={(event) => setActive(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">Any status</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
          {hasFilters && (
            <button
              type="button"
              onClick={clearFilters}
              className="text-sm font-medium text-[#059669] hover:text-[#047857]"
            >
              Clear
            </button>
          )}
        </div>

        {currentView === 'calendar' ? (
          scheduleArray.length === 0 ? (
            <div className="rounded-[16px] border border-[#EAEAEA] bg-white py-16 text-center shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
              <div className="text-sm text-[#737373]">
                {hasFilters ? 'No schedules found.' : 'No schedules yet — add your first weekly slot.'}
              </div>
              {hasFilters && (
                <button
                  type="button"
                  onClick={clearFilters}
                  className="mt-2 text-sm font-medium text-[#059669] hover:text-[#047857]"
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <WeeklyCalendar schedules={scheduleArray} />
          )
        ) : (
          <>
            {/* Table */}
            <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
              {scheduleArray.length === 0 ? (
                <div className="py-16 text-center">
                  <div className="text-sm text-[#737373]">
                    {hasFilters
                      ? 'No schedules found.'
                      : 'No schedules yet — add your first weekly slot.'}
                  </div>
                  {hasFilters && (
                    <button
                      type="button"
                      onClick={clearFilters}
                      className="mt-2 text-sm font-medium text-[#059669] hover:text-[#047857]"
                    >
                      Clear filters
                    </button>
                  )}
                </div>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                      <th className="px-5 py-3 text-left">Day</th>
                      <th className="px-5 py-3 text-left">Time</th>
                      <th className="px-5 py-3 text-left">Host</th>
                      <th className="px-5 py-3 text-left">Platform</th>
                      <th className="px-5 py-3 text-left">Status</th>
                      <th className="px-5 py-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {scheduleArray.map((schedule) => (
                      <tr
                        key={schedule.id}
                        className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                      >
                        <td className="px-5 py-3.5">
                          <span
                            className={`inline-flex min-w-[40px] justify-center rounded-md px-2 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                              DAY_COLORS[schedule.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]'
                            }`}
                          >
                            {DAY_NAMES[schedule.dayOfWeek] ?? '—'}
                          </span>
                        </td>
                        <td className="px-5 py-3.5 tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                          {formatTimeRange(schedule.startTime, schedule.endTime)}
                          {schedule.isRecurring ? (
                            <span className="ml-2 text-[11px] text-[#737373]">· weekly</span>
                          ) : null}
                        </td>
                        <td className="px-5 py-3.5">
                          {schedule.hostName ? (
                            <span className="text-[13px] text-[#0A0A0A]">{schedule.hostName}</span>
                          ) : (
                            <span className="text-[13px] italic text-[#A3A3A3]">Unassigned</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5">
                          <div className="text-[13px] text-[#0A0A0A]">
                            {schedule.platformAccount ?? '—'}
                          </div>
                          {schedule.platformType && (
                            <div className="text-[11px] uppercase tracking-wide text-[#737373]">
                              {schedule.platformType}
                            </div>
                          )}
                        </td>
                        <td className="px-5 py-3.5">
                          <StatusChip variant={schedule.isActive ? 'active' : 'inactive'} />
                        </td>
                        <td className="px-5 py-3.5 text-right">
                          <div className="inline-flex gap-1">
                            <Link
                              href={`/livehost/schedules/${schedule.id}`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                              title="View"
                            >
                              <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                            </Link>
                            <Link
                              href={`/livehost/schedules/${schedule.id}/edit`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                              title="Edit"
                            >
                              <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                            </Link>
                            <button
                              type="button"
                              onClick={() => {
                                if (
                                  window.confirm(
                                    `Delete the ${DAY_NAMES[schedule.dayOfWeek]} ${schedule.startTime}–${schedule.endTime} slot?`
                                  )
                                ) {
                                  router.delete(`/livehost/schedules/${schedule.id}`, {
                                    preserveScroll: true,
                                  });
                                }
                              }}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#FFF1F2] hover:text-[#F43F5E]"
                              title="Delete"
                            >
                              <Trash2 className="h-[14px] w-[14px]" strokeWidth={2} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            {/* Pagination */}
            {!Array.isArray(schedules) && schedules.last_page > 1 && (
              <div className="flex items-center justify-between">
                <div className="text-xs text-[#737373]">
                  Showing {schedules.from}–{schedules.to} of {schedules.total}
                </div>
                <div className="flex gap-1">
                  {schedules.links.map((link, index) => (
                    <button
                      key={`${link.label}-${index}`}
                      type="button"
                      disabled={!link.url}
                      onClick={() => {
                        if (link.url) {
                          router.visit(link.url, {
                            preserveScroll: true,
                            preserveState: true,
                          });
                        }
                      }}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                      className={[
                        'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                        link.active
                          ? 'bg-[#0A0A0A] text-white'
                          : 'text-[#737373] hover:bg-[#F5F5F5]',
                        !link.url ? 'cursor-default opacity-40' : '',
                      ].join(' ')}
                    />
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );
}

SchedulesIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
