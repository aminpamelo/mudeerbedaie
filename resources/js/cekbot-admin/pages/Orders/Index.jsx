import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ShoppingBag, Search, Wallet, Clock, CalendarDays, ChevronDown, Workflow, CreditCard, CircleDollarSign, ExternalLink, X, Sparkles } from 'lucide-react';
import CekbotLayout from '@/cekbot-admin/layouts/CekbotLayout';
import { Card, Badge, Select, Input, EmptyState } from '@/cekbot-admin/components/Ui';
import { cn, formatPhone, formatDate, clockTime } from '@/cekbot-admin/lib/utils';

const PAYMENT_METHODS = {
  bank_transfer: { label: 'Transfer', color: 'blue' },
  cod: { label: 'COD', color: 'amber' },
};

const PAYMENT_STATUSES = {
  pending: { label: 'Belum bayar', color: 'amber' },
  paid: { label: 'Dibayar', color: 'emerald' },
  failed: { label: 'Gagal', color: 'red' },
  refunded: { label: 'Refund', color: 'slate' },
};

function money(currency, amount) {
  return `${currency || 'RM'} ${Number(amount || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function Stat({ icon: Icon, label, value, tint }) {
  return (
    <Card className="flex items-center gap-3.5 p-4">
      <span className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-xl', tint)}>
        <Icon className="h-5 w-5" strokeWidth={2} />
      </span>
      <div className="min-w-0">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-white/35">{label}</p>
        <p className="truncate text-[22px] font-bold leading-tight tabular-nums text-white">{value}</p>
      </div>
    </Card>
  );
}

function FilterSelect({ icon: Icon, active, className, children, ...rest }) {
  return (
    <div className={cn('relative', className)}>
      {Icon && <Icon className={cn('pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2', active ? 'text-emerald-300' : 'text-white/35')} />}
      <Select {...rest} className={cn('w-full pl-9 pr-9', active && 'ring-emerald-500/40 text-white')}>{children}</Select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35" />
    </div>
  );
}

function ActiveChip({ label, onClear }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/12 py-1 pl-2.5 pr-1.5 text-[12px] font-medium text-emerald-200 ring-1 ring-inset ring-emerald-400/20">
      {label}
      <button type="button" onClick={onClear} className="grid h-4 w-4 place-items-center rounded text-emerald-200/70 hover:bg-emerald-400/20 hover:text-white" aria-label={`Buang tapisan ${label}`}>
        <X className="h-3 w-3" strokeWidth={2.5} />
      </button>
    </span>
  );
}

export default function Index() {
  const { props } = usePage();
  const orders = props.orders ?? { data: [], links: [] };
  const flows = props.flows ?? [];
  const stats = props.stats ?? {};
  const filters = props.filters ?? {};
  const [search, setSearch] = useState(filters.search ?? '');
  const first = useRef(true);

  function visit(params) {
    router.get(route('cekbot.orders'), { search, flow: filters.flow, payment: filters.payment, status: filters.status, ...params }, { preserveState: true, preserveScroll: true, replace: true });
  }

  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => visit({ search }), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const hasActiveFilter = Boolean(filters.search || filters.flow || filters.payment || filters.status);
  const flowName = flows.find((f) => String(f.id) === String(filters.flow))?.name;

  function clearFilters() {
    setSearch('');
    visit({ search: '', flow: null, payment: null, status: null });
  }

  return (
    <CekbotLayout title="Order" subtitle="Semua order yang dicipta oleh bot WhatsApp">
      <Head title="Order" />

      <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat icon={ShoppingBag} label="Jumlah order" value={stats.total ?? 0} tint="bg-emerald-500/15 text-emerald-300" />
        <Stat icon={Wallet} label="Jumlah jualan" value={money('RM', stats.revenue)} tint="bg-sky-500/15 text-sky-300" />
        <Stat icon={Clock} label="Belum bayar" value={stats.pending_payment ?? 0} tint="bg-amber-500/15 text-amber-300" />
        <Stat icon={CalendarDays} label="Hari ini" value={stats.today ?? 0} tint="bg-violet-500/15 text-violet-300" />
      </div>

      <div className="mb-4 space-y-3">
        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari no. order, nama atau telefon…" className="pl-9" />
          </div>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:flex lg:shrink-0">
            <FilterSelect icon={Workflow} active={!!filters.flow} value={filters.flow ?? ''} onChange={(e) => visit({ flow: e.target.value || null })} className="lg:w-48">
              <option value="">Semua flow</option>
              {flows.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
            </FilterSelect>
            <FilterSelect icon={CreditCard} active={!!filters.payment} value={filters.payment ?? ''} onChange={(e) => visit({ payment: e.target.value || null })} className="lg:w-40">
              <option value="">Semua cara bayar</option>
              {Object.entries(PAYMENT_METHODS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </FilterSelect>
            <FilterSelect icon={CircleDollarSign} active={!!filters.status} value={filters.status ?? ''} onChange={(e) => visit({ status: e.target.value || null })} className="lg:w-40">
              <option value="">Semua status bayar</option>
              {Object.entries(PAYMENT_STATUSES).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </FilterSelect>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {hasActiveFilter ? (
            <>
              <span className="text-[12px] font-medium text-white/40">Tapisan:</span>
              {filters.search && <ActiveChip label={`"${filters.search}"`} onClear={() => setSearch('')} />}
              {filters.flow && <ActiveChip label={flowName ?? 'Flow'} onClear={() => visit({ flow: null })} />}
              {filters.payment && <ActiveChip label={PAYMENT_METHODS[filters.payment]?.label ?? filters.payment} onClear={() => visit({ payment: null })} />}
              {filters.status && <ActiveChip label={PAYMENT_STATUSES[filters.status]?.label ?? filters.status} onClear={() => visit({ status: null })} />}
              <button type="button" onClick={clearFilters} className="text-[12px] font-medium text-white/45 underline-offset-2 hover:text-white hover:underline">Kosongkan semua</button>
            </>
          ) : (
            <span className="text-[12px] text-white/30">Tiada tapisan digunakan</span>
          )}
          <span className="ml-auto text-[12px] text-white/45"><b className="tabular-nums text-white/75">{orders.total ?? orders.data.length}</b> order</span>
        </div>
      </div>

      {orders.data.length === 0 ? (
        <EmptyState
          icon={ShoppingBag}
          title={hasActiveFilter ? 'Tiada order sepadan' : 'Belum ada order dari bot'}
          hint={hasActiveFilter ? 'Cuba ubah atau kosongkan tapisan.' : 'Bila pelanggan lengkapkan flow jualan di WhatsApp, order akan muncul di sini secara automatik.'}
        />
      ) : (
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[13px]">
              <thead>
                <tr className="border-b border-white/8 text-[11px] uppercase tracking-wide text-white/40">
                  <th className="px-4 py-3 font-semibold">Order</th>
                  <th className="px-4 py-3 font-semibold">Pelanggan</th>
                  <th className="px-4 py-3 font-semibold">Pakej</th>
                  <th className="px-4 py-3 text-right font-semibold">Jumlah</th>
                  <th className="px-4 py-3 font-semibold">Bayaran</th>
                  <th className="px-4 py-3 font-semibold">Flow</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody>
                {orders.data.map((order) => {
                  const method = PAYMENT_METHODS[order.payment_method];
                  const payStatus = PAYMENT_STATUSES[order.payment_status];
                  const waNumber = String(order.chat_id || order.customer_phone || '').replace(/\D/g, '');

                  return (
                    <tr key={order.id} className="border-b border-white/5 align-top last:border-0 hover:bg-white/[0.03]">
                      <td className="px-4 py-3">
                        <div className="font-semibold tabular-nums text-white">{order.order_number}</div>
                        <div className="mt-0.5 whitespace-nowrap text-[11.5px] text-white/40">{formatDate(order.created_at)} · {clockTime(order.created_at)}</div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-semibold text-white">{order.customer_name || '—'}</div>
                        {order.customer_phone && (
                          <a href={`https://wa.me/${waNumber}`} target="_blank" rel="noopener" className="text-[12px] tabular-nums text-sky-300 hover:underline">
                            {formatPhone(order.customer_phone)}
                          </a>
                        )}
                        {order.address && <div className="mt-1 max-w-[260px] text-[11.5px] leading-snug text-white/40">{order.address}</div>}
                      </td>
                      <td className="px-4 py-3 text-white/80">
                        {order.items.length === 0 ? '—' : order.items.map((item, i) => (
                          <div key={i}>{item.name}{item.quantity > 1 && <span className="text-white/40"> × {item.quantity}</span>}</div>
                        ))}
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-white">{money(order.currency, order.total)}</td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-1">
                          {method ? <Badge color={method.color}>{method.label}</Badge> : <span className="text-white/25">—</span>}
                          {payStatus && <Badge color={payStatus.color}>{payStatus.label}</Badge>}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-white/60">{order.flow_name ?? '—'}</div>
                        {order.driver === 'ai' && (
                          <span className="mt-0.5 inline-flex items-center gap-1 text-[11px] text-violet-300/80"><Sparkles className="h-3 w-3" /> AI</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <a href={order.url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 whitespace-nowrap text-[12px] font-medium text-white/50 hover:text-emerald-300">
                          <ExternalLink className="h-3.5 w-3.5" /> Lihat
                        </a>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {orders.links && orders.links.length > 3 && (
            <div className="flex flex-wrap items-center justify-center gap-1 border-t border-white/8 px-4 py-3">
              {orders.links.map((link, i) => (
                <button
                  key={i}
                  type="button"
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                  className={cn(
                    'min-w-[32px] rounded-lg px-2.5 py-1.5 text-[12.5px] font-medium transition-colors',
                    link.active ? 'bg-emerald-500/20 text-emerald-300' : 'text-white/50 hover:bg-white/8 hover:text-white',
                    !link.url && 'cursor-not-allowed opacity-30'
                  )}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          )}
        </Card>
      )}
    </CekbotLayout>
  );
}
