import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { FileText, Send, Lock } from 'lucide-react';
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
      <Shell>
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
    <Shell>
      <div className="mb-6 border-b border-line pb-5">
        <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-brand text-white">
          <FileText className="h-6 w-6" />
        </div>
        <h1 className="text-2xl font-bold text-ink">{form.title}</h1>
        {form.description && <p className="mt-2 text-sm leading-relaxed text-muted">{form.description}</p>}
      </div>

      <form onSubmit={submit} className="space-y-6">
        {form.fields.map((field) => (
          <div key={field.id} className={isLayoutField(field.type) ? '' : 'rounded-xl'}>
            <FieldRenderer
              field={field}
              value={answers[field.id]}
              onChange={(v) => setAnswer(field.id, v)}
              error={errors[`answers.${field.id}`]}
            />
          </div>
        ))}

        <div className="border-t border-line pt-5">
          <button
            type="submit"
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-ink disabled:opacity-60"
          >
            <Send className="h-4 w-4" />
            {processing ? 'Menghantar…' : 'Hantar'}
          </button>
        </div>
      </form>
    </Shell>
  );
}

function Shell({ children }) {
  return (
    <div className="min-h-dvh bg-surface py-8 sm:py-14">
      <div className="mx-auto max-w-2xl px-4">
        <div className="rounded-2xl border-t-4 border-brand border-x border-b border-line bg-white p-6 shadow-sm sm:p-8 fade-up">{children}</div>
        <p className="mt-4 text-center text-xs text-slate-400">Dikuasakan oleh Borang</p>
      </div>
    </div>
  );
}
