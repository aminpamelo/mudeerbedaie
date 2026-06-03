import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { cn, toneText } from '@/ceo/lib/utils';
import StatusDot from '@/ceo/components/StatusDot';
import Sparkline from '@/ceo/components/Sparkline';

/**
 * One department's health panel: traffic-light status, lead operational
 * metrics, a sparkline of recent activity, and a drill-in link into that
 * module's own detailed views.
 */
export default function DepartmentCard({ department }) {
  const { label, accent, status, href, metrics = [], trend = [], alerts = [] } = department;

  return (
    <Link
      href={href}
      data-accent={accent}
      data-status={status}
      className="group relative flex flex-col gap-5 overflow-hidden rounded-[16px] border border-border bg-surface p-6 transition-all hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--accent)_35%,var(--color-border))] hover:shadow-[0_12px_30px_-16px_color-mix(in_oklab,var(--accent)_30%,transparent)]"
    >
      <span className="absolute inset-x-0 top-0 h-[3px]" style={{ background: 'var(--accent)' }} aria-hidden="true" />

      <div className="flex items-start justify-between">
        <div className="flex items-center gap-2.5">
          <StatusDot status={status} pulse />
          <div>
            <div className="font-display text-[16px] text-ink">{label}</div>
            <div className="text-[11px] text-muted">
              {alerts.length > 0 ? `${alerts.length} need${alerts.length === 1 ? 's' : ''} attention` : 'All clear'}
            </div>
          </div>
        </div>
        <ArrowUpRight className="h-4 w-4 text-muted-2 transition-colors group-hover:text-ink" strokeWidth={2} />
      </div>

      <div className="grid grid-cols-2 gap-x-4 gap-y-3.5">
        {metrics.map((metric) => (
          <div key={metric.label} className="flex flex-col gap-0.5">
            <span className="text-[11px] text-muted">{metric.label}</span>
            <span className={cn('font-display text-[19px] leading-tight tabular-nums', toneText(metric.tone))}>{metric.value}</span>
            {metric.hint && <span className="text-[10.5px] text-muted-2">{metric.hint}</span>}
          </div>
        ))}
      </div>

      <div className="mt-auto flex items-end justify-between border-t border-border-2 pt-4">
        <span className="text-[11px] font-medium text-muted-2 transition-colors group-hover:text-[var(--accent-ink)]">View detail</span>
        <Sparkline data={trend} color="var(--accent)" />
      </div>
    </Link>
  );
}
