import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import MultiLineChart from '@/livehost/components/reports/MultiLineChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';

const fmtMyr = (n) => `RM ${Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;

function delta(current, prior) {
  if (!prior) return null;
  return ((current - prior) / prior) * 100;
}

function buildChartData(trendByAccount, accountSeries) {
  return trendByAccount.map((row) => {
    const point = { date: row.date };
    for (const acc of accountSeries) {
      point[`acc_${acc.accountId}`] = Number(row.series?.[acc.accountId] ?? 0);
    }
    return point;
  });
}

export default function GmvReport({ kpis, trendByAccount, accountSeries, hostRows, topSessions, filters, filterOptions }) {
  const series = accountSeries.map((acc) => ({ key: `acc_${acc.accountId}`, name: acc.name }));
  const chartData = buildChartData(trendByAccount, accountSeries);

  const topAccount = accountSeries.find((a) => a.accountId === kpis.current.topAccountId);
  const topHost = hostRows.find((h) => h.hostId === kpis.current.topHostId);

  return (
    <>
      <Head title="GMV Performance" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports', 'GMV Performance']} />
      <div className="space-y-6 p-8">
        <header className="flex items-start justify-between gap-4">
          <div>
            <Link
              href="/livehost/reports"
              className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-3" /> Reports
            </Link>
            <h1 className="mt-1 text-2xl font-semibold tracking-tight">GMV Performance</h1>
            <p className="text-sm text-muted-foreground">
              Daily GMV trend by account, top hosts and sessions.
            </p>
          </div>
          <ExportCsvButton
            exportPath="/livehost/reports/gmv/export"
            filters={filters}
          />
        </header>

        <ReportFilters
          filters={filters}
          options={filterOptions}
          basePath="/livehost/reports/gmv"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="Total GMV"
            value={kpis.current.totalGmv}
            delta={delta(kpis.current.totalGmv, kpis.prior.totalGmv)}
            format={fmtMyr}
          />
          <KpiCard
            label="GMV / session"
            value={kpis.current.gmvPerSession}
            delta={delta(kpis.current.gmvPerSession, kpis.prior.gmvPerSession)}
            format={fmtMyr}
          />
          <KpiCard
            label={topAccount ? `Top account: ${topAccount.name}` : 'Top account'}
            value={kpis.current.topAccountGmv}
            format={fmtMyr}
          />
          <KpiCard
            label={topHost ? `Top host: ${topHost.hostName}` : 'Top host'}
            value={kpis.current.topHostGmv}
            format={fmtMyr}
          />
        </div>

        <MultiLineChart
          title="Daily GMV by account"
          data={chartData}
          series={series}
        />

        <div className="grid gap-6 lg:grid-cols-2">
          <HostsTable rows={hostRows} />
          <TopSessionsPanel sessions={topSessions} />
        </div>
      </div>
    </>
  );
}

GmvReport.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function HostsTable({ rows }) {
  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <div className="bg-muted/50 px-4 py-2.5 text-sm font-medium">By host</div>
      {rows.length === 0 ? (
        <div className="p-6 text-center text-sm text-muted-foreground">No host activity in this range.</div>
      ) : (
        <table className="w-full text-sm">
          <thead className="bg-muted/30 text-xs uppercase tracking-wide text-muted-foreground">
            <tr>
              <th className="px-4 py-2 text-left font-medium">Host</th>
              <th className="px-4 py-2 text-right font-medium">Sessions</th>
              <th className="px-4 py-2 text-right font-medium">GMV</th>
              <th className="px-4 py-2 text-right font-medium">Avg/session</th>
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
                <td className="px-4 py-2.5 text-right">{r.sessions}</td>
                <td className="px-4 py-2.5 text-right">{fmtMyr(r.gmv)}</td>
                <td className="px-4 py-2.5 text-right">{fmtMyr(r.avgGmvPerSession)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function TopSessionsPanel({ sessions }) {
  return (
    <div className="overflow-hidden rounded-xl border bg-card">
      <div className="bg-muted/50 px-4 py-2.5 text-sm font-medium">Top sessions</div>
      {sessions.length === 0 ? (
        <div className="p-6 text-center text-sm text-muted-foreground">No sessions in this range.</div>
      ) : (
        <ul className="divide-y">
          {sessions.map((s) => (
            <li key={s.sessionId} className="px-4 py-2.5 text-sm">
              <Link href={`/livehost/sessions/${s.sessionId}`} className="flex items-center justify-between gap-3 hover:underline">
                <div className="min-w-0 flex-1 truncate">
                  <span className="font-medium">{fmtMyr(s.gmv)}</span>
                  <span className="text-muted-foreground"> · {s.hostName ?? '—'}</span>
                  <span className="text-muted-foreground"> · {s.accountName ?? '—'}</span>
                </div>
                <span className="text-xs text-muted-foreground">{s.date}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
