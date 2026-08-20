import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import {
  Plus,
  FileText,
  Inbox,
  Pencil,
  Copy,
  Link2,
  Trash2,
  ExternalLink,
  Check,
  BarChart3,
} from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import StatusBadge from '../components/StatusBadge';

export default function Index({ forms = [] }) {
  const [copied, setCopied] = useState(null);

  const copyLink = (form) => {
    navigator.clipboard?.writeText(form.public_url);
    setCopied(form.id);
    setTimeout(() => setCopied(null), 1800);
  };

  const remove = (form) => {
    if (confirm(`Buang borang "${form.title}"? Tindakan ini boleh dipulihkan oleh admin.`)) {
      router.delete(`/forms/${form.id}`);
    }
  };

  const duplicate = (form) => router.post(`/forms/${form.id}/duplicate`);

  const actions = (
    <Link
      href="/forms/create"
      className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink"
    >
      <Plus className="h-4 w-4" />
      Cipta Borang
    </Link>
  );

  return (
    <FormsLayout title="Borang Saya" subtitle="Bina, kongsi dan kumpul jawapan borang anda." actions={actions}>
      {forms.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-20 text-center">
          <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-soft text-brand-ink">
            <FileText className="h-7 w-7" />
          </div>
          <h3 className="text-lg font-semibold text-ink">Belum ada borang</h3>
          <p className="mt-1 max-w-sm text-sm text-muted">
            Cipta borang pertama anda — macam Google Form — untuk mula mengumpul maklumat.
          </p>
          <Link
            href="/forms/create"
            className="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-ink"
          >
            <Plus className="h-4 w-4" />
            Cipta Borang
          </Link>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {forms.map((form) => (
            <div key={form.id} className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-sm transition hover:shadow-md">
              <div className="mb-3 flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <h3 className="truncate text-[15px] font-semibold text-ink">{form.title}</h3>
                  <div className="mt-1.5 flex flex-wrap items-center gap-2">
                    <StatusBadge status={form.status} />
                    {form.category && (
                      <span
                        className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        style={{ backgroundColor: (form.category.color || '#64748B') + '22', color: form.category.color || '#64748B' }}
                      >
                        {form.category.name}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              <div className="mb-4 flex items-center gap-4 text-sm text-muted">
                <span className="inline-flex items-center gap-1.5">
                  <Inbox className="h-4 w-4" />
                  {form.submissions_count} jawapan
                </span>
                <span>{form.field_count} soalan</span>
              </div>

              <div className="mt-auto flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <Link
                  href={`/forms/${form.id}/edit`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                >
                  <Pencil className="h-3.5 w-3.5" /> Edit
                </Link>
                <Link
                  href={`/forms/${form.id}/submissions`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                >
                  <Inbox className="h-3.5 w-3.5" /> Jawapan
                </Link>
                <Link
                  href={`/forms/${form.id}/report`}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                >
                  <BarChart3 className="h-3.5 w-3.5" /> Report
                </Link>
                <button
                  onClick={() => copyLink(form)}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                  title="Salin pautan awam"
                >
                  {copied === form.id ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Link2 className="h-3.5 w-3.5" />}
                  {copied === form.id ? 'Disalin' : 'Pautan'}
                </button>
                <a
                  href={form.public_url}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                  title="Buka borang awam"
                >
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
                <button
                  onClick={() => duplicate(form)}
                  className="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs text-slate-500 transition hover:bg-slate-100"
                  title="Salin borang"
                >
                  <Copy className="h-3.5 w-3.5" />
                </button>
                <button
                  onClick={() => remove(form)}
                  className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs text-rose-500 transition hover:bg-rose-50"
                  title="Buang"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </FormsLayout>
  );
}
