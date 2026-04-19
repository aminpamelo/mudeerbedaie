import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';

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

const STATUS_OPTIONS = [
  { value: '', label: 'Any status' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];

function statusChipVariant(status) {
  switch (status) {
    case 'confirmed':
      return 'active';
    case 'in_progress':
      return 'live';
    case 'completed':
      return 'done';
    case 'cancelled':
      return 'suspended';
    case 'scheduled':
    default:
      return 'scheduled';
  }
}

function statusLabel(status) {
  const found = STATUS_OPTIONS.find((s) => s.value === status);
  return found ? found.label : status;
}

export default function SessionSlotsIndex() {
  const { sessionSlots, filters, hosts, platformAccounts, flash } = usePage().props;
  const [host, setHost] = useState(filters?.host ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');
  const [dayOfWeek, setDayOfWeek] = useState(filters?.day_of_week ?? '');
  const [mode, setMode] = useState(filters?.mode ?? '');
  const [scheduleDate, setScheduleDate] = useState(filters?.schedule_date ?? '');

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.host ?? '') === host &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.status ?? '') === status &&
      (initial.day_of_week ?? '') === dayOfWeek &&
      (initial.mode ?? '') === mode &&
      (initial.schedule_date ?? '') === scheduleDate
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/session-slots',
        {
          host: host || undefined,
          platform_account: platformAccount || undefined,
          status: status || undefined,
          day_of_week: dayOfWeek !== '' ? dayOfWeek : undefined,
          mode: mode || undefined,
          schedule_date: scheduleDate || undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [host, platformAccount, status, dayOfWeek, mode, scheduleDate, filters]);

  const clearFilters = () => {
    setHost('');
    setPlatformAccount('');
    setStatus('');
    setDayOfWeek('');
    setMode('');
    setScheduleDate('');
  };

  const newSlotAction = (
    <Link href="/livehost/session-slots/create">
      <Button size="sm" className="h-9 gap-1.5 rounded-lg bg-ink text-white hover:bg-[#262626]">
        <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
        New session slot
      </Button>
    </Link>
  );

  const hasFilters = Boolean(
    host || platformAccount || status || dayOfWeek !== '' || mode || scheduleDate
  );

  return (
    <>
      <Head title="Session Slots" />
      <TopBar breadcrumb={['Live Host Desk', 'Session Slots']} actions={newSlotAction} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Session Slots
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {sessionSlots.total} assignment{sessionSlots.total === 1 ? '' : 's'} linking platform
              accounts to time slots
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
            value={host}
            onChange={(event) => setHost(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">All hosts</option>
            <option value="unassigned">Unassigned only</option>
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
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            {STATUS_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
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
            value={mode}
            onChange={(event) => setMode(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">Template & dated</option>
            <option value="template">Weekly template</option>
            <option value="dated">Dated only</option>
          </select>
          <input
            type="date"
            value={scheduleDate}
            onChange={(event) => setScheduleDate(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
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
          {sessionSlots.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">
                {hasFilters
                  ? 'No session slots found.'
                  : 'No session slots yet — assign a platform account to a time slot.'}
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
                  <th className="px-5 py-3 text-left">Time slot</th>
                  <th className="px-5 py-3 text-left">Platform</th>
                  <th className="px-5 py-3 text-left">Host</th>
                  <th className="px-5 py-3 text-left">Mode</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {sessionSlots.data.map((slot) => (
                  <tr
                    key={slot.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <span
                        className={`inline-flex min-w-[40px] justify-center rounded-md px-2 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                          DAY_COLORS[slot.dayOfWeek] ?? 'bg-[#F5F5F5] text-[#737373]'
                        }`}
                      >
                        {DAY_NAMES[slot.dayOfWeek] ?? '—'}
                      </span>
                      {slot.scheduleDate && (
                        <div className="mt-1 text-[11px] text-[#737373]">{slot.scheduleDate}</div>
                      )}
                    </td>
                    <td className="px-5 py-3.5 tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                      {slot.timeSlotLabel}
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="text-[13px] text-[#0A0A0A]">
                        {slot.platformAccount ?? '—'}
                      </div>
                      {slot.platformType && (
                        <div className="text-[11px] uppercase tracking-wide text-[#737373]">
                          {slot.platformType}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      {slot.hostName ? (
                        <>
                          <div className="text-[13px] text-[#0A0A0A]">{slot.hostName}</div>
                          {slot.hostEmail && (
                            <div className="text-[11px] text-[#737373]">{slot.hostEmail}</div>
                          )}
                        </>
                      ) : (
                        <span className="text-[13px] italic text-[#A3A3A3]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <span
                        className={`inline-flex rounded-md px-2 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                          slot.isTemplate
                            ? 'bg-[#F5F3FF] text-[#5B21B6]'
                            : 'bg-[#FFF7ED] text-[#B45309]'
                        }`}
                      >
                        {slot.isTemplate ? 'Weekly' : 'Dated'}
                      </span>
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusChip variant={statusChipVariant(slot.status)}>
                        {statusLabel(slot.status)}
                      </StatusChip>
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <div className="inline-flex gap-1">
                        <Link
                          href={`/livehost/session-slots/${slot.id}`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title="View"
                        >
                          <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                        </Link>
                        <Link
                          href={`/livehost/session-slots/${slot.id}/edit`}
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
                                `Delete the ${DAY_NAMES[slot.dayOfWeek]} ${slot.timeSlotLabel} session slot?`
                              )
                            ) {
                              router.delete(`/livehost/session-slots/${slot.id}`, {
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
        {sessionSlots.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {sessionSlots.from}–{sessionSlots.to} of {sessionSlots.total}
            </div>
            <div className="flex gap-1">
              {sessionSlots.links.map((link, index) => (
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

SessionSlotsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
