import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

const PALETTE = ['#10b981', '#94a3b8', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'];

/**
 * data: array of objects with at minimum an x-axis key plus one numeric column per series.
 * series: [{ key, name, color? }]
 * xKey: string — defaults to 'date'
 */
export default function StackedBarChart({ data, series, height = 280, title, xKey = 'date' }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      {title && <div className="text-sm font-medium">{title}</div>}
      <div className="mt-3" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey={xKey} fontSize={11} />
            <YAxis fontSize={11} allowDecimals={false} />
            <Tooltip />
            <Legend />
            {series.map((s, i) => (
              <Bar
                key={s.key}
                dataKey={s.key}
                stackId="a"
                name={s.name}
                fill={s.color ?? PALETTE[i % PALETTE.length]}
              />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
