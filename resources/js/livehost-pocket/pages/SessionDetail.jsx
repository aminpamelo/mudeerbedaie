import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
  ChevronLeft,
  Image as ImageIcon,
  FileText,
  Video,
  File,
  X,
  Plus,
  UploadCloud,
} from 'lucide-react';
import PocketLayout from '@/livehost-pocket/layouts/PocketLayout';
import { cn } from '@/livehost-pocket/lib/utils';
import { formatSessionScheduleLine } from '@/livehost-pocket/lib/format';

const MISSED_REASONS = [
  { code: 'tech_issue', label: 'Tech / connection issue' },
  { code: 'sick', label: 'Sick' },
  { code: 'account_issue', label: 'Platform account issue' },
  { code: 'schedule_conflict', label: 'Schedule conflict' },
  { code: 'other', label: 'Other' },
];

/**
 * Session detail + recap — screen 03 (UPLOAD/RECAP) of the mockup.
 *
 * Props from {@link \App\Http\Controllers\LiveHostPocket\SessionDetailController::show}:
 *   - `session`      — DTO (id, title, status, schedule/actual timings, image)
 *   - `analytics`    — LiveAnalytics DTO or null
 *   - `attachments`  — array of LiveSessionAttachment DTOs
 *
 * Posts to `live-host.sessions.recap` / `.attachments.store` / `.attachments.destroy`.
 */
