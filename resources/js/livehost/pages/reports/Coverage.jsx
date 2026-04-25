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
  if (!prior) return null;
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
      <div className="space-y-6 p-8">
        <header className="flex items-start justify-between gap-4">
          <div>
            <Link
              href="/livehost/reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-3" /> Reports
            </Link>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Schedule Coverage</h1>
            <p className="text-sm text-muted-foreground">
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
      <div className="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
        No scheduled slots in this date range.
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
    <div className="overflow-hidden rounded-xl border bg-card">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
          <tr>
            <th className="px-4 py-2.5 text-left font-medium">Account</th>
            <th className="px-4 py-2.5 text-right font-medium">Total</th>
            <th className="px-4 py-2.5 text-right font-medium">Assigned</th>
            <th className="px-4 py-2.5 text-right font-medium">Replaced</th>
            <th className="px-4 py-2.5 text-right font-medium">Unassigned</th>
            <th className="px-4 py-2.5 text-right font-medium">Missed</th>
            <th className="px-4 py-2.5 text-right font-medium">Coverage %</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.accountId} className="border-t">
              <td className="px-4 py-2.5 font-medium">{r.name}</td>
              <td className="px-4 py-2.5 text-right">{fmtInt(r.totalSlots)}</td>
              <td className="px-4 py-2.5 text-right">{fmtInt(r.assigned)}</td>
              <td className="px-4 py-2.5 text-right">{fmtInt(r.replaced)}</td>
              <td className="px-4 py-2.5 text-right">
                {r.unassigned > 0 ? (
                  <Link href={slotsHref(r.accountId)} className="hover:underline">
                    {fmtInt(r.unassigned)}
                  </Link>
                ) : (
                  fmtInt(r.unassigned)
                )}
              </td>
              <td className="px-4 py-2.5 text-right">{fmtInt(r.missed)}</td>
              <td className="px-4 py-2.5 text-right">{fmtPct(r.coverageRate)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
