import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight, ChevronLeft, ChevronRight } from 'lucide-react';
import CeoLayout from '@/ceo/layouts/CeoLayout';
import AreaChart from '@/ceo/components/AreaChart';
import MonthlyMatrix from '@/ceo/components/MonthlyMatrix';
import { useT } from '@/ceo/lib/i18n';

function YearStepper({ report }) {
  const go = (year) => {
    if (!year) return;
    router.get('/ceo/reports/monthly', { department: report.department, year }, { preserveScroll: true });
  };

  return (
    <div className="glass inline-flex items-center gap-1 rounded-[12px] p-1">
      <button
        type="button"
        onClick={() => go(report.prevYear)}
        className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/60 hover:text-ink"
        aria-label="Previous year"
      >
        <ChevronLeft className="h-4 w-4" strokeWidth={2.2} />
      </button>
      <span className="min-w-[56px] px-1 text-center text-[13px] font-semibold tabular-nums text-ink">{report.year}</span>
      <button
        type="button"
        onClick={() => go(report.nextYear)}
        disabled={!report.nextYear}
        className="grid h-7 w-7 place-items-center rounded-[9px] text-muted transition-colors hover:bg-white/60 hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
        aria-label="Next year"
      >
        <ChevronRight className="h-4 w-4" strokeWidth={2.2} />
      </button>
    </div>
  );
}

export default function MonthlyReport({ report }) {
  const t = useT();
  const { label, year, months, columns, rows, summary, moduleHref, moduleLabel, accent, backHref } = report;
  const accentVar = `var(--color-${accent})`;

  return (
    <CeoLayout>
      <Head title={`${t('monthly_nav')} · ${label} · ${year}`} />

      <header className="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-6 lg:px-8 pb-2 pt-6">
        <div className="flex items-center gap-3">
          <Link href={backHref} className="grid h-9 w-9 place-items-center rounded-xl glass text-muted transition-colors hover:text-ink" aria-label={t('back_to_overview')}>
            <ArrowLeft className="h-4 w-4" strokeWidth={2} />
          </Link>
          <div>
            <h1 className="font-display text-[22px] text-ink">{t('monthly_nav')}</h1>
            <p className="text-[12.5px] text-muted">{label} · {year}</p>
          </div>
        </div>
        <YearStepper report={report} />
      </header>

      <div className="flex flex-col gap-6 px-4 sm:px-6 lg:px-8 pb-10" data-accent={accent}>
        {/* Hero summary */}
        <section className="glass-card relative flex flex-col gap-4 overflow-hidden rounded-[22px] p-6">
          <span className="absolute inset-x-0 top-0 h-[3px]" style={{ background: `linear-gradient(90deg, var(--color-brand), ${accentVar})` }} aria-hidden="true" />
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <span className="label-eyebrow">{summary.heroLabel}</span>
              <div className="font-display text-[28px] leading-tight text-ink tabular-nums">{summary.heroValue}</div>
            </div>
            {moduleHref && (
              <a
                href={moduleHref}
                className="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-[12.5px] font-semibold text-white transition-transform hover:-translate-y-0.5"
                style={{ background: `linear-gradient(90deg, var(--color-brand), ${accentVar})` }}
              >
                {moduleLabel}
                <ArrowUpRight className="h-4 w-4" strokeWidth={2.2} />
              </a>
            )}
          </div>
          <AreaChart data={summary.trend} color={accentVar} height={120} />
        </section>

        {/* Monthly matrix */}
        <section className="glass-card flex flex-col gap-4 rounded-[20px] p-6">
          <MonthlyMatrix months={months} columns={columns} rows={rows} />
        </section>
      </div>
    </CeoLayout>
  );
}
