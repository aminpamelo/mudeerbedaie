import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import KpiCard from '@/livehost/components/reports/KpiCard';
import MultiLineChart from '@/livehost/components/reports/MultiLineChart';
import ReportFilters from '@/livehost/components/reports/ReportFilters';
import ExportCsvButton from '@/livehost/components/reports/ExportCsvButton';

const fmtMyr = (n) => `RM ${Number(n).toLocaleString('en-MY', { minimumFractionDigits: 2 })}`;

function delta(current, prior) {
  if (prior == null || prior === 0) return null;
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
      <div className="space-y-7 p-8" data-accent="amber">
        <header className="flex flex-wrap items-start justify-between gap-6">
          <div className="max-w-2xl">
            <Link
              href="/livehost/reports"
              className="label-eyebrow inline-flex items-center gap-1 transition hover:text-[var(--color-ink)]"
            >
              <ArrowLeft className="size-3" strokeWidth={2.4} /> Reports
            </Link>
            <h1 className="mt-3 text-[40px] leading-[1.05] tracking-tight text-[var(--color-ink)]">
              <span className="font-display">GMV</span>{' '}
              <span className="font-display text-[var(--color-amber-ink)]">Performance</span>
            </h1>
            <p className="mt-2 text-[14.5px] leading-relaxed text-[var(--color-muted)]">
              Daily revenue trend by account, top hosts, and the highest-grossing sessions.
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
            label={topAccount ? `Top account · ${topAccount.name}` : 'Top account'}
            value={kpis.current.topAccountGmv}
            valueSize="sm"
            format={fmtMyr}
          />
          <KpiCard
            label={topHost ? `Top host · ${topHost.hostName}` : 'Top host'}
            value={kpis.current.topHostGmv}
            valueSize="sm"
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
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">By host · sorted by GMV</div>
        <div className="text-[11px] text-[var(--color-muted)]">{rows.length} host{rows.length === 1 ? '' : 's'}</div>
      </div>
      {rows.length === 0 ? (
        <div className="p-10 text-center text-sm text-[var(--color-muted)]">No host activity in this range.</div>
      ) : (
        <table className="reports-table">
          <thead>
            <tr>
              <th>Host</th>
              <th className="num">Sessions</th>
              <th className="num">GMV</th>
              <th className="num">Avg / session</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.hostId}>
                <td className="row-name">
                  <Link href={`/livehost/hosts/${r.hostId}/edit`}>{r.hostName}</Link>
                </td>
                <td className="num">{r.sessions}</td>
                <td className="num">{fmtMyr(r.gmv)}</td>
                <td className="num">{fmtMyr(r.avgGmvPerSession)}</td>
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
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">Top sessions</div>
        <div className="text-[11px] text-[var(--color-muted)]">{sessions.length}</div>
      </div>
      {sessions.length === 0 ? (
        <div className="p-10 text-center text-sm text-[var(--color-muted)]">No sessions in this range.</div>
      ) : (
        <ul className="divide-y divide-[var(--color-border-2)]">
          {sessions.map((s) => (
            <li key={s.sessionId}>
              <Link
                href={`/livehost/sessions/${s.sessionId}`}
                className="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-[color-mix(in_oklab,var(--color-amber)_5%,transparent)]"
              >
                <div className="min-w-0 flex-1">
                  <div className="font-display text-lg tabular-nums text-[var(--color-ink)]">
                    {fmtMyr(s.gmv)}
                  </div>
                  <div className="mt-0.5 truncate text-[12.5px] text-[var(--color-muted)]">
                    {s.hostName ?? '—'} · {s.accountName ?? '—'}
                  </div>
                </div>
                <span className="font-mono text-[11px] tabular-nums text-[var(--color-muted)]">{s.date}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
