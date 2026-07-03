import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
  Database,
  DollarSign,
  Eye,
  Link2,
  Search,
  Users,
  Package,
} from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import SearchableSelect from '@/livehost/components/SearchableSelect';

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'live', label: 'Live' },
  { value: 'ended', label: 'Ended' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'missed', label: 'Missed' },
];

const STATUS_LABELS = {
  scheduled: 'Scheduled',
  live: 'Live',
  ended: 'Ended',
  completed: 'Completed',
  cancelled: 'Cancelled',
  missed: 'Missed',
};

const LINK_OPTIONS = [
  { value: '', label: 'Linked + manual' },
  { value: 'linked', label: 'Linked to API only' },
  { value: 'unlinked', label: 'Unlinked (manual) only' },
];

function statusChipVariant(status) {
  switch (status) {
    case 'live':
      return 'live';
    case 'ended':
    case 'completed':
      return 'done';
    case 'cancelled':
      return 'suspended';
    case 'missed':
      return 'inactive';
    case 'scheduled':
    default:
      return 'scheduled';
  }
}

function formatDateTime(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatMyr(value) {
  if (value === null || value === undefined) {
    return '—';
  }
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return `RM ${num.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatMyrCompact(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) {
    return 'RM 0';
  }
  if (Math.abs(num) >= 1_000_000) {
    return `RM ${(num / 1_000_000).toLocaleString(undefined, { maximumFractionDigits: 2 })}M`;
  }
  return `RM ${num.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

function formatNumber(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }
  const num = Number(value);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return num.toLocaleString();
}

export default function SessionsData() {
  const { sessions, filters, summary, hosts, platformAccounts, liveAccounts, flash } =
    usePage().props;

  const [search, setSearch] = useState(filters?.search ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [host, setHost] = useState(filters?.host ?? '');
  const [liveAccount, setLiveAccount] = useState(filters?.live_account ?? '');
  const [link, setLink] = useState(filters?.link ?? '');
  const [from, setFrom] = useState(filters?.from ?? '');
  const [to, setTo] = useState(filters?.to ?? '');
  const [minGmv, setMinGmv] = useState(filters?.min_gmv ?? '');

  const hostOptions = useMemo(
    () => [
      { value: '', label: 'All hosts' },
      ...hosts.map((h) => ({ value: String(h.id), label: h.name, hint: h.email })),
    ],
    [hosts]
  );
  const platformOptions = useMemo(
    () => [
      { value: '', label: 'All accounts' },
      ...platformAccounts.map((pa) => ({
        value: String(pa.id),
        label: pa.name,
        hint: pa.platform,
      })),
    ],
    [platformAccounts]
  );
  const creatorOptions = useMemo(
    () => [
      { value: '', label: 'All creators' },
      ...liveAccounts.map((a) => ({ value: String(a.id), label: a.label, hint: a.hint })),
    ],
    [liveAccounts]
  );

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.search ?? '') === search &&
      (initial.status ?? '') === status &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.host ?? '') === host &&
      (initial.live_account ?? '') === liveAccount &&
      (initial.link ?? '') === link &&
      (initial.from ?? '') === from &&
      (initial.to ?? '') === to &&
      (initial.min_gmv ?? '') === minGmv
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/session-data',
        {
          search: search || undefined,
          status: status || undefined,
          platform_account: platformAccount || undefined,
          host: host || undefined,
          live_account: liveAccount || undefined,
          link: link || undefined,
          from: from || undefined,
          to: to || undefined,
          min_gmv: minGmv || undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [search, status, platformAccount, host, liveAccount, link, from, to, minGmv, filters]);

  const clearFilters = () => {
    setSearch('');
    setStatus('');
    setPlatformAccount('');
    setHost('');
    setLiveAccount('');
    setLink('');
    setFrom('');
    setTo('');
    setMinGmv('');
  };

  const hasFilters = Boolean(
    search || status || platformAccount || host || liveAccount || link || from || to || minGmv
  );

  const linkedPct =
    summary.total > 0 ? Math.round((summary.linked / summary.total) * 100) : 0;

  return (
    <>
      <Head title="Session Data" />
      <TopBar breadcrumb={['Live Host Desk', 'Session Data']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <div className="flex flex-wrap items-end justify-between gap-4 sm:gap-8">
          <div>
            <h1 className="text-2xl sm:text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Session Data
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              Every session combined with its linked TikTok API record — GMV, viewers, items
              and creator, in one filterable view.
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

        {/* KPI strip — reflects the current filters */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
          <Kpi label="Sessions" value={formatNumber(summary.total)} icon={Database} tint="ink" />
          <Kpi
            label="Linked to API"
            value={formatNumber(summary.linked)}
            sub={`${linkedPct}% · ${formatNumber(summary.unlinked)} unlinked`}
            icon={Link2}
            tint="emerald"
          />
          <Kpi
            label="Live-attrib GMV"
            value={formatMyrCompact(summary.liveAttribGmv)}
            icon={DollarSign}
            tint="emerald"
          />
          <Kpi
            label="Total GMV"
            value={formatMyrCompact(summary.totalGmv)}
            sub="linked records"
            icon={DollarSign}
            tint="sky"
          />
          <Kpi label="Viewers" value={formatNumber(summary.viewers)} icon={Users} tint="violet" />
          <Kpi label="Items sold" value={formatNumber(summary.items)} icon={Package} tint="amber" />
        </div>

        {/* Filter bar */}
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="relative w-full min-w-[220px] flex-1 sm:w-auto">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-[14px] w-[14px] -translate-y-1/2 text-[#A3A3A3]"
              strokeWidth={2}
            />
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search session, host, creator handle…"
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white pl-9 pr-3 text-sm text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          <div className="w-full sm:w-[150px]">
            <SearchableSelect
              value={host}
              onChange={setHost}
              options={hostOptions}
              placeholder="All hosts"
              searchPlaceholder="Search hosts…"
            />
          </div>
          <div className="w-full sm:w-[170px]">
            <SearchableSelect
              value={platformAccount}
              onChange={setPlatformAccount}
              options={platformOptions}
              placeholder="All accounts"
              searchPlaceholder="Search accounts…"
            />
          </div>
          <div className="w-full sm:w-[170px]">
            <SearchableSelect
              value={liveAccount}
              onChange={setLiveAccount}
              options={creatorOptions}
              placeholder="All creators"
              searchPlaceholder="Search creators…"
            />
          </div>
          <select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
          >
            {STATUS_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          <select
            value={link}
            onChange={(event) => setLink(event.target.value)}
            className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
          >
            {LINK_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          <div className="flex w-full flex-wrap items-center gap-1.5 sm:inline-flex sm:w-auto sm:flex-nowrap">
            <label className="text-xs font-medium text-[#737373]" htmlFor="session-data-from">
              From
            </label>
            <input
              id="session-data-from"
              type="date"
              value={from}
              onChange={(event) => setFrom(event.target.value)}
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
            />
            <label className="ml-1 text-xs font-medium text-[#737373]" htmlFor="session-data-to">
              To
            </label>
            <input
              id="session-data-to"
              type="date"
              value={to}
              onChange={(event) => setTo(event.target.value)}
              className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20 sm:w-auto"
            />
          </div>
          <div className="flex w-full flex-wrap items-center gap-1.5 sm:inline-flex sm:w-auto sm:flex-nowrap">
            <label className="text-xs font-medium text-[#737373]" htmlFor="session-data-min-gmv">
              Min GMV
            </label>
            <input
              id="session-data-min-gmv"
              type="number"
              min="0"
              step="0.01"
              inputMode="decimal"
              value={minGmv}
              onChange={(event) => setMinGmv(event.target.value)}
              placeholder="0.00"
              className="h-9 w-[110px] rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] placeholder:text-[#A3A3A3] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
          {hasFilters && (
            <button
              type="button"
              onClick={clearFilters}
              className="w-full text-sm font-medium text-[#059669] hover:text-[#047857] sm:w-auto"
            >
              Clear
            </button>
          )}
        </div>

        {/* Table */}
        <div className="overflow-x-auto rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {sessions.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">
                {hasFilters
                  ? 'No sessions match the current filters.'
                  : 'No session data yet.'}
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
            <table className="w-full min-w-[1080px] text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Session</th>
                  <th className="px-5 py-3 text-left">Host</th>
                  <th className="px-5 py-3 text-left">Account · Creator</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-left">Source</th>
                  <th className="px-5 py-3 text-right">Live-attrib GMV</th>
                  <th className="px-5 py-3 text-right">Total GMV</th>
                  <th className="px-5 py-3 text-right">Viewers</th>
                  <th className="px-5 py-3 text-right">Items</th>
                  <th className="px-5 py-3 text-left">Date</th>
                  <th className="px-5 py-3 text-right">View</th>
                </tr>
              </thead>
              <tbody>
                {sessions.data.map((session) => (
                  <tr
                    key={session.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <Link
                        href={`/livehost/sessions/${session.id}`}
                        className="font-mono text-[12.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A] hover:text-[#059669]"
                      >
                        {session.sessionId}
                      </Link>
                      {session.title && (
                        <div className="mt-0.5 max-w-[200px] truncate text-[11.5px] text-[#737373]">
                          {session.title}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      {session.hostName ? (
                        <div className="text-[13px] text-[#0A0A0A]">{session.hostName}</div>
                      ) : (
                        <span className="text-[13px] italic text-[#A3A3A3]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="text-[13px] text-[#0A0A0A]">
                        {session.accountName ?? '—'}
                      </div>
                      {session.creatorHandle && (
                        <div className="text-[11.5px] text-[#737373]">
                          {session.creatorHandle}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusChip variant={statusChipVariant(session.status)}>
                        {STATUS_LABELS[session.status] ?? session.status}
                      </StatusChip>
                    </td>
                    <td className="px-5 py-3.5">
                      <SourceBadge linked={session.linked} source={session.source} />
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                      {formatMyr(session.liveAttribGmv)}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#404040]">
                      {formatMyr(session.totalGmv)}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#404040]">
                      {formatNumber(session.viewers)}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#404040]">
                      {formatNumber(session.itemsSold)}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                      {formatDateTime(session.liveStart ?? session.scheduledStart)}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <Link
                        href={`/livehost/sessions/${session.id}`}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                        title="Open session"
                      >
                        <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination */}
        {sessions.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {sessions.from}–{sessions.to} of {sessions.total}
            </div>
            <div className="flex gap-1">
              {sessions.links.map((linkItem, index) => (
                <button
                  key={`${linkItem.label}-${index}`}
                  type="button"
                  disabled={!linkItem.url}
                  onClick={() => {
                    if (linkItem.url) {
                      router.visit(linkItem.url, {
                        preserveScroll: true,
                        preserveState: true,
                      });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: linkItem.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    linkItem.active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !linkItem.url ? 'cursor-default opacity-40' : '',
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

SessionsData.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

const KPI_TINTS = {
  ink: 'bg-[#0A0A0A] text-white',
  emerald: 'bg-[#ECFDF5] text-[#059669]',
  sky: 'bg-[#F0F9FF] text-[#0369A1]',
  violet: 'bg-[#F5F3FF] text-[#6D28D9]',
  amber: 'bg-[#FFFBEB] text-[#B45309]',
};

function Kpi({ label, value, sub, icon: Icon, tint = 'emerald' }) {
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="flex items-center justify-between gap-2">
        <span className="text-[12px] font-medium text-[#737373]">{label}</span>
        {Icon && (
          <span className={`grid h-7 w-7 place-items-center rounded-lg ${KPI_TINTS[tint]}`}>
            <Icon className="h-[15px] w-[15px]" strokeWidth={2} />
          </span>
        )}
      </div>
      <div className="mt-2 text-[22px] font-semibold tabular-nums leading-none tracking-[-0.03em] text-[#0A0A0A]">
        {value}
      </div>
      {sub && <div className="mt-1.5 text-[11.5px] text-[#737373]">{sub}</div>}
    </div>
  );
}

function SourceBadge({ linked, source }) {
  if (!linked) {
    return (
      <span className="inline-flex items-center rounded-full border border-[#EAEAEA] bg-[#F5F5F5] px-2 py-0.5 text-[11px] font-medium text-[#737373]">
        Manual
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full border border-[#BAE6FD] bg-[#F0F9FF] px-2 py-0.5 text-[11px] font-medium uppercase text-[#0369A1]">
      {source || 'api'}
    </span>
  );
}
