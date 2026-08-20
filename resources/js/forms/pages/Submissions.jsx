import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Search, Download, FileText, Eye, Trash2, X, Inbox, ExternalLink } from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import FormSubnav from '../components/FormSubnav';

function fmtDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function Submissions({ form, submissions = [], meta, filters = {} }) {
  const [search, setSearch] = useState(filters.search || '');
  const [from, setFrom] = useState(filters.from || '');
  const [to, setTo] = useState(filters.to || '');
  const [active, setActive] = useState(null);

  const applyFilters = (e) => {
    e?.preventDefault();
    router.get(`/forms/${form.id}/submissions`, { search, from, to }, { preserveState: true, preserveScroll: true });
  };

  const reset = () => {
    setSearch('');
    setFrom('');
    setTo('');
    router.get(`/forms/${form.id}/submissions`);
  };

  const remove = (sub) => {
    if (confirm('Buang submission ini secara kekal?')) {
      router.delete(`/forms/${form.id}/submissions/${sub.id}`, { preserveScroll: true });
    }
  };

  const actions = (
    <a
      href={`/forms/${form.id}/submissions/export`}
      className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink"
    >
      <Download className="h-4 w-4" /> Eksport CSV
    </a>
  );

  return (
    <FormsLayout
      title={form.title}
      subtitle={
        <span>
          <Link href="/forms" className="text-brand hover:underline">
            ← Borang Saya
          </Link>
          {'  ·  '}
          {meta?.total ?? submissions.length} jawapan diterima
        </span>
      }
      actions={actions}
    >
      <div className="mb-5">
        <FormSubnav formId={form.id} active="submissions" />
      </div>

      {/* Filters */}
      <form onSubmit={applyFilters} className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
        <div className="min-w-[200px] flex-1">
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
        {(filters.search || filters.from || filters.to) && (
          <button type="button" onClick={reset} className="rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-800">
            Set semula
          </button>
        )}
      </form>

      {submissions.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-16 text-center">
          <Inbox className="mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm font-medium text-ink">Tiada jawapan lagi</p>
          <p className="mt-1 text-sm text-muted">Kongsi pautan borang untuk mula mengumpul jawapan.</p>
          <a href={form.public_url} target="_blank" rel="noreferrer" className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
            <ExternalLink className="h-4 w-4" /> Buka borang awam
          </a>
        </div>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
          <div className="overflow-x-auto scroll-thin">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-line bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  <th className="px-4 py-3">Tarikh</th>
                  <th className="px-4 py-3">Dihantar Oleh</th>
                  {form.fields.slice(0, 2).map((f) => (
                    <th key={f.id} className="px-4 py-3">
                      {f.label}
                    </th>
                  ))}
                  <th className="px-4 py-3 text-right">Tindakan</th>
                </tr>
              </thead>
              <tbody>
                {submissions.map((sub) => (
                  <tr key={sub.id} className="border-b border-line last:border-0 hover:bg-slate-50">
                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{fmtDate(sub.submitted_at)}</td>
                    <td className="px-4 py-3 text-slate-600">{sub.submitted_by || 'Awam'}</td>
                    {form.fields.slice(0, 2).map((f) => {
                      const ans = sub.answers.find((a) => a.id === f.id);
                      return (
                        <td key={f.id} className="max-w-[200px] truncate px-4 py-3 text-ink">
                          {ans?.value || '—'}
                        </td>
                      );
                    })}
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1">
                        <button onClick={() => setActive(sub)} className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100" title="Lihat">
                          <Eye className="h-4 w-4" />
                        </button>
                        <a
                          href={`/forms/${form.id}/submissions/${sub.id}/pdf`}
                          className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100"
                          title="Muat turun PDF"
                        >
                          <FileText className="h-4 w-4" />
                        </a>
                        <button onClick={() => remove(sub)} className="rounded-lg p-1.5 text-rose-400 transition hover:bg-rose-50" title="Buang">
                          <Trash2 className="h-4 w-4" />
                        </button>
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

      {/* Detail modal */}
      {active && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setActive(null)} />
          <div className="relative z-10 max-h-[85vh] w-full max-w-lg overflow-y-auto scroll-thin rounded-2xl bg-white p-6 shadow-2xl">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h3 className="text-lg font-semibold text-ink">Butiran Jawapan</h3>
                <p className="text-xs text-muted">
                  {fmtDate(active.submitted_at)} · {active.submitted_by || 'Awam'}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <a
                  href={`/forms/${form.id}/submissions/${active.id}/pdf`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-ink"
                >
                  <FileText className="h-3.5 w-3.5" /> PDF
                </a>
                <button onClick={() => setActive(null)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-3">
              {active.answers.map((a) => (
                <div key={a.id} className="rounded-lg border border-line p-3">
                  <p className="text-xs font-medium text-slate-500">{a.label}</p>
                  {a.type === 'file' && a.file_url ? (
                    <a href={a.file_url} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
                      <Download className="h-3.5 w-3.5" /> {a.value || 'Muat turun fail'}
                    </a>
                  ) : (
                    <p className="mt-1 text-sm text-ink">{a.value || '—'}</p>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </FormsLayout>
  );
}
