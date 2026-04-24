import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, Download, FileText, Image as ImageIcon, Lock, Plus, Trash2, Video } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';
import VerifyLinkPanel from '@/livehost/pages/sessions/VerifyLinkPanel';

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

function formatDuration(minutes) {
  if (minutes == null || !Number.isFinite(Number(minutes))) {
    return '—';
  }
  const mins = Math.abs(Math.round(Number(minutes)));
  if (mins === 0) {
    return '—';
  }
  if (mins < 60) {
    return `${mins}m`;
  }
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

function formatMyr(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return num.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export default function SessionsShow() {
  const { auth, session, analytics, attachments, candidates } = usePage().props;

  const canSeeCommission =
    auth?.user?.role === 'admin_livehost' || auth?.user?.role === 'admin';

  return (
    <>
      <Head title={`Session ${session.sessionId}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Sessions', session.sessionId]}
        actions={
          <Link href="/livehost/sessions">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="p-8 space-y-6">
        {/* Hero block */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-start justify-between gap-6">
            <div className="min-w-0">
              <div className="font-mono text-[12px] uppercase tracking-wide text-[#737373]">
                {session.sessionId}
              </div>
              <h1 className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {session.title ?? 'Untitled session'}
              </h1>
              <div className="mt-2 text-sm text-[#737373]">
                {session.hostName ? `Host: ${session.hostName}` : 'Unassigned'}
                {session.platformAccount ? ` · ${session.platformAccount}` : ''}
                {session.platformType ? ` · ${session.platformType}` : ''}
              </div>
              {session.description && (
                <p className="mt-3 max-w-2xl text-sm leading-relaxed text-[#404040]">
                  {session.description}
                </p>
              )}
            </div>
            <StatusChip variant={statusChipVariant(session.status)}>
              {STATUS_LABELS[session.status] ?? session.status}
            </StatusChip>
          </div>
        </div>

        {/* Verification (pending sessions only) */}
        <VerifyLinkPanel session={session} candidates={candidates ?? []} />

        {/* Timing */}
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          <InfoTile label="Scheduled start" value={formatDateTime(session.scheduledStart)} />
          <InfoTile label="Actual start" value={formatDateTime(session.actualStart)} />
          <InfoTile label="Actual end" value={formatDateTime(session.actualEnd)} />
          <InfoTile label="Duration" value={formatDuration(session.duration)} />
        </div>

        {/* Analytics */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="mb-4 flex items-center justify-between">
            <div className="text-[15px] font-semibold tracking-[-0.015em]">Analytics</div>
            {!analytics && (
              <span className="text-xs text-[#737373]">No analytics recorded for this session</span>
            )}
          </div>
          {analytics ? (
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
              <MetricTile label="Peak viewers" value={analytics.viewersPeak.toLocaleString()} />
              <MetricTile label="Avg viewers" value={analytics.viewersAvg.toLocaleString()} />
              <MetricTile label="Total likes" value={analytics.totalLikes.toLocaleString()} />
              <MetricTile label="Total comments" value={analytics.totalComments.toLocaleString()} />
              <MetricTile label="Total shares" value={analytics.totalShares.toLocaleString()} />
              <MetricTile
                label="Engagement rate"
                value={`${Number(analytics.engagementRate ?? 0).toFixed(2)}%`}
              />
              <MetricTile
                label="Gifts value"
                value={`$${Number(analytics.giftsValue ?? 0).toFixed(2)}`}
              />
              <MetricTile
                label="Logged duration"
                value={formatDuration(analytics.durationMinutes)}
              />
            </div>
          ) : (
            <div className="py-6 text-sm text-[#737373]">
              Analytics appear once the session ends and stats are imported.
            </div>
          )}
        </div>

        {/* Commission (PIC-only) */}
        {canSeeCommission && <CommissionPanel session={session} />}

        {/* Attachments */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="mb-4 flex items-center justify-between">
            <div className="text-[15px] font-semibold tracking-[-0.015em]">
              Attachments
              <span className="ml-2 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[11px] text-[#737373]">
                {attachments.length}
              </span>
            </div>
          </div>
          {attachments.length === 0 ? (
            <div className="py-6 text-sm text-[#737373]">
              No attachments have been uploaded for this session.
            </div>
          ) : (
            <div className="flex flex-col gap-3">
              {attachments.map((attachment) => (
                <div
                  key={attachment.id}
                  className="flex items-center gap-4 rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] p-4"
                >
                  <div className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-white text-[#404040]">
                    {attachment.isImage ? (
                      <ImageIcon className="h-5 w-5" strokeWidth={1.8} />
                    ) : attachment.isVideo ? (
                      <Video className="h-5 w-5" strokeWidth={1.8} />
                    ) : (
                      <FileText className="h-5 w-5" strokeWidth={1.8} />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                      {attachment.fileName}
                    </div>
                    <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[#737373]">
                      <span>{attachment.fileSizeFormatted}</span>
                      <span>·</span>
                      <span>{formatDateTime(attachment.createdAt)}</span>
                      {attachment.uploaderName && (
                        <>
                          <span>·</span>
                          <span>Uploaded by {attachment.uploaderName}</span>
                        </>
                      )}
                    </div>
                    {attachment.description && (
                      <p className="mt-1 text-[12.5px] text-[#404040]">{attachment.description}</p>
                    )}
                  </div>
                  <a
                    href={attachment.fileUrl}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="inline-flex items-center gap-1.5 rounded-md border border-[#EAEAEA] bg-white px-3 py-1.5 text-xs font-medium text-[#0A0A0A] hover:bg-[#F5F5F5]"
                  >
                    <Download className="h-3.5 w-3.5" strokeWidth={2} />
                    Download
                  </a>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

SessionsShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function InfoTile({ label, value }) {
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="mt-2 truncate text-lg font-semibold tracking-[-0.015em]">{value}</div>
    </div>
  );
}

function MetricTile({ label, value }) {
  return (
    <div className="rounded-[12px] bg-[#FAFAFA] p-4">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="mt-1.5 text-xl font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">
        {value}
      </div>
    </div>
  );
}

/**
 * PIC-only commission surface. Reads the commission_snapshot_json if present,
 * otherwise derives a live preview of net GMV so the PIC can still see the
 * numbers before they verify the session and lock the snapshot.
 */
function CommissionPanel({ session }) {
  const [showAddForm, setShowAddForm] = useState(false);

  const adjustments = session.gmv_adjustments ?? [];
  const gmvAmount = Number(session.gmv_amount ?? 0);
  const gmvAdjustment = Number(session.gmv_adjustment ?? 0);
  const netGmv = Number(
    session.net_gmv ?? session.commission_snapshot_json?.net_gmv ?? gmvAmount + gmvAdjustment,
  );
  const snapshot = session.commission_snapshot_json;
  const locked = Boolean(session.payroll_locked);

  const addForm = useForm({ amount: '', reason: '' });

  const submitAdjustment = (event) => {
    event.preventDefault();
    addForm.post(`/livehost/sessions/${session.id}/adjustments`, {
      preserveScroll: true,
      onSuccess: () => {
        addForm.reset();
        setShowAddForm(false);
      },
    });
  };

  const deleteAdjustment = (adjustmentId) => {
    if (!window.confirm('Delete this GMV adjustment?')) {
      return;
    }
    router.delete(
      `/livehost/sessions/${session.id}/adjustments/${adjustmentId}`,
      { preserveScroll: true },
    );
  };

  const snapshotJson = useMemo(() => {
    if (!snapshot) {
      return null;
    }
    try {
      return JSON.stringify(snapshot, null, 2);
    } catch {
      return String(snapshot);
    }
  }, [snapshot]);

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="mb-4 flex items-center justify-between">
        <div className="text-[15px] font-semibold tracking-[-0.015em]">Commission</div>
        {locked && (
          <span
            title="Payroll locked"
            className="inline-flex items-center gap-1 rounded-full bg-[#FEF3C7] px-2 py-0.5 text-[11px] font-medium text-[#92400E]"
          >
            <Lock className="h-3 w-3" />
            Payroll locked
          </span>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div className="rounded-[10px] bg-[#FAFAFA] p-4">
          <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">
            GMV (RM)
          </div>
          <div className="mt-1 flex items-baseline gap-2">
            <div className="text-xl font-semibold tabular-nums tracking-[-0.02em]">
              {formatMyr(gmvAmount)}
            </div>
            {session.gmv_locked_at && (
              <div className="text-[11px] text-[#737373]">
                locked at {formatDateTime(session.gmv_locked_at)}
                {session.verifiedByName ? ` by ${session.verifiedByName}` : ''}
              </div>
            )}
          </div>
        </div>
        <div className="rounded-[10px] bg-[#FAFAFA] p-4">
          <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">
            Creator identity
          </div>
          <div className="mt-1 text-sm font-medium text-[#0A0A0A]">
            {session.creator_handle ?? '—'}
            {session.creator_platform_user_id && (
              <span className="ml-2 text-[12px] text-[#737373]">
                (ID {session.creator_platform_user_id})
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Adjustments list */}
      <div className="mt-5">
        <div className="mb-2 flex items-center justify-between">
          <div className="text-[12px] font-semibold uppercase tracking-wide text-[#737373]">
            GMV Adjustments
          </div>
          <button
            type="button"
            disabled={locked}
            onClick={() => setShowAddForm((v) => !v)}
            title={locked ? 'Payroll locked' : undefined}
            className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1 text-[11.5px] font-medium text-[#0A0A0A] hover:bg-[#F5F5F5] disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Plus className="h-3 w-3" />
            Add adjustment
          </button>
        </div>

        {adjustments.length === 0 ? (
          <div className="rounded-[10px] border border-dashed border-[#EAEAEA] bg-white px-4 py-3 text-[12.5px] text-[#737373]">
            No adjustments recorded.
          </div>
        ) : (
          <ul className="flex flex-col gap-1.5">
            {adjustments.map((adj) => (
              <li
                key={adj.id}
                className="flex items-center justify-between gap-3 rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] px-3 py-2 text-[13px]"
              >
                <div className="min-w-0 flex-1">
                  <span
                    className={`tabular-nums font-semibold ${
                      Number(adj.amount_myr) < 0 ? 'text-[#B91C1C]' : 'text-[#047857]'
                    }`}
                  >
                    {Number(adj.amount_myr) < 0 ? '−' : '+'} RM {formatMyr(Math.abs(Number(adj.amount_myr)))}
                  </span>
                  <span className="ml-2 text-[#404040]">· {adj.reason}</span>
                  <span className="ml-2 text-[11px] text-[#737373]">
                    · {formatDateTime(adj.adjusted_at)}
                    {adj.adjusted_by_name ? ` · ${adj.adjusted_by_name}` : ''}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={() => deleteAdjustment(adj.id)}
                  disabled={locked}
                  title={locked ? 'Payroll locked' : 'Delete adjustment'}
                  className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] text-[#B91C1C] hover:bg-[#FEE2E2] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Trash2 className="h-3 w-3" />
                  Delete
                </button>
              </li>
            ))}
          </ul>
        )}

        {showAddForm && !locked && (
          <form
            onSubmit={submitAdjustment}
            className="mt-3 flex flex-col gap-2 rounded-[10px] border border-[#EAEAEA] bg-white p-3 md:flex-row md:items-end"
          >
            <div className="flex flex-col gap-1 md:w-40">
              <label className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                Amount (RM)
              </label>
              <input
                type="number"
                step="0.01"
                value={addForm.data.amount}
                onChange={(e) => addForm.setData('amount', e.target.value)}
                placeholder="e.g. -120"
                className="rounded-md border border-[#EAEAEA] bg-white px-2 py-1.5 text-sm focus:border-[#0A0A0A] focus:outline-none"
              />
              {addForm.errors.amount && (
                <span className="text-[11px] text-[#B91C1C]">{addForm.errors.amount}</span>
              )}
            </div>
            <div className="flex flex-1 flex-col gap-1">
              <label className="text-[11px] font-medium uppercase tracking-wide text-[#737373]">
                Reason
              </label>
              <input
                type="text"
                value={addForm.data.reason}
                onChange={(e) => addForm.setData('reason', e.target.value)}
                placeholder="e.g. Order ABC refunded"
                className="rounded-md border border-[#EAEAEA] bg-white px-2 py-1.5 text-sm focus:border-[#0A0A0A] focus:outline-none"
              />
              {addForm.errors.reason && (
                <span className="text-[11px] text-[#B91C1C]">{addForm.errors.reason}</span>
              )}
            </div>
            <div className="flex gap-2">
              <Button type="submit" size="sm" disabled={addForm.processing}>
                Save
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  addForm.reset();
                  addForm.clearErrors();
                  setShowAddForm(false);
                }}
              >
                Cancel
              </Button>
            </div>
          </form>
        )}
      </div>

      {/* Totals / breakdown */}
      <div className="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
        <MetricTile
          label="Total adjustment"
          value={`RM ${formatMyr(gmvAdjustment)}`}
        />
        <MetricTile label="Net GMV" value={`RM ${formatMyr(netGmv)}`} />
        <MetricTile
          label="Session total"
          value={`RM ${formatMyr(snapshot?.session_total ?? 0)}`}
        />
      </div>

      {snapshot ? (
        <div className="mt-5 rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] p-4 text-[13px]">
          <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[#737373]">
            Commission breakdown (snapshot)
          </div>
          <div className="tabular-nums text-[#0A0A0A]">
            RM {formatMyr(snapshot.net_gmv)} × {Number(snapshot.platform_rate_percent ?? 0).toFixed(2)}% = RM {formatMyr(snapshot.gmv_commission)}
          </div>
          <div className="tabular-nums text-[#0A0A0A]">
            + Per-live rate RM {formatMyr(snapshot.per_live_rate)}
          </div>
          <div className="mt-1 border-t border-[#EAEAEA] pt-1 font-semibold tabular-nums text-[#0A0A0A]">
            = Total RM {formatMyr(snapshot.session_total)}
          </div>
          {Array.isArray(snapshot.warnings) && snapshot.warnings.length > 0 && (
            <div className="mt-2 text-[11.5px] text-[#B45309]">
              Warnings: {snapshot.warnings.join(', ')}
            </div>
          )}
        </div>
      ) : (
        <div className="mt-5 rounded-[10px] border border-dashed border-[#EAEAEA] bg-white px-4 py-3 text-[12.5px] text-[#737373]">
          Commission snapshot will be captured when this session is verified.
        </div>
      )}

      {snapshotJson && (
        <details className="mt-4 text-[12px] text-[#404040]">
          <summary className="cursor-pointer select-none text-[#737373] hover:text-[#0A0A0A]">
            View snapshot JSON
          </summary>
          <pre className="mt-2 overflow-x-auto rounded-md bg-[#0A0A0A] p-3 text-[11.5px] leading-relaxed text-[#E5E5E5]">
            {snapshotJson}
          </pre>
        </details>
      )}
    </div>
  );
}
