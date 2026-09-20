import { Sparkles } from 'lucide-react';
import { cn } from '@/student/lib/utils';

export default function PageHeader({ tag, title, subtitle, icon: Icon, children }) {
  return (
    <div className="hero-banner fade-up relative overflow-hidden rounded-3xl ring-1 ring-brand-100">
      <div className="pattern-islamic absolute inset-0 opacity-60" />
      <div className="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/40 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-16 left-1/3 h-48 w-48 rounded-full bg-rose-200/30 blur-3xl" />

      <div className="relative flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div className="flex items-center gap-4">
          {Icon && (
            <div className="hidden h-16 w-16 shrink-0 place-items-center rounded-2xl bg-white/75 ring-1 ring-brand-100 sm:grid">
              <Icon className="h-8 w-8 text-brand" strokeWidth={1.7} />
            </div>
          )}
          <div className="min-w-0">
            {tag && (
              <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/70 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-brand-ink ring-1 ring-brand-100">
                <Sparkles className="h-3 w-3" strokeWidth={2.5} />
                {tag}
              </div>
            )}
            <h1 className="text-[26px] font-extrabold leading-tight tracking-[-0.03em] text-ink sm:text-[32px]">
              {title}
            </h1>
            {subtitle && (
              <p className="mt-1.5 max-w-lg text-[14px] leading-relaxed text-ink-2/80">{subtitle}</p>
            )}
          </div>
        </div>

        {children && (
          <div className="fade-up flex flex-wrap gap-3" style={{ animationDelay: '0.1s' }}>
            {children}
          </div>
        )}
      </div>
    </div>
  );
}

/** Stat pill for use inside PageHeader children. */
export function HeroStat({ icon: Icon, label, value, iconClassName }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl bg-white/75 px-5 py-3.5 ring-1 ring-brand-100 backdrop-blur-sm">
      <div className={cn('grid h-10 w-10 place-items-center rounded-xl', iconClassName ?? 'bg-brand-soft')}>
        <Icon className="h-5 w-5 text-brand" strokeWidth={2} />
      </div>
      <div>
        <p className="text-[11px] font-medium uppercase tracking-wider text-muted">{label}</p>
        <p className="text-[22px] font-extrabold leading-tight text-ink">{value}</p>
      </div>
    </div>
  );
}
