import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Search, FileText, Users, Inbox, CheckCircle2, ExternalLink } from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import StatusBadge from '../components/StatusBadge';

function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

function StatCard({ icon: Icon, label, value, tint }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl border border-line bg-white p-4 shadow-sm">
      <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${tint}`}>
        <Icon className="h-5 w-5" />
      </div>
      <div>
        <p className="text-xl font-bold text-ink">{value}</p>
        <p className="text-xs text-muted">{label}</p>
      </div>
    </div>
  );
}

export default function Admin({ forms = [], meta, filters = {}, categories = [], stats }) {
  const [search, setSearch] = useState(filters.search || '');
  const [category, setCategory] = useState(filters.category || '');
  const [status, setStatus] = useState(filters.status || '');

  const apply = (e) => {
    e?.preventDefault();
    router.get('/forms/admin', { search, category, status }, { preserveState: true, preserveScroll: true });
  };

  return (
    <FormsLayout title="Semua Borang" subtitle="Pandangan admin — setiap borang dalam sistem dan penciptanya.">
      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard icon={FileText} label="Jumlah borang" value={stats.total_forms} tint="bg-brand-soft text-brand-ink" />
        <StatCard icon={CheckCircle2} label="Diterbitkan" value={stats.published} tint="bg-emerald-100 text-emerald-700" />
        <StatCard icon={Inbox} label="Jumlah jawapan" value={stats.total_submissions} tint="bg-sky-100 text-sky-700" />
        <StatCard icon={Users} label="Pencipta" value={stats.creators} tint="bg-amber-100 text-amber-700" />
      </div>

      <form onSubmit={apply} className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div className="min-w-[200px] flex-1">
          <label className="mb-1 block text-xs font-medium text-slate-600">Cari tajuk</label>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="w-full rounded-lg border border-line py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
              placeholder="Tajuk borang…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Kategori</label>
          <select className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">Semua</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Status</label>
          <select className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">Semua</option>
            <option value="draft">Draf</option>
            <option value="published">Diterbitkan</option>
            <option value="closed">Ditutup</option>
          </select>
        </div>
        <button type="submit" className="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
          Tapis
        </button>
      </form>

      <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
        <div className="overflow-x-auto scroll-thin">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <th className="px-4 py-3">Borang</th>
                <th className="px-4 py-3">Pencipta</th>
                <th className="px-4 py-3">Kategori</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3 text-center">Jawapan</th>
                <th className="px-4 py-3">Dicipta</th>
                <th className="px-4 py-3 text-right">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              {forms.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-10 text-center text-sm text-muted">
                    Tiada borang ditemui.
                  </td>
                </tr>
              )}
              {forms.map((form) => (
                <tr key={form.id} className="border-b border-line last:border-0 hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-ink">{form.title}</td>
                  <td className="px-4 py-3 text-slate-600">
                    {form.creator ? (
                      <div>
                        <div>{form.creator.name}</div>
                        <div className="text-xs text-slate-400">{form.creator.email}</div>
                      </div>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {form.category ? (
                      <span
                        className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        style={{ backgroundColor: (form.category.color || '#64748B') + '22', color: form.category.color || '#64748B' }}
                      >
                        {form.category.name}
                      </span>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={form.status} />
                  </td>
                  <td className="px-4 py-3 text-center font-medium text-ink">{form.submissions_count}</td>
                  <td className="whitespace-nowrap px-4 py-3 text-slate-500">{fmtDate(form.created_at)}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1">
                      <Link href={`/forms/${form.id}/submissions`} className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100" title="Jawapan">
                        <Inbox className="h-4 w-4" />
                      </Link>
                      <a href={form.public_url} target="_blank" rel="noreferrer" className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100" title="Buka awam">
                        <ExternalLink className="h-4 w-4" />
                      </a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {meta && meta.last_page > 1 && (
          <div className="flex flex-wrap items-center justify-center gap-1 border-t border-line px-4 py-3">
            {meta.links.map((link, i) => (
              <button
                key={i}
                disabled={!link.url}
                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                className={`min-w-[34px] rounded-lg px-2.5 py-1.5 text-sm transition ${
                  link.active ? 'bg-brand text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300'
                }`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </div>
    </FormsLayout>
  );
}
