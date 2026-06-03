/**
 * Tiny inline-SVG sparkline. No dependency — draws a smoothed area+line for a
 * numeric series. Renders nothing meaningful for empty/flat input beyond a
 * baseline, which is the honest representation of "no activity".
 */
export default function Sparkline({ data = [], width = 132, height = 34, color = 'var(--accent, #0A0A0A)' }) {
  const series = Array.isArray(data) ? data.map((n) => Number(n) || 0) : [];

  if (series.length === 0) {
    return <svg width={width} height={height} aria-hidden="true" />;
  }

  const max = Math.max(...series, 1);
  const min = Math.min(...series, 0);
  const range = max - min || 1;
  const stepX = series.length > 1 ? width / (series.length - 1) : 0;

  const points = series.map((value, i) => {
    const x = series.length === 1 ? width / 2 : i * stepX;
    const y = height - ((value - min) / range) * (height - 4) - 2;
    return [x, y];
  });

  const linePath = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const areaPath = `${linePath} L${width},${height} L0,${height} Z`;
  const gradientId = `spark-${Math.round(width)}-${series.length}-${Math.round(max)}`;

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} fill="none" aria-hidden="true">
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.18" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={areaPath} fill={`url(#${gradientId})`} />
      <path d={linePath} stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
      {points.length === 1 && <circle cx={points[0][0]} cy={points[0][1]} r="2" fill={color} />}
    </svg>
  );
}
