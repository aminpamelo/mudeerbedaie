import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import StackedBarChart from '@/livehost/components/reports/StackedBarChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';

const fmtPct = (n) => `${(Number(n) * 100).toFixed(1)}%`;
const fmtInt = (n) => Number(n).toLocaleString('en-MY');

function delta(current, prior) {
  if (prior == null || prior === 0) return null;
  return ((current - prior) / prior) * 100;
}

export default function CoverageReport({ kpis, weeklyTrend, accountRows, filters, filterOptions }) {
  const chartData = weeklyTrend.map((row) => ({
    date: row.weekStart,
    assigned: row.assigned,
    replaced: row.replaced,
    unassigned: row.unassigned,
    missed: row.missed,
  }));
  const chartSeries = [
    { key: 'assigned', name: 'Assigned', color: '#10b981' },
    { key: 'replaced', name: 'Replaced', color: '#3b82f6' },
    { key: 'unassigned', name: 'Unassigned', color: '#94a3b8' },
    { key: 'missed', name: 'Missed', color: '#ef4444' },
  ];

  return (
    <>
      <Head title="Schedule Coverage" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', 'Schedule Coverage']} />
      <div className="space-y-7 p-8" data-accent="sky">
        <header className="flex flex-wrap items-start justify-between gap-6">
          <div className="max-w-2xl">
            <Link
              href="/livehost/reports"
              className="label-eyebrow inline-flex items-center gap-1 transition hover:text-[var(--color-ink)]"
            >
              <ArrowLeft className="size-3" strokeWidth={2.4} /> Reports
            </Link>
            <h1 className="mt-3 text-[40px] leading-[1.05] tracking-tight text-[var(--color-ink)]">
              <span className="font-display">Schedule</span>{' '}
              <span className="font-display text-[var(--color-sky-ink)]">Coverage</span>
            </h1>
            <p className="mt-2 text-[14.5px] leading-relaxed text-[var(--color-muted)]">
              Slots filled vs unassigned, weekly trend, per-account breakdown.
            </p>
          </div>
          <ExportCsvButton
            exportPath="/livehost/reports/coverage/export"
            filters={filters}
          />
        </header>

        <ReportFilters
          filters={filters}
          options={filterOptions}
          basePath="/livehost/reports/coverage"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="% Filled"
            value={kpis.current.percentFilled}
            delta={delta(kpis.current.percentFilled, kpis.prior.percentFilled)}
            format={fmtPct}
          />
          <KpiCard
            label="Unassigned"
            value={kpis.current.unassignedCount}
            delta={delta(kpis.current.unassignedCount, kpis.prior.unassignedCount)}
            format={fmtInt}
          />
          <KpiCard
            label="Replaced"
            value={kpis.current.replacedCount}
            delta={delta(kpis.current.replacedCount, kpis.prior.replacedCount)}
            format={fmtInt}
          />
          <KpiCard
            label="No-show rate"
            value={kpis.current.noShowRate}
            delta={delta(kpis.current.noShowRate, kpis.prior.noShowRate)}
            format={fmtPct}
          />
        </div>

        <StackedBarChart
          title="Weekly slot coverage"
          data={chartData}
          series={chartSeries}
        />

        <AccountsTable rows={accountRows} filters={filters} />
      </div>
    </>
  );
}

CoverageReport.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function AccountsTable({ rows, filters }) {
  if (rows.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-[var(--color-border)] bg-[var(--color-surface)] p-14 text-center">
        <div className="font-display text-2xl text-[var(--color-ink-2)]">No slots scheduled</div>
        <p className="mt-2 text-sm text-[var(--color-muted)]">No scheduled slots in this date range.</p>
      </div>
    );
  }

  const slotsHref = (accountId) => {
    const params = new URLSearchParams({
      account: String(accountId),
      from: filters.dateFrom,
      to: filters.dateTo,
      unassignedOnly: '1',
    });
    return `/livehost/session-slots?${params.toString()}`;
  };

  return (
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">By account</div>
        <div className="text-[11px] text-[var(--color-muted)]">{rows.length} account{rows.length === 1 ? '' : 's'}</div>
      </div>
      <table className="reports-table">
        <thead>
          <tr>
            <th>Account</th>
            <th className="num">Total</th>
            <th className="num">Assigned</th>
            <th className="num">Replaced</th>
            <th className="num">Unassigned</th>
            <th className="num">Missed</th>
            <th className="num">Coverage %</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.accountId}>
              <td className="row-name">{r.name}</td>
              <td className="num">{fmtInt(r.totalSlots)}</td>
              <td className="num">{fmtInt(r.assigned)}</td>
              <td className="num">{fmtInt(r.replaced)}</td>
              <td className="num">
                {r.unassigned > 0 ? (
                  <Link
                    href={slotsHref(r.accountId)}
                    className="text-[var(--color-sky-ink)] underline-offset-4 hover:underline"
                  >
                    {fmtInt(r.unassigned)}
                  </Link>
                ) : (
                  fmtInt(r.unassigned)
                )}
              </td>
              <td className="num">{fmtInt(r.missed)}</td>
              <td className="num">{fmtPct(r.coverageRate)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
