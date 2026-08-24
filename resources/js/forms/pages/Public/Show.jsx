import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Send, Lock } from 'lucide-react';
import FieldRenderer from '../../components/FieldRenderer';

export default function Show({ form }) {
  const { errors = {} } = usePage().props;
  const [answers, setAnswers] = useState({});
  const [processing, setProcessing] = useState(false);

  const setAnswer = (id, value) => setAnswers((a) => ({ ...a, [id]: value }));

  const submit = (e) => {
    e.preventDefault();
    setProcessing(true);
    router.post(
      `/form/${form.slug}`,
      { answers },
      {
        forceFormData: true,
        onFinish: () => setProcessing(false),
      },
    );
  };

  if (!form.is_open) {
    return (
      <Shell logo={form.logo_url}>
        <div className="flex flex-col items-center px-6 py-12 text-center sm:px-9">
          <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
            <Lock className="h-8 w-8" />
          </div>
          <h1 className="text-xl font-bold text-ink">{form.title}</h1>
          <p className="mt-2 text-sm text-muted">Borang ini tidak lagi menerima jawapan.</p>
        </div>
      </Shell>
    );
  }

  const hasRequired = form.fields.some((f) => f.required);
  const hasErrors = Object.keys(errors).length > 0;

  const header = (
    <div className="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-400 px-6 py-8 sm:px-9 sm:py-10">
      {/* Decorative floating shapes for a livelier, striking header. */}
      <div className="pointer-events-none absolute -right-12 -top-14 h-44 w-44 rounded-full bg-white/15" />
      <div className="pointer-events-none absolute -bottom-16 -left-8 h-40 w-40 rounded-full bg-white/10" />
      <div className="pointer-events-none absolute right-16 bottom-2 h-20 w-20 rounded-full border border-white/20" />
      <h1 className="relative text-[28px] font-extrabold leading-tight tracking-[-0.02em] text-white sm:text-[32px]">
        {form.title}
      </h1>
      {form.description && (
        <p className="relative mt-2.5 max-w-lg text-[14px] leading-relaxed text-white/85">{form.description}</p>
      )}
      {hasRequired && (
        <p className="relative mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
          <span aria-hidden="true">*</span> Wajib diisi
        </p>
      )}
    </div>
  );

  return (
    <Shell logo={form.logo_url} header={header}>
      {hasErrors && (
        <div role="alert" className="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          Sila semak medan yang ditandakan di bawah dan cuba lagi.
        </div>
      )}

      <form onSubmit={submit} className="space-y-7" noValidate>
        {form.fields.map((field, i) => (
          <div key={field.id} className="field-in" style={{ animationDelay: `${Math.min(i * 45, 400)}ms` }}>
            <FieldRenderer
              field={field}
              value={answers[field.id]}
              onChange={(v) => setAnswer(field.id, v)}
              error={errors[`answers.${field.id}`]}
            />
          </div>
        ))}

        <div className="border-t border-line pt-6">
          <button
            type="submit"
            disabled={processing}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/30 transition hover:from-emerald-500 hover:to-teal-400 hover:shadow-emerald-500/40 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <Send className={`h-4 w-4 ${processing ? 'animate-pulse' : ''}`} />
            {processing ? 'Menghantar…' : 'Hantar'}
          </button>
        </div>
      </form>
    </Shell>
  );
}

function Shell({ children, logo, header }) {
  return (
    <div className="relative min-h-dvh overflow-hidden bg-gradient-to-b from-emerald-50 via-surface to-emerald-50/60 py-10 sm:py-16">
      {/* Animated emerald blobs for a vibrant, striking backdrop. */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="form-blob form-blob-1" />
        <div className="form-blob form-blob-2" />
        <div className="form-blob form-blob-3" />
      </div>

      <div className="relative mx-auto max-w-2xl px-4">
        {logo && (
          <div className="mb-6 flex justify-center">
            <img src={logo} alt="" className="max-h-20 w-auto drop-shadow-sm" />
          </div>
        )}
        <div className="fade-up overflow-hidden rounded-3xl bg-white shadow-[0_24px_70px_-20px_rgba(5,150,105,0.4)] ring-1 ring-emerald-900/5">
          {header}
          <div className="p-6 sm:p-9">{children}</div>
        </div>
        <p className="mt-6 text-center text-xs text-emerald-800/50">Dikuasakan oleh Borang</p>
      </div>
    </div>
  );
}
