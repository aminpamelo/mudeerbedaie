import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { cn, toneText } from '@/ceo/lib/utils';
import { useT, statusLabel } from '@/ceo/lib/i18n';
import RadialGauge from '@/ceo/components/RadialGauge';
import ProgressBar from '@/ceo/components/ProgressBar';
import Sparkline from '@/ceo/components/Sparkline';

/**
 * One department's frosted-glass health panel: a primary radial gauge, lead
 * metrics, progress/bullet bars vs target, a sparkline of recent activity, and
 * a drill-in link into that module. Vibrant accent driven by `data-accent`.
 */
export default function DepartmentCard({ department }) {
  const t = useT();
  const { key, label, accent, status, metrics = [], trend = [], alerts = [], gauges = [], bars = [] } = department;
  const gauge = gauges[0] ?? null;

  return (
    <Link
      href={`/ceo/${key}`}
      data-accent={accent}
      data-status={status}
      className="glass-card group relative flex flex-col gap-5 overflow-hidden rounded-[22px] p-6"
    >
      <span className="pointer-events-none absolute -right-16 -top-20 h-48 w-48 rounded-full opacity-30 blur-3xl" style={{ background: 'var(--accent)' }} aria-hidden="true" />
      <span className="absolute inset-x-0 top-0 h-[3px]" style={{ background: 'linear-gradient(90deg, var(--accent), var(--accent-2))' }} aria-hidden="true" />

      <div className="flex items-start justify-between">
        <div className="flex items-center gap-2.5">
          <span className="grid h-9 w-9 place-items-center rounded-xl" style={{ background: 'color-mix(in oklab, var(--accent) 16%, white)' }}>
            <span className="h-2.5 w-2.5 rounded-full" style={{ background: 'var(--signal)' }} aria-hidden="true" />
          </span>
          <div>
            <div className="font-display text-[16px] text-ink">{label}</div>
            <div className="text-[11px] font-medium" style={{ color: 'var(--signal-ink)' }}>
              {alerts.length > 0 ? `${statusLabel(t, status)} · ${alerts.length} ${alerts.length === 1 ? t('item') : t('items')}` : t('all_clear')}
            </div>
          </div>
        </div>
        <ArrowUpRight className="h-4 w-4 text-muted-2 transition-colors group-hover:text-ink" strokeWidth={2} />
      </div>

      <div className="flex items-center gap-5">
        {gauge && (
          <RadialGauge value={gauge.value} target={gauge.target} suffix={gauge.suffix ?? '%'} tone={gauge.tone} label={gauge.label} size={92} stroke={9} />
        )}
        <div className={cn('grid flex-1 gap-x-4 gap-y-3', gauge ? 'grid-cols-2' : 'grid-cols-3')}>
          {metrics.slice(0, gauge ? 2 : 3).map((metric) => (
            <div key={metric.label} className="flex flex-col gap-0.5">
              <span className="text-[11px] text-muted">{metric.label}</span>
              <span className={cn('font-display text-[18px] leading-tight tabular-nums', toneText(metric.tone))}>{metric.value}</span>
              {metric.hint && <span className="text-[10px] text-muted-2">{metric.hint}</span>}
            </div>
          ))}
        </div>
      </div>

      {bars.length > 0 && (
        <div className="flex flex-col gap-3">
          {bars.map((bar) => (
            <ProgressBar key={bar.label} {...bar} />
          ))}
        </div>
      )}

      <div className="mt-auto flex items-end justify-between border-t border-[rgba(15,23,42,0.07)] pt-4">
        <span className="text-[11px] font-semibold text-muted-2 transition-colors group-hover:text-[var(--accent-ink)]">{t('view_detail')}</span>
        <Sparkline data={trend} color="var(--accent)" />
      </div>
    </Link>
  );
}
