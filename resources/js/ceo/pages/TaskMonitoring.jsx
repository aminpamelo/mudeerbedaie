import { Head } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import CeoLayout from '@/ceo/layouts/CeoLayout';
import PeriodSwitcher from '@/ceo/components/PeriodSwitcher';
import RadialGauge from '@/ceo/components/RadialGauge';
import KpiTile from '@/ceo/components/KpiTile';
import AreaChart from '@/ceo/components/AreaChart';
import SegmentedBar from '@/ceo/components/SegmentedBar';
import DataList from '@/ceo/components/DataList';
import StaffPerformanceTable from '@/ceo/components/StaffPerformanceTable';
import AttentionFeed from '@/ceo/components/AttentionFeed';
import TaskBoard from '@/ceo/components/TaskBoard';
import { useT, statusLabel } from '@/ceo/lib/i18n';

function Card({ title, subtitle, children, className = '' }) {
  return (
    <div className={`glass-card flex flex-col gap-4 rounded-[20px] p-6 ${className}`}>
      <div>
        <h3 className="font-display text-[15px] text-ink">{title}</h3>
        {subtitle && <p className="text-[11px] text-muted">{subtitle}</p>}
      </div>
      {children}
    </div>
  );
}

export default function TaskMonitoring({ period, tasks, board, employees = [], categories = [] }) {
  const t = useT();
  const { status, gauge, kpis = [], breakdowns = [], trend, staff, overdueList, alerts = [], moduleHref, moduleLabel } = tasks;

  const alertItems = alerts.map((a, i) => ({
    ...a,
    department: t('tasks_nav'),
    departmentKey: `task-${i}`,
    href: a.href ?? moduleHref,
  }));

  return (
    <CeoLayout>
      <Head title={t('tasks_title')} />

      <header className="flex flex-wrap items-center justify-between gap-3 px-8 pb-2 pt-6">
        <div>
          <h1 className="font-display text-[22px] text-ink">{t('tasks_title')}</h1>
          <p className="text-[12.5px] text-muted">{t('tasks_subtitle')} · {period?.label}</p>
        </div>
        <PeriodSwitcher period={period} />
      </header>

      <div className="flex flex-col gap-6 px-8 pb-10" data-accent="rose" data-status={status}>
        {/* Hero: on-time gauge + status + module link */}
        <section className="glass-card relative flex flex-col items-center gap-6 overflow-hidden rounded-[22px] p-6 sm:flex-row sm:gap-9 sm:p-7">
          <span className="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full opacity-30 blur-3xl" style={{ background: 'var(--signal)' }} aria-hidden="true" />
          <span className="absolute inset-x-0 top-0 h-[3px]" style={{ background: 'linear-gradient(90deg, var(--color-brand), var(--color-violet))' }} aria-hidden="true" />
          {gauge && <RadialGauge value={gauge.value} target={gauge.target} suffix={gauge.suffix ?? '%'} tone={gauge.tone} label={gauge.label} size={132} stroke={12} />}
          <div className="flex flex-1 flex-col items-center gap-2 sm:items-start">
            <span className="label-eyebrow">{t('tasks_title')}</span>
            <span className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[12px] font-semibold" style={{ background: 'var(--signal-soft)', color: 'var(--signal-ink)' }}>
              <span className="live-dot" />
              {statusLabel(t, status)}
            </span>
            <p className="text-center text-[13px] text-muted sm:text-left">{t('tasks_subtitle')}</p>
          </div>
          {moduleHref && (
            <a
              href={moduleHref}
              className="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5"
              style={{ background: 'linear-gradient(90deg, var(--color-brand), var(--color-violet))' }}
            >
              {moduleLabel}
              <ArrowUpRight className="h-4 w-4" strokeWidth={2.2} />
            </a>
          )}
        </section>

        {/* KPI tiles */}
        <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {kpis.map((kpi) => (
            <KpiTile key={kpi.label} kpi={kpi} />
          ))}
        </section>

        {/* Editable task list — the CEO acts on tasks directly here */}
        {board && <TaskBoard board={board} employees={employees} categories={categories} />}

        {/* Breakdowns */}
        <section className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          {breakdowns.map((b) => (
            <Card key={b.title} title={b.title} subtitle={b.subtitle}>
              <SegmentedBar segments={b.segments} />
            </Card>
          ))}
        </section>

        {/* Completed-per-day trend */}
        {trend && (
          <Card title={trend.title} subtitle={trend.subtitle}>
            <AreaChart data={trend.data} color="var(--color-violet)" />
          </Card>
        )}

        {/* Staff performance leaderboard */}
        {staff && (
          <Card title={staff.title}>
            <StaffPerformanceTable columns={staff.columns} rows={staff.rows} emptyText={t('tasks_no_staff')} />
          </Card>
        )}

        {/* Overdue tasks */}
        {overdueList && (
          <Card title={overdueList.title}>
            <DataList columns={overdueList.columns} rows={overdueList.rows} />
          </Card>
        )}

        {/* Attention feed */}
        {alertItems.length > 0 && (
          <section className="flex flex-col gap-3">
            <h2 className="label-eyebrow px-1">{t('needs_attention')}</h2>
            <AttentionFeed items={alertItems} />
          </section>
        )}
      </div>
    </CeoLayout>
  );
}
