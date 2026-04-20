import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, Check, Loader2, X } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const STATUS_STYLES = {
  pending: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  processing: 'bg-[#DBEAFE] text-[#1E40AF] border-[#BFDBFE]',
  completed: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  failed: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
};

const STATUS_LABELS = {
  pending: 'Pending',
  processing: 'Processing',
  completed: 'Completed',
  failed: 'Failed',
};

const TYPE_LABELS = {
  live_analysis: 'Live Analysis',
  order_list: 'Order List',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.pending;
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[12px] font-medium ${cls}`}
    >
      {STATUS_LABELS[status] ?? status}
    </span>
  );
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
    hour: 'numeric',
    minute: '2-digit',
  });
}

function formatDuration(seconds) {
  const n = Number(seconds ?? 0);
  if (!Number.isFinite(n) || n <= 0) {
    return '—';
  }
  const hours = Math.floor(n / 3600);
  const mins = Math.floor((n % 3600) / 60);
  if (hours > 0) {
    return `${hours}h ${mins}m`;
  }
  return `${mins}m`;
}

function TabBar({ tabs, active, onChange }) {
  return (
    <div className="flex items-center gap-1 border-b border-[#EAEAEA]">
      {tabs.map((tab) => {
        const isActive = tab.key === active;
        return (
          <button
            key={tab.key}
            type="button"
            onClick={() => onChange(tab.key)}
            className={[
              '-mb-px border-b-2 px-4 py-2.5 text-[13px] font-medium transition-colors',
              isActive
                ? 'border-[#0A0A0A] text-[#0A0A0A]'
                : 'border-transparent text-[#737373] hover:text-[#0A0A0A]',
            ].join(' ')}
          >
            {tab.label}
            {typeof tab.count === 'number' && (
              <span
                className={[
                  'ml-2 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums',
                  isActive ? 'bg-[#0A0A0A] text-white' : 'bg-[#F5F5F5] text-[#737373]',
                ].join(' ')}
              >
                {tab.count}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}

function LiveAnalysisView({ rows }) {
  const matched = useMemo(
    () => rows.filter((r) => r.matched_live_session_id !== null),
    [rows]
  );
  const unmatched = useMemo(
    () => rows.filter((r) => r.matched_live_session_id === null),
    [rows]
  );

  const [tab, setTab] = useState('matched');
  const [selected, setSelected] = useState(() => new Set());
  const [applying, setApplying] = useState(false);

  const toggle = (id) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const toggleAll = () => {
    if (selected.size === matched.length) {
      setSelected(new Set());
    } else {
      setSelected(new Set(matched.map((r) => r.id)));
    }
  };

  const apply = () => {
    if (selected.size === 0) {
      return;
    }
    if (!window.confirm(`Apply TikTok GMV to ${selected.size} selected session(s)?`)) {
      return;
    }
    setApplying(true);
    router.post(
      `/livehost/tiktok-imports/${matched[0]?.import_id ?? ''}/apply`,
      { report_ids: Array.from(selected) },
      {
        preserveScroll: true,
        onFinish: () => {
          setApplying(false);
          setSelected(new Set());
        },
      }
    );
  };

  return (
    <div className="space-y-4">
      <TabBar
        tabs={[
          { key: 'matched', label: 'Matched Reports', count: matched.length },
          { key: 'unmatched', label: 'Unmatched Reports', count: unmatched.length },
        ]}
        active={tab}
        onChange={setTab}
      />

      {tab === 'matched' ? (
        <MatchedReportsTable
          rows={matched}
          selected={selected}
          toggle={toggle}
          toggleAll={toggleAll}
          onApply={apply}
          applying={applying}
        />
      ) : (
        <UnmatchedReportsTable rows={unmatched} />
      )}
    </div>
  );
}

function MatchedReportsTable({ rows, selected, toggle, toggleAll, onApply, applying }) {
  const importId = rows[0]?.import_id;

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {rows.length === 0 ? (
        <div className="py-16 text-center text-sm text-[#737373]">
          No matched reports in this import.
        </div>
      ) : (
        <>
          <div className="overflow-auto">
            <table className="w-full min-w-[1100px] text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-3 py-3 text-left">
                    <input
                      type="checkbox"
                      checked={selected.size > 0 && selected.size === rows.length}
                      onChange={toggleAll}
                      aria-label="Select all"
                      className="h-4 w-4 rounded border-[#D4D4D4]"
                    />
                  </th>
                  <th className="px-3 py-3 text-left">Creator</th>
                  <th className="px-3 py-3 text-left">Launched</th>
                  <th className="px-3 py-3 text-right">Duration</th>
                  <th className="px-3 py-3 text-right">TikTok GMV</th>
                  <th className="px-3 py-3 text-right">Current GMV</th>
                  <th className="px-3 py-3 text-right">Variance</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => {
                  const tiktokGmv = Number(row.gmv_myr ?? 0);
                  const currentGmv = Number(row.matched_session?.gmv_amount ?? 0);
                  const variance = tiktokGmv - currentGmv;
                  const variancePct = currentGmv === 0
                    ? null
                    : (variance / currentGmv) * 100;
                  return (
                    <tr
                      key={row.id}
                      className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                    >
                      <td className="px-3 py-3">
                        <input
                          type="checkbox"
                          checked={selected.has(row.id)}
                          onChange={() => toggle(row.id)}
                          aria-label={`Select report ${row.id}`}
                          className="h-4 w-4 rounded border-[#D4D4D4]"
                        />
                      </td>
                      <td className="px-3 py-3">
                        <div className="text-[13px] font-medium text-[#0A0A0A]">
                          {row.creator_display_name ?? row.creator_nickname ?? '—'}
                        </div>
                        <div className="font-mono text-[11px] text-[#737373]">
                          {row.tiktok_creator_id}
                        </div>
                      </td>
                      <td className="px-3 py-3 text-[12.5px] tabular-nums text-[#404040]">
                        {formatDateTime(row.launched_time)}
                      </td>
                      <td className="px-3 py-3 text-right tabular-nums text-[12.5px]">
                        {formatDuration(row.duration_seconds)}
                      </td>
                      <td className="px-3 py-3 text-right tabular-nums text-[13px] text-[#0A0A0A]">
                        RM {formatMyr(tiktokGmv)}
                      </td>
                      <td className="px-3 py-3 text-right tabular-nums text-[13px] text-[#404040]">
                        RM {formatMyr(currentGmv)}
                      </td>
                      <td className="px-3 py-3 text-right tabular-nums text-[13px]">
                        <span className={variance === 0 ? 'text-[#737373]' : variance > 0 ? 'text-[#047857]' : 'text-[#B91C1C]'}>
                          {variance >= 0 ? '+' : ''}RM {formatMyr(Math.abs(variance))}
                        </span>
                        {variancePct !== null && (
                          <span className="ml-1 text-[11px] text-[#737373]">
                            ({variancePct >= 0 ? '+' : ''}
                            {variancePct.toFixed(1)}%)
                          </span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between border-t border-[#EAEAEA] bg-[#FAFAFA] px-5 py-3">
            <div className="text-[12.5px] text-[#737373]">
              {selected.size > 0
                ? `${selected.size} selected`
                : 'Select rows to apply TikTok GMV'}
            </div>
            <Button
              size="sm"
              disabled={selected.size === 0 || applying || !importId}
              onClick={onApply}
              className="h-9 gap-1.5 rounded-lg bg-[#0A0A0A] text-white hover:bg-[#262626] disabled:bg-[#D4D4D4]"
            >
              {applying && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
              Apply selected
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

function UnmatchedReportsTable({ rows }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {rows.length === 0 ? (
        <div className="py-16 text-center text-sm text-[#737373]">
          All reports matched a live session — nothing left to reconcile.
        </div>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
              <th className="px-5 py-3 text-left">Creator ID</th>
              <th className="px-5 py-3 text-left">Nickname</th>
              <th className="px-5 py-3 text-left">Launched</th>
              <th className="px-5 py-3 text-right">GMV</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr
                key={row.id}
                className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
              >
                <td className="px-5 py-3.5 font-mono text-[12px] text-[#0A0A0A]">
                  {row.tiktok_creator_id}
                </td>
                <td className="px-5 py-3.5 text-[13px] text-[#404040]">
                  {row.creator_display_name ?? row.creator_nickname ?? '—'}
                </td>
                <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                  {formatDateTime(row.launched_time)}
                </td>
                <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#0A0A0A]">
                  RM {formatMyr(row.gmv_myr)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function OrderListView({ rows, adjustments }) {
  const matched = useMemo(
    () => rows.filter((r) => r.matched_live_session_id !== null),
    [rows]
  );
  const unmatched = useMemo(
    () => rows.filter((r) => r.matched_live_session_id === null),
    [rows]
  );
  const proposed = useMemo(
    () => adjustments.filter((a) => a.status === 'proposed'),
    [adjustments]
  );

  const [tab, setTab] = useState('proposed');

  return (
    <div className="space-y-4">
      <TabBar
        tabs={[
          { key: 'proposed', label: 'Proposed Adjustments', count: proposed.length },
          { key: 'orders', label: 'Orders', count: rows.length },
          { key: 'unmatched', label: 'Unmatched Orders', count: unmatched.length },
        ]}
        active={tab}
        onChange={setTab}
      />

      {tab === 'proposed' && <ProposedAdjustmentsTable rows={proposed} />}
      {tab === 'orders' && <OrdersTable rows={rows} />}
      {tab === 'unmatched' && <OrdersTable rows={unmatched} />}
      {tab === 'orders' && matched.length > 0 && (
        <p className="text-[12px] text-[#737373]">
          {matched.length} of {rows.length} orders are attached to a live session.
        </p>
      )}
    </div>
  );
}

function ProposedAdjustmentsTable({ rows }) {
  const [pendingId, setPendingId] = useState(null);

  const action = (adj, kind) => {
    if (!adj.live_session_id) {
      return;
    }
    const verb = kind === 'approve' ? 'Approve' : 'Reject';
    if (!window.confirm(`${verb} this GMV adjustment?`)) {
      return;
    }
    setPendingId(`${adj.id}:${kind}`);
    router.post(
      `/livehost/sessions/${adj.live_session_id}/adjustments/${adj.id}/${kind}`,
      {},
      {
        preserveScroll: true,
        onFinish: () => setPendingId(null),
      }
    );
  };

  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {rows.length === 0 ? (
        <div className="py-16 text-center text-sm text-[#737373]">
          No proposed adjustments — refund reconciler found no actionable rows.
        </div>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
              <th className="px-5 py-3 text-left">Session</th>
              <th className="px-5 py-3 text-left">Reason</th>
              <th className="px-5 py-3 text-right">Refund</th>
              <th className="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const approveKey = `${row.id}:approve`;
              const rejectKey = `${row.id}:reject`;
              return (
                <tr
                  key={row.id}
                  className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                >
                  <td className="px-5 py-3.5">
                    <div className="text-[13px] font-medium text-[#0A0A0A]">
                      {row.session?.host_name ?? 'Unknown host'}
                    </div>
                    <div className="text-[11.5px] text-[#737373]">
                      {formatDateTime(row.session?.actual_start_at)}
                    </div>
                  </td>
                  <td className="px-5 py-3.5 text-[12.5px] text-[#404040]">
                    {row.reason}
                  </td>
                  <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#B91C1C]">
                    RM {formatMyr(row.amount_myr)}
                  </td>
                  <td className="px-5 py-3.5 text-right">
                    <div className="inline-flex items-center gap-1.5">
                      <Button
                        size="sm"
                        disabled={pendingId !== null}
                        onClick={() => action(row, 'approve')}
                        className="h-8 gap-1.5 rounded-md bg-[#10B981] text-white hover:bg-[#059669] disabled:bg-[#D4D4D4]"
                      >
                        {pendingId === approveKey ? (
                          <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                          <Check className="h-3 w-3" strokeWidth={2.5} />
                        )}
                        Approve
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={pendingId !== null}
                        onClick={() => action(row, 'reject')}
                        className="h-8 gap-1.5 rounded-md border-[#EAEAEA] bg-white text-[#404040] hover:bg-[#F5F5F5]"
                      >
                        {pendingId === rejectKey ? (
                          <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                          <X className="h-3 w-3" strokeWidth={2.5} />
                        )}
                        Reject
                      </Button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </div>
  );
}

function OrdersTable({ rows }) {
  return (
    <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      {rows.length === 0 ? (
        <div className="py-16 text-center text-sm text-[#737373]">
          No orders to display.
        </div>
      ) : (
        <div className="overflow-auto">
          <table className="w-full min-w-[900px] text-sm">
            <thead>
              <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                <th className="px-3 py-3 text-left">Order ID</th>
                <th className="px-3 py-3 text-left">Status</th>
                <th className="px-3 py-3 text-left">Created</th>
                <th className="px-3 py-3 text-right">Amount</th>
                <th className="px-3 py-3 text-right">Refund</th>
                <th className="px-3 py-3 text-left">Matched Session</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={row.id}
                  className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                >
                  <td className="px-3 py-3 font-mono text-[12px] text-[#0A0A0A]">
                    {row.tiktok_order_id}
                  </td>
                  <td className="px-3 py-3 text-[12.5px] text-[#404040]">
                    {row.order_status ?? '—'}
                    {row.order_substatus && (
                      <span className="ml-1 text-[11px] text-[#737373]">
                        ({row.order_substatus})
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-3 text-[12.5px] tabular-nums text-[#404040]">
                    {formatDateTime(row.created_time)}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums text-[13px] text-[#0A0A0A]">
                    RM {formatMyr(row.order_amount_myr)}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums text-[13px]">
                    {Number(row.order_refund_amount_myr) > 0 ? (
                      <span className="text-[#B91C1C]">
                        RM {formatMyr(row.order_refund_amount_myr)}
                      </span>
                    ) : (
                      <span className="text-[#737373]">—</span>
                    )}
                  </td>
                  <td className="px-3 py-3 text-[12.5px] text-[#404040]">
                    {row.matched_session ? (
                      <>
                        <span className="text-[#0A0A0A]">
                          {row.matched_session.host_name ?? 'Unknown'}
                        </span>
                        <span className="ml-1 text-[11px] text-[#737373]">
                          #{row.matched_session.id}
                        </span>
                      </>
                    ) : (
                      <span className="text-[#737373]">Unmatched</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default function TiktokImportShow() {
  const { import: imp, rows, adjustments = [], flash } = usePage().props;

  return (
    <>
      <Head title={`TikTok Import #${imp.id}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'TikTok Imports', `Import #${imp.id}`]}
        actions={
          <Link href="/livehost/tiktok-imports">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-8">
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

        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex flex-wrap items-start justify-between gap-5">
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.02em] text-[#0A0A0A]">
                  {TYPE_LABELS[imp.report_type] ?? imp.report_type}
                </h1>
                <StatusBadge status={imp.status} />
              </div>
              <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-[12.5px] text-[#737373]">
                <span>Import #{imp.id}</span>
                <span>{imp.file_name}</span>
                <span>
                  {imp.period_start} – {imp.period_end}
                </span>
                <span>
                  Uploaded {formatDateTime(imp.uploaded_at)}
                  {imp.uploaded_by?.name ? ` by ${imp.uploaded_by.name}` : ''}
                </span>
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-4 text-[12.5px]">
              <div>
                <div className="text-[11px] font-medium uppercase tracking-[0.02em] text-[#737373]">
                  Rows
                </div>
                <div className="mt-1 text-xl font-semibold tabular-nums text-[#0A0A0A]">
                  {imp.total_rows ?? 0}
                </div>
              </div>
              {imp.report_type === 'live_analysis' && (
                <>
                  <div>
                    <div className="text-[11px] font-medium uppercase tracking-[0.02em] text-[#737373]">
                      Matched
                    </div>
                    <div className="mt-1 text-xl font-semibold tabular-nums text-[#047857]">
                      {imp.matched_rows ?? 0}
                    </div>
                  </div>
                  <div>
                    <div className="text-[11px] font-medium uppercase tracking-[0.02em] text-[#737373]">
                      Unmatched
                    </div>
                    <div className="mt-1 text-xl font-semibold tabular-nums text-[#B45309]">
                      {imp.unmatched_rows ?? 0}
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>

          {imp.status === 'failed' && imp.error_log_json?.message && (
            <div className="mt-4 rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
              <div className="font-medium">Processing failed</div>
              <div className="mt-0.5 text-[12.5px]">{imp.error_log_json.message}</div>
            </div>
          )}
        </div>

        {imp.report_type === 'live_analysis' ? (
          <LiveAnalysisView
            rows={rows.map((r) => ({ ...r, import_id: imp.id }))}
          />
        ) : (
          <OrderListView rows={rows} adjustments={adjustments} />
        )}
      </div>
    </>
  );
}

TiktokImportShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
