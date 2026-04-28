import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

const PALETTE = ['#10B981', '#0EA5E9', '#F59E0B', '#8B5CF6', '#F43F5E', '#14B8A6', '#EC4899', '#6366F1'];

const AXIS_TICK = {
  fill: '#737373',
  fontSize: 10.5,
  fontFamily: '"Geist Mono", monospace',
  letterSpacing: 0.4,
};

const TOOLTIP_STYLE = {
  background: '#0A0A0A',
  border: '1px solid #1f1f1f',
  borderRadius: 10,
  padding: '8px 12px',
  fontSize: 12,
  color: '#ffffff',
  fontFamily: '"Geist", sans-serif',
};

const LEGEND_STYLE = {
  fontSize: 11,
  letterSpacing: 0.04,
  color: '#737373',
  paddingTop: 12,
};

/**
 * data: array of objects with at minimum a `date` key plus one numeric column per series.
 * series: [{ key, name, color? }]
 */
export default function MultiLineChart({ data, series, height = 280, title }) {
  return (
    <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
      {title && <div className="label-eyebrow mb-1">{title}</div>}
      <div className="mt-3" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
            <CartesianGrid strokeDasharray="2 4" vertical={false} stroke="#EAEAEA" />
            <XAxis dataKey="date" tick={AXIS_TICK} tickLine={false} axisLine={false} />
            <YAxis tick={AXIS_TICK} tickLine={false} axisLine={false} width={48} />
            <Tooltip
              contentStyle={TOOLTIP_STYLE}
              cursor={{ stroke: 'rgba(10,10,10,0.15)', strokeWidth: 1 }}
              labelStyle={{ color: '#ffffff', fontSize: 11, marginBottom: 4 }}
              itemStyle={{ color: '#ffffff', padding: 0 }}
            />
            <Legend wrapperStyle={LEGEND_STYLE} iconType="circle" iconSize={8} />
            {series.map((s, i) => (
              <Line
                key={s.key}
                type="monotone"
                dataKey={s.key}
                name={s.name}
                stroke={s.color ?? PALETTE[i % PALETTE.length]}
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 4, strokeWidth: 0 }}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
