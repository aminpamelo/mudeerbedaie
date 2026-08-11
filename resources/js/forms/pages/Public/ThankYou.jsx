import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function ThankYou({ form, message }) {
  return (
    <div className="min-h-dvh bg-surface py-8 sm:py-14">
      <div className="mx-auto max-w-2xl px-4">
        <div className="rounded-2xl border-t-4 border-emerald-500 border-x border-b border-line bg-white p-8 text-center shadow-sm fade-up">
          <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <CheckCircle2 className="h-9 w-9" />
          </div>
          <h1 className="text-2xl font-bold text-ink">{form.title}</h1>
          <p className="mt-3 text-sm leading-relaxed text-muted">{message}</p>

          {form.is_open && (
            <Link
              href={`/form/${form.slug}`}
              className="mt-6 inline-flex items-center rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-200"
            >
              Hantar jawapan lain
            </Link>
          )}
        </div>
        <p className="mt-4 text-center text-xs text-slate-400">Dikuasakan oleh Borang</p>
      </div>
    </div>
  );
}