export default function SessionDetail() {
  const { session, analytics, attachments, commission } = usePage().props;

  const recap = useForm({
    went_live: session?.status === 'missed'
      ? false
      : (session?.status === 'ended' || session?.status === 'live')
        ? true
        : null,
    actual_start_at: toLocalDatetime(session?.actualStartAt),
    actual_end_at: toLocalDatetime(session?.actualEndAt),
    remarks: session?.remarks ?? '',
    viewers_peak: analytics?.viewersPeak ?? 0,
    viewers_avg: analytics?.viewersAvg ?? 0,
    total_likes: analytics?.totalLikes ?? 0,
    total_comments: analytics?.totalComments ?? 0,
    total_shares: analytics?.totalShares ?? 0,
    gifts_value: analytics?.giftsValue ?? 0,
    gmv_amount: session?.gmvAmount ?? '',
    missed_reason_code: session?.missedReasonCode ?? '',
    missed_reason_note: session?.missedReasonNote ?? '',
  });

  const attachmentInputRef = useRef(null);
  const tiktokScreenshotInputRef = useRef(null);

  // Seed `went_live` from the list-card CTA's query param (`?recap=yes|no`)
  // only on initial mount. Re-running on recap changes would overwrite
  // the user's in-progress edits.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const hint = params.get('recap');
    if (recap.data.went_live === null) {
      if (hint === 'yes') recap.setData('went_live', true);
      if (hint === 'no') recap.setData('went_live', false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const attachmentList = attachments ?? [];
  const proofAttachments = attachmentList.filter(
    (a) => a.attachmentType !== 'tiktok_shop_screenshot'
  );
  const tiktokScreenshotAttachments = attachmentList.filter(
    (a) => a.attachmentType === 'tiktok_shop_screenshot'
  );
  const hasVisualProof = proofAttachments.some((a) =>
    a.fileType?.startsWith('image/') || a.fileType?.startsWith('video/')
  );
  const hasNonVisualOnly = !hasVisualProof && proofAttachments.length > 0;
  const hasTikTokShopScreenshot = tiktokScreenshotAttachments.length > 0;

  const handleSave = () => {
    recap.post(`/live-host/sessions/${session.id}/recap`, {
      preserveScroll: true,
    });
  };

  const handleMarkMissed = () => {
    // The missed path has no file upload, so we send JSON (no forceFormData).
    // That keeps the payload small and avoids multipart parsing quirks in
    // the Pest v4 in-process browser server, where the missed flow is
    // exercised end-to-end in a Playwright run.
    recap.post(`/live-host/sessions/${session.id}/recap`, {
      preserveScroll: true,
    });
  };

  const handleSwitchPath = (nextWentLive) => {
    if (recap.data.went_live === nextWentLive) {
      return;
    }
    // Warn if flipping away from a saved "missed" decision
    if (recap.data.went_live === false && nextWentLive === true && session?.status === 'missed') {
      const ok = window.confirm('Switch from "Did not go live" to "Went live"? Your reason will be cleared.');
      if (!ok) return;
    }
    recap.setData('went_live', nextWentLive);
    recap.clearErrors();
  };

  const handleAttachmentUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    const data = new FormData();
    data.append('file', file);
    router.post(`/live-host/sessions/${session.id}/attachments`, data, {
      preserveScroll: true,
      forceFormData: true,
      onFinish: () => {
        if (attachmentInputRef.current) {
          attachmentInputRef.current.value = '';
        }
      },
    });
  };

  const handleTikTokScreenshotUpload = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    const data = new FormData();
    data.append('file', file);
    data.append('attachment_type', 'tiktok_shop_screenshot');
    router.post(`/live-host/sessions/${session.id}/attachments`, data, {
      preserveScroll: true,
      forceFormData: true,
      onFinish: () => {
        if (tiktokScreenshotInputRef.current) {
          tiktokScreenshotInputRef.current.value = '';
        }
      },
    });
  };

  const handleAttachmentDelete = (attachmentId) => {
    if (!window.confirm('Remove this attachment?')) {
      return;
    }
    router.delete(
      `/live-host/sessions/${session.id}/attachments/${attachmentId}`,
      { preserveScroll: true }
    );
  };

  return (
    <>
      <Head title={session?.title ?? 'Session'} />
      <div className="-mx-5 min-h-full bg-[var(--app-bg)] px-4 pt-3 pb-8">
        <DetailHeader session={session} />

        <SessionHead session={session} />

        <PathSwitch
          value={recap.data.went_live}
          onChange={handleSwitchPath}
        />

        {recap.data.went_live === true ? (
          <>
            <Section title="Proof of live" hint="required">
              <div className="space-y-[10px]">
                {!hasVisualProof ? (
                  <button
                    type="button"
                    onClick={() => attachmentInputRef.current?.click()}
                    className="group relative flex aspect-square w-full flex-col items-center justify-center gap-[14px] overflow-hidden rounded-[18px] border-2 border-dashed border-[var(--accent)] bg-[var(--accent-soft)] px-[20px] text-center transition hover:scale-[1.01]"
                  >
                    <span className="grid h-[56px] w-[56px] place-items-center rounded-full bg-[var(--accent)] text-[var(--accent-ink)] shadow-sm transition group-hover:scale-110">
                      <UploadCloud className="h-[26px] w-[26px]" strokeWidth={2} />
                    </span>
                    <div className="space-y-[6px]">
                      <div className="font-display text-[15px] font-semibold tracking-[-0.01em] text-[var(--accent)]">
                        Upload your live summary
                      </div>
                      <p className="text-[12px] leading-relaxed text-[var(--fg-2)]">
                        Add a <strong className="text-[var(--fg)]">screenshot of your live summary</strong> from the platform (viewers, likes, gifts) so admin can verify your recap.
                      </p>
                    </div>
                    <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
                      Tap to upload &middot; image or video
                    </span>
                  </button>
                ) : null}

                {proofAttachments.length > 0
                  ? proofAttachments.map((attachment) => (
                      <AttachmentRow
                        key={attachment.id}
                        attachment={attachment}
                        onDelete={() => handleAttachmentDelete(attachment.id)}
                      />
                    ))
                  : null}

                {hasVisualProof ? (
                  <button
                    type="button"
                    onClick={() => attachmentInputRef.current?.click()}
                    className="flex w-full items-center justify-center gap-[8px] rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[12px] py-[12px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                  >
                    <Plus className="h-[14px] w-[14px]" strokeWidth={2} />
                    Add another file
                  </button>
                ) : null}

                <input
                  ref={attachmentInputRef}
                  type="file"
                  accept="image/*,video/*,application/pdf"
                  className="hidden"
                  onChange={handleAttachmentUpload}
                />
              </div>
            </Section>

            <ProofHint
              hasVisualProof={hasVisualProof}
              hasNonVisualOnly={hasNonVisualOnly}
              error={recap.errors.proof}
            />

            <Section title="GMV (RM)" hint="required">
              <div
                className={cn(
                  'rounded-[12px] border bg-[var(--app-bg-2)] px-[12px] py-[10px]',
                  recap.errors.gmv_amount ? 'border-[var(--hot)]' : 'border-[var(--accent)]'
                )}
              >
                <div className="mb-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
                  Total GMV for this live
                </div>
                <div className="flex items-baseline gap-[8px]">
                  <span className="font-display text-[20px] font-medium tracking-[-0.03em] text-[var(--accent)]">
                    RM
                  </span>
                  <input
                    type="number"
                    inputMode="decimal"
                    min="0"
                    max="9999999.99"
                    step="0.01"
                    required
                    name="gmv_amount"
                    placeholder="e.g. 1250.00"
                    value={recap.data.gmv_amount ?? ''}
                    onChange={(e) => recap.setData('gmv_amount', e.target.value)}
                    className="w-full bg-transparent font-display text-[20px] font-medium tracking-[-0.03em] tabular-nums text-[var(--accent)] placeholder:text-[var(--fg-3)] focus:outline-none"
                  />
                </div>
                {recap.errors.gmv_amount ? (
                  <FieldError>{recap.errors.gmv_amount}</FieldError>
                ) : null}
              </div>
            </Section>

            <Section title="TikTok Shop screenshot" hint="required">
              <div className="space-y-[10px]">
                {!hasTikTokShopScreenshot ? (
                  <button
                    type="button"
                    onClick={() => tiktokScreenshotInputRef.current?.click()}
                    className="group relative flex w-full flex-col items-center justify-center gap-[10px] overflow-hidden rounded-[14px] border-2 border-dashed border-[var(--accent)] bg-[var(--accent-soft)] px-[20px] py-[24px] text-center transition hover:scale-[1.01]"
                  >
                    <span className="grid h-[44px] w-[44px] place-items-center rounded-full bg-[var(--accent)] text-[var(--accent-ink)] shadow-sm transition group-hover:scale-110">
                      <UploadCloud className="h-[20px] w-[20px]" strokeWidth={2} />
                    </span>
                    <div className="space-y-[4px]">
                      <div className="font-display text-[14px] font-semibold tracking-[-0.01em] text-[var(--accent)]">
                        Upload TikTok Shop backend screenshot
                      </div>
                      <p className="text-[11.5px] leading-relaxed text-[var(--fg-2)]">
                        Tap to upload &middot; image only
                      </p>
                    </div>
                  </button>
                ) : (
                  <>
                    {tiktokScreenshotAttachments.map((attachment) => (
                      <AttachmentRow
                        key={attachment.id}
                        attachment={attachment}
                        onDelete={() => handleAttachmentDelete(attachment.id)}
                      />
                    ))}
                    <button
                      type="button"
                      onClick={() => tiktokScreenshotInputRef.current?.click()}
                      className="flex w-full items-center justify-center gap-[8px] rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[12px] py-[12px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                    >
                      <Plus className="h-[14px] w-[14px]" strokeWidth={2} />
                      Replace screenshot
                    </button>
                  </>
                )}

                <input
                  ref={tiktokScreenshotInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={handleTikTokScreenshotUpload}
                />

                <p className="px-1 text-[11.5px] leading-relaxed text-[var(--fg-2)]">
                  We use this to verify your GMV number.
                </p>
                {recap.errors.tiktok_shop_screenshot ? (
                  <FieldError>{recap.errors.tiktok_shop_screenshot}</FieldError>
                ) : null}
              </div>
            </Section>

            <Section title="Timing">
              <div className="grid grid-cols-1 gap-[10px]">
                <DateTimeField
                  label="Actual start"
                  value={recap.data.actual_start_at}
                  onChange={(v) => recap.setData('actual_start_at', v)}
                  error={recap.errors.actual_start_at}
                />
                <DateTimeField
                  label="Actual end"
                  value={recap.data.actual_end_at}
                  onChange={(v) => recap.setData('actual_end_at', v)}
                  error={recap.errors.actual_end_at}
                />
              </div>
            </Section>

            <Section title="Analytics">
              <div className="grid grid-cols-2 gap-[10px]">
                <NumberField
                  label="Peak viewers"
                  value={recap.data.viewers_peak}
                  onChange={(v) => recap.setData('viewers_peak', v)}
                  error={recap.errors.viewers_peak}
                  accent
                />
                <NumberField
                  label="Avg viewers"
                  value={recap.data.viewers_avg}
                  onChange={(v) => recap.setData('viewers_avg', v)}
                  error={recap.errors.viewers_avg}
                />
                <NumberField
                  label="Likes"
                  value={recap.data.total_likes}
                  onChange={(v) => recap.setData('total_likes', v)}
                  error={recap.errors.total_likes}
                />
                <NumberField
                  label="Comments"
                  value={recap.data.total_comments}
                  onChange={(v) => recap.setData('total_comments', v)}
                  error={recap.errors.total_comments}
                />
                <NumberField
                  label="Shares"
                  value={recap.data.total_shares}
                  onChange={(v) => recap.setData('total_shares', v)}
                  error={recap.errors.total_shares}
                />
                <NumberField
                  label="Gifts value (RM)"
                  value={recap.data.gifts_value}
                  onChange={(v) => recap.setData('gifts_value', v)}
                  error={recap.errors.gifts_value}
                  accent
                  step="0.01"
                />
              </div>
            </Section>

            <Section title="Your remarks">
              <textarea
                value={recap.data.remarks}
                onChange={(e) => recap.setData('remarks', e.target.value)}
                placeholder="How did the session go?"
                rows={4}
                className="w-full resize-none rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px] leading-snug text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--accent)] focus:outline-none"
              />
              {recap.errors.remarks ? (
                <FieldError>{recap.errors.remarks}</FieldError>
              ) : null}
            </Section>

            <EarningsEstimate
              gmvAmount={recap.data.gmv_amount}
              commission={commission}
            />

            <div className="pt-3">
              <button
                type="button"
                onClick={handleSave}
                disabled={
                  recap.processing
                  || !hasVisualProof
                  || !hasTikTokShopScreenshot
                  || recap.data.gmv_amount === ''
                  || recap.data.gmv_amount === null
                }
                className="w-full rounded-[12px] bg-[var(--accent)] px-4 py-[13px] font-sans text-[14px] font-bold tracking-[-0.005em] text-[var(--accent-ink)] transition active:scale-[0.98] disabled:opacity-60"
              >
                {recap.processing ? 'Saving...' : 'Save recap'}
              </button>
            </div>
          </>
        ) : null}

        {recap.data.went_live === false ? (
          <MissedReasonForm
            reasonCode={recap.data.missed_reason_code}
            onReasonCodeChange={(v) => recap.setData('missed_reason_code', v)}
            note={recap.data.missed_reason_note}
            onNoteChange={(v) => recap.setData('missed_reason_note', v)}
            errors={recap.errors}
            processing={recap.processing}
            onSubmit={handleMarkMissed}
          />
        ) : null}

        {recap.data.went_live === null ? (
          <div className="mb-[10px] rounded-[14px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-3 py-6 text-center text-[12px] text-[var(--fg-3)]">
            Choose above whether you went live so we can show the right form.
          </div>
        ) : null}
      </div>
    </>
  );
}

SessionDetail.layout = (page) => <PocketLayout>{page}</PocketLayout>;

function DetailHeader({ session }) {
  const sessionLabel = session?.id ? `SESSION · LS-${String(session.id).padStart(5, '0')}` : 'SESSION';
  return (
    <div className="mb-3 flex items-center justify-between px-1 pt-3">
      <Link
        href="/live-host/sessions"
        className="grid h-8 w-8 place-items-center rounded-full border border-[var(--hair)] bg-[var(--app-bg-2)] text-[var(--fg)] transition hover:border-[var(--fg-2)]"
        aria-label="Back"
      >
        <ChevronLeft className="h-[16px] w-[16px]" strokeWidth={2} />
      </Link>
      <span className="font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {sessionLabel}
      </span>
      <span className="h-8 w-8" aria-hidden="true" />
    </div>
  );
}

function SessionHead({ session }) {
  const scheduleLine = buildScheduleLine(session);
  const statusLabel = {
    live: 'LIVE',
    ended: 'ENDED',
    scheduled: 'SCHED',
    cancelled: 'CANCELLED',
  }[session?.status] ?? 'ENDED';
  const statusClass = {
    live: 'border-[var(--accent)] text-[var(--accent)]',
    scheduled: 'text-[var(--cool)]',
    cancelled: 'text-[var(--hot)]',
    ended: 'text-[var(--fg-2)]',
  }[session?.status] ?? 'text-[var(--fg-2)]';

  return (
    <div className="mb-4 rounded-[16px] border border-[var(--hair)] bg-[var(--app-bg-2)] p-[14px]">
      <div className="mb-2 inline-flex items-center gap-[5px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-2)]">
        <span className="h-1 w-1 bg-[var(--fg-1)]" aria-hidden="true" />
        {session?.platformAccount ?? session?.platformType ?? 'Platform'}
      </div>
      <h1 className="mb-2 font-display text-[19px] font-medium leading-[1.12] tracking-[-0.03em] text-[var(--fg)]">
        {session?.title ?? 'Untitled session'}
      </h1>
      {scheduleLine ? (
        <div className="mb-2 font-mono text-[11px] tracking-[0.02em] text-[var(--fg-2)]">
          {scheduleLine}
        </div>
      ) : null}
      <span
        className={cn(
          'inline-flex items-center rounded-full border border-transparent px-[7px] py-[3px] font-mono text-[8.5px] font-extrabold uppercase tracking-[0.14em]',
          statusClass
        )}
        style={
          session?.status === 'ended'
            ? { backgroundColor: 'var(--hair)' }
            : session?.status === 'scheduled'
              ? { backgroundColor: 'rgba(37,99,235,0.1)' }
              : session?.status === 'cancelled'
                ? { backgroundColor: 'rgba(225,29,72,0.1)' }
                : { backgroundColor: 'var(--accent-soft)' }
        }
      >
        {statusLabel}
      </span>
    </div>
  );
}

function buildScheduleLine(session) {
  if (!session) {
    return null;
  }
  if (session.actualStartAt) {
    return formatSessionScheduleLine({
      start: session.actualStartAt,
      end: session.actualEndAt,
      durationMinutes: session.durationMinutes,
    });
  }
  if (session.scheduledStartAt) {
    return formatSessionScheduleLine({
      start: session.scheduledStartAt,
      end: null,
      durationMinutes: session.durationMinutes,
    });
  }
  return null;
}

function Section({ title, hint, children }) {
  return (
    <div className="mb-4">
      <div className="mb-2 flex items-baseline justify-between px-1">
        <h4 className="font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          {title}
        </h4>
        {hint ? (
          <span className="font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
            {hint}
          </span>
        ) : null}
      </div>
      {children}
    </div>
  );
}

function DateTimeField({ label, value, onChange, error }) {
  return (
    <div className="rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px]">
      <div className="mb-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {label}
      </div>
      <input
        type="datetime-local"
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        className="w-full bg-transparent font-mono text-[13px] text-[var(--fg)] focus:outline-none"
      />
      {error ? <FieldError>{error}</FieldError> : null}
    </div>
  );
}

function NumberField({ label, value, onChange, error, accent = false, step }) {
  return (
    <div
      className={cn(
        'rounded-[12px] border bg-[var(--app-bg-2)] px-[12px] py-[10px]',
        accent ? 'border-[var(--accent)]' : 'border-[var(--hair)]'
      )}
    >
      <div className="mb-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        {label}
      </div>
      <input
        type="number"
        inputMode="decimal"
        min="0"
        step={step ?? '1'}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={cn(
          'w-full bg-transparent font-display text-[20px] font-medium tracking-[-0.03em] tabular-nums focus:outline-none',
          accent ? 'text-[var(--accent)]' : 'text-[var(--fg)]'
        )}
      />
      {error ? <FieldError>{error}</FieldError> : null}
    </div>
  );
}

function AttachmentRow({ attachment, onDelete }) {
  const Icon = pickIcon(attachment.fileType);
  const typeLabel = shortType(attachment.fileType);
  const sizeLabel = formatBytes(attachment.fileSize);
  const isImage = attachment.fileType?.startsWith('image/');
  const isVideo = attachment.fileType?.startsWith('video/');
  const hasPreview = isImage || isVideo;
  const isTikTokShopScreenshot = attachment.attachmentType === 'tiktok_shop_screenshot';

  return (
    <div className="overflow-hidden rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)]">
      {hasPreview ? (
        <a
          href={attachment.fileUrl}
          target="_blank"
          rel="noreferrer"
          className="relative block aspect-square w-full bg-[var(--app-bg)]"
          aria-label={`Open ${attachment.fileName}`}
        >
          {isImage ? (
            <img
              src={attachment.fileUrl}
              alt={attachment.fileName}
              className="h-full w-full object-cover"
              loading="lazy"
            />
          ) : (
            <video
              src={attachment.fileUrl}
              className="h-full w-full object-cover"
              controls
              preload="metadata"
            />
          )}
        </a>
      ) : null}

      <div className="flex items-center gap-[10px] px-[12px] py-[10px]">
        {!hasPreview ? (
          <div className="grid h-[36px] w-[36px] place-items-center rounded-[8px] bg-[var(--app-bg)] text-[var(--fg-2)]">
            <Icon className="h-[16px] w-[16px]" strokeWidth={1.8} />
          </div>
        ) : null}
        <div className="min-w-0 flex-1">
          <a
            href={attachment.fileUrl}
            target="_blank"
            rel="noreferrer"
            className="block truncate text-[13px] font-bold text-[var(--fg)] hover:text-[var(--accent)]"
          >
            {attachment.fileName}
          </a>
          <div className="mt-[2px] flex flex-wrap items-center gap-[6px]">
            <span className="font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
              {typeLabel} &middot; {sizeLabel}
            </span>
            {isTikTokShopScreenshot ? (
              <span className="inline-flex items-center rounded-full border border-[var(--accent)] bg-[var(--accent-soft)] px-[6px] py-[1px] font-mono text-[8.5px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
                TikTok Shop Screenshot
              </span>
            ) : null}
          </div>
        </div>
        <button
          type="button"
          onClick={onDelete}
          className="grid h-[28px] w-[28px] place-items-center rounded-full border border-[var(--hair)] text-[var(--fg-3)] transition hover:border-[var(--hot)] hover:text-[var(--hot)]"
          aria-label={`Remove ${attachment.fileName}`}
        >
          <X className="h-[12px] w-[12px]" strokeWidth={2} />
        </button>
      </div>
    </div>
  );
}

/**
 * Read-only card that previews the host's expected earnings for the session.
 *
 * Depends on Inertia shared props carrying the host's commission plan
 * (Task 11). Until that lands, or when the host is off a platform without a
 * configured rate, we render a graceful fallback rather than an incorrect
 * number. `commission` is expected to look like:
 *   { primaryPlatformRatePercent: number, perLiveRateMyr: number }
 */
function EarningsEstimate({ gmvAmount, commission }) {
  const gmv = Number(gmvAmount);
  const rate = commission?.primaryPlatformRatePercent;
  const perLive = commission?.perLiveRateMyr;
  const hasCommission = Number.isFinite(Number(rate)) && Number.isFinite(Number(perLive));

  if (!hasCommission) {
    return (
      <div className="mb-4 rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[11.5px] leading-relaxed text-[var(--fg-2)]">
        Earnings estimate unavailable &mdash; ask PIC about your commission plan.
      </div>
    );
  }

  if (!Number.isFinite(gmv) || gmv <= 0) {
    return (
      <div className="mb-4 rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[11.5px] leading-relaxed text-[var(--fg-2)]">
        Enter your GMV above to see an earnings estimate.
      </div>
    );
  }

  const gmvCommission = (gmv * Number(rate)) / 100;
  const total = gmvCommission + Number(perLive);

  return (
    <div className="mb-4 rounded-[12px] border border-[var(--accent)] bg-[var(--accent-soft)] px-[14px] py-[12px]">
      <div className="mb-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--accent)]">
        Estimated earnings
      </div>
      <div className="font-display text-[22px] font-medium tracking-[-0.03em] text-[var(--accent)]">
        RM {formatMoney(total)}
      </div>
      <div className="mt-[4px] font-mono text-[10px] text-[var(--fg-2)]">
        RM {formatMoney(gmvCommission)} commission &middot; RM {formatMoney(Number(perLive))} per-live
      </div>
    </div>
  );
}

function formatMoney(value) {
  if (!Number.isFinite(Number(value))) {
    return '0.00';
  }
  return Number(value).toFixed(2);
}

function FieldError({ children }) {
  return (
    <div className="mt-[4px] font-mono text-[10px] font-medium text-[var(--hot)]">
      {children}
    </div>
  );
}

function PathSwitch({ value, onChange }) {
  return (
    <div className="mb-4">
      <div className="mb-2 px-1">
        <h4 id="path-switch-label" className="font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Did you go live?
        </h4>
      </div>
      <div role="group" aria-labelledby="path-switch-label" className="grid grid-cols-2 gap-[8px]">
        <PathButton
          active={value === true}
          onClick={() => onChange(true)}
          accent
        >
          Yes, I went live
        </PathButton>
        <PathButton
          active={value === false}
          onClick={() => onChange(false)}
        >
          No, I missed it
        </PathButton>
      </div>
    </div>
  );
}

function PathButton({ active, onClick, accent = false, children }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        'rounded-[12px] border px-[12px] py-[12px] text-left font-display text-[13px] font-medium leading-tight tracking-[-0.01em] transition',
        active && accent
          ? 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent)]'
          : active
            ? 'border-[var(--hot)] bg-[rgba(225,29,72,0.08)] text-[var(--hot)]'
            : 'border-[var(--hair)] bg-[var(--app-bg-2)] text-[var(--fg-2)]'
      )}
    >
      {children}
    </button>
  );
}

