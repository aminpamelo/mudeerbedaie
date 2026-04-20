import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Loader2, Upload } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';

const REPORT_TYPES = [
  { value: 'live_analysis', label: 'Live Analysis' },
  { value: 'order_list', label: 'All Orders' },
];

export default function TiktokImportsCreate() {
  const { platformAccounts = [], flash } = usePage().props;

  const [platformAccountId, setPlatformAccountId] = useState(
    platformAccounts[0]?.id ? String(platformAccounts[0].id) : ''
  );
  const [reportType, setReportType] = useState('live_analysis');
  const [file, setFile] = useState(null);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [clientError, setClientError] = useState('');

  const selectedAccount = platformAccounts.find(
    (acc) => String(acc.id) === String(platformAccountId)
  );

  const submit = (event) => {
    event.preventDefault();
    setErrors({});
    setClientError('');

    if (!platformAccountId) {
      setClientError('Please select a TikTok Shop account.');
      return;
    }
    if (!file) {
      setClientError('Please choose an xlsx file to upload.');
      return;
    }
    if (periodStart && periodEnd && periodEnd < periodStart) {
      setClientError('Period end must be on or after period start.');
      return;
    }

    setSubmitting(true);

    router.post(
      '/livehost/tiktok-imports',
      {
        platform_account_id: platformAccountId,
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
      }
    );
  };

  return (
    <>
      <Head title="New TikTok Import" />
      <TopBar
        breadcrumb={['Live Host Desk', 'TikTok Imports', 'New']}
        actions={
          <Link
            href="/livehost/tiktok-imports"
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-[#EAEAEA] bg-white px-3 text-[12.5px] font-medium text-[#404040] hover:bg-[#F5F5F5]"
          >
            <ArrowLeft className="h-[13px] w-[13px]" strokeWidth={2.5} />
            Back to list
          </Link>
        }
      />

      <div className="space-y-6 p-8">
        <div>
          <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
            New TikTok Import
          </h1>
          <p className="mt-1.5 text-sm text-[#737373]">
            Upload a Live Analysis or All Orders xlsx export for one TikTok Shop. Parsing runs in
            the background — status updates on the import detail page; refresh to see progress.
          </p>
        </div>

        {flash?.error && (
          <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {flash.error}
          </div>
        )}
        {clientError && (
          <div className="rounded-[12px] border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {clientError}
          </div>
        )}

        <form
          onSubmit={submit}
          className="max-w-2xl rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
        >
          <div className="space-y-5">
            <div className="flex flex-col gap-1.5">
              <label
                htmlFor="platform_account_id"
                className="text-[12px] font-medium text-[#404040]"
              >
                TikTok Shop account
              </label>
              <select
                id="platform_account_id"
                value={platformAccountId}
                onChange={(event) => setPlatformAccountId(event.target.value)}
                required
                className="h-9 rounded-md border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                <option value="" disabled>
                  {platformAccounts.length === 0
                    ? 'No TikTok Shop accounts available'
                    : 'Select a shop…'}
                </option>
                {platformAccounts.map((acc) => (
                  <option key={acc.id} value={acc.id}>
                    {acc.display_name}
                    {acc.owner_name ? ` — ${acc.owner_name}` : ''}
                  </option>
                ))}
              </select>
              {selectedAccount?.owner_name && (
                <span className="text-[11.5px] text-[#737373]">
                  Owner: {selectedAccount.owner_name}
                </span>
              )}
              {errors.platform_account_id && (
                <span className="text-[11px] text-[#DC2626]">{errors.platform_account_id}</span>
              )}
            </div>

            <div className="flex flex-col gap-1.5">
              <span className="text-[12px] font-medium text-[#404040]">Report type</span>
              <div className="flex flex-wrap gap-2">
                {REPORT_TYPES.map((opt) => (
                  <label
                    key={opt.value}
                    className={[
                      'flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors',
                      reportType === opt.value
                        ? 'border-[#0A0A0A] bg-[#0A0A0A] text-white'
                        : 'border-[#EAEAEA] bg-white text-[#404040] hover:bg-[#F5F5F5]',
                    ].join(' ')}
                  >
                    <input
                      type="radio"
                      name="report_type"
                      value={opt.value}
                      checked={reportType === opt.value}
                      onChange={(event) => setReportType(event.target.value)}
                      className="sr-only"
                    />
                    {opt.label}
                  </label>
                ))}
              </div>
              {errors.report_type && (
                <span className="text-[11px] text-[#DC2626]">{errors.report_type}</span>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                  className="h-9 rounded-md border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
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
                  min={periodStart || undefined}
                  value={periodEnd}
                  onChange={(event) => setPeriodEnd(event.target.value)}
                  className="h-9 rounded-md border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
                {errors.period_end && (
                  <span className="text-[11px] text-[#DC2626]">{errors.period_end}</span>
                )}
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="file" className="text-[12px] font-medium text-[#404040]">
                xlsx file
              </label>
              <div className="rounded-md border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-4 py-5">
                <input
                  id="file"
                  type="file"
                  accept=".xlsx,.xls"
                  required
                  onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                  className="w-full text-xs text-[#0A0A0A] file:mr-3 file:rounded-md file:border-0 file:bg-[#0A0A0A] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-[#262626]"
                />
                <p className="mt-2 text-[11.5px] text-[#737373]">
                  Drag & drop or click to select. Max 20 MB. TikTok Seller Center → Analytics →
                  Export.
                </p>
                {file && (
                  <p className="mt-1 text-[11.5px] text-[#0A0A0A]">
                    Selected: <span className="font-medium">{file.name}</span>
                  </p>
                )}
              </div>
              {errors.file && <span className="text-[11px] text-[#DC2626]">{errors.file}</span>}
            </div>

            <div className="flex items-center gap-3 border-t border-[#F0F0F0] pt-4">
              <Button
                type="submit"
                size="sm"
                disabled={submitting || platformAccounts.length === 0}
                className="h-9 gap-1.5 rounded-lg bg-[#0A0A0A] text-white hover:bg-[#262626]"
              >
                {submitting ? (
                  <Loader2 className="h-[13px] w-[13px] animate-spin" strokeWidth={2.5} />
                ) : (
                  <Upload className="h-[13px] w-[13px]" strokeWidth={2.5} />
                )}
                Start import
              </Button>
              <Link
                href="/livehost/tiktok-imports"
                className="inline-flex h-9 items-center rounded-lg px-3 text-[12.5px] font-medium text-[#737373] hover:text-[#0A0A0A]"
              >
                Cancel
              </Link>
            </div>
          </div>
        </form>

        <div className="max-w-2xl rounded-[12px] border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-3 text-[12.5px] text-[#4B5563]">
          Imports process in the background. Status updates on the import detail page — refresh to
          see progress.
        </div>
      </div>
    </>
  );
}

TiktokImportsCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;
