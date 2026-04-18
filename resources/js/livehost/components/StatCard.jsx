import { ArrowUp, ArrowDown } from 'lucide-react';
import { cn } from '@/livehost/lib/utils';

/**
 * StatCard — Pulse-styled KPI card with 5 variants.
 *
 * @typedef {Object} StatCardTrend
 * @property {'up'|'down'} direction
 * @property {string} label
 *
 * @typedef {Object} StatCardProps
 * @property {'hero'|'small'|'dark'|'progress'|'ring'} [variant]
 * @property {string} label
 * @property {string|number} [value]
 * @property {string} [valueUnit]            unit shown after value (e.g. "/ 14")
 * @property {React.ComponentType} [icon]    lucide icon component
 * @property {'emerald'|'amber'|'rose'|'ink'} [iconTint]
 * @property {StatCardTrend} [trend]
 * @property {React.ReactNode} [subtitle]
 * @property {number} [progressPercent]      0-100, variant='progress'
 * @property {number} [ringPercent]          0-100, variant='ring'
 * @property {string} [ringValueLabel]       label inside ring, e.g. '72%'
 * @property {string} [ringSideTitle]        big number beside ring, e.g. '48.2'
 * @property {string} [ringSideUnit]         unit, e.g. 'h'
 * @property {string} [ringSideSubtitle]     fine print beside ring, e.g. 'weekly target 67h'
 * @property {string} [className]
 */

const CARD_BASE =
  'relative overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-all duration-200 hover:shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.03)]';

const ICON_TINT_CLASSES = {
  emerald: 'bg-[#ECFDF5] text-[#059669]',
  amber: 'bg-[#FFFBEB] text-[#F59E0B]',
  rose: 'bg-[#FFF1F2] text-[#F43F5E]',
  ink: 'bg-[#0A0A0A] text-white',
};

function formatValue(value) {
  if (value === null || value === undefined) {
    return '—';
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value.toLocaleString();
  }
  return String(value);
}

function TrendPill({ trend, dark = false }) {
  if (!trend || !trend.label) {
    return null;
  }
  const isDown = trend.direction === 'down';
  const Icon = isDown ? ArrowDown : ArrowUp;
  const base = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[12px] font-medium leading-none';
  let tone;
  if (dark) {
    tone = isDown
      ? 'bg-[#F43F5E]/20 text-[#FDA4AF]'
      : 'bg-[#10B981]/20 text-[#6EE7B7]';
  } else {
    tone = isDown
      ? 'bg-[#FFF1F2] text-[#F43F5E]'
      : 'bg-[#ECFDF5] text-[#059669]';
  }
  return (
    <span className={cn(base, tone)}>
      <Icon className="h-2.5 w-2.5" strokeWidth={3} />
      {trend.label}
    </span>
  );
}

function StatCardHeader({ label, icon: Icon, iconTint = 'emerald', dark = false, className }) {
  const iconWrapClass = dark
    ? 'bg-[#10B981]/20 text-[#10B981]'
    : ICON_TINT_CLASSES[iconTint] || ICON_TINT_CLASSES.emerald;

  return (
    <div className={cn('flex items-center justify-between gap-3', className)}>
      <span
        className={cn(
          'text-[12px] font-medium tracking-[0.01em]',
          dark ? 'text-white/70' : 'text-[#737373]'
        )}
      >
        {label}
      </span>
      {Icon ? (
        <div className={cn('grid h-7 w-7 place-items-center rounded-lg', iconWrapClass)}>
          <Icon className="h-[15px] w-[15px]" strokeWidth={2} />
        </div>
      ) : null}
    </div>
  );
}

function HeroValue({ children, hero = true, dark = false, unit }) {
  return (
    <div
      className={cn(
        'mt-3 font-semibold tabular-nums leading-none tracking-[-0.04em]',
        hero ? 'text-[64px]' : 'text-[48px]',
        dark ? 'text-[#6EE7B7]' : 'text-[#0A0A0A]'
      )}
    >
      {children}
      {unit ? (
        <span
          className={cn(
            'ml-1.5 text-base font-medium tracking-[-0.02em]',
            dark ? 'text-white/60' : 'text-[#737373]'
          )}
        >
          {unit}
        </span>
      ) : null}
    </div>
  );
}

function Foot({ children, dark = false, className }) {
  return (
    <div
      className={cn(
        'mt-3 flex items-center gap-2.5 text-[12.5px]',
        dark ? 'text-white/60' : 'text-[#737373]',
        className
      )}
    >
      {children}
    </div>
  );
}

function ProgressBar({ percent = 0 }) {
  const clamped = Math.max(0, Math.min(100, Number(percent) || 0));
  return (
    <div className="h-1.5 w-full overflow-hidden rounded-full bg-[#F5F5F5]">
      <div
        className="h-full rounded-full bg-gradient-to-r from-[#10B981] to-[#0EA5E9]"
        style={{ width: `${clamped}%` }}
      />
    </div>
  );
}

