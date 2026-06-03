import RadialGauge from '@/ceo/components/RadialGauge';
import { useT, statusLabel } from '@/ceo/lib/i18n';

const STATUS_TONE = { green: 'positive', amber: 'warning', red: 'negative' };

/**
 * The hero panel: one big composite "Operations Health" gauge answering "how is
 * the company running right now?", flanked by a green/amber/red department
 * breakdown so the CEO can see at a glance where any trouble sits.
 */
export default function HealthHero({ health, period }) {
  const t = useT();
  if (!health) return null;
  const tone = STATUS_TONE[health.status] ?? 'info';

  return (
    <section className="glass-card relative overflow-hidden rounded-[24px] p-6 sm:p-8" data-status={health.status}>
      <span className="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full opacity-40 blur-3xl" style={{ background: 'var(--signal)' }} aria-hidden="true" />
      <div className="relative flex flex-col items-center gap-7 sm:flex-row sm:items-center sm:gap-9">
        <div className="flex shrink-0 items-center gap-5">
          <RadialGauge value={health.score} suffix="" tone={tone} size={140} stroke={13} centerLabel={`${health.score}`} />
          <div className="flex flex-col gap-2">
            <span className="label-eyebrow">{t('operations_health')}</span>
            <span className="font-display text-[26px] leading-tight text-ink">{health.label}</span>
            <span className="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-[12px] font-semibold" style={{ background: 'var(--signal-soft)', color: 'var(--signal-ink)' }}>
              <span className="live-dot" />
              {statusLabel(t, health.status)} · {period?.label}
            </span>
          </div>
        </div>

        <div className="hidden h-24 w-px bg-[rgba(15,23,42,0.08)] sm:block" />

        <div className="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4">
          {health.segments.map((seg) => (
            <div key={seg.key} data-status={seg.status} className="flex flex-col gap-2 rounded-2xl bg-white/45 p-3.5">
              <div className="flex items-center justify-between">
                <span className="text-[12px] font-semibold text-ink">{seg.label}</span>
                <span className="h-2.5 w-2.5 rounded-full" style={{ background: 'var(--signal)' }} aria-hidden="true" />
              </div>
              <span className="text-[11px] font-medium" style={{ color: 'var(--signal-ink)' }}>
                {statusLabel(t, seg.status)}
              </span>
            </div>
          ))}
        </div>

        <div className="flex shrink-0 items-center gap-4 sm:flex-col sm:items-end sm:gap-1.5">
          {[
            { k: 'green', label: t('healthy'), c: 'var(--color-emerald)' },
            { k: 'amber', label: t('watch'), c: 'var(--color-amber)' },
            { k: 'red', label: t('attention'), c: 'var(--color-rose)' },
          ].map((row) => (
            <div key={row.k} className="flex items-center gap-2">
              <span className="h-2 w-2 rounded-full" style={{ background: row.c }} aria-hidden="true" />
              <span className="font-display text-[15px] text-ink tabular-nums">{health.counts[row.k]}</span>
              <span className="text-[11px] text-muted">{row.label}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
