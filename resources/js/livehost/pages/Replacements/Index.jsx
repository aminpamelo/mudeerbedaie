import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Input } from '@/livehost/components/ui/input';

const TABS = [
  { id: 'pending', label: 'Tertunda' },
  { id: 'assigned', label: 'Telah ditugaskan' },
  { id: 'expired', label: 'Tamat tempoh' },
  { id: 'rejected', label: 'Ditolak' },
  { id: 'withdrawn', label: 'Ditarik balik' },
];

const REASON_LABELS = {
  sick: 'Sakit',
  family: 'Kecemasan keluarga',
  personal: 'Urusan peribadi',
  other: 'Lain-lain',
};

const DAY_NAMES = [
  'Ahad',
  'Isnin',
  'Selasa',
  'Rabu',
  'Khamis',
  'Jumaat',
  'Sabtu',
];

function formatTime(value) {
  if (!value) {
    return '';
  }
  const match = String(value).match(/^(\d{1,2}):(\d{2})/);
  if (!match) {
    return String(value);
  }
  return `${match[1].padStart(2, '0')}:${match[2]}`;
}

function dayLabel(dayOfWeek) {
  if (dayOfWeek === null || dayOfWeek === undefined) {
    return '—';
  }
  return DAY_NAMES[Number(dayOfWeek)] ?? '—';
}

/**
 * Live countdown until the request expires. Updates every 30 seconds — the
 * Inertia page reload that follows assignment / rejection naturally clears
 * any stale state.
 */
function useNow(intervalMs = 30_000) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);
  return now;
}

function formatExpiresIn(expiresAt, now) {
  if (!expiresAt) {
    return '—';
  }
  const target = Date.parse(expiresAt);
  if (Number.isNaN(target)) {
    return '—';
  }
  const diffMs = target - now;
  if (diffMs <= 0) {
    return 'Tamat tempoh';
  }
  const totalMinutes = Math.floor(diffMs / 60_000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
}

function statusVariant(status) {
  switch (status) {
    case 'pending':
      return 'bg-[#FFFBEB] text-[#92400E] ring-[#FDE68A]';
    case 'assigned':
      return 'bg-[#ECFDF5] text-[#065F46] ring-[#A7F3D0]';
    case 'expired':
      return 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]';
    case 'rejected':
      return 'bg-[#FEF2F2] text-[#991B1B] ring-[#FECACA]';
    case 'withdrawn':
      return 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]';
    default:
      return 'bg-[#F5F5F5] text-[#525252] ring-[#E5E5E5]';
  }
}

function statusLabel(status) {
  const tab = TABS.find((t) => t.id === status);
  return tab?.label ?? status;
}

