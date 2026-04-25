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

export default function TrendChart({ data, height = 240 }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      <div className="text-sm font-medium">Daily session counts</div>
      <div className="mt-3" style={{ height }}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="date" fontSize={11} />
            <YAxis fontSize={11} allowDecimals={false} />
            <Tooltip />
            <Legend />
            <Bar dataKey="ended" stackId="a" name="Ended" fill="#10b981" />
            <Bar dataKey="missed" stackId="a" name="Missed" fill="#ef4444" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
