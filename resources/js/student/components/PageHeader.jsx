import { Sparkles } from 'lucide-react';
import { t } from '@/student/lib/utils';

export default function PageHeader({ tag, title, subtitle, children }) {
  return (
    <div className="hero-gradient relative overflow-hidden">
      <div className="dot-pattern">
        <div className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-rose-500/20 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-violet-400/20 blur-3xl" />

        <div className="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
          <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="fade-up">
              {tag && (
                <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-white/80 ring-1 ring-white/20">
                  <Sparkles className="h-3 w-3" strokeWidth={2.5} />
                  {tag}
                </div>
              )}
              <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.03em] text-white sm:text-[34px]">
                {title}
              </h1>
              {subtitle && (
                <p className="mt-1.5 max-w-md text-[14px] leading-relaxed text-white/65">{subtitle}</p>
              )}
            </div>

            {/* Optional stat pills or action buttons */}
            {children && (
              <div className="fade-up flex flex-wrap gap-3" style={{ animationDelay: '0.1s' }}>
                {children}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

/** Stat pill for use inside PageHeader children */
export function HeroStat({ icon: Icon, label, value, iconClassName }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl bg-white/10 px-5 py-3.5 ring-1 ring-white/15 backdrop-blur-sm">
      <div className={`grid h-10 w-10 place-items-center rounded-xl ${iconClassName ?? 'bg-white/15'}`}>
        <Icon className="h-5 w-5 text-white" strokeWidth={2} />
      </div>
      <div>
        <p className="text-[11px] font-medium uppercase tracking-wider text-white/55">{label}</p>
        <p className="text-[22px] font-extrabold leading-tight text-white">{value}</p>
      </div>
    </div>
  );
}