export default function Index() {
  const { requests, counts, currentStatus } = usePage().props;
  const now = useNow();

  const [hostSearch, setHostSearch] = useState('');
  const [platformFilter, setPlatformFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  // Reset filters when the active tab changes — the underlying dataset shifts.
  useEffect(() => {
    setHostSearch('');
    setPlatformFilter('');
    setDateFrom('');
    setDateTo('');
  }, [currentStatus]);

  const platformOptions = useMemo(() => {
    const set = new Set();
    (requests ?? []).forEach((r) => {
      if (r.slot?.platformAccount) {
        set.add(r.slot.platformAccount);
      }
    });
    return Array.from(set).sort((a, b) => a.localeCompare(b));
  }, [requests]);

  const filteredRequests = useMemo(() => {
    const list = requests ?? [];
    const hostQuery = hostSearch.trim().toLowerCase();
    const fromMs = dateFrom ? Date.parse(`${dateFrom}T00:00:00`) : null;
    const toMs = dateTo ? Date.parse(`${dateTo}T23:59:59`) : null;

    return list.filter((req) => {
      if (hostQuery && !(req.originalHost?.name ?? '').toLowerCase().includes(hostQuery)) {
        return false;
      }
      if (platformFilter && req.slot?.platformAccount !== platformFilter) {
        return false;
      }
      if (fromMs !== null || toMs !== null) {
        const when = req.requestedAt ? Date.parse(req.requestedAt) : null;
        if (when === null || Number.isNaN(when)) {
          return false;
        }
        if (fromMs !== null && when < fromMs) {
          return false;
        }
        if (toMs !== null && when > toMs) {
          return false;
        }
      }
      return true;
    });
  }, [requests, hostSearch, platformFilter, dateFrom, dateTo]);

  const handleTabChange = (tabId) => {
    if (tabId === currentStatus) {
      return;
    }
    router.get(
      '/livehost/replacements',
      { status: tabId },
      { preserveState: true, preserveScroll: true }
    );
  };

  return (
    <>
      <Head title="Permohonan ganti" />
      <TopBar breadcrumb={['Live Host Desk', 'Permohonan ganti']} />

      <div className="space-y-6 p-8">
        <div>
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            Permohonan ganti
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            Urus permohonan ganti slot live daripada hos.
          </p>
        </div>

        {/* Tabs */}
        <div className="flex flex-wrap items-center gap-1 border-b border-[#EAEAEA]">
          {TABS.map((tab) => {
            const active = tab.id === currentStatus;
            const count = counts?.[tab.id] ?? 0;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => handleTabChange(tab.id)}
                className={[
                  '-mb-px inline-flex items-center gap-2 border-b-2 px-3 pb-3 pt-1 text-sm font-medium transition-colors',
                  active
                    ? 'border-[#0A0A0A] text-[#0A0A0A]'
                    : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
                ].join(' ')}
              >
                {tab.label}
                <span
                  className={[
                    'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold tabular-nums',
                    active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'bg-[#F5F5F5] text-[#525252]',
                  ].join(' ')}
                >
                  {count}
                </span>
              </button>
            );
          })}
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-end gap-3 rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative min-w-[220px] flex-1">
            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Hos
            </label>
            <Search
              className="pointer-events-none absolute left-3 top-[34px] h-[14px] w-[14px] -translate-y-1/2 text-[#737373]"
              strokeWidth={2}
            />
            <Input
              value={hostSearch}
              onChange={(event) => setHostSearch(event.target.value)}
              placeholder="Cari nama hos…"
              className="border-[#EAEAEA] bg-[#FAFAFA] pl-9"
            />
          </div>
          <div className="min-w-[180px]">
            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Platform
            </label>
            <select
              value={platformFilter}
              onChange={(event) => setPlatformFilter(event.target.value)}
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            >
              <option value="">Semua platform</option>
              {platformOptions.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Daripada
            </label>
            <input
              type="date"
              value={dateFrom}
              onChange={(event) => setDateFrom(event.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#737373]">
              Sehingga
            </label>
            <input
              type="date"
              value={dateTo}
              onChange={(event) => setDateTo(event.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          {(hostSearch || platformFilter || dateFrom || dateTo) && (
            <button
              type="button"
              onClick={() => {
                setHostSearch('');
                setPlatformFilter('');
                setDateFrom('');
                setDateTo('');
              }}
              className="h-9 rounded-lg px-3 text-xs font-medium text-[#059669] hover:bg-[#ECFDF5] hover:text-[#047857]"
            >
              Kosongkan tapisan
            </button>
          )}
        </div>

        {/* List */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {filteredRequests.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">
                {(requests?.length ?? 0) === 0
                  ? 'Tiada permohonan dalam status ini.'
                  : 'Tiada permohonan yang memenuhi tapisan.'}
              </div>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Hos</th>
                  <th className="px-5 py-3 text-left">Slot</th>
                  <th className="px-5 py-3 text-left">Skop</th>
                  <th className="px-5 py-3 text-left">Sebab</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  {currentStatus === 'pending' && (
                    <th className="px-5 py-3 text-right">Tamat dalam</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {filteredRequests.map((req) => (
                  <tr
                    key={req.id}
                    onClick={() => router.visit(`/livehost/replacements/${req.id}`)}
                    className="cursor-pointer border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <div className="text-[13.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                        {req.originalHost?.name ?? '—'}
                      </div>
                      <div className="mt-0.5 text-[11.5px] text-[#737373]">
                        ID {req.originalHost?.id ?? '—'}
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="text-[13px] text-[#0A0A0A]">
                        {dayLabel(req.slot?.dayOfWeek)} ·{' '}
                        {formatTime(req.slot?.startTime)} – {formatTime(req.slot?.endTime)}
                      </div>
                      <div className="mt-0.5 text-[11.5px] text-[#737373]">
                        {req.slot?.platformAccount ?? '—'}
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      {req.scope === 'permanent' ? (
                        <span className="inline-flex items-center rounded-md bg-[#FDF2F8] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[#9D174D] ring-1 ring-inset ring-[#FBCFE8]">
                          Permanent
                        </span>
                      ) : (
                        <span className="text-[12.5px] tabular-nums text-[#0A0A0A]">
                          {req.targetDate ?? '—'}
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#0A0A0A]">
                      {REASON_LABELS[req.reasonCategory] ?? req.reasonCategory ?? '—'}
                    </td>
                    <td className="px-5 py-3.5">
                      <span
                        className={[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                          statusVariant(req.status),
                        ].join(' ')}
                      >
                        {statusLabel(req.status)}
                      </span>
                    </td>
                    {currentStatus === 'pending' && (
                      <td className="px-5 py-3.5 text-right font-mono text-[12.5px] tabular-nums text-[#0A0A0A]">
                        {formatExpiresIn(req.expiresAt, now)}
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  );
}

Index.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