function Ring({ percent = 0, label }) {
  const clamped = Math.max(0, Math.min(100, Number(percent) || 0));
  const circumference = 2 * Math.PI * 40;
  const dashoffset = circumference * (1 - clamped / 100);
  return (
    <div className="relative h-24 w-24 shrink-0">
      <svg width="96" height="96" className="-rotate-90">
        <circle cx="48" cy="48" r="40" fill="none" stroke="#EAEAEA" strokeWidth="8" />
        <circle
          cx="48"
          cy="48"
          r="40"
          fill="none"
          stroke="#0A0A0A"
          strokeWidth="8"
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={dashoffset}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-xl font-semibold tabular-nums tracking-[-0.03em] text-[#0A0A0A]">
        {label ?? `${Math.round(clamped)}%`}
      </div>
    </div>
  );
}

export default function StatCard({
  variant = 'hero',
  label,
  value,
  valueUnit,
  icon,
  iconTint = 'emerald',
  trend,
  subtitle,
  progressPercent,
  ringPercent,
  ringValueLabel,
  ringSideTitle,
  ringSideUnit,
  ringSideSubtitle,
  className,
}) {
  if (variant === 'dark') {
    return (
      <div
        className={cn(
          CARD_BASE,
          'border-0 bg-gradient-to-br from-[#064E3B] to-[#022C22] text-white',
          className
        )}
      >
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-[40%] -right-[20%] h-80 w-80 rounded-full"
          style={{
            background:
              'radial-gradient(circle, rgba(16,185,129,0.4), transparent 70%)',
          }}
        />
        <div className="relative z-10">
          <StatCardHeader label={label} icon={icon} iconTint={iconTint} dark />
          <HeroValue hero unit={valueUnit} dark>
            {formatValue(value)}
          </HeroValue>
          {(trend || subtitle) ? (
            <Foot dark>
              <TrendPill trend={trend} dark />
              {subtitle ? <span>{subtitle}</span> : null}
            </Foot>
          ) : null}
        </div>
      </div>
    );
  }

  if (variant === 'ring') {
    return (
      <div className={cn(CARD_BASE, className)}>
        <StatCardHeader label={label} icon={icon} iconTint={iconTint} className="mb-[18px]" />
        <div className="flex items-center gap-5">
          <Ring percent={ringPercent} label={ringValueLabel} />
          <div className="min-w-0 flex-1">
            {ringSideTitle ? (
              <div className="text-2xl font-semibold leading-none tracking-[-0.03em] text-[#0A0A0A]">
                {ringSideTitle}
                {ringSideUnit ? (
                  <span className="ml-1 text-sm font-medium text-[#737373]">
                    {ringSideUnit}
                  </span>
                ) : null}
              </div>
            ) : null}
            {ringSideSubtitle ? (
              <div className="mt-1.5 text-[12.5px] text-[#737373]">{ringSideSubtitle}</div>
            ) : null}
          </div>
        </div>
      </div>
    );
  }

  if (variant === 'progress') {
    return (
      <div className={cn(CARD_BASE, className)}>
        <StatCardHeader label={label} icon={icon} iconTint={iconTint} />
        <HeroValue hero={false} unit={valueUnit}>
          {formatValue(value)}
        </HeroValue>
        <div className="mt-3.5">
          <ProgressBar percent={progressPercent} />
          {(trend || subtitle) ? (
            <Foot className="mt-2">
              <TrendPill trend={trend} />
              {subtitle ? <span>{subtitle}</span> : null}
            </Foot>
          ) : null}
        </div>
      </div>
    );
  }

  if (variant === 'small') {
    return (
      <div className={cn(CARD_BASE, className)}>
        <StatCardHeader label={label} icon={icon} iconTint={iconTint} />
        <HeroValue hero={false} unit={valueUnit}>
          {formatValue(value)}
        </HeroValue>
        {(trend || subtitle) ? (
          <Foot>
            <TrendPill trend={trend} />
            {subtitle ? <span>{subtitle}</span> : null}
          </Foot>
        ) : null}
      </div>
    );
  }

  // variant === 'hero'
  return (
    <div
      className={cn(
        CARD_BASE,
        'min-h-[180px]',
        // Top-right emerald radial highlight for hero
        'bg-[radial-gradient(ellipse_400px_200px_at_80%_-20%,rgba(16,185,129,0.15),transparent_70%)]',
        className
      )}
    >
      <StatCardHeader label={label} icon={icon} iconTint={iconTint} />
      <HeroValue hero unit={valueUnit}>
        {formatValue(value)}
      </HeroValue>
      {(trend || subtitle) ? (
        <Foot>
          <TrendPill trend={trend} />
          {subtitle ? <span>{subtitle}</span> : null}
        </Foot>
      ) : null}
    </div>
  );
}
