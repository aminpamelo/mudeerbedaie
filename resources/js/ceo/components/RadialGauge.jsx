import { toneColor } from '@/ceo/lib/utils';

/**
 * Circular progress gauge (SVG). Shows a value as a fraction of 100 with a
 * vibrant gradient arc, an optional target tick, and the value in the center.
 * Tone drives the arc color; size/stroke are configurable.
 */
export default function RadialGauge({
  value = 0,
  target = null,
  suffix = '%',
  label = null,
  tone = 'info',
  size = 96,
  stroke = 9,
  centerLabel = null,
}) {
  const clamped = Math.max(0, Math.min(100, Number(value) || 0));
  const r = (size - stroke) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ * (1 - clamped / 100);
  const color = toneColor(tone);
  const gid = `g-${label ?? 'x'}-${size}-${Math.round(clamped)}`.replace(/\s+/g, '');

  // Target tick angle (start at top, clockwise)
  const targetAngle = target != null ? (Math.max(0, Math.min(100, target)) / 100) * 360 - 90 : null;
  const tickInner = r - stroke / 2 - 1;
  const tickOuter = r + stroke / 2 + 1;
  const cx = size / 2;
  const cy = size / 2;
  const tick =
    targetAngle != null
      ? {
          x1: cx + tickInner * Math.cos((targetAngle * Math.PI) / 180),
          y1: cy + tickInner * Math.sin((targetAngle * Math.PI) / 180),
          x2: cx + tickOuter * Math.cos((targetAngle * Math.PI) / 180),
          y2: cy + tickOuter * Math.sin((targetAngle * Math.PI) / 180),
        }
      : null;

  return (
    <div className="flex flex-col items-center gap-1.5">
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90">
          <defs>
            <linearGradient id={gid} x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.65" />
              <stop offset="100%" stopColor={color} />
            </linearGradient>
          </defs>
          <circle cx={cx} cy={cy} r={r} fill="none" stroke="rgba(15,23,42,0.08)" strokeWidth={stroke} />
          <circle
            cx={cx}
            cy={cy}
            r={r}
            fill="none"
            stroke={`url(#${gid})`}
            strokeWidth={stroke}
            strokeLinecap="round"
            strokeDasharray={circ}
            strokeDashoffset={offset}
            className="gauge-arc"
            style={{ '--circ': `${circ}px` }}
          />
          {tick && <line x1={tick.x1} y1={tick.y1} x2={tick.x2} y2={tick.y2} stroke="rgba(15,23,42,0.45)" strokeWidth="2" strokeLinecap="round" />}
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-display text-[20px] leading-none text-ink tabular-nums">
            {centerLabel ?? `${Math.round(clamped)}${suffix}`}
          </span>
        </div>
      </div>
      {label && <span className="text-[11px] font-medium text-muted">{label}</span>}
    </div>
  );
}
