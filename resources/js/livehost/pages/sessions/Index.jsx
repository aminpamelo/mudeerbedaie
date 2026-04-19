import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Eye, Paperclip } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import LiveSessionModal from '@/livehost/components/LiveSessionModal';

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'live', label: 'Live' },
  { value: 'ended', label: 'Ended' },
  { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_LABELS = {
  scheduled: 'Scheduled',
  live: 'Live',
  ended: 'Ended',
  cancelled: 'Cancelled',
};

function statusChipVariant(status) {
  switch (status) {
    case 'live':
      return 'live';
    case 'ended':
      return 'done';
    case 'cancelled':
      return 'suspended';
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

function formatDurationFromMinutes(minutes) {
  const mins = Math.abs(Math.round(Number(minutes) || 0));
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/**
 * Duration rules:
 *  - Completed (actualStart + actualEnd)  → HH:MM
 *  - Live     (actualStart, no end)       → elapsed HH:MM + live tag
 *  - Otherwise                            → —
 */
function renderDuration(session) {
  const { actualStart, actualEnd, duration, status } = session;

  if (actualStart && actualEnd) {
    if (duration != null && Number.isFinite(Number(duration))) {
      return formatDurationFromMinutes(duration);
    }
    const start = new Date(actualStart).getTime();
    const end = new Date(actualEnd).getTime();
    if (!Number.isNaN(start) && !Number.isNaN(end) && end > start) {
      return formatDurationFromMinutes((end - start) / 60000);
    }
  }

  if (status === 'live' && actualStart) {
    const start = new Date(actualStart).getTime();
    if (!Number.isNaN(start)) {
      const elapsed = Math.max(0, (Date.now() - start) / 60000);
      return `${formatDurationFromMinutes(elapsed)} · live`;
    }
    return 'in progress';
  }

  return '—';
}

export default function SessionsIndex() {
  const { sessions, filters, hosts, platformAccounts, flash } = usePage().props;
  const [modalSession, setModalSession] = useState(null);

  useEffect(() => {
    if (!modalSession) {
      return;
    }
    const fresh = sessions.data.find((s) => s.id === modalSession.id);
    if (fresh && fresh !== modalSession) {
      setModalSession(fresh);
    }
  }, [sessions.data, modalSession]);

  const [status, setStatus] = useState(filters?.status ?? '');
  const [platformAccount, setPlatformAccount] = useState(filters?.platform_account ?? '');
  const [host, setHost] = useState(filters?.host ?? '');
  const [verification, setVerification] = useState(filters?.verification ?? '');
  const [from, setFrom] = useState(filters?.from ?? '');
  const [to, setTo] = useState(filters?.to ?? '');

  // Tick once per minute so that "live" session durations advance.
  const [, setNow] = useState(() => Date.now());
  useEffect(() => {
    const hasLive = sessions.data.some((s) => s.status === 'live' && s.actualStart);
    if (!hasLive) {
      return undefined;
    }
    const id = setInterval(() => setNow(Date.now()), 60_000);
    return () => clearInterval(id);
  }, [sessions.data]);

  useEffect(() => {
    const initial = filters ?? {};
    if (
      (initial.status ?? '') === status &&
      (initial.platform_account ?? '') === platformAccount &&
      (initial.host ?? '') === host &&
      (initial.verification ?? '') === verification &&
      (initial.from ?? '') === from &&
      (initial.to ?? '') === to
    ) {
      return undefined;
    }

    const handle = setTimeout(() => {
      router.get(
        '/livehost/sessions',
        {
          status: status || undefined,
          platform_account: platformAccount || undefined,
          host: host || undefined,
          verification: verification || undefined,
          from: from || undefined,
          to: to || undefined,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        }
      );
    }, 300);

    return () => clearTimeout(handle);
  }, [status, platformAccount, host, verification, from, to, filters]);

  const clearFilters = () => {
    setStatus('');
    setPlatformAccount('');
    setHost('');
    setVerification('');
    setFrom('');
    setTo('');
  };

  const hasFilters = Boolean(status || platformAccount || host || verification || from || to);

  return (
    <>
      <Head title="Live Sessions" />
      <TopBar breadcrumb={['Live Host Desk', 'Live Sessions']} />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Live Sessions
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {sessions.total} session{sessions.total === 1 ? '' : 's'} — read-only log of
              streams created by the streaming system
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
            value={verification}
            onChange={(event) => setVerification(event.target.value)}
            className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          >
            <option value="">Any verification</option>
            <option value="pending">Pending review</option>
            <option value="verified">Verified</option>
            <option value="rejected">Rejected</option>
          </select>
          <div className="inline-flex items-center gap-1.5">
            <label className="text-xs font-medium text-[#737373]" htmlFor="session-date-from">
              From
            </label>
            <input
              id="session-date-from"
              type="date"
              value={from}
              onChange={(event) => setFrom(event.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
            <label className="ml-1 text-xs font-medium text-[#737373]" htmlFor="session-date-to">
              To
            </label>
            <input
              id="session-date-to"
              type="date"
              value={to}
              onChange={(event) => setTo(event.target.value)}
              className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </div>
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
          {sessions.data.length === 0 ? (
            <div className="py-16 text-center">
              <div className="text-sm text-[#737373]">
                {hasFilters
                  ? 'No sessions match the current filters.'
                  : 'No sessions yet — they are created automatically by the streaming system.'}
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
                  <th className="px-5 py-3 text-left">Session ID</th>
                  <th className="px-5 py-3 text-left">Host</th>
                  <th className="px-5 py-3 text-left">Platform</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-left">Verification</th>
                  <th className="px-5 py-3 text-left">Scheduled start</th>
                  <th className="px-5 py-3 text-left">Actual start</th>
                  <th className="px-5 py-3 text-right">Duration</th>
                  <th className="px-5 py-3 text-center">Attachments</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {sessions.data.map((session) => (
                  <tr
                    key={session.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#F5F5F5]"
                  >
                    <td className="px-5 py-3.5">
                      <div className="font-mono text-[12.5px] font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                        {session.sessionId}
                      </div>
                      {session.title && (
                        <div className="mt-0.5 truncate max-w-[220px] text-[11.5px] text-[#737373]">
                          {session.title}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      {session.hostName ? (
                        <>
                          <div className="text-[13px] text-[#0A0A0A]">{session.hostName}</div>
                          {session.hostEmail && (
                            <div className="text-[11px] text-[#737373]">{session.hostEmail}</div>
                          )}
                        </>
                      ) : (
                        <span className="text-[13px] italic text-[#A3A3A3]">Unassigned</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="text-[13px] text-[#0A0A0A]">
                        {session.platformAccount ?? '—'}
                      </div>
                      {session.platformType && (
                        <div className="text-[11px] uppercase tracking-wide text-[#737373]">
                          {session.platformType}
                        </div>
                      )}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusChip variant={statusChipVariant(session.status)}>
                        {STATUS_LABELS[session.status] ?? session.status}
                      </StatusChip>
                    </td>
                    <td className="px-5 py-3.5">
                      <VerificationBadge status={session.verificationStatus ?? 'pending'} />
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                      {formatDateTime(session.scheduledStart)}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                      {formatDateTime(session.actualStart)}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                      {renderDuration(session)}
                    </td>
                    <td className="px-5 py-3.5 text-center">
                      {session.attachmentCount > 0 ? (
                        <button
                          type="button"
                          onClick={() => setModalSession(session)}
                          className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[12.5px] font-medium text-[#404040] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                          title={
                            session.attachments
                              ?.map((a) => a.fileName)
                              .join('\n') ?? `${session.attachmentCount} attachment(s)`
                          }
                        >
                          <Paperclip className="h-3.5 w-3.5" strokeWidth={2} />
                          <span className="tabular-nums">{session.attachmentCount}</span>
                        </button>
                      ) : (
                        <span className="text-[12.5px] text-[#A3A3A3]">—</span>
                      )}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <button
                        type="button"
                        onClick={() => setModalSession(session)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#737373] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                        title="View"
                      >
                        <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                      </button>
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
              {sessions.links.map((link, index) => (
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

      <LiveSessionModal
        open={modalSession !== null}
        onOpenChange={(next) => {
          if (!next) {
            setModalSession(null);
          }
        }}
        session={modalSession}
        hosts={hosts ?? []}
        platformAccounts={platformAccounts ?? []}
      />
    </>
  );
}

SessionsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function VerificationBadge({ status }) {
  const styles = {
    pending: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
    verified: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
    rejected: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
  };
  const label = {
    pending: 'Pending',
    verified: 'Verified',
    rejected: 'Rejected',
  };
  const cls = styles[status] ?? styles.pending;

  return (
    <span
      className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${cls}`}
    >
      {label[status] ?? status}
    </span>
  );
}
