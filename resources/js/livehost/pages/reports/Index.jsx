import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Activity, BarChart3, CalendarRange, Replace } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';

const ACCENTS = {
  'host-scorecard': {
    accent: 'emerald',
    icon: Activity,
    metric: 'Hours · GMV · Attendance',
  },
  gmv: {
    accent: 'amber',
    icon: BarChart3,
    metric: 'Daily revenue · Top accounts',
  },
  coverage: {
    accent: 'sky',
    icon: CalendarRange,
    metric: 'Slot fill rate · Gaps',
  },
  replacements: {
    accent: 'violet',
    icon: Replace,
    metric: 'SLA · Top requesters',
  },
};

export default function ReportsIndex({ reports }) {
  return (
    <>
      <Head title="Reports" />
      <TopBar breadcrumb={['Live Host Desk', 'Reports']} />
      <div className="space-y-10 p-8">
        <header className="max-w-3xl">
          <span className="label-eyebrow">Live Host Desk</span>
          <h1 className="mt-3 text-[44px] leading-[1.05] tracking-tight text-[var(--color-ink)]">
            <span className="font-display">Reports</span>{' '}
            <span className="text-[var(--color-muted-2)]">·</span>{' '}
            <span className="text-[var(--color-ink-2)]">at a glance</span>
          </h1>
          <p className="mt-3 max-w-xl text-[15px] leading-relaxed text-[var(--color-muted)]">
            Operational and financial views across the Live Host operation.
            Pick a report to drill in.
          </p>
        </header>

        <div className="grid gap-5 md:grid-cols-2">
          {reports.map((report) => (
            <ReportTile key={report.key} report={report} />
          ))}
        </div>
      </div>
    </>
  );
}

ReportsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function ReportTile({ report }) {
  const meta = ACCENTS[report.key] ?? {};
  const Icon = meta.icon ?? Activity;

  const inner = (
    <article
      className={`report-tile ${report.available ? '' : 'is-soon'}`}
      data-accent={meta.accent}
    >
      <div className="relative flex items-start justify-between gap-4">
        <div
          className="grid size-11 place-items-center rounded-xl"
          style={{
            background: 'color-mix(in oklab, var(--accent) 10%, white)',
            color: 'var(--accent-ink, var(--color-ink-2))',
          }}
        >
          <Icon className="size-5" strokeWidth={1.75} />
        </div>
        {report.available ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-[var(--color-surface-2)] px-2 py-1 text-[10.5px] font-medium uppercase tracking-[0.12em] text-[var(--color-muted)] transition group-hover:bg-[color-mix(in_oklab,var(--accent)_8%,white)] group-hover:text-[var(--accent-ink)]">
            Open
            <ArrowUpRight className="size-3" strokeWidth={2.2} />
          </span>
        ) : (
          <span className="inline-flex items-center rounded-full bg-[var(--color-surface-2)] px-2 py-1 text-[10.5px] font-medium uppercase tracking-[0.12em] text-[var(--color-muted-2)]">
            Coming soon
          </span>
        )}
      </div>

      <div className="relative mt-6">
        <h3 className="font-display text-[28px] leading-tight tracking-tight text-[var(--color-ink)]">
          {report.title}
        </h3>
        <p className="mt-2 text-[14px] leading-relaxed text-[var(--color-muted)]">
          {report.description}
        </p>
      </div>

      {meta.metric && (
        <div className="relative mt-6 border-t border-dashed border-[var(--color-border)] pt-4">
          <div className="label-eyebrow">{meta.metric}</div>
        </div>
      )}
    </article>
  );

  return report.available ? (
    <Link href={report.href} className="group block">
      {inner}
    </Link>
  ) : (
    <div className="group block">{inner}</div>
  );
}
