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

const PALETTE = ['#2563eb', '#16a34a', '#f97316', '#9333ea', '#dc2626', '#0891b2', '#ca8a04', '#db2777'];

/**
 * data: array of objects with at minimum a `date` key plus one numeric column per series.
 * series: [{ key: string, name: string, color?: string }]
 */
export default function MultiLineChart({ data, series, height = 280, title }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      {title && <div className="text-sm font-medium">{title}</div>}
      <div className="mt-3" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="date" fontSize={11} />
            <YAxis fontSize={11} />
            <Tooltip />
            <Legend />
            {series.map((s, i) => (
              <Line
                key={s.key}
                type="monotone"
                dataKey={s.key}
                name={s.name}
                stroke={s.color ?? PALETTE[i % PALETTE.length]}
                strokeWidth={2}
                dot={false}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
