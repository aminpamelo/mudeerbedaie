import { Link } from '@inertiajs/react';
import { Download, Inbox, TrendingUp, CalendarDays, Star, Hash, BarChart3, ExternalLink } from 'lucide-react';
import FormsLayout from '../layouts/FormsLayout';
import FormSubnav from '../components/FormSubnav';
import { Sparkline, Bar } from '../components/charts';

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Report({ form, stats, timeseries = [], fields = [] }) {
  const ratingFields = fields.filter((f) => f.kind === 'rating' && f.average != null);
  const headlineRating = ratingFields.length
    ? (ratingFields.reduce((s, f) => s + f.average, 0) / ratingFields.length).toFixed(1)
    : null;

  const actions = (
    <a
      href={`/forms/${form.id}/submissions/export`}
      className="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
    >
      <Download className="h-4 w-4" /> Eksport CSV
    </a>
  );

  return (
    <FormsLayout
      title={form.title}
      subtitle={
        <Link href="/forms" className="text-brand hover:underline">
          ← Borang Saya
        </Link>
      }
      actions={actions}
    >
      <div className="mb-6">
        <FormSubnav formId={form.id} active="report" />
      </div>

      {stats.total === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-white py-20 text-center">
          <BarChart3 className="mb-3 h-10 w-10 text-slate-300" />
          <p className="text-sm font-semibold text-ink">Belum ada data untuk dianalisis</p>
          <p className="mt-1 text-sm text-muted">Kongsi pautan borang untuk mula mengumpul jawapan.</p>
          <a href={form.public_url} target="_blank" rel="noreferrer" className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
            <ExternalLink className="h-4 w-4" /> Buka borang awam
          </a>
        </div>
      ) : (
        <div className="space-y-6">
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Kpi icon={Inbox} tone="brand" label="Jumlah Respons" value={stats.total} />
            <Kpi icon={TrendingUp} tone="brand" label="7 Hari Lepas" value={stats.last7} />
            <Kpi icon={CalendarDays} tone="slate" label="Hari Ini" value={stats.today} />
            {headlineRating ? (
              <Kpi icon={Star} tone="amber" label="Purata Rating" value={headlineRating} suffix="/ 5" />
            ) : (
              <Kpi icon={CalendarDays} tone="slate" label="Purata / Hari" value={stats.avg_per_active_day} />
            )}
          </div>

          {/* Trend */}
          <div className="rounded-2xl border border-line bg-white p-5 card-soft">
            <div className="mb-3 flex items-center justify-between">
              <div>
                <h3 className="text-[15px] font-semibold text-ink">Respons 30 Hari</h3>
                <p className="text-[12.5px] text-muted">
                  Pertama: {fmtDate(stats.first_at)} · Terkini: {fmtDate(stats.last_at)}
                </p>
              </div>
            </div>
            <Sparkline points={timeseries} />
            <div className="mt-1 flex justify-between text-[11px] text-slate-400">
              <span>{fmtDate(timeseries[0]?.date)}</span>
              <span>{fmtDate(timeseries[timeseries.length - 1]?.date)}</span>
            </div>
          </div>

          {/* Per-question breakdown */}
          <div className="space-y-4">
            <h3 className="text-[15px] font-semibold text-ink">Pecahan Soalan</h3>
            {fields.map((field) => (
              <FieldReport key={field.id} field={field} />
            ))}
            {fields.length === 0 && (
              <p className="rounded-2xl border border-dashed border-line bg-white py-8 text-center text-sm text-muted">
                Borang ini tiada soalan yang boleh dianalisis.
              </p>
            )}
          </div>
        </div>
      )}
    </FormsLayout>
  );
}

function Kpi({ icon: Icon, label, value, suffix, tone }) {
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
      <p className="mt-2 text-[28px] font-bold leading-none tracking-[-0.02em] tabular-nums text-ink">
        {value}
        {suffix && <span className="ml-1 text-sm font-medium text-muted">{suffix}</span>}
      </p>
    </div>
  );
}

const TYPE_LABELS = {
  short_text: 'Teks Pendek', long_text: 'Teks Panjang', number: 'Nombor', email: 'Emel',
  date: 'Tarikh', radio: 'Pilihan Tunggal', checkbox: 'Kotak Semak', dropdown: 'Dropdown',
  file: 'Fail', rating: 'Rating', phone: 'Telefon',
};

function FieldReport({ field }) {
  return (
    <div className="rounded-2xl border border-line bg-white p-5 card-soft">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h4 className="text-[14px] font-semibold text-ink">{field.label}</h4>
        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11.5px] font-medium text-slate-500">
          {TYPE_LABELS[field.type] || field.type} · {field.answered} jawapan
        </span>
      </div>

      {field.kind === 'choice' && (
        <div className="space-y-2.5">
          {field.options.length === 0 ? (
            <p className="text-sm text-muted">Tiada pilihan.</p>
          ) : (
            field.options.map((o, i) => <Bar key={i} label={o.label} count={o.count} pct={o.pct} />)
          )}
        </div>
      )}

      {field.kind === 'rating' && (
        <div>
          <div className="mb-4 flex items-baseline gap-2">
            <span className="text-[32px] font-bold leading-none tabular-nums text-ink">{field.average ?? '—'}</span>
            <span className="text-sm text-muted">/ {field.max_stars} purata</span>
          </div>
          <div className="space-y-2.5">
            {field.distribution.map((d) => (
              <Bar key={d.star} label={`${d.star} ★`} count={d.count} pct={d.pct} tone="amber" />
            ))}
          </div>
        </div>
      )}

      {field.kind === 'number' && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <NumStat icon={Hash} label="Purata" value={field.average ?? '—'} />
          <NumStat label="Minimum" value={field.min ?? '—'} />
          <NumStat label="Maksimum" value={field.max ?? '—'} />
          <NumStat label="Jumlah" value={field.sum ?? '—'} />
        </div>
      )}

      {field.kind === 'text' && (
        <div>
          {field.samples.length === 0 ? (
            <p className="text-sm text-muted">Tiada jawapan teks lagi.</p>
          ) : (
            <ul className="space-y-1.5">
              {field.samples.map((s, i) => (
                <li key={i} className="truncate rounded-lg border border-line bg-surface px-3 py-2 text-[13px] text-ink" title={s}>
                  {s}
                </li>
              ))}
              <li className="pt-1 text-[12px] text-muted">Menunjukkan {field.samples.length} jawapan terkini · lihat semua di tab Jawapan.</li>
            </ul>
          )}
        </div>
      )}
    </div>
  );
}

function NumStat({ label, value }) {
  return (
    <div className="rounded-xl border border-line bg-surface px-3 py-2.5">
      <p className="text-[11.5px] text-muted">{label}</p>
      <p className="mt-0.5 text-[18px] font-bold tabular-nums text-ink">{value}</p>
    </div>
  );
}
