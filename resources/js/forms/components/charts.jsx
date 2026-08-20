/**
 * Dependency-free chart primitives for the Forms reports. Kept intentionally
 * small — an SVG area sparkline and a labelled horizontal bar — so the module
 * ships no extra charting library.
 */

/**
 * Area sparkline for a series of {date, count} points.
 */
export function Sparkline({ points = [], height = 72 }) {
  const width = 640;
  const counts = points.map((p) => p.count);
  const max = Math.max(1, ...counts);
  const n = points.length;

  if (n === 0) {
    return <div className="text-sm text-muted">Tiada data.</div>;
  }

  const stepX = n > 1 ? width / (n - 1) : 0;
  const y = (c) => height - (c / max) * (height - 8) - 2;
  const coords = points.map((p, i) => [i * stepX, y(p.count)]);
  const line = coords.map(([x, yy], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${yy.toFixed(1)}`).join(' ');
  const area = `${line} L${width},${height} L0,${height} Z`;

  return (
    <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-20 w-full">
      <defs>
        <linearGradient id="spark-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#059669" stopOpacity="0.22" />
          <stop offset="100%" stopColor="#059669" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={area} fill="url(#spark-fill)" />
      <path d={line} fill="none" stroke="#059669" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

/**
 * Labelled horizontal bar with count + percentage.
 */
export function Bar({ label, count, pct, tone = 'brand' }) {
  const fill = tone === 'amber' ? '#F59E0B' : '#059669';
  return (
    <div className="flex items-center gap-3">
      <div className="w-32 shrink-0 truncate text-[13px] text-ink" title={label}>
        {label}
      </div>
      <div className="relative h-6 flex-1 overflow-hidden rounded-md bg-slate-100">
        <div className="h-full rounded-md transition-all" style={{ width: `${Math.max(pct, count > 0 ? 4 : 0)}%`, background: fill + '33' }} />
        <div className="absolute inset-y-0 left-0 rounded-md" style={{ width: `${Math.max(pct, count > 0 ? 4 : 0)}%`, borderRight: count > 0 ? `2px solid ${fill}` : 'none' }} />
      </div>
      <div className="w-10 shrink-0 text-right text-[13px] font-semibold tabular-nums text-ink">{count}</div>
    </div>
  );
}
