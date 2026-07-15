import { router } from '@inertiajs/react';
import { Users, Eye, Target, Percent, ShoppingBag, Wallet } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import StatTile from '@/fighter/components/StatTile';
import { formatMoney, formatNumber } from '@/fighter/lib/utils';

function FunnelFilter({ funnels, selected }) {
  const onChange = (e) => {
    const value = e.target.value;
    router.get('/fighter/performance', value ? { funnel: value } : {}, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  };

  return (
    <select
      value={selected ?? ''}
      onChange={onChange}
      className="rounded-xl border border-line bg-white px-3 py-2.5 text-[13px] font-semibold text-ink shadow-sm outline-none focus:border-[var(--color-brand)]"
    >
      <option value="">All funnels</option>
      {funnels.map((f) => (
        <option key={f.uuid} value={f.uuid}>
          {f.name}
        </option>
      ))}
    </select>
  );
}

function HeaderCell({ children, align = 'right' }) {
  return (
    <th className={`px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2 ${align === 'left' ? 'text-left' : 'text-right'}`}>
      {children}
    </th>
  );
}

function Cell({ children, strong, align = 'right' }) {
  return (
    <td className={`px-3 py-3 text-[13.5px] tabular-nums ${align === 'left' ? 'text-left' : 'text-right'} ${strong ? 'font-semibold text-ink' : 'text-ink-2'}`}>
      {children}
    </td>
  );
}

export default function Performance({ funnels = [], selectedFunnel, rows = [], totals }) {
  const hasData = rows.some((r) => r.visitors || r.orders || r.revenue);

  return (
    <FighterLayout
      title="Monthly performance"
      subtitle="Track your funnels' key metrics month by month."
      actions={<FunnelFilter funnels={funnels} selected={selectedFunnel} />}
    >
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatTile icon={Users} label="Visitors" value={formatNumber(totals?.visitors ?? 0)} accent="sky" />
        <StatTile icon={Target} label="Conversions" value={formatNumber(totals?.conversions ?? 0)} accent="emerald" />
        <StatTile icon={Percent} label="Conv. rate" value={`${totals?.conversion_rate ?? 0}%`} accent="brand" />
        <StatTile icon={ShoppingBag} label="Orders" value={formatNumber(totals?.orders ?? 0)} accent="sky" />
        <StatTile icon={Wallet} label="Revenue" value={formatMoney(totals?.revenue ?? 0)} accent="amber" />
      </div>

      <div className="mt-7 overflow-hidden rounded-2xl ring-1 ring-line/70">
        <div className="overflow-x-auto">
          <table className="min-w-full">
            <thead className="bg-surface">
              <tr>
                <HeaderCell align="left">Month</HeaderCell>
                <HeaderCell>Visitors</HeaderCell>
                <HeaderCell>Page views</HeaderCell>
                <HeaderCell>Conversions</HeaderCell>
                <HeaderCell>Conv. rate</HeaderCell>
                <HeaderCell>Orders</HeaderCell>
                <HeaderCell>Revenue</HeaderCell>
              </tr>
            </thead>
            <tbody className="divide-y divide-line/70 bg-white">
              {rows.map((row) => (
                <tr key={row.key} className="transition-colors hover:bg-surface/60">
                  <Cell align="left" strong>{row.label}</Cell>
                  <Cell>{formatNumber(row.visitors)}</Cell>
                  <Cell>{formatNumber(row.pageviews)}</Cell>
                  <Cell>{formatNumber(row.conversions)}</Cell>
                  <Cell>
                    <span className={row.conversion_rate >= 3 ? 'text-emerald-600' : row.conversion_rate > 0 ? 'text-ink-2' : 'text-muted-2'}>
                      {row.conversion_rate}%
                    </span>
                  </Cell>
                  <Cell strong>{formatNumber(row.orders)}</Cell>
                  <Cell strong>{formatMoney(row.revenue)}</Cell>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {!hasData && (
        <p className="mt-4 text-center text-[13px] text-muted">
          No performance data yet — figures will appear here as your funnels get traffic and orders.
        </p>
      )}
    </FighterLayout>
  );
}
