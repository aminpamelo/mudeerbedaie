import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ChevronDown, ChevronRight, Download, ExternalLink, Loader2, Lock, RefreshCw, Wallet } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import PayrollBreakdownBody from '@/livehost/components/payroll/PayrollBreakdown';

const STATUS_STYLES = {
  draft: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  locked: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
};

const STATUS_LABELS = {
  draft: 'Draft',
  locked: 'Locked',
  paid: 'Paid',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.draft;
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

function formatPeriod(startIso, endIso) {
  if (!startIso || !endIso) {
    return '—';
  }
  const start = new Date(`${startIso}T00:00:00`);
  const end = new Date(`${endIso}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return `${startIso} – ${endIso}`;
  }

  const startMonth = start.toLocaleString(undefined, { month: 'short' });
  const endMonth = end.toLocaleString(undefined, { month: 'short' });
  const year = end.getFullYear();

  if (start.getFullYear() === end.getFullYear() && startMonth === endMonth) {
    return `${startMonth} ${start.getDate()} – ${end.getDate()}, ${year}`;
  }

  return `${startMonth} ${start.getDate()} – ${endMonth} ${end.getDate()}, ${year}`;
}

function formatDate(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(`${iso}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function SummaryCard({ label, value, accent = 'default' }) {
  const accentCls =
    accent === 'primary'
      ? 'text-[#10B981]'
      : accent === 'warn'
      ? 'text-[#B45309]'
      : 'text-[#0A0A0A]';

  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="text-[11.5px] font-medium uppercase tracking-[0.02em] text-[#737373]">
        {label}
      </div>
      <div className={`mt-1.5 text-2xl font-semibold tabular-nums tracking-[-0.02em] ${accentCls}`}>
        {value}
      </div>
    </div>
  );
}

function ItemRow({ item, runId, expanded, onToggle }) {
  return (
    <>
      <tr
        onClick={onToggle}
        className="cursor-pointer border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
      >
        <td className="px-3 py-3.5">
          {expanded ? (
            <ChevronDown className="h-3.5 w-3.5 text-[#737373]" strokeWidth={2} />
          ) : (
            <ChevronRight className="h-3.5 w-3.5 text-[#737373]" strokeWidth={2} />
          )}
        </td>
        <td className="px-3 py-3.5">
          <Link
            href={`/livehost/payroll/${runId}/items/${item.id}`}
            onClick={(e) => e.stopPropagation()}
            className="group inline-flex items-center gap-1 font-medium text-[#0A0A0A] hover:text-[#4338CA]"
            title="Open full detail page"
          >
            {item.host_name ?? 'Unknown host'}
            <ExternalLink className="h-3 w-3 text-[#C4C4C4] group-hover:text-[#4338CA]" strokeWidth={2} />
          </Link>
          {item.host_email && (
            <div className="text-[11.5px] text-[#737373]">{item.host_email}</div>
          )}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.base_salary_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {item.sessions_count}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.total_per_live_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.total_gmv_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.total_gmv_adjustment_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.net_gmv_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.gmv_commission_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.override_l1_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.override_l2_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.gross_total_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px]">
          {formatMyr(item.deductions_myr)}
        </td>
        <td className="px-3 py-3.5 text-right tabular-nums text-[13px] font-semibold text-[#0A0A0A]">
          {formatMyr(item.net_payout_myr)}
        </td>
      </tr>
      {expanded && <ItemBreakdown item={item} />}
    </>
  );
}

function ItemBreakdown({ item }) {
  return (
    <tr className="border-t border-[#F0F0F0] bg-[#FAFAFA]">
      <td colSpan={14} className="px-6 py-5">
        <PayrollBreakdownBody item={item} />
      </td>
    </tr>
  );
}

const COMMISSION_SEGMENTS = [
  { value: 'all', label: 'All' },
  { value: 'with', label: 'With Commission' },
  { value: 'without', label: 'No Commission' },
];

function CommissionFilterToggle({ value, onChange, counts }) {
  return (
    <div className="inline-flex items-center rounded-[10px] border border-[#EAEAEA] bg-[#F5F5F5] p-0.5 text-[12.5px]">
      {COMMISSION_SEGMENTS.map((seg) => {
        const active = value === seg.value;
        return (
          <button
            key={seg.value}
            type="button"
            onClick={() => onChange(seg.value)}
            className={`rounded-[8px] px-3 py-1.5 font-medium transition-colors ${
              active
                ? 'bg-white text-[#0A0A0A] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                : 'text-[#737373] hover:text-[#0A0A0A]'
            }`}
          >
            {seg.label}
            <span
              className={`ml-1.5 tabular-nums text-[11px] ${
                active ? 'text-[#737373]' : 'text-[#A3A3A3]'
              }`}
            >
              {counts[seg.value]}
            </span>
          </button>
        );
      })}
    </div>
  );
}

export default function PayrollShow() {
  const { run, flash } = usePage().props;
  const [expandedIds, setExpandedIds] = useState(() => new Set());
  const [actionPending, setActionPending] = useState(null);
  const [commissionFilter, setCommissionFilter] = useState('all');

  const toggleRow = (id) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const performAction = (path, confirmMessage) => {
    if (confirmMessage && !window.confirm(confirmMessage)) {
      return;
    }
    setActionPending(path);
    router.post(
      path,
      {},
      {
        preserveScroll: true,
        onFinish: () => setActionPending(null),
      }
    );
  };

  const totals = run.totals ?? {};
  const items = run.items ?? [];

  const hasCommission = (item) => Number(item.gmv_commission_myr ?? 0) > 0;
  const withCommissionCount = items.filter(hasCommission).length;
  const filterCounts = {
    all: items.length,
    with: withCommissionCount,
    without: items.length - withCommissionCount,
  };

  const filteredItems = items.filter((item) => {
    if (commissionFilter === 'with') {
      return hasCommission(item);
    }
    if (commissionFilter === 'without') {
      return !hasCommission(item);
    }
    return true;
  });

  const sumField = (field) =>
    filteredItems.reduce((acc, item) => acc + Number(item[field] ?? 0), 0);
  const displayTotals =
    commissionFilter === 'all'
      ? totals
      : {
          base_salary_myr: sumField('base_salary_myr'),
          total_per_live_myr: sumField('total_per_live_myr'),
          net_gmv_myr: sumField('net_gmv_myr'),
          gmv_commission_myr: sumField('gmv_commission_myr'),
          override_l1_myr: sumField('override_l1_myr'),
          override_l2_myr: sumField('override_l2_myr'),
          gross_total_myr: sumField('gross_total_myr'),
          deductions_myr: sumField('deductions_myr'),
          net_payout_myr: sumField('net_payout_myr'),
        };

  return (
    <>
      <Head title={`Payroll · ${formatPeriod(run.period_start, run.period_end)}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Payroll', `Run #${run.id}`]}
        actions={
          <Link href="/livehost/payroll">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
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

        {/* Header */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex flex-wrap items-start justify-between gap-5">
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-2xl font-semibold leading-[1.1] tracking-[-0.02em] text-[#0A0A0A]">
                  {formatPeriod(run.period_start, run.period_end)}
                </h1>
                <StatusBadge status={run.status} />
              </div>
              <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-[12.5px] text-[#737373]">
                <span>Run #{run.id}</span>
                <span>Cutoff: {formatDate(run.cutoff_date)}</span>
                {run.locked_at && (
                  <span>
                    Locked {formatDate(run.locked_at.slice(0, 10))}
                    {run.locked_by?.name ? ` by ${run.locked_by.name}` : ''}
                  </span>
                )}
                {run.paid_at && <span>Paid {formatDate(run.paid_at.slice(0, 10))}</span>}
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {run.status === 'draft' && (
                <>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={actionPending !== null}
                    onClick={() => performAction(`/livehost/payroll/${run.id}/recompute`)}
                    className="h-9 gap-1.5 rounded-lg border-[#EAEAEA] bg-white text-[#0A0A0A] hover:bg-[#F5F5F5] shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20"
                  >
                    {actionPending === `/livehost/payroll/${run.id}/recompute` ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <RefreshCw className="h-3.5 w-3.5" strokeWidth={2} />
                    )}
                    Recompute
                  </Button>
                  <Button
                    size="sm"
                    disabled={actionPending !== null}
                    onClick={() =>
                      performAction(
                        `/livehost/payroll/${run.id}/lock`,
                        'Lock this payroll run? Once locked, numbers are frozen and can no longer be recomputed.'
                      )
                    }
                    className="h-9 gap-1.5 rounded-lg bg-[#F59E0B] text-white hover:bg-[#D97706]"
                  >
                    {actionPending === `/livehost/payroll/${run.id}/lock` ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Lock className="h-3.5 w-3.5" strokeWidth={2} />
                    )}
                    Lock
                  </Button>
                </>
              )}

              {run.status === 'locked' && (
                <Button
                  size="sm"
                  disabled={actionPending !== null}
                  onClick={() =>
                    performAction(
                      `/livehost/payroll/${run.id}/mark-paid`,
                      'Mark this payroll run as paid? This confirms all hosts have been disbursed.'
                    )
                  }
                  className="h-9 gap-1.5 rounded-lg bg-[#10B981] text-white hover:bg-[#059669]"
                >
                  {actionPending === `/livehost/payroll/${run.id}/mark-paid` ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Wallet className="h-3.5 w-3.5" strokeWidth={2} />
                  )}
                  Mark Paid
                </Button>
              )}

              <a href={`/livehost/payroll/${run.id}/export`}>
                <Button
                  size="sm"
                  variant="outline"
                  className="h-9 gap-1.5 rounded-lg border-[#EAEAEA] bg-white text-[#0A0A0A] hover:bg-[#F5F5F5] shadow-none focus-visible:border-[#EAEAEA] focus-visible:ring-2 focus-visible:ring-[#10B981]/20"
                >
                  <Download className="h-3.5 w-3.5" strokeWidth={2} />
                  Export CSV
                </Button>
              </a>
            </div>
          </div>
        </div>

        {/* Summary cards */}
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <SummaryCard label="Total Hosts" value={items.length} />
          <SummaryCard label="Total Net GMV" value={formatMyr(totals.net_gmv_myr)} />
          <SummaryCard
            label="Total GMV Commission"
            value={formatMyr(totals.gmv_commission_myr)}
          />
          <SummaryCard
            label="Total Payout"
            value={formatMyr(totals.net_payout_myr)}
            accent="primary"
          />
        </div>

        {/* Items table */}
        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {items.length > 0 && (
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#F0F0F0] px-4 py-3">
              <div className="text-[13px] font-medium text-[#0A0A0A]">
                Hosts <span className="font-normal text-[#737373]">({filteredItems.length})</span>
              </div>
              <CommissionFilterToggle
                value={commissionFilter}
                onChange={setCommissionFilter}
                counts={filterCounts}
              />
            </div>
          )}
          {items.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">
              No payroll items generated for this run.
            </div>
          ) : filteredItems.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">
              No hosts match this filter.
            </div>
          ) : (
            <div className="overflow-auto">
            <table className="w-full min-w-[1400px] text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11px] font-medium text-[#737373]">
                  <th className="px-3 py-3 text-left"></th>
                  <th className="px-3 py-3 text-left">Host</th>
                  <th className="px-3 py-3 text-right">Base Salary</th>
                  <th className="px-3 py-3 text-right">Sessions</th>
                  <th className="px-3 py-3 text-right">Per-Live Total</th>
                  <th className="px-3 py-3 text-right">Gross GMV</th>
                  <th className="px-3 py-3 text-right">Adjustments</th>
                  <th className="px-3 py-3 text-right">Net GMV</th>
                  <th className="px-3 py-3 text-right">GMV Comm.</th>
                  <th className="px-3 py-3 text-right">Override L1</th>
                  <th className="px-3 py-3 text-right">Override L2</th>
                  <th className="px-3 py-3 text-right">Gross Total</th>
                  <th className="px-3 py-3 text-right">Deductions</th>
                  <th className="px-3 py-3 text-right text-[#0A0A0A]">Net Payout</th>
                </tr>
              </thead>
              <tbody>
                {filteredItems.map((item) => (
                  <ItemRow
                    key={item.id}
                    item={item}
                    runId={run.id}
                    expanded={expandedIds.has(item.id)}
                    onToggle={() => toggleRow(item.id)}
                  />
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t-2 border-[#E5E5E5] bg-[#FAFAFA] text-[12.5px] font-semibold text-[#0A0A0A]">
                  <td className="px-3 py-3.5"></td>
                  <td className="px-3 py-3.5">
                    {commissionFilter === 'all' ? 'Totals' : 'Filtered Totals'}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.base_salary_myr)}
                  </td>
                  <td className="px-3 py-3.5"></td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.total_per_live_myr)}
                  </td>
                  <td className="px-3 py-3.5"></td>
                  <td className="px-3 py-3.5"></td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.net_gmv_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.gmv_commission_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.override_l1_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.override_l2_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.gross_total_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums">
                    {formatMyr(displayTotals.deductions_myr)}
                  </td>
                  <td className="px-3 py-3.5 text-right tabular-nums text-[#10B981]">
                    {formatMyr(displayTotals.net_payout_myr)}
                  </td>
                </tr>
              </tfoot>
            </table>
            </div>
          )}
        </div>

        {items.length > 0 && (
          <p className="text-[12px] text-[#737373]">
            Tip: click any row to expand its per-session and per-downline breakdown.
          </p>
        )}
      </div>
    </>
  );
}

PayrollShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
