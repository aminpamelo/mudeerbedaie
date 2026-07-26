import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatMoney, formatNumber } from '@/fighter/lib/utils';

const AXIS_TICK = { fill: '#94a3b8', fontSize: 11, fontWeight: 600 };

function ChartTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null;
  const row = payload[0].payload;
  return (
    <div className="rounded-xl bg-ink px-3 py-2 text-white shadow-lg">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-white/60">{label}</div>
      <div className="mt-0.5 text-[13px] font-bold">{formatMoney(row.revenue)}</div>
      <div className="text-[11px] text-white/70">{formatNumber(row.orders)} orders</div>
    </div>
  );
}

/** 7-day revenue bar chart. `data` = [{ label, orders, revenue }]. */
export default function WeeklyChart({ data = [], height = 200 }) {
  return (
    <div style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 4, left: -18, bottom: 0 }}>
          <CartesianGrid strokeDasharray="2 5" vertical={false} stroke="#eef2f7" />
          <XAxis dataKey="label" tick={AXIS_TICK} tickLine={false} axisLine={false} />
          <YAxis
            allowDecimals={false}
            tick={AXIS_TICK}
            tickLine={false}
            axisLine={false}
            width={40}
            tickFormatter={(v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : v)}
          />
          <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(234,88,12,0.06)' }} />
          <Bar dataKey="revenue" fill="var(--color-brand)" radius={[5, 5, 0, 0]} maxBarSize={40} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