function ProofHint({ hasVisualProof, hasNonVisualOnly, error }) {
  if (error) {
    return (
      <div className="mb-3 rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-[10px] text-[12px] leading-snug text-[var(--hot)]">
        {error}
      </div>
    );
  }

  if (hasVisualProof) {
    return (
      <div className="mb-3 flex items-center gap-[8px] rounded-[10px] border border-[var(--accent)] bg-[var(--accent-soft)] px-3 py-[10px] text-[12px] font-medium text-[var(--accent)]">
        <span className="font-mono text-[11px] font-bold">✓</span>
        Proof attached — you&rsquo;re good to save.
      </div>
    );
  }

  if (hasNonVisualOnly) {
    return (
      <div className="mb-3 rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-[10px] text-[12px] leading-snug text-[var(--hot)]">
        <div className="mb-[2px] font-mono text-[10px] font-bold uppercase tracking-[0.14em]">
          Not enough &mdash; add visual proof
        </div>
        Your file doesn&rsquo;t count as proof you went live. Add at least one{' '}
        <strong>image or video</strong> (a screenshot or short recording works fine).
      </div>
    );
  }

  return (
    <div className="mb-3 rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-[10px] text-[12px] leading-snug text-[var(--hot)]">
      <div className="mb-[2px] font-mono text-[10px] font-bold uppercase tracking-[0.14em]">
        Proof required
      </div>
      Add a screenshot of your live dashboard, or a short screen recording, to
      prove you went live before saving.
    </div>
  );
}

