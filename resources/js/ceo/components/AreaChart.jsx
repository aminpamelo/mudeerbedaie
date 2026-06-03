/**
 * Responsive SVG area chart for a single numeric series — a scaled-up sparkline
 * with a gradient fill, baseline, last-point marker, and min/max guide values.
 * Used for department trend sections.
 */
import { useT } from '@/ceo/lib/i18n';

export default function AreaChart({ data = [], color = 'var(--accent, #6366F1)', height = 140 }) {
  const t = useT();
  const series = Array.isArray(data) ? data.map((n) => Number(n) || 0) : [];
  const W = 600;
  const H = height;
  const pad = 10;

  if (series.length === 0) {
    return <div className="grid h-[140px] place-items-center text-[12px] text-muted">{t('no_data_period')}</div>;
  }

  const max = Math.max(...series, 1);
  const min = Math.min(...series, 0);
  const range = max - min || 1;
  const stepX = series.length > 1 ? (W - pad * 2) / (series.length - 1) : 0;

  const points = series.map((value, i) => {
    const x = series.length === 1 ? W / 2 : pad + i * stepX;
    const y = pad + (H - pad * 2) * (1 - (value - min) / range);
    return [x, y];
  });

  const linePath = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const areaPath = `${linePath} L${points[points.length - 1][0]},${H} L${points[0][0]},${H} Z`;
  const gid = `area-${Math.round(max)}-${series.length}`;
  const last = points[points.length - 1];
  const peak = Math.max(...series);

  return (
    <div className="relative">
      <svg width="100%" height={H} viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" fill="none" className="block" aria-hidden="true">
        <defs>
          <linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity="0.28" />
            <stop offset="100%" stopColor={color} stopOpacity="0.02" />
          </linearGradient>
        </defs>
        {[0.5].map((f) => (
          <line key={f} x1={pad} y1={pad + (H - pad * 2) * f} x2={W - pad} y2={pad + (H - pad * 2) * f} stroke="rgba(15,23,42,0.06)" strokeWidth="1" strokeDasharray="3 4" />
        ))}
        <path d={areaPath} fill={`url(#${gid})`} />
        <path d={linePath} stroke={color} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
        <circle cx={last[0]} cy={last[1]} r="3.5" fill={color} />
      </svg>
      <div className="pointer-events-none absolute right-2 top-1 font-mono text-[10px] text-muted-2">{t('peak')} {peak}</div>
    </div>
  );
}
