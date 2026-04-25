import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import TrendChart from '@/livehost/components/reports/TrendChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';

const fmtMyr = (n) => `RM ${Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;
const fmtPct = (n) => `${(Number(n) * 100).toFixed(1)}%`;
const fmtHours = (n) => `${Number(n).toFixed(1)} hr`;

function delta(current, prior) {
  if (!prior) return null;
  return ((current - prior) / prior) * 100;
}

export default function HostScorecard({ kpis, rows, trend, filters, filterOptions }) {
  return (
    <>
      <Head title="Host Scorecard" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', 'Host Scorecard']} />
      <div className="space-y-6 p-8">
        <header className="flex items-start justify-between gap-4">
          <div>
            <Link
              href="/livehost/reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-3" /> Reports
            </Link>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Host Scorecard</h1>
            <p className="text-sm text-muted-foreground">
              Per-host hours live, GMV, commission, attendance.
            </p>
          </div>
          <ExportCsvButton
            exportPath="/livehost/reports/host-scorecard/export"
            filters={filters}
          />
        </header>

        <ReportFilters
          filters={filters}
          options={filterOptions}
          basePath="/livehost/reports/host-scorecard"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="Total live hours"
            value={kpis.current.totalHours}
            delta={delta(kpis.current.totalHours, kpis.prior.totalHours)}
            format={fmtHours}
          />
          <KpiCard
            label="Total GMV"
            value={kpis.current.totalGmv}
            delta={delta(kpis.current.totalGmv, kpis.prior.totalGmv)}
            format={fmtMyr}
          />
          <KpiCard
            label="Commission paid"
            value={kpis.current.totalCommission}
            delta={delta(kpis.current.totalCommission, kpis.prior.totalCommission)}
            format={fmtMyr}
          />
          <KpiCard
            label="Attendance rate"
            value={kpis.current.attendanceRate}
            delta={delta(kpis.current.attendanceRate, kpis.prior.attendanceRate)}
            format={fmtPct}
          />
        </div>

        <TrendChart data={trend} />

        <ScorecardTable rows={rows} />
      </div>
    </>
  );
}

HostScorecard.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function ScorecardTable({ rows }) {
  if (rows.length === 0) {
    return (
      <div className="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
        No host activity in this date range.
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
          <tr>
            <Th>Host</Th>
            <Th align="right">Sched</Th>
            <Th align="right">Ended</Th>
            <Th align="right">Hours</Th>
            <Th align="right">GMV</Th>
            <Th align="right">Avg/hr</Th>
            <Th align="right">No-shows</Th>
            <Th align="right">Late</Th>
            <Th align="right">Att%</Th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.hostId} className="border-t">
              <td className="px-4 py-2.5">
                <Link
                  href={`/livehost/hosts/${r.hostId}/edit`}
                  className="font-medium hover:underline"
                >
                  {r.hostName}
                </Link>
              </td>
              <Td align="right">{r.sessionsScheduled}</Td>
              <Td align="right">{r.sessionsEnded}</Td>
              <Td align="right">{r.hoursLive.toFixed(1)}</Td>
              <Td align="right">{fmtMyr(r.gmv)}</Td>
              <Td align="right">{fmtMyr(r.avgGmvPerHour)}</Td>
              <Td align="right">{r.noShows}</Td>
              <Td align="right">{r.lateStarts}</Td>
              <Td align="right">{fmtPct(r.attendanceRate)}</Td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Th({ children, align = 'left' }) {
  const cls = align === 'right' ? 'text-right' : 'text-left';
  return <th className={`px-4 py-2.5 ${cls} font-medium`}>{children}</th>;
}
function Td({ children, align = 'left' }) {
  const cls = align === 'right' ? 'text-right' : 'text-left';
  return <td className={`px-4 py-2.5 ${cls}`}>{children}</td>;
}
