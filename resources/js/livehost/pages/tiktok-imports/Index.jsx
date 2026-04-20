import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
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

  return (
    <>
      <Head title="TikTok Imports" />
      <TopBar
        breadcrumb={['Live Host Desk', 'TikTok Imports']}
        actions={
          <Link
            href="/livehost/tiktok-imports/create"
            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#0A0A0A] px-3 text-[12.5px] font-medium text-white hover:bg-[#262626]"
          >
            <Plus className="h-[13px] w-[13px]" strokeWidth={2.5} />
            New Import
          </Link>
        }
      />

      <div className="space-y-6 p-8">
        <div className="flex flex-wrap items-end justify-between gap-8">
          <div>
            <h1 className="text-3xl font-semibold leading-[1.1] tracking-[-0.03em] text-[#0A0A0A]">
              TikTok Imports
            </h1>
            <p className="mt-1.5 text-sm text-[#737373]">
              {imports.total} import{imports.total === 1 ? '' : 's'} — each targets a single
              TikTok Shop. Parsing runs in the background; refresh to see status.
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

        <div className="overflow-hidden rounded-[16px] border border-[#EAEAEA] bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          {imports.data.length === 0 ? (
            <div className="py-16 text-center text-sm text-[#737373]">
              No TikTok imports yet —{' '}
              <Link
                href="/livehost/tiktok-imports/create"
                className="font-medium text-[#0A0A0A] underline underline-offset-2 hover:text-[#262626]"
              >
                start a new import
              </Link>
              .
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F5F5F5] text-[11.5px] font-medium text-[#737373]">
                  <th className="px-5 py-3 text-left">Uploaded At</th>
                  <th className="px-5 py-3 text-left">Shop</th>
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
                    <td className="px-5 py-3.5 text-[12.5px] text-[#0A0A0A]">
                      {imp.platform_account?.display_name ?? '—'}
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
