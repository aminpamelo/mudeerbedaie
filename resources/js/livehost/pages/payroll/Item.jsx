import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Wallet } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import PayrollBreakdownBody from '@/livehost/components/payroll/PayrollBreakdown';

const STATUS_STYLES = {
  draft: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  locked: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
};
const STATUS_LABELS = { draft: 'Draft', locked: 'Locked', paid: 'Paid' };

function rm(value) {
  const n = Number(value ?? 0);
  if (!Number.isFinite(n)) {
    return '—';
  }
  return `RM ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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
  const sM = start.toLocaleString(undefined, { month: 'short' });
  const eM = end.toLocaleString(undefined, { month: 'short' });
  const year = end.getFullYear();
  return start.getFullYear() === end.getFullYear() && sM === eM
    ? `${sM} ${start.getDate()} – ${end.getDate()}, ${year}`
    : `${sM} ${start.getDate()} – ${eM} ${end.getDate()}, ${year}`;
}

function initialsOf(name) {
  return (name ?? '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('') || '?';
}

function Tile({ label, value, strong = false, accent }) {
  const tone = accent === 'primary' ? 'text-[#059669]' : accent === 'warn' ? 'text-[#B45309]' : 'text-[#0A0A0A]';
  return (
    <div className={`rounded-[14px] border border-[#EAEAEA] bg-white p-4 ${strong ? 'ring-1 ring-[#A7F3D0]' : ''}`}>
      <div className="text-[10.5px] font-medium uppercase tracking-[0.04em] text-[#737373]">{label}</div>
      <div className={`mt-1.5 text-[18px] font-semibold tabular-nums tracking-[-0.02em] ${tone}`}>{value}</div>
    </div>
  );
}

export default function PayrollItem() {
  const { run, item } = usePage().props;
  const statusCls = STATUS_STYLES[run.status] ?? STATUS_STYLES.draft;

  return (
    <>
      <Head title={`${item.host_name ?? 'Host'} · Payroll Run #${run.id}`} />
      <TopBar breadcrumb={['Live Host Desk', 'Payroll', `Run #${run.id}`, item.host_name ?? 'Host']} />

      <div className="space-y-6 p-4 sm:p-6 lg:p-8">
        <Link href={`/livehost/payroll/${run.id}`} className="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#737373] hover:text-[#0A0A0A]">
          <ArrowLeft className="h-4 w-4" strokeWidth={2} /> Back to Run #{run.id}
        </Link>

        {/* Host header */}
        <div className="flex flex-wrap items-start justify-between gap-4 rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-center gap-3.5">
            <span className="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-[#10B981] to-[#059669] text-sm font-semibold text-white">
              {initialsOf(item.host_name)}
            </span>
            <div>
              <h1 className="text-xl font-semibold tracking-[-0.02em] text-[#0A0A0A] sm:text-2xl">{item.host_name ?? 'Unknown host'}</h1>
              <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12.5px] text-[#737373]">
                {item.host_email && <span>{item.host_email}</span>}
                <span className="text-[#D4D4D4]">·</span>
                <span>Run #{run.id} · {formatPeriod(run.period_start, run.period_end)}</span>
                <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${statusCls}`}>
                  {STATUS_LABELS[run.status] ?? run.status}
                </span>
              </div>
            </div>
          </div>
          <div className="text-right">
            <div className="flex items-center justify-end gap-1.5 text-[11px] font-medium uppercase tracking-[0.04em] text-[#737373]">
              <Wallet className="h-3.5 w-3.5" strokeWidth={2} /> Net payout
            </div>
            <div className="mt-0.5 text-2xl font-semibold tabular-nums tracking-[-0.02em] text-[#059669]">{rm(item.net_payout_myr)}</div>
          </div>
        </div>

        {/* Payout components */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <Tile label="Base salary" value={rm(item.base_salary_myr)} />
          <Tile label={`Per-live (${item.sessions_count} sessions)`} value={rm(item.total_per_live_myr)} />
          <Tile label="Net GMV" value={rm(item.net_gmv_myr)} />
          <Tile label="GMV commission" value={rm(item.gmv_commission_myr)} accent="primary" />
          <Tile label="Override L1" value={rm(item.override_l1_myr)} />
          <Tile label="Override L2" value={rm(item.override_l2_myr)} />
          <Tile label="Gross total" value={rm(item.gross_total_myr)} />
          <Tile label="Deductions" value={rm(item.deductions_myr)} accent="warn" />
        </div>

        {/* Session detail + overrides (shared with the inline expand) */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <PayrollBreakdownBody item={item} />
        </div>
      </div>
    </>
  );
}

PayrollItem.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
