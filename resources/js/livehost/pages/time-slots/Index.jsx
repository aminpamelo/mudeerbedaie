import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const DAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
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

export default function TimeSlotsIndex() {
  const { timeSlots, filters, platformAccounts, flash } = usePage().props;
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [dayOfWeek, setDayOfWeek] = useState(filters?.day_of_week ?? '');
  const [active, setActive] = useState(filters?.active ?? '');

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.platform_account ?? '') === platformAccount &&
      (initial.day_of_week ?? '') === dayOfWeek &&
      (initial.active ?? '') === active
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/time-slots',
        {
          platform_account: platformAccount || undefined,
          day_of_week: dayOfWeek !== '' ? dayOfWeek : undefined,
          active: active || undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [platformAccount, dayOfWeek, active, filters]);

  const clearFilters = () => {
    setPlatformAccount('');
    setDayOfWeek('');
    setActive('');
  };

  const newSlotAction = (
    <Link href="/livehost/time-slots/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New time slot
      </Button>
    </Link>
  );

  const hasFilters = Boolean(platformAccount || dayOfWeek !== '' || active);

  return (
    <>
      <Head title="Time Slots" />
      <TopBar breadcrumb={['Live Host Desk', 'Time Slots']} actions={newSlotAction} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Time Slots
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {timeSlots.total} reusable time window{timeSlots.total === 1 ? '' : 's'} used by schedule assignments
            </p>
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

        {/* Filter bar */}
        <div className="flex flex-wrap items-center gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <select
            value={platformAccount}
            onChange={(event) => setPlatformAccount(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All platform accounts</option>
            <option value="global">Global (All platforms)</option>
            {platformAccounts.map((pa) => (
              <option key={pa.id} value={pa.id}>
                {pa.name} {pa.platform ? `· ${pa.platform}` : ''}
              </option>
            ))}
          </select>
          <select
            value={dayOfWeek}
            onChange={(event) => setDayOfWeek(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All days</option>
            <option value="global">All Days (Global)</option>
            {DAY_NAMES.map((label, idx) => (
              <option key={label} value={idx}>
                {label}
              </option>
            ))}
          </select>
          <select
            value={active}
            onChange={(event) => setActive(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
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

        {/* Table */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {timeSlots.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">
                {hasFilters ? 'No time slots found.' : 'No time slots yet — create your first reusable window.'}
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
                  <th className="px-5 py-3 text-left">Duration</th>
                  <th className="px-5 py-3 text-left">Platform</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {timeSlots.data.map((slot) => (
                  <tr
                    key={slot.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      {slot.dayOfWeek === null || slot.dayOfWeek === undefined ? (
                        <span className="inline-flex min-w-[60px] justify-center rounded-md bg-[#F5F3FF] px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#5B21B6]">
                          All days
                        </span>
                      ) : (
                        <span
                          className={`inline-flex min-w-[40px] justify-center rounded-md px-2 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                            DAY_COLORS[slot.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]'
                          }`}
                        >
                          {DAY_SHORT[slot.dayOfWeek] ?? '—'}
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                      {formatTimeRange(slot.startTime, slot.endTime)}
                    </td>
                    <td className="px-5 py-3.5 text-[13px] text-[#737373]">
                      {slot.durationMinutes ? `${slot.durationMinutes} min` : '—'}
                    </td>
                    <td className="px-5 py-3.5">
                      {slot.platformAccount ? (
                        <>
                          <div className="text-[13px] text-[#0A0A0A]">{slot.platformAccount}</div>
                          {slot.platformType && (
                            <div className="text-[11px] uppercase tracking-wide text-[#737373]">
                              {slot.platformType}
                            </div>
                          )}
                        </>
                      ) : (
                        <span className="inline-flex rounded-md bg-[#F5F3FF] px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#5B21B6]">
                          Global
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusChip variant={slot.isActive ? 'active' : 'inactive'} />
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex gap-1">
                        <Link
                          href={`/livehost/time-slots/${slot.id}/edit`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="Edit"
                        >
                          <Pencil className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <button
                          type="button"
                          onClick={() => {
                            const dayLabel =
                              slot.dayOfWeek === null || slot.dayOfWeek === undefined
                                ? 'All days'
                                : DAY_SHORT[slot.dayOfWeek];
                            if (
                              window.confirm(
                                `Delete the ${dayLabel} ${slot.startTime}–${slot.endTime} time slot?`
                              )
                            ) {
                              router.delete(`/livehost/time-slots/${slot.id}`, {
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
        {timeSlots.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {timeSlots.from}–{timeSlots.to} of {timeSlots.total}
            </div>
            <div className="flex gap-1">
              {timeSlots.links.map((link, index) => (
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
      </div>
    </>
  );
}

TimeSlotsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
