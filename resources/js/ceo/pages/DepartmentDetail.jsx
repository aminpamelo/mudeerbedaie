import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';
import CeoLayout from '@/ceo/layouts/CeoLayout';
import PeriodSwitcher from '@/ceo/components/PeriodSwitcher';
import RadialGauge from '@/ceo/components/RadialGauge';
import KpiTile from '@/ceo/components/KpiTile';
import AreaChart from '@/ceo/components/AreaChart';
import SegmentedBar from '@/ceo/components/SegmentedBar';
import DataList from '@/ceo/components/DataList';
import MonthlyMatrix from '@/ceo/components/MonthlyMatrix';
import AttentionFeed from '@/ceo/components/AttentionFeed';
import { useT, statusLabel } from '@/ceo/lib/i18n';

function SectionCard({ section }) {
  const span = section.type === 'breakdown' ? '' : 'lg:col-span-2';
  return (
    <div className={`glass-card flex flex-col gap-4 rounded-[20px] p-6 ${span}`}>
      <div>
        <h3 className="font-display text-[15px] text-ink">{section.title}</h3>
        {section.subtitle && <p className="text-[11px] text-muted">{section.subtitle}</p>}
      </div>
      {section.type === 'chart' && <AreaChart data={section.data} color="var(--accent)" />}
      {section.type === 'breakdown' && <SegmentedBar segments={section.segments} />}
      {section.type === 'list' && <DataList columns={section.columns} rows={section.rows} />}
    </div>
  );
}

export default function DepartmentDetail({ period, department }) {
  const t = useT();
  const { label, accent, status, gauges = [], kpis = [], sections = [], alerts = [], matrix = null, moduleHref, moduleLabel } = department;
  const gauge = gauges[0] ?? null;

  const alertItems = alerts.map((a, i) => ({
    ...a,
    department: label,
    departmentKey: `${department.key}-${i}`,
    href: a.href ?? moduleHref,
  }));

  return (
    <CeoLayout>
      <Head title={label} />

      <header className="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 lg:px-8 pb-2 pt-6">
        <div className="flex items-center gap-3">
          <Link href="/ceo" className="grid h-9 w-9 place-items-center rounded-xl glass text-muted transition-colors hover:text-ink" aria-label={t('back_to_overview')}>
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
          </Link>
          <div>
            <h1 className="font-display text-[22px] text-ink">{label}</h1>
            <p className="text-[12.5px] text-muted">{t('department_detail')} · {period?.label}</p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <PeriodSwitcher period={period} />
        </div>
      </header>

      <div className="flex flex-col gap-6 px-4 sm:px-6 lg:px-8 pb-10" data-accent={accent} data-status={status}>
        {/* Hero */}
        <section className="glass-card relative flex flex-col items-center gap-6 overflow-hidden rounded-[22px] p-6 sm:flex-row sm:gap-9 sm:p-7">
          <span className="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full opacity-30 blur-3xl" style={{ background: 'var(--accent)' }} aria-hidden="true" />
          <span className="absolute inset-x-0 top-0 h-[3px]" style={{ background: 'linear-gradient(90deg, var(--accent), var(--accent-2))' }} aria-hidden="true" />
          {gauge && <RadialGauge value={gauge.value} target={gauge.target} suffix={gauge.suffix ?? '%'} tone={gauge.tone} label={gauge.label} size={132} stroke={12} />}
          <div className="flex flex-1 flex-col items-center gap-2 sm:items-start">
            <span className="label-eyebrow">{t('department_health')}</span>
            <span className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[12px] font-semibold" style={{ background: 'var(--signal-soft)', color: 'var(--signal-ink)' }}>
              <span className="live-dot" />
              {statusLabel(t, status)}
            </span>
            <p className="text-center text-[13px] text-muted sm:text-left">
              {alerts.length > 0
                ? t(alerts.length === 1 ? 'items_need_attention_one' : 'items_need_attention_many', { count: alerts.length })
                : t('within_thresholds')}
            </p>
          </div>
          {moduleHref && (
            <a
              href={moduleHref}
              className="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5"
              style={{ background: 'linear-gradient(90deg, var(--accent), var(--accent-2))' }}
            >
              {moduleLabel ?? 'Open module'}
              <ArrowUpRight className="h-4 w-4" strokeWidth={2.2} />
            </a>
          )}
        </section>

        {/* Performance matrix — metric × period buckets (the report centerpiece) */}
        {matrix && (
          <section className="glass-card flex flex-col gap-4 rounded-[20px] p-6">
            <div>
              <h3 className="font-display text-[15px] text-ink">{matrix.title}</h3>
              {matrix.subtitle && <p className="text-[11px] text-muted">{matrix.subtitle}</p>}
            </div>
            {matrix.empty ? (
              <div className="grid h-20 place-items-center rounded-xl bg-[rgba(15,23,42,0.04)] text-[12px] text-muted">
                {t('no_activity_period')}
              </div>
            ) : (
              <MonthlyMatrix months={matrix.months} columns={matrix.columns} rows={matrix.rows} />
            )}
          </section>
        )}

        {/* Snapshot KPI tiles — point-in-time + rate metrics */}
        <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {kpis.map((kpi) => (
            <KpiTile key={kpi.label} kpi={kpi} />
          ))}
        </section>

        {/* Detail sections */}
        <section className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          {sections.map((section, i) => (
            <SectionCard key={`${section.type}-${i}`} section={section} />
          ))}
        </section>

        {/* Department alerts */}
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
