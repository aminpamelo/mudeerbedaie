import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import MultiLineChart from '@/livehost/components/reports/MultiLineChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';

const fmtInt = (n) => Number(n).toLocaleString('en-MY');

function fmtDuration(minutes) {
  if (minutes == null) return '—';
  const m = Math.round(minutes);
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  const rem = m % 60;
  return rem === 0 ? `${h}h` : `${h}h ${rem}m`;
}

function delta(current, prior) {
  if (prior == null || prior === 0) return null;
  return ((current - prior) / prior) * 100;
}

const REASON_LABELS = {
  sick: 'Sick',
  family: 'Family',
  personal: 'Personal',
  other: 'Other',
};

const REASON_TONES = {
  sick: 'bg-red-50 text-red-700',
  family: 'bg-blue-50 text-blue-700',
  personal: 'bg-amber-50 text-amber-700',
  other: 'bg-gray-100 text-gray-700',
};

export default function ReplacementsReport({ kpis, trend, topRequesters, topCoverers, filters, filterOptions }) {
  const chartSeries = [
    { key: 'requested', name: 'Requested', color: '#6366f1' },
    { key: 'fulfilled', name: 'Fulfilled', color: '#10b981' },
  ];

  return (
    <>
      <Head title="Replacement Activity" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', 'Replacement Activity']} />
      <div className="space-y-6 p-8">
        <header className="flex items-start justify-between gap-4">
          <div>
            <Link
              href="/livehost/reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-3" /> Reports
            </Link>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">Replacement Activity</h1>
            <p className="text-sm text-muted-foreground">
              How often hosts request replacements, who covers, fulfillment SLA.
            </p>
          </div>
          <ExportCsvButton
            exportPath="/livehost/reports/replacements/export"
            filters={filters}
          />
        </header>

        <ReportFilters
          filters={filters}
          options={filterOptions}
          basePath="/livehost/reports/replacements"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="Total requests"
            value={kpis.current.total}
            delta={delta(kpis.current.total, kpis.prior.total)}
            format={fmtInt}
          />
          <KpiCard
            label="Fulfilled"
            value={kpis.current.fulfilled}
            delta={delta(kpis.current.fulfilled, kpis.prior.fulfilled)}
            format={fmtInt}
          />
          <KpiCard
            label="Expired"
            value={kpis.current.expired}
            delta={delta(kpis.current.expired, kpis.prior.expired)}
            format={fmtInt}
          />
          <KpiCard
            label="Avg time to assign"
            value={kpis.current.avgTimeToAssignMinutes}
            delta={delta(kpis.current.avgTimeToAssignMinutes, kpis.prior.avgTimeToAssignMinutes)}
            format={fmtDuration}
          />
        </div>

        <MultiLineChart
          title="Daily request volume"
          data={trend}
          series={chartSeries}
        />

        <div className="grid gap-6 lg:grid-cols-2">
          <RequestersTable rows={topRequesters} />
          <CoverersTable rows={topCoverers} />
        </div>
      </div>
    </>
  );
}

ReplacementsReport.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function RequestersTable({ rows }) {
  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <div className="bg-muted/50 px-4 py-2.5 text-sm font-medium">Top requesters</div>
      {rows.length === 0 ? (
        <div className="p-6 text-center text-sm text-muted-foreground">No replacement requests in this range.</div>
      ) : (
        <ul className="divide-y">
          {rows.map((r) => (
            <li key={r.hostId} className="px-4 py-3 text-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <Link href={`/livehost/hosts/${r.hostId}/edit`} className="font-medium hover:underline">
                    {r.hostName}
                  </Link>
                  <div className="mt-1 flex flex-wrap gap-1">
                    {Object.entries(r.reasons).map(([key, count]) => (
                      count > 0 && (
                        <span
                          key={key}
                          className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs ${REASON_TONES[key] ?? 'bg-gray-100 text-gray-700'}`}
                        >
                          {REASON_LABELS[key] ?? key}: {count}
                        </span>
                      )
                    ))}
                  </div>
                </div>
                <div className="shrink-0 text-right font-medium">{r.requestCount}</div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function CoverersTable({ rows }) {
  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <div className="bg-muted/50 px-4 py-2.5 text-sm font-medium">Top coverers</div>
      {rows.length === 0 ? (
        <div className="p-6 text-center text-sm text-muted-foreground">No fulfilled replacements in this range.</div>
      ) : (
        <table className="w-full text-sm">
          <thead className="bg-muted/30 text-xs uppercase tracking-wide text-muted-foreground">
            <tr>
              <th className="px-4 py-2 text-left font-medium">Host</th>
              <th className="px-4 py-2 text-right font-medium">Covers</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.hostId} className="border-t">
                <td className="px-4 py-2.5">
                  <Link href={`/livehost/hosts/${r.hostId}/edit`} className="font-medium hover:underline">
                    {r.hostName}
                  </Link>
                </td>
                <td className="px-4 py-2.5 text-right">{fmtInt(r.coverCount)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
