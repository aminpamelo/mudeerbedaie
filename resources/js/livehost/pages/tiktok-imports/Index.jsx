import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Eye, Loader2, Upload } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const STATUS_STYLES = {
  pending: 'bg-[#F5F5F5] text-[#737373] border-[#E5E5E5]',
  processing: 'bg-[#DBEAFE] text-[#1E40AF] border-[#BFDBFE]',
  completed: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
  failed: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
};

const STATUS_LABELS = {
  pending: 'Pending',
  processing: 'Processing',
  completed: 'Completed',
  failed: 'Failed',
};

const TYPE_LABELS = {
  live_analysis: 'Live Analysis',
  order_list: 'Order List',
};

function StatusBadge({ status }) {
  const cls = STATUS_STYLES[status] ?? STATUS_STYLES.pending;
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium ${cls}`}>
      {STATUS_LABELS[status] ?? status}
    </span>
  );
}

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

function formatDateTime(iso) {
  if (!iso) {
    return '—';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

export default function TiktokImportsIndex() {
  const { imports, flash } = usePage().props;
  const [showForm, setShowForm] = useState(false);
  const [reportType, setReportType] = useState('live_analysis');
  const [file, setFile] = useState(null);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});

  const resetForm = () => {
    setReportType('live_analysis');
    setFile(null);
    setPeriodStart('');
    setPeriodEnd('');
    setErrors({});
  };

  const submit = (event) => {
    event.preventDefault();
    setErrors({});
    setSubmitting(true);

    router.post(
      '/livehost/tiktok-imports',
      {
        report_type: reportType,
        file,
        period_start: periodStart,
        period_end: periodEnd,
      },
      {
        forceFormData: true,
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
      <Head title="TikTok Imports" />
      <TopBar
        breadcrumb={['Live Host Desk', 'TikTok Imports']}
        actions={
          <Button
            size="sm"
            onClick={() => setShowForm((prev) => !prev)}
            className="h-9 gap-1.5 rounded-lg bg-[#0A0A0A] text-white hover:bg-[#262626]"
          >
            <Upload className="h-[13px] w-[13px]" strokeWidth={2.5} />
            Upload
          </Button>
        }
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              TikTok Imports
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {imports.total} import{imports.total === 1 ? '' : 's'} — upload a Live Analysis or All
              Order xlsx export. Parsing runs in the background; refresh to see status.
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
                <label htmlFor="report_type" className="text-[12px] font-medium text-[#404040]">
                  Report type
                </label>
                <select
                  id="report_type"
                  value={reportType}
                  onChange={(event) => setReportType(event.target.value)}
                  className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                >
                  <option value="live_analysis">Live Analysis</option>
                  <option value="order_list">Order List</option>
                </select>
                {errors.report_type && (
                  <span className="text-[11px] text-[#DC2626]">{errors.report_type}</span>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <label htmlFor="file" className="text-[12px] font-medium text-[#404040]">
                  xlsx file
                </label>
                <input
                  id="file"
                  type="file"
                  accept=".xlsx,.xls"
                  required
                  onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                  className="h-9 rounded-lg border border-[#EAEAEA] bg-white px-3 py-1.5 text-xs text-[#0A0A0A] file:mr-3 file:rounded-md file:border-0 file:bg-[#F5F5F5] file:px-3 file:py-1 file:text-xs file:font-medium file:text-[#404040] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
                {errors.file && <span className="text-[11px] text-[#DC2626]">{errors.file}</span>}
              </div>
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
                  Upload
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
          {imports.data.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">
              No TikTok imports yet — click “Upload” to add one.
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Uploaded At</th>
                  <th className="px-5 py-3 text-left">Type</th>
                  <th className="px-5 py-3 text-left">Period</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Rows</th>
                  <th className="px-5 py-3 text-left">Uploaded By</th>
                  <th className="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {imports.data.map((imp) => (
                  <tr
                    key={imp.id}
                    className="border-t border-[#F0F0F0] transition-colors hover:bg-[#FAFAFA]"
                  >
                    <td className="px-5 py-3.5 text-[12.5px] tabular-nums text-[#0A0A0A]">
                      {formatDateTime(imp.uploaded_at)}
                    </td>
                    <td className="px-5 py-3.5 text-[13px] text-[#0A0A0A]">
                      {TYPE_LABELS[imp.report_type] ?? imp.report_type}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#404040]">
                      {formatPeriod(imp.period_start, imp.period_end)}
                    </td>
                    <td className="px-5 py-3.5">
                      <StatusBadge status={imp.status} />
                    </td>
                    <td className="px-5 py-3.5 text-right tabular-nums text-[13px] text-[#0A0A0A]">
                      {imp.report_type === 'live_analysis'
                        ? `${imp.matched_rows ?? 0} matched / ${imp.total_rows ?? 0}`
                        : `${imp.total_rows ?? 0}`}
                    </td>
                    <td className="px-5 py-3.5 text-[12.5px] text-[#404040]">
                      {imp.uploaded_by?.name ?? '—'}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <Link
                        href={`/livehost/tiktok-imports/${imp.id}`}
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

        {imports.last_page > 1 && (
          <div className="flex items-center justify-between">
            <div className="text-xs text-[#737373]">
              Showing {imports.from}–{imports.to} of {imports.total}
            </div>
            <div className="flex gap-1">
              {imports.links.map((link, index) => (
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

TiktokImportsIndex.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
