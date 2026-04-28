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
      <div className="space-y-7 p-8" data-accent="violet">
        <header className="flex flex-wrap items-start justify-between gap-6">
          <div className="max-w-2xl">
            <Link
              href="/livehost/reports"
              className="label-eyebrow inline-flex items-center gap-1 transition hover:text-[var(--color-ink)]"
            >
              <ArrowLeft className="size-3" strokeWidth={2.4} /> Reports
            </Link>
            <h1 className="mt-3 text-[40px] leading-[1.05] tracking-tight text-[var(--color-ink)]">
              <span className="font-display">Replacement</span>{' '}
              <span className="font-display text-[var(--color-violet-ink)]">Activity</span>
            </h1>
            <p className="mt-2 text-[14.5px] leading-relaxed text-[var(--color-muted)]">
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
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">Top requesters</div>
        <div className="text-[11px] text-[var(--color-muted)]">{rows.length}</div>
      </div>
      {rows.length === 0 ? (
        <div className="p-10 text-center text-sm text-[var(--color-muted)]">No replacement requests in this range.</div>
      ) : (
        <ul className="divide-y divide-[var(--color-border-2)]">
          {rows.map((r) => (
            <li key={r.hostId} className="px-5 py-3.5">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <Link
                    href={`/livehost/hosts/${r.hostId}/edit`}
                    className="text-sm font-medium text-[var(--color-ink)] hover:text-[var(--color-violet-ink)]"
                  >
                    {r.hostName}
                  </Link>
                  <div className="mt-1.5 flex flex-wrap gap-1">
                    {Object.entries(r.reasons).map(([key, count]) => (
                      count > 0 && (
                        <span
                          key={key}
                          className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-medium ${REASON_TONES[key] ?? 'bg-gray-100 text-gray-700'}`}
                        >
                          {REASON_LABELS[key] ?? key} · {count}
                        </span>
                      )
                    ))}
                  </div>
                </div>
                <div className="shrink-0 font-display text-2xl tabular-nums leading-none text-[var(--color-ink)]">
                  {r.requestCount}
                </div>
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
    <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="flex items-center justify-between border-b border-[var(--color-border-2)] px-5 py-3">
        <div className="label-eyebrow">Top coverers</div>
        <div className="text-[11px] text-[var(--color-muted)]">{rows.length}</div>
      </div>
      {rows.length === 0 ? (
        <div className="p-10 text-center text-sm text-[var(--color-muted)]">No fulfilled replacements in this range.</div>
      ) : (
        <table className="reports-table">
          <thead>
            <tr>
              <th>Host</th>
              <th className="num">Covers</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.hostId}>
                <td className="row-name">
                  <Link href={`/livehost/hosts/${r.hostId}/edit`}>{r.hostName}</Link>
                </td>
                <td className="num">{fmtInt(r.coverCount)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