function MissedReasonForm({
  reasonCode,
  onReasonCodeChange,
  note,
  onNoteChange,
  errors,
  processing,
  onSubmit,
}) {
  return (
    <>
      <Section title="Why didn't you go live?">
        <div
          role="radiogroup"
          aria-label="Why didn't you go live?"
          className="space-y-[6px]"
        >
          {MISSED_REASONS.map((r) => (
            <label
              key={r.code}
              className={cn(
                'flex cursor-pointer items-center gap-[10px] rounded-[10px] border bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px]',
                reasonCode === r.code ? 'border-[var(--hot)]' : 'border-[var(--hair)]'
              )}
            >
              <input
                type="radio"
                name="missed_reason_code"
                value={r.code}
                checked={reasonCode === r.code}
                onChange={() => onReasonCodeChange(r.code)}
                className="h-[14px] w-[14px] accent-[var(--hot)]"
              />
              <span className="text-[var(--fg)]">{r.label}</span>
            </label>
          ))}
        </div>
        {errors.missed_reason_code ? <FieldError>{errors.missed_reason_code}</FieldError> : null}
      </Section>

      <Section title="Note (optional)">
        <textarea
          value={note}
          onChange={(e) => onNoteChange(e.target.value)}
          placeholder="Add any context admin should see"
          rows={3}
          maxLength={500}
          className="w-full resize-none rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px] text-[13px] leading-snug text-[var(--fg)] placeholder:text-[var(--fg-3)] focus:border-[var(--hot)] focus:outline-none"
        />
        {errors.missed_reason_note ? <FieldError>{errors.missed_reason_note}</FieldError> : null}
      </Section>

      <div className="pt-3">
        <button
          type="button"
          onClick={onSubmit}
          disabled={processing || !reasonCode}
          className="w-full rounded-[12px] bg-[var(--hot)] px-4 py-[13px] font-sans text-[14px] font-bold tracking-[-0.005em] text-white transition active:scale-[0.98] disabled:opacity-60"
        >
          {processing ? 'Saving...' : 'Mark as missed'}
        </button>
      </div>
    </>
  );
}

function pickIcon(mime) {
  if (!mime) {
    return File;
  }
  if (mime.startsWith('image/')) {
    return ImageIcon;
  }
  if (mime.startsWith('video/')) {
    return Video;
  }
  if (mime === 'application/pdf') {
    return FileText;
  }
  return File;
}

function shortType(mime) {
  if (!mime) {
    return 'FILE';
  }
  if (mime.startsWith('image/')) {
    return 'IMAGE';
  }
  if (mime.startsWith('video/')) {
    return 'VIDEO';
  }
  if (mime === 'application/pdf') {
    return 'PDF';
  }
  const parts = mime.split('/');
  return parts[1]?.toUpperCase() ?? 'FILE';
}

function formatBytes(bytes) {
  const b = Number(bytes);
  if (!Number.isFinite(b) || b <= 0) {
    return '0 B';
  }
  const units = ['B', 'KB', 'MB', 'GB'];
  let v = b;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i += 1;
  }
  return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

/**
 * Convert an ISO timestamp into the local YYYY-MM-DDTHH:mm format expected by
 * `<input type="datetime-local">`. Returns empty string if nullish.
 */
function toLocalDatetime(iso) {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
