import { Link, router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Megaphone, Receipt, Wallet, Gauge, Scale, Briefcase, ArrowRight } from 'lucide-react';
import FighterLayout from '@/fighter/layouts/FighterLayout';
import StatTile from '@/fighter/components/StatTile';
import { cn, formatMoney, formatNumber, timeAgo } from '@/fighter/lib/utils';

const RANGES = [7, 30, 90];
const AXIS_TICK = { fill: '#94a3b8', fontSize: 11, fontWeight: 600 };
const SPEND_COLOR = 'var(--color-sky)';
const SALES_COLOR = 'var(--color-brand)';

function RangeSwitch({ days }) {
  const go = (value) =>
    router.get('/fighter/daily-reporting', { days: value }, { preserveScroll: true, preserveState: true, replace: true });

  return (
    <div className="flex gap-1 rounded-xl bg-surface p-1 ring-1 ring-line/70">
      {RANGES.map((value) => (
        <button
          key={value}
          type="button"
          onClick={() => go(value)}
          className={cn(
            'rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition-colors',
            days === value ? 'bg-[var(--color-brand)] text-white shadow-sm' : 'text-ink-2 hover:bg-white'
          )}
        >
          {value}d
        </button>
      ))}
    </div>
  );
}

function dayLabel(iso, opts = { day: 'numeric', month: 'short' }) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', opts);
}

function roasTone(roas) {
  if (roas === null || roas === undefined) return undefined;
  return roas >= 1 ? 'good' : 'bad';
}

function RoasText({ value }) {
  if (value === null || value === undefined) return <span className="text-muted-2">—</span>;
  return <span className={value >= 1 ? 'text-emerald-600' : 'text-rose-600'}>{value.toFixed(2)}×</span>;
}

function NetText({ value }) {
  const cls = value > 0 ? 'text-emerald-600' : value < 0 ? 'text-rose-600' : 'text-muted-2';
  return <span className={cls}>{value < 0 ? `−${formatMoney(Math.abs(value))}` : formatMoney(value)}</span>;
}

function ChartTooltip({ active, payload }) {
  if (!active || !payload?.length) return null;
  const row = payload[0].payload;
  return (
    <div className="rounded-xl bg-ink px-3 py-2 text-white shadow-lg">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-white/60">{dayLabel(row.day, { weekday: 'short', day: 'numeric', month: 'short' })}</div>
      <div className="mt-1 text-[12px]"><span className="text-sky-300">Spend</span> {formatMoney(row.spend)}</div>
      <div className="text-[12px]"><span className="text-orange-300">Sales</span> {formatMoney(row.sales)}</div>
      <div className="text-[11px] text-white/70">{formatNumber(row.orders)} orders · ROAS {row.roas === null ? '—' : `${row.roas.toFixed(2)}×`}</div>
    </div>
  );
}

function SpendSalesChart({ daily }) {
  const data = [...daily].reverse().map((row) => ({ ...row, label: dayLabel(row.day) }));
  return (
    <div className="h-[260px]">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 4, left: -12, bottom: 0 }} barGap={2}>
          <CartesianGrid strokeDasharray="2 5" vertical={false} stroke="#eef2f7" />
          <XAxis dataKey="label" tick={AXIS_TICK} tickLine={false} axisLine={false} minTickGap={18} />
          <YAxis
            tick={AXIS_TICK}
            tickLine={false}
            axisLine={false}
            width={44}
            tickFormatter={(v) => (v >= 1000 ? `${Math.round(v / 1000)}k` : v)}
          />
          <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(234,88,12,0.06)' }} />
          <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12, fontWeight: 600, paddingTop: 8 }} />
          <Bar name="Ad spend" dataKey="spend" fill={SPEND_COLOR} radius={[4, 4, 0, 0]} maxBarSize={18} />
          <Bar name="Sales" dataKey="sales" fill={SALES_COLOR} radius={[4, 4, 0, 0]} maxBarSize={18} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

function Section({ title, subtitle, children }) {
  return (
    <section className="mt-7 overflow-hidden rounded-2xl ring-1 ring-line/70">
      <div className="border-b border-line/70 bg-white px-4 py-3.5">
        <h2 className="text-[15px] font-semibold text-ink">{title}</h2>
        {subtitle && <p className="mt-0.5 text-[12.5px] text-muted">{subtitle}</p>}
      </div>
      {children}
    </section>
  );
}

function Th({ children, align = 'right' }) {
  return (
    <th className={cn('whitespace-nowrap px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.04em] text-muted-2', align === 'left' ? 'text-left' : 'text-right')}>
      {children}
    </th>
  );
}

function Td({ children, strong, align = 'right' }) {
  return (
    <td className={cn('whitespace-nowrap px-3 py-3 text-[13.5px] tabular-nums', align === 'left' ? 'text-left' : 'text-right', strong ? 'font-semibold text-ink' : 'text-ink-2')}>
      {children}
    </td>
  );
}

