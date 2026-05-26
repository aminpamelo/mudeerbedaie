import { cn } from '../../lib/utils';
import { getAccent } from '../../lib/accents';

/**
 * BalanceRing — reusable progress ring for any percentage/balance metric.
 * Used by MyAttendance, MyLeave, MyOvertime, etc.
 *
 * <BalanceRing
 *   value={12}            // current value
 *   max={20}              // max value (denominator)
 *   accent="indigo"       // color theme
 *   label="days left"     // suffix for the sub label
 *   children={<>...</>}   // optional override of inner content
 * />
 */
export function BalanceRing({
    value = 0,
    max = 100,
    accent = 'indigo',
    label,
    unit,
    size = 220,
    stroke = 12,
    showPercent = false,
    children,
    className,
    halo = true,
}) {
    const a = getAccent(accent);
    const percent = Math.max(0, Math.min(1, max > 0 ? value / max : 0));
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference * (1 - percent);
    const center = size / 2;
    const ringId = `bring-${accent}-${Math.random().toString(36).slice(2, 8)}`;

    // Per-accent gradient stops (warm sunrise default for indigo)
    const stops = (() => {
        switch (accent) {
            case 'indigo': return [['0%', '#6366F1'], ['50%', '#EC4899'], ['100%', '#FB923C']];
            case 'emerald': return [['0%', '#10B981'], ['100%', '#34D399']];
            case 'violet': return [['0%', '#8B5CF6'], ['100%', '#A78BFA']];
            case 'rose': return [['0%', '#F43F5E'], ['100%', '#FB7185']];
            case 'amber': return [['0%', '#F59E0B'], ['100%', '#FBBF24']];
            case 'sky': return [['0%', '#0EA5E9'], ['100%', '#38BDF8']];
            case 'pink': return [['0%', '#EC4899'], ['100%', '#F472B6']];
            default: return [['0%', '#6366F1'], ['100%', '#A5B4FC']];
        }
    })();

    return (
        <div className={cn('relative mx-auto', className)} style={{ width: size, height: size }}>
            {halo && (
                <div className={cn(
                    'absolute inset-0 rounded-full blur-3xl opacity-40',
                    a.cardTint
                )} aria-hidden />
            )}
            <svg width={size} height={size} className="relative -rotate-90">
                <defs>
                    <linearGradient id={ringId} x1="0%" y1="0%" x2="100%" y2="100%">
                        {stops.map(([off, color]) => (
                            <stop key={off} offset={off} stopColor={color} />
                        ))}
                    </linearGradient>
                </defs>
                <circle
                    cx={center}
                    cy={center}
                    r={radius}
                    fill="none"
                    stroke="#F1F5F9"
                    strokeWidth={stroke}
                />
                <circle
                    cx={center}
                    cy={center}
                    r={radius}
                    fill="none"
                    stroke={`url(#${ringId})`}
                    strokeWidth={stroke}
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    style={{ transition: 'stroke-dashoffset 1s ease-out' }}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center text-center px-6">
                {children ?? (
                    <>
                        <div className="flex items-baseline tabular-nums leading-none">
                            <span className="text-5xl font-bold tracking-tight text-slate-900">{value}</span>
                            {max != null && (
                                <span className="ml-1 text-base font-semibold text-slate-400">/ {max}</span>
                            )}
                            {unit && (
                                <span className="ml-1 text-base font-semibold text-slate-500">{unit}</span>
                            )}
                        </div>
                        {label && (
                            <p className="mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                {label}
                            </p>
                        )}
                        {showPercent && (
                            <p className="mt-1 text-[10px] font-semibold tabular-nums text-slate-400">
                                {Math.round(percent * 100)}%
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * MultiSegmentRing — multiple ring arcs in one circle (e.g., per leave type).
 * `segments` is an array of { value, max, color } objects.
 */
export function MultiSegmentRing({ segments = [], size = 220, stroke = 10, children, className }) {
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const center = size / 2;

    // Each segment occupies a slice of the circle proportional to its max
    const totalMax = segments.reduce((sum, s) => sum + (s.max || 0), 0) || 1;
    let currentOffset = 0;

    return (
        <div className={cn('relative mx-auto', className)} style={{ width: size, height: size }}>
            <svg width={size} height={size} className="-rotate-90">
                <circle cx={center} cy={center} r={radius} fill="none" stroke="#F1F5F9" strokeWidth={stroke} />
                {segments.map((seg, i) => {
                    const slice = (seg.max / totalMax) * circumference;
                    const filled = Math.max(0, Math.min(1, seg.max > 0 ? seg.value / seg.max : 0));
                    const filledLen = slice * filled;
                    const startOffset = currentOffset;
                    currentOffset += slice;
                    return (
                        <circle
                            key={i}
                            cx={center}
                            cy={center}
                            r={radius}
                            fill="none"
                            stroke={seg.color}
                            strokeWidth={stroke}
                            strokeDasharray={`${filledLen} ${circumference - filledLen}`}
                            strokeDashoffset={-startOffset}
                            strokeLinecap="round"
                            style={{ transition: 'stroke-dasharray 0.6s ease-out' }}
                        />
                    );
                })}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center px-6">
                {children}
            </div>
        </div>
    );
}
