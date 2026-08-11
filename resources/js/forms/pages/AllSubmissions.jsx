import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, FileText, Inbox, ExternalLink } from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';

function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString('ms-MY', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function AllSubmissions({ submissions = [], meta, filters = {}, forms = [] }) {
  const [search, setSearch] = useState(filters.search || '');
  const [form, setForm] = useState(filters.form || '');
  const [from, setFrom] = useState(filters.from || '');
  const [to, setTo] = useState(filters.to || '');

  const apply = (e) => {
    e?.preventDefault();
    router.get('/forms/submissions', { search, form, from, to }, { preserveState: true, preserveScroll: true });
  };

  const reset = () => {
    setSearch('');
    setForm('');
    setFrom('');
    setTo('');
    router.get('/forms/submissions');
  };

  return (
    <FormsLayout title="Semua Submission" subtitle={`${meta?.total ?? submissions.length} jawapan dari semua borang dalam sistem.`}>
      <form onSubmit={apply} className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div className="min-w-[180px] flex-1">
          <label className="mb-1 block text-xs font-medium text-slate-600">Cari jawapan</label>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="w-full rounded-lg border border-line py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
              placeholder="Cari teks…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600">Borang</label>
          <select className="rounded-lg border border-line px-3 py-2 text-sm outline-none focus:border-brand" value={form} onChange={(e) => setForm(e.target.value)}>
            <option value="">Semua borang</option>
            {forms.map((f) => (
              <option key={f.id} value={f.id}>
                {f.title}
              </option>
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
        {(filters.search || filters.form || filters.from || filters.to) && (
          <button type="button" onClick={reset} className="rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-800">
            Set semula
          </button>
        )}
      </form>

      {submissions.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-16 text-center">
          <Inbox className="mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm font-medium text-ink">Tiada submission ditemui</p>
          <p className="mt-1 text-sm text-muted">Jawapan dari semua borang akan terkumpul di sini.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
          <div className="overflow-x-auto scroll-thin">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-line bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  <th className="px-4 py-3">Borang</th>
                  <th className="px-4 py-3">Dihantar Oleh</th>
                  <th className="px-4 py-3">Ringkasan</th>
                  <th className="px-4 py-3">Tarikh</th>
                  <th className="px-4 py-3 text-right">Tindakan</th>
                </tr>
              </thead>
              <tbody>
                {submissions.map((s) => (
                  <tr key={s.id} className="border-b border-line last:border-0 hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-ink">{s.form_title}</td>
                    <td className="px-4 py-3 text-slate-600">{s.submitted_by || 'Awam'}</td>
                    <td className="max-w-[220px] truncate px-4 py-3 text-slate-600">{s.preview || '—'}</td>
                    <td className="whitespace-nowrap px-4 py-3 text-slate-500">{fmtDate(s.submitted_at)}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1">
                        {s.form_url && (
                          <a href={s.form_url} className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100" title="Buka borang">
                            <ExternalLink className="h-4 w-4" />
                          </a>
                        )}
                        {s.pdf_url && (
                          <a href={s.pdf_url} className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100" title="Muat turun PDF">
                            <FileText className="h-4 w-4" />
                          </a>
                        )}
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
      )}
    </FormsLayout>
  );
}
