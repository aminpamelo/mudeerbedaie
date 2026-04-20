import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Eye, Loader2 } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const STATUS_STYLES = {
  draft: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  locked: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
  paid: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
};

const STATUS_LABELS = {
  draft: 'Draft',
  locked: 'Locked',
  paid: 'Paid',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.draft;
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${cls}`}>
      {STATUS_LABELS[status] ?? status}
    </span>
  );
}

function formatMyr(value) {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) {
    return '—';
  }
  return num.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Pretty print a period like "Apr 1 – Apr 30, 2026". Falls back to the raw
 * ISO strings if either endpoint fails to parse.
 */
function formatPeriod(startIso, endIso) {
  if (!startIso || !endIso) {
    return '—';
  }
  const start = new Date(`${startIso}T00:00:00`);
  const end = new Date(`${endIso}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return `${startIso} – ${endIso}`;
  }

  const startMonth = start.toLocaleString(undefined, { month: 'short' });
  const endMonth = end.toLocaleString(undefined, { month: 'short' });
  const year = end.getFullYear();

  if (start.getFullYear() === end.getFullYear() && startMonth === endMonth) {
    return `${startMonth} ${start.getDate()} – ${end.getDate()}, ${year}`;
  }

  return `${startMonth} ${start.getDate()} – ${endMonth} ${end.getDate()}, ${year}`;
}

function formatDate(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(`${iso}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

export default function PayrollIndex() {
  const { runs, flash } = usePage().props;
  const [showForm, setShowForm] = useState(false);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});

  const resetForm = () => {
    setPeriodStart('');
    setPeriodEnd('');
    setErrors({});
  };

  const submit = (event) => {
    event.preventDefault();
    setErrors({});
    setSubmitting(true);

    router.post(
      '/livehost/payroll',
      {
        period_start: periodStart,
        period_end: periodEnd,
      },
      {
        preserveScroll: true,
        onError: (errs) => {
          setErrors(errs || {});
        },
        onFinish: () => {
          setSubmitting(false);
        },
        onSuccess: () => {
          resetForm();
          setShowForm(false);
        },
      }
    );
  };

  return (
    <>
      <Head title="Payroll" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Payroll']}
        actions={
          <Button
            size="sm"
            onClick={() => setShowForm((prev) => !prev)}
            className="h-9 gap-1.5 rounded-lg bg-[#0A0A0A] text-white hover:bg-[#262626]"
          >
            <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
            New Run
          </Button>
        }
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              Payroll
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {runs.total} payroll run{runs.total === 1 ? '' : 's'} — generate a draft per period,
              lock once numbers are final, mark paid after disbursing.
            </p>
          </div>
        </div>

        {flash?.error && (
          <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {flash.error}
          </div>
        )}
        {flash?.success && (
          <div className="rounded-[12px] border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#065F46]">
            {flash.success}
          </div>
        )}

        {showForm && (
          <form
            onSubmit={submit}
            className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
          >
            <div className="flex flex-wrap items-end gap-4">
              <div className="flex flex-col gap-1.5">
                <label htmlFor="period_start" className="text-[12px] font-medium text-[#404040]">
                  Period start
                </label>
                <input
                  id="period_start"
                  type="date"
                  required
                  value={periodStart}
                  onChange={(event) => setPeriodStart(event.target.value)}
                  className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
                {errors.period_start && (
                  <span className="text-[11px] text-[#DC2626]">{errors.period_start}</span>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <label htmlFor="period_end" className="text-[12px] font-medium text-[#404040]">
                  Period end
                </label>
                <input
                  id="period_end"
                  type="date"
                  required
                  value={periodEnd}
                  onChange={(event) => setPeriodEnd(event.target.value)}
                  className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
                {errors.period_end && (
                  <span className="text-[11px] text-[#DC2626]">{errors.period_end}</span>
                )}
              </div>
              <div className="flex items-center gap-2">
                <Button
                  type="submit"
                  size="sm"
                  disabled={submitting}
                  className="h-9 gap-1.5 rounded-lg bg-[#10B981] text-white hover:bg-[#059669]"
                >
                  {submitting && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  Generate draft
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => {
                    resetForm();
                    setShowForm(false);
                  }}
                  className="h-9 rounded-lg border-[#EAEAEA] bg-white text-[#404040] hover:bg-[#F5F5F5]"
                >
                  Cancel
                </Button>
              </div>
            </div>
          </form>
        )}

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {runs.data.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">
              No payroll runs yet — click “New Run” to generate one.
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Period</th>
                  <th className="px-5 py-3 text-left">Cutoff</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Items</th>
                  <th className="px-5 py-3 text-right">Total Payout (RM)</th>
                  <th className="px-5 py-3 text-left">Created</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {runs.data.map((run) => (
                  <tr
                    key={run.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                  >
                    <td className="px-5 py-3.5">
                      <div className="font-medium text-[#0A0A0A]">
                        {formatPeriod(run.period_start, run.period_end)}
                      </div>
                      <div className="text-[11.5px] text-[#737373]">Run #{run.id}</div>
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#404040]">
                      {formatDate(run.cutoff_date)}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusBadge status={run.status} />
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#0A0A0A]">
                      {run.items_count}
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] font-medium text-[#0A0A0A]">
                      {formatMyr(run.net_payout_total_myr)}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#737373]">
                      {run.locked_at
                        ? `locked ${formatDate(run.locked_at.slice(0, 10))}`
                        : run.paid_at
                        ? `paid ${formatDate(run.paid_at.slice(0, 10))}`
                        : formatDate(run.period_start)}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <Link
                        href={`/livehost/payroll/${run.id}`}
                        className="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-[12.5px] font-medium text-[#404040] hover:bg-[#F0F0F0] hover:text-[#0A0A0A]"
                        title="View"
                      >
                        <Eye className="h-[14px] w-[14px]" strokeWidth={2} />
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {runs.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {runs.from}–{runs.to} of {runs.total}
            </div>
            <div className="flex gap-1">
              {runs.links.map((link, index) => (
                <button
                  key={`${link.label}-${index}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => {
                    if (link.url) {
                      router.visit(link.url, {
                        preserveScroll: true,
                        preserveState: true,
                      });
                    }
                  }}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                  className={[
                    'min-w-8 h-8 rounded-md px-2 text-xs font-medium',
                    link.active
                      ? 'bg-[#0A0A0A] text-white'
                      : 'text-[#737373] hover:bg-[#F5F5F5]',
                    !link.url ? 'cursor-default opacity-40' : '',
                  ].join(' ')}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </>
  );
}

PayrollIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
