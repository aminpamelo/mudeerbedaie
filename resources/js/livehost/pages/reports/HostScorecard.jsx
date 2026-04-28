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
  if (prior == null || prior === 0) return null;
  return ((current - prior) / prior) * 100;
}

export default function HostScorecard({ kpis, rows, trend, filters, filterOptions }) {
  return (
    <>
      <Head title="Host Scorecard" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', 'Host Scorecard']} />
      <div className="space-y-7 p-8" data-accent="emerald">
        <header className="flex flex-wrap items-start justify-between gap-6">
          <div className="max-w-2xl">
            <Link
              href="/livehost/reports"
              className="label-eyebrow inline-flex items-center gap-1 transition hover:text-[var(--color-ink)]"
            >
              <ArrowLeft className="size-3" strokeWidth={2.4} /> Reports
            </Link>
            <h1 className="mt-3 text-[40px] leading-[1.05] tracking-tight text-[var(--color-ink)]">
              <span className="font-display">Host</span>{' '}
              <span className="font-display text-[var(--color-emerald-ink)]">Scorecard</span>
            </h1>
            <p className="mt-2 text-[14.5px] leading-relaxed text-[var(--color-muted)]">
              Per-host hours live, GMV, commission, attendance — drill into who's pulling weight.
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
      <div className="rounded-2xl border border-dashed border-[var(--color-border)] bg-[var(--color-surface)] p-14 text-center">
        <div className="font-display text-2xl text-[var(--color-ink-2)]">No activity yet</div>
        <p className="mt-2 text-sm text-[var(--color-muted)]">No host activity in this date range. Try widening to "Last 30 days".</p>
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">By host · sorted by GMV</div>
        <div className="text-[11px] text-[var(--color-muted)]">{rows.length} host{rows.length === 1 ? '' : 's'}</div>
      </div>
      <table className="reports-table">
        <thead>
          <tr>
            <th>Host</th>
            <th className="num">Scheduled</th>
            <th className="num">Ended</th>
            <th className="num">Hours</th>
            <th className="num">GMV</th>
            <th className="num">Avg / hr</th>
            <th className="num">No-shows</th>
            <th className="num">Late</th>
            <th className="num">Att %</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.hostId}>
              <td className="row-name">
                <Link href={`/livehost/hosts/${r.hostId}/edit`}>{r.hostName}</Link>
              </td>
              <td className="num">{r.sessionsScheduled}</td>
              <td className="num">{r.sessionsEnded}</td>
              <td className="num">{r.hoursLive.toFixed(1)}</td>
              <td className="num">{fmtMyr(r.gmv)}</td>
              <td className="num">{fmtMyr(r.avgGmvPerHour)}</td>
              <td className="num">{r.noShows}</td>
              <td className="num">{r.lateStarts}</td>
              <td className="num">{fmtPct(r.attendanceRate)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
