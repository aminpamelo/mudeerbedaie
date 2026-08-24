import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import {
  Inbox,
  FileText,
  Users,
  TrendingUp,
  BarChart3,
  Trophy,
  ExternalLink,
} from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import { Sparkline, Bar } from '../components/charts';

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

const STATUS_STYLES = {
  published: 'bg-emerald-50 text-emerald-600',
  draft: 'bg-slate-100 text-slate-500',
  closed: 'bg-rose-50 text-rose-500',
};
const STATUS_LABELS = { published: 'Diterbitkan', draft: 'Draf', closed: 'Ditutup' };

export default function Reports({
  stats,
  timeseries = [],
  top_forms = [],
  categories = [],
  statuses = [],
  filters = {},
  range_label = '30 Hari',
  form_options = [],
  category_options = [],
}) {
  const first = timeseries[0]?.date;
  const last = timeseries[timeseries.length - 1]?.date;

  const [form, setForm] = useState(filters.form || '');
  const [category, setCategory] = useState(filters.category || '');
  const [from, setFrom] = useState(filters.from || '');
  const [to, setTo] = useState(filters.to || '');

  const apply = (e) => {
    e?.preventDefault();
    router.get('/forms/reports', { form, category, from, to }, { preserveState: true, preserveScroll: true });
  };

  const reset = () => {
    setForm('');
    setCategory('');
    setFrom('');
    setTo('');
    router.get('/forms/reports');
  };

  const hasFilters = filters.form || filters.category || filters.from || filters.to;

  return (
    <FormsLayout
      title="Laporan"
      subtitle="Ringkasan menyeluruh submission borang di seluruh sistem."
    >
      <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div className="min-w-[180px] flex-1">
          <label className="mb-1 block text-xs font-medium text-slate-600">Borang</label>
          <select className="w-full rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={form} onChange={(e) => setForm(e.target.value)}>
            <option value="">Semua borang</option>
            {form_options.map((f) => (
              <option key={f.id} value={f.id}>{f.title}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
          <select className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">Semua kategori</option>
            {category_options.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Dari</label>
          <input type="date" className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Hingga</label>
          <input type="date" className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
        <button type="submit" className="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
          Tapis
        </button>
        {hasFilters && (
          <button type="button" onClick={reset} className="rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-800">
            Set semula
          </button>
        )}
      </form>

      {stats.total_forms === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-20 text-center">
          <BarChart3 className="mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm font-semibold text-ink">
            {hasFilters ? 'Tiada borang sepadan dengan tapisan' : 'Belum ada data untuk dilaporkan'}
          </p>
          <p className="mt-1 text-sm text-muted">
            {hasFilters ? 'Cuba longgarkan tapisan atau set semula.' : 'Cipta borang dan mula kumpul jawapan untuk melihat laporan.'}
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Kpi icon={Inbox} tone="brand" label="Jumlah Jawapan" value={stats.total_submissions} />
            <Kpi icon={FileText} tone="slate" label="Jumlah Borang" value={stats.total_forms} sub={`${stats.published} diterbitkan`} />
            <Kpi icon={TrendingUp} tone="brand" label="7 Hari Lepas" value={stats.last7} sub={`${stats.today} hari ini`} />
            <Kpi icon={Users} tone="amber" label="Pencipta" value={stats.creators} sub={`~${stats.avg_per_day} / hari`} />
          </div>

          {/* 30-day trend */}
          <div className="rounded-2xl border border-line bg-white p-5 card-soft">
            <div className="mb-3 flex items-center justify-between">
              <div>
                <h3 className="text-[15px] font-semibold text-ink">Submission · {range_label}</h3>
                <p className="text-[12.5px] text-muted">{fmtDate(first)} — {fmtDate(last)}</p>
              </div>
              <span className="rounded-full bg-brand-soft px-2.5 py-0.5 text-[11.5px] font-semibold text-brand">
                {timeseries.reduce((s, p) => s + p.count, 0)} jawapan
              </span>
            </div>
            <Sparkline points={timeseries} />
            <div className="mt-1 flex justify-between text-[11px] text-slate-400">
              <span>{fmtDate(first)}</span>
              <span>{fmtDate(last)}</span>
            </div>
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            {/* Top forms */}
            <div className="rounded-2xl border border-line bg-white p-5 card-soft">
              <div className="mb-4 flex items-center gap-2">
                <Trophy className="h-4 w-4 text-amber-500" />
                <h3 className="text-[15px] font-semibold text-ink">Borang Paling Aktif</h3>
              </div>
              {top_forms.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted">Tiada borang lagi.</p>
              ) : (
                <ul className="space-y-3">
                  {top_forms.map((f, i) => (
                    <li key={f.id}>
                      <div className="mb-1 flex items-center justify-between gap-2">
                        <Link
                          href={`/forms/${f.id}/report`}
                          className="flex min-w-0 items-center gap-2 text-[13.5px] font-medium text-ink hover:text-brand"
                        >
                          <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-bold tabular-nums text-slate-500">
                            {i + 1}
                          </span>
                          <span className="truncate" title={f.title}>{f.title}</span>
                          <ExternalLink className="h-3 w-3 shrink-0 text-slate-300" />
                        </Link>
                        <span className="shrink-0 text-[13px] font-semibold tabular-nums text-ink">{f.submissions}</span>
                      </div>
                      <div className="relative ml-7 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div className="h-full rounded-full bg-brand/70" style={{ width: `${Math.max(f.pct, f.submissions > 0 ? 4 : 0)}%` }} />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {/* Status + category */}
            <div className="space-y-6">
              <div className="rounded-2xl border border-line bg-white p-5 card-soft">
                <h3 className="mb-4 text-[15px] font-semibold text-ink">Status Borang</h3>
                <div className="grid grid-cols-3 gap-3">
                  {statuses.map((s) => (
                    <div key={s.status} className="rounded-xl border border-line bg-surface px-3 py-3 text-center">
                      <p className="text-[22px] font-bold tabular-nums text-ink">{s.count}</p>
                      <span className={`mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium ${STATUS_STYLES[s.status] || 'bg-slate-100 text-slate-500'}`}>
                        {s.label}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="rounded-2xl border border-line bg-white p-5 card-soft">
                <h3 className="mb-4 text-[15px] font-semibold text-ink">Jawapan Ikut Kategori</h3>
                {categories.length === 0 ? (
                  <p className="py-4 text-center text-sm text-muted">Tiada kategori.</p>
                ) : (
                  <div className="space-y-2.5">
                    {categories.map((c, i) => (
                      <Bar key={i} label={c.name} count={c.submissions} pct={c.pct} />
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </FormsLayout>
  );
}

function Kpi({ icon: Icon, label, value, sub, tone }) {
  const tones = {
    brand: 'bg-brand-soft text-brand',
    amber: 'bg-amber-50 text-amber-500',
    slate: 'bg-slate-100 text-slate-500',
  };
  return (
    <div className="rounded-2xl border border-line bg-white p-4 card-soft">
      <div className="flex items-center justify-between">
        <p className="text-[12.5px] font-medium text-muted">{label}</p>
        <span className={`grid h-7 w-7 place-items-center rounded-lg ${tones[tone]}`}>
          <Icon className="h-4 w-4" />
        </span>
      </div>
      <p className="mt-2 text-[28px] font-bold leading-none tracking-[-0.02em] tabular-nums text-ink">{value}</p>
      {sub && <p className="mt-1.5 text-[11.5px] text-muted">{sub}</p>}
    </div>
  );
}