/** Spend / spend+SST / sales / orders / ROAS / net table shared by the monthly + daily breakdowns. */
function PnlTable({ rows, labelHeader, labelOf, rowKey }) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full">
        <thead className="bg-surface">
          <tr>
            <Th align="left">{labelHeader}</Th>
            <Th>Ad spend</Th>
            <Th>Spend + SST</Th>
            <Th>Sales</Th>
            <Th>Orders</Th>
            <Th>ROAS</Th>
            <Th>Net</Th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line/70 bg-white">
          {rows.map((row) => (
            <tr key={rowKey(row)} className="transition-colors hover:bg-surface/60">
              <Td align="left" strong>{labelOf(row)}</Td>
              <Td>{formatMoney(row.spend)}</Td>
              <Td>{formatMoney(row.spend_with_sst)}</Td>
              <Td strong>{formatMoney(row.sales)}</Td>
              <Td>{formatNumber(row.orders)}</Td>
              <Td><RoasText value={row.roas} /></Td>
              <Td strong><NetText value={row.net} /></Td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ConnectBanner() {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[var(--color-brand-soft)] p-4 ring-1 ring-orange-600/15">
      <div className="flex items-center gap-3">
        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-[var(--color-brand)] ring-1 ring-orange-600/15">
          <Briefcase className="h-5 w-5" strokeWidth={2} />
        </div>
        <div>
          <div className="text-[13.5px] font-semibold text-ink">Connect your Business Manager to see ad spend</div>
          <div className="text-[12.5px] text-muted">Sales already show below — link your Facebook BM to get spend, ROAS and net profit.</div>
        </div>
      </div>
      <Link
        href="/fighter/business-manager"
        className="flex items-center gap-1.5 rounded-xl bg-[var(--color-brand)] px-3.5 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[var(--color-brand-ink)]"
      >
        Connect now <ArrowRight className="h-4 w-4" strokeWidth={2.2} />
      </Link>
    </div>
  );
}

export default function DailyReporting({ report, connectionsCount = 0, lastSyncedAt }) {
  const { days, totals, daily = [], monthly = [], by_funnel: byFunnel = [], sst_rate: sstRate } = report;
  const sstPct = Math.round((sstRate ?? 0.08) * 100);
  const activeDays = daily.filter((row) => row.spend > 0 || row.sales > 0 || row.orders > 0);

  return (
    <FighterLayout
      title="Daily reporting"
      subtitle={`Ad spend, spend incl. ${sstPct}% SST, and the sales each day drove — with ROAS & net.`}
      actions={<RangeSwitch days={days} />}
    >
      {connectionsCount === 0 && <ConnectBanner />}

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatTile icon={Megaphone} label={`Ad spend (${days}d)`} value={formatMoney(totals.spend)} accent="sky" />
        <StatTile icon={Receipt} label={`Spend + SST (${sstPct}%)`} value={formatMoney(totals.spend_with_sst)} accent="sky" />
        <StatTile icon={Wallet} label="Sales" value={formatMoney(totals.sales)} sub={`${formatNumber(totals.orders)} orders`} accent="brand" />
        <StatTile
          icon={Gauge}
          label="ROAS"
          value={totals.roas === null ? '—' : `${totals.roas.toFixed(2)}×`}
          accent="amber"
          tone={roasTone(totals.roas)}
        />
        <StatTile
          icon={Scale}
          label="Net (sales − spend+SST)"
          value={totals.net < 0 ? `−${formatMoney(Math.abs(totals.net))}` : formatMoney(totals.net)}
          accent={totals.net < 0 ? 'rose' : 'emerald'}
          tone={totals.net > 0 ? 'good' : totals.net < 0 ? 'bad' : undefined}
        />
      </div>

      {connectionsCount > 0 && lastSyncedAt && (
        <p className="mt-3 text-[12px] text-muted">
          Ad spend last synced {timeAgo(lastSyncedAt)} · syncs automatically every night ·{' '}
          <Link href="/fighter/business-manager" className="font-semibold text-[var(--color-brand-ink)] hover:underline">Manage Business Manager</Link>
        </p>
      )}

      <Section title="Daily spend vs sales">
        <div className="bg-white px-3 py-4">
          <SpendSalesChart daily={daily} />
        </div>
      </Section>

      <Section title="Monthly performance" subtitle="Every calendar month in this window, most recent first.">
        <PnlTable rows={monthly} labelHeader="Month" labelOf={(row) => row.month_label} rowKey={(row) => row.month} />
      </Section>

      <Section title="By funnel" subtitle="Sales per funnel, with spend & ROAS from the ad account each funnel is linked to.">
        {byFunnel.length === 0 ? (
          <p className="bg-white px-4 py-8 text-center text-[13px] text-muted">No funnel sales or linked spend in this window.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full">
              <thead className="bg-surface">
                <tr>
                  <Th align="left">Funnel</Th>
                  <Th>Sales</Th>
                  <Th>Orders</Th>
                  <Th>Linked spend</Th>
                  <Th>Spend + SST</Th>
                  <Th>ROAS</Th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line/70 bg-white">
                {byFunnel.map((row) => (
                  <tr key={row.funnel_uuid} className="transition-colors hover:bg-surface/60">
                    <Td align="left" strong>{row.funnel_name}</Td>
                    <Td strong>{formatMoney(row.sales)}</Td>
                    <Td>{formatNumber(row.orders)}</Td>
                    <Td>{row.linked_spend === null ? <span className="text-muted-2">Not linked</span> : formatMoney(row.linked_spend)}</Td>
                    <Td>{row.linked_spend_with_sst === null ? <span className="text-muted-2">—</span> : formatMoney(row.linked_spend_with_sst)}</Td>
                    <Td><RoasText value={row.roas} /></Td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Section>

      <Section title="Daily breakdown" subtitle="Day-by-day spend against sales, most recent first.">
        {activeDays.length === 0 ? (
          <p className="bg-white px-4 py-8 text-center text-[13px] text-muted">No spend or sales in this window yet.</p>
        ) : (
          <PnlTable
            rows={activeDays}
            labelHeader="Date"
            labelOf={(row) => dayLabel(row.day, { weekday: 'short', day: 'numeric', month: 'short' })}
            rowKey={(row) => row.day}
          />
        )}
      </Section>
    </FighterLayout>
  );
}
