import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Newspaper, RefreshCw, Sparkles, PenLine, X, RotateCcw, ExternalLink, Loader2, AlertTriangle, CheckCircle2, BookOpen, ShieldCheck } from 'lucide-react';
import BlogSeoLayout from '@/blogseo/layouts/BlogSeoLayout';
import { Card, Button, EmptyState, Select } from '@/blogseo/components/Ui';
import { cn, timeAgo } from '@/blogseo/lib/utils';

const VIEWS = [
  ['suggested', 'Dicadangkan'],
  ['written', 'Sudah ditulis'],
  ['all', 'Semua isu'],
];

function RelevanceBadge({ value }) {
  if (value === null || value === undefined) {
    return <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">Belum dinilai</span>;
  }
  const tone = value >= 8 ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : value >= 6 ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-slate-100 text-slate-500 ring-slate-400/20';
  return <span className={cn('rounded-full px-2 py-0.5 text-[11px] font-bold ring-1', tone)}>{value}/10</span>;
}

function IssueRow({ issue }) {
  const [busy, setBusy] = useState(false);

  const write = () => {
    setBusy(true);
    router.post(`/blog-seo/issues/${issue.id}/write`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const dismiss = () => router.post(`/blog-seo/issues/${issue.id}/dismiss`, {}, { preserveScroll: true });

  return (
    <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start">
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2 text-[11.5px] text-muted">
          <RelevanceBadge value={issue.relevance} />
          <span className="font-semibold text-ink-2">{issue.source}</span>
          {issue.published_at && <span>· {timeAgo(issue.published_at)}</span>}
        </div>
        <a href={issue.url} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-start gap-1 text-[14.5px] font-semibold leading-snug text-ink hover:text-[var(--color-brand-ink)]">
          {issue.title}
          <ExternalLink className="mt-1 h-3 w-3 shrink-0 text-muted-2" />
        </a>
        {issue.angle && (
          <p className="mt-1 flex items-start gap-1.5 text-[12.5px] text-ink-2">
            <Sparkles className="mt-0.5 h-3.5 w-3.5 shrink-0 text-violet-500" />
            <span><span className="font-semibold">Sudut kupasan:</span> {issue.angle}</span>
          </p>
        )}
        {issue.status === 'failed' && issue.error && (
          <p className="mt-1 flex items-center gap-1 text-[12px] text-rose-600"><AlertTriangle className="h-3.5 w-3.5" /> {issue.error}</p>
        )}
      </div>

      <div className="flex shrink-0 items-center gap-2">
        {issue.status === 'written' && issue.post ? (
          <Button size="sm" variant="secondary" href={issue.post.edit_url}>
            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" /> Buka draf
          </Button>
        ) : issue.status === 'writing' ? (
          <span className="inline-flex items-center gap-1.5 rounded-xl bg-violet-50 px-3 py-1.5 text-[12.5px] font-semibold text-violet-700">
            <Loader2 className="h-3.5 w-3.5 animate-spin" /> Sedang ditulis…
          </span>
        ) : issue.status === 'dismissed' ? (
          <Button size="sm" variant="ghost" onClick={dismiss}><RotateCcw className="h-3.5 w-3.5" /> Pulihkan</Button>
        ) : (
          <>
            <Button size="sm" variant="primary" onClick={write} disabled={busy}>
              <PenLine className="h-3.5 w-3.5" /> {issue.status === 'failed' ? 'Cuba lagi' : 'Tulis artikel'}
            </Button>
            <Button size="sm" variant="ghost" onClick={dismiss} title="Abaikan isu ini" aria-label="Abaikan"><X className="h-3.5 w-3.5" /></Button>
          </>
        )}
      </div>
    </div>
  );
}

function AutoWriteCard({ value }) {
  const form = useForm({ auto_daily_count: value });

  const change = (e) => {
    form.setData('auto_daily_count', Number(e.target.value));
  };

  useEffect(() => {
    if (form.data.auto_daily_count !== value) {
      form.put('/blog-seo/issues/settings', { preserveScroll: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.data.auto_daily_count]);

  return (
    <div className="flex items-center gap-2">
      <span className="text-[12.5px] font-semibold text-ink-2">Auto-tulis setiap pagi:</span>
      <Select value={form.data.auto_daily_count} onChange={change} className="w-36" disabled={form.processing}>
        <option value={0}>Off</option>
        {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n} artikel</option>)}
      </Select>
    </div>
  );
}

export default function Issues({ issues, view, counts, autoDailyCount, lastCapturedAt, aiReady, sources }) {
  const [capturing, setCapturing] = useState(false);
  const writing = issues.some((i) => i.status === 'writing');

  // While AI drafts are being written, refresh every 8s so they flip to "Buka draf".
  useEffect(() => {
    if (!writing) return undefined;
    const id = setInterval(() => router.reload({ only: ['issues', 'counts'], preserveScroll: true }), 8000);
    return () => clearInterval(id);
  }, [writing]);

  const capture = () => {
    setCapturing(true);
    router.post('/blog-seo/issues/capture', {}, { preserveScroll: true, onFinish: () => setCapturing(false) });
  };

  return (
    <BlogSeoLayout
      title="Isu Semasa"
      subtitle="Isu semasa ditangkap automatik setiap jam, dinilai AI, dan ditulis sebagai artikel kupasan agama dengan CTA buku BeDaie."
      actions={
        <Button variant="secondary" onClick={capture} disabled={capturing}>
          <RefreshCw className={cn('h-4 w-4', capturing && 'animate-spin')} /> {capturing ? 'Menangkap…' : 'Tangkap isu sekarang'}
        </Button>
      }
    >
      <Head title="Isu Semasa" />

      {!aiReady && (
        <div className="mb-5 flex items-center gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-[13px] text-amber-800 ring-1 ring-amber-600/20">
          <AlertTriangle className="h-4 w-4" /> OPENAI_API_KEY belum ditetapkan — penilaian dan penulisan AI tidak akan berjalan.
        </div>
      )}

      <div className="mb-5 grid gap-3 lg:grid-cols-3">
        <div className="flex items-start gap-2.5 rounded-2xl bg-white p-4 ring-1 ring-line">
          <Newspaper className="mt-0.5 h-4 w-4 shrink-0 text-[var(--color-brand)]" />
          <p className="text-[12.5px] leading-relaxed text-ink-2">
            <span className="font-semibold">1. Tangkap isu</span> — dari {sources.join(', ')}.{' '}
            {lastCapturedAt && <span className="text-muted">Terakhir {timeAgo(lastCapturedAt)}.</span>}
          </p>
        </div>
        <div className="flex items-start gap-2.5 rounded-2xl bg-white p-4 ring-1 ring-line">
          <BookOpen className="mt-0.5 h-4 w-4 shrink-0 text-violet-500" />
          <p className="text-[12.5px] leading-relaxed text-ink-2">
            <span className="font-semibold">2. Tulis artikel</span> — kupasan agama + ayat & hadis bersumber, ditutup dengan CTA buku BeDaie yang paling berkaitan. Disimpan sebagai <span className="font-semibold">draf</span>.
          </p>
        </div>
        <div className="flex items-start gap-2.5 rounded-2xl bg-white p-4 ring-1 ring-line">
          <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
          <p className="text-[12.5px] leading-relaxed text-ink-2">
            <span className="font-semibold">3. Editor semak</span> — senarai hadis/ayat yang dipetik dipaparkan dalam editor untuk disemak sebelum publish.
          </p>
        </div>
      </div>

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex gap-1 rounded-xl bg-white p-1 ring-1 ring-line">
          {VIEWS.map(([key, label]) => (
            <Link
              key={key}
              href={`/blog-seo/issues?view=${key}`}
              preserveScroll
              className={cn('rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition-colors', view === key ? 'bg-[var(--color-brand)] text-white' : 'text-ink-2 hover:bg-surface')}
            >
              {label} <span className={cn('ml-1 text-[11px]', view === key ? 'text-white/80' : 'text-muted-2')}>{counts[key]}</span>
            </Link>
          ))}
        </div>
        <AutoWriteCard value={autoDailyCount} />
      </div>

      {issues.length === 0 ? (
        <EmptyState
          icon={Newspaper}
          title={view === 'written' ? 'Belum ada artikel ditulis' : 'Tiada isu dicadangkan buat masa ini'}
          hint="Isu baru ditangkap setiap jam. Tekan 'Tangkap isu sekarang' untuk tarik berita terkini."
        />
      ) : (
        <Card className="divide-y divide-line p-0">
          {issues.map((issue) => <IssueRow key={issue.id} issue={issue} />)}
        </Card>
      )}
    </BlogSeoLayout>
  );
}
