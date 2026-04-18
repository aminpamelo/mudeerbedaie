import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Image as ImageIcon, Video } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import StatusChip from '@/livehost/components/StatusChip';
import { Button } from '@/livehost/components/ui/button';

const STATUS_LABELS = {
  scheduled: 'Scheduled',
  live: 'Live',
  ended: 'Ended',
  cancelled: 'Cancelled',
};

function statusChipVariant(status) {
  switch (status) {
    case 'live':
      return 'live';
    case 'ended':
      return 'done';
    case 'cancelled':
      return 'suspended';
    case 'scheduled':
    default:
      return 'scheduled';
  }
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
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDuration(minutes) {
  if (minutes == null || !Number.isFinite(Number(minutes))) {
    return '—';
  }
  const mins = Math.abs(Math.round(Number(minutes)));
  if (mins === 0) {
    return '—';
  }
  if (mins < 60) {
    return `${mins}m`;
  }
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

export default function SessionsShow() {
  const { session, analytics, attachments } = usePage().props;

  return (
    <>
      <Head title={`Session ${session.sessionId}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Sessions', session.sessionId]}
        actions={
          <Link href="/livehost/sessions">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="p-8 space-y-6">
        {/* Hero block */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="flex items-start justify-between gap-6">
            <div className="min-w-0">
              <div className="font-mono text-[12px] uppercase tracking-wide text-[#737373]">
                {session.sessionId}
              </div>
              <h1 className="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {session.title ?? 'Untitled session'}
              </h1>
              <div className="mt-2 text-sm text-[#737373]">
                {session.hostName ? `Host: ${session.hostName}` : 'Unassigned'}
                {session.platformAccount ? ` · ${session.platformAccount}` : ''}
                {session.platformType ? ` · ${session.platformType}` : ''}
              </div>
              {session.description && (
                <p className="mt-3 max-w-2xl text-sm leading-relaxed text-[#404040]">
                  {session.description}
                </p>
              )}
            </div>
            <StatusChip variant={statusChipVariant(session.status)}>
              {STATUS_LABELS[session.status] ?? session.status}
            </StatusChip>
          </div>
        </div>

        {/* Timing */}
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          <InfoTile label="Scheduled start" value={formatDateTime(session.scheduledStart)} />
          <InfoTile label="Actual start" value={formatDateTime(session.actualStart)} />
          <InfoTile label="Actual end" value={formatDateTime(session.actualEnd)} />
          <InfoTile label="Duration" value={formatDuration(session.duration)} />
        </div>

        {/* Analytics */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="mb-4 flex items-center justify-between">
            <div className="text-[15px] font-semibold tracking-[-0.015em]">Analytics</div>
            {!analytics && (
              <span className="text-xs text-[#737373]">No analytics recorded for this session</span>
            )}
          </div>
          {analytics ? (
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
              <MetricTile label="Peak viewers" value={analytics.viewersPeak.toLocaleString()} />
              <MetricTile label="Avg viewers" value={analytics.viewersAvg.toLocaleString()} />
              <MetricTile label="Total likes" value={analytics.totalLikes.toLocaleString()} />
              <MetricTile label="Total comments" value={analytics.totalComments.toLocaleString()} />
              <MetricTile label="Total shares" value={analytics.totalShares.toLocaleString()} />
              <MetricTile
                label="Engagement rate"
                value={`${Number(analytics.engagementRate ?? 0).toFixed(2)}%`}
              />
              <MetricTile
                label="Gifts value"
                value={`$${Number(analytics.giftsValue ?? 0).toFixed(2)}`}
              />
              <MetricTile
                label="Logged duration"
                value={formatDuration(analytics.durationMinutes)}
              />
            </div>
          ) : (
            <div className="py-6 text-sm text-[#737373]">
              Analytics appear once the session ends and stats are imported.
            </div>
          )}
        </div>

        {/* Attachments */}
        <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
          <div className="mb-4 flex items-center justify-between">
            <div className="text-[15px] font-semibold tracking-[-0.015em]">
              Attachments
              <span className="ml-2 rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[11px] text-[#737373]">
                {attachments.length}
              </span>
            </div>
          </div>
          {attachments.length === 0 ? (
            <div className="py-6 text-sm text-[#737373]">
              No attachments have been uploaded for this session.
            </div>
          ) : (
            <div className="flex flex-col gap-3">
              {attachments.map((attachment) => (
                <div
                  key={attachment.id}
                  className="flex items-center gap-4 rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] p-4"
                >
                  <div className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-white text-[#404040]">
                    {attachment.isImage ? (
                      <ImageIcon className="h-5 w-5" strokeWidth={1.8} />
                    ) : attachment.isVideo ? (
                      <Video className="h-5 w-5" strokeWidth={1.8} />
                    ) : (
                      <FileText className="h-5 w-5" strokeWidth={1.8} />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold tracking-[-0.01em] text-[#0A0A0A]">
                      {attachment.fileName}
                    </div>
                    <div className="mt-0.5 flex items-center gap-2 text-[11.5px] text-[#737373]">
                      <span>{attachment.fileSizeFormatted}</span>
                      <span>·</span>
                      <span>{formatDateTime(attachment.createdAt)}</span>
                      {attachment.uploaderName && (
                        <>
                          <span>·</span>
                          <span>Uploaded by {attachment.uploaderName}</span>
                        </>
                      )}
                    </div>
                    {attachment.description && (
                      <p className="mt-1 text-[12.5px] text-[#404040]">{attachment.description}</p>
                    )}
                  </div>
                  <a
                    href={attachment.fileUrl}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="inline-flex items-center gap-1.5 rounded-md border border-[#EAEAEA] bg-white px-3 py-1.5 text-xs font-medium text-[#0A0A0A] hover:bg-[#F5F5F5]"
                  >
                    <Download className="h-3.5 w-3.5" strokeWidth={2} />
                    Download
                  </a>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

SessionsShow.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function InfoTile({ label, value }) {
  return (
    <div className="rounded-[16px] border border-[#EAEAEA] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="mt-2 truncate text-lg font-semibold tracking-[-0.015em]">{value}</div>
    </div>
  );
}

function MetricTile({ label, value }) {
  return (
    <div className="rounded-[12px] bg-[#FAFAFA] p-4">
      <div className="text-[11px] uppercase tracking-wide text-[#737373] font-medium">{label}</div>
      <div className="mt-1.5 text-xl font-semibold tabular-nums tracking-[-0.02em] text-[#0A0A0A]">
        {value}
      </div>
    </div>
  );
}
