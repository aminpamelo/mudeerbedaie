import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Send, Lock } from 'lucide-react';
import FieldRenderer from '../../components/FieldRenderer';
import { isLayoutField } from '../../lib/fields';

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
        <div className="flex flex-col items-center py-8 text-center">
          <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
            <Lock className="h-7 w-7" />
          </div>
          <h1 className="text-xl font-bold text-ink">{form.title}</h1>
          <p className="mt-2 text-sm text-muted">Borang ini tidak lagi menerima jawapan.</p>
        </div>
      </Shell>
    );
  }

  return (
    <Shell logo={form.logo_url}>
      <div className="mb-7 border-b border-line pb-6">
        <h1 className="text-[26px] font-bold tracking-[-0.02em] text-ink">{form.title}</h1>
        {form.description && <p className="mt-2.5 text-[14px] leading-relaxed text-muted">{form.description}</p>}
      </div>

      <form onSubmit={submit} className="space-y-7">
        {form.fields.map((field) => (
          <div key={field.id} className={isLayoutField(field.type) ? '' : ''}>
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
            className="inline-flex items-center gap-2 rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink disabled:opacity-60"
          >
            <Send className="h-4 w-4" />
            {processing ? 'Menghantar…' : 'Hantar'}
          </button>
        </div>
      </form>
    </Shell>
  );
}

function Shell({ children, logo }) {
  return (
    <div className="relative min-h-dvh overflow-hidden bg-surface py-10 sm:py-16">
      {/* Soft emerald wash behind the card, mirroring the Live Host aesthetic. */}
      <div
        className="pointer-events-none absolute inset-x-0 top-0 h-64"
        style={{ background: 'radial-gradient(ellipse 600px 260px at 50% -40px, rgba(5,150,105,0.12), transparent 70%)' }}
      />
      <div className="relative mx-auto max-w-2xl px-4">
        {logo && (
          <div className="mb-5 flex justify-center">
            <img src={logo} alt="" className="max-h-20 w-auto" />
          </div>
        )}
        <div className="fade-up overflow-hidden rounded-[20px] border border-line bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
          <div className="h-1.5 bg-brand" />
          <div className="p-6 sm:p-9">{children}</div>
        </div>
        <p className="mt-5 text-center text-xs text-slate-400">Dikuasakan oleh Borang</p>
      </div>
    </div>
  );
}
