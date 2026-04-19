import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
  ChevronLeft,
  Image as ImageIcon,
  FileText,
  Video,
  File,
  X,
  Plus,
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
  const { session, analytics, attachments } = usePage().props;

  const recap = useForm({
    went_live: session?.status === 'missed' ? false : (session?.status === 'ended' ? true : null),
    cover_image: null,
    actual_start_at: toLocalDatetime(session?.actualStartAt),
    actual_end_at: toLocalDatetime(session?.actualEndAt),
    remarks: session?.remarks ?? '',
    viewers_peak: analytics?.viewersPeak ?? 0,
    viewers_avg: analytics?.viewersAvg ?? 0,
    total_likes: analytics?.totalLikes ?? 0,
    total_comments: analytics?.totalComments ?? 0,
    total_shares: analytics?.totalShares ?? 0,
    gifts_value: analytics?.giftsValue ?? 0,
    missed_reason_code: session?.missedReasonCode ?? '',
    missed_reason_note: session?.missedReasonNote ?? '',
  });

  const [coverPreview, setCoverPreview] = useState(session?.imageUrl ?? null);
  const coverInputRef = useRef(null);
  const attachmentInputRef = useRef(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const hint = params.get('recap');
    if (recap.data.went_live === null) {
      if (hint === 'yes') recap.setData('went_live', true);
      if (hint === 'no') recap.setData('went_live', false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const hasVisualProof = (attachments ?? []).some((a) =>
    a.fileType?.startsWith('image/') || a.fileType?.startsWith('video/')
  );

  const handleCoverChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    recap.setData('cover_image', file);
    const reader = new FileReader();
    reader.onload = () => setCoverPreview(reader.result);
    reader.readAsDataURL(file);
  };

  const handleSave = (event) => {
    event.preventDefault();
    recap.post(`/live-host/sessions/${session.id}/recap`, {
      preserveScroll: true,
      forceFormData: true,
    });
  };

  const handleMarkMissed = () => {
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
            <Section title="Cover image" hint="image_path">
              <CoverUpload
                preview={coverPreview}
                onPick={() => coverInputRef.current?.click()}
              />
              <input
                ref={coverInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={handleCoverChange}
              />
              {recap.errors.cover_image ? (
                <FieldError>{recap.errors.cover_image}</FieldError>
              ) : null}
            </Section>

            <Section title="Timing" hint="actual_start_at / actual_end_at">
              <div className="grid grid-cols-1 gap-[10px]">
                <DateTimeField
                  label="Actual start"
                  name="actual_start_at"
                  value={recap.data.actual_start_at}
                  onChange={(v) => recap.setData('actual_start_at', v)}
                  error={recap.errors.actual_start_at}
                />
                <DateTimeField
                  label="Actual end"
                  name="actual_end_at"
                  value={recap.data.actual_end_at}
                  onChange={(v) => recap.setData('actual_end_at', v)}
                  error={recap.errors.actual_end_at}
                />
              </div>
            </Section>

            <Section title="Analytics" hint="LiveAnalytics">
              <div className="grid grid-cols-2 gap-[10px]">
                <NumberField
                  label="Peak viewers"
                  hint="viewers_peak"
                  value={recap.data.viewers_peak}
                  onChange={(v) => recap.setData('viewers_peak', v)}
                  error={recap.errors.viewers_peak}
                  accent
                />
                <NumberField
                  label="Avg viewers"
                  hint="viewers_avg"
                  value={recap.data.viewers_avg}
                  onChange={(v) => recap.setData('viewers_avg', v)}
                  error={recap.errors.viewers_avg}
                />
                <NumberField
                  label="Likes"
                  hint="total_likes"
                  value={recap.data.total_likes}
                  onChange={(v) => recap.setData('total_likes', v)}
                  error={recap.errors.total_likes}
                />
                <NumberField
                  label="Comments"
                  hint="total_comments"
                  value={recap.data.total_comments}
                  onChange={(v) => recap.setData('total_comments', v)}
                  error={recap.errors.total_comments}
                />
                <NumberField
                  label="Shares"
                  hint="total_shares"
                  value={recap.data.total_shares}
                  onChange={(v) => recap.setData('total_shares', v)}
                  error={recap.errors.total_shares}
                />
                <NumberField
                  label="Gifts value (RM)"
                  hint="gifts_value"
                  value={recap.data.gifts_value}
                  onChange={(v) => recap.setData('gifts_value', v)}
                  error={recap.errors.gifts_value}
                  accent
                  step="0.01"
                />
              </div>
            </Section>

            <Section title="Your remarks" hint="remarks">
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

            <Section title="Attachments" hint="LiveSessionAttachment">
              <div className="space-y-[8px]">
                {Array.isArray(attachments) && attachments.length > 0 ? (
                  attachments.map((attachment) => (
                    <AttachmentRow
                      key={attachment.id}
                      attachment={attachment}
                      onDelete={() => handleAttachmentDelete(attachment.id)}
                    />
                  ))
                ) : null}
                <button
                  type="button"
                  onClick={() => attachmentInputRef.current?.click()}
                  className="flex w-full items-center justify-center gap-[8px] rounded-[12px] border border-dashed border-[var(--hair-2)] bg-[var(--app-bg-2)] px-[12px] py-[14px] font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                >
                  <Plus className="h-[14px] w-[14px]" strokeWidth={2} />
                  Add file
                </button>
                <input
                  ref={attachmentInputRef}
                  type="file"
                  className="hidden"
                  onChange={handleAttachmentUpload}
                />
              </div>
            </Section>

            <ProofHint hasVisualProof={hasVisualProof} error={recap.errors.proof} />

            <div className="pt-3">
              <button
                type="button"
                onClick={handleSave}
                disabled={recap.processing || !hasVisualProof}
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

function CoverUpload({ preview, onPick }) {
  return (
    <button
      type="button"
      onClick={onPick}
      className="relative flex aspect-[16/9] w-full items-center justify-center overflow-hidden rounded-[14px] border border-[var(--hair)] bg-[var(--app-bg-2)] transition hover:border-[var(--accent)]"
    >
      {preview ? (
        <>
          <img
            src={preview}
            alt="Cover"
            className="h-full w-full object-cover"
          />
          <span className="absolute bottom-2 right-2 rounded-full bg-black/60 px-[9px] py-[4px] font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-white">
            Change
          </span>
        </>
      ) : (
        <div className="flex flex-col items-center gap-[6px] text-[var(--fg-3)]">
          <ImageIcon className="h-[24px] w-[24px]" strokeWidth={1.8} />
          <span className="font-mono text-[10px] font-bold uppercase tracking-[0.14em]">
            Tap to upload
          </span>
        </div>
      )}
    </button>
  );
}

function DateTimeField({ label, name, value, onChange, error }) {
  return (
    <div className="rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px]">
      <div className="mb-[4px] flex items-baseline justify-between font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        <span>{label}</span>
        <span>{name}</span>
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

function NumberField({ label, hint, value, onChange, error, accent = false, step }) {
  return (
    <div
      className={cn(
        'rounded-[12px] border bg-[var(--app-bg-2)] px-[12px] py-[10px]',
        accent ? 'border-[var(--accent)]' : 'border-[var(--hair)]'
      )}
    >
      <div className="mb-[4px] flex items-baseline justify-between font-mono text-[9px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        <span>{label}</span>
        <span>{hint}</span>
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

  return (
    <div className="flex items-center gap-[10px] rounded-[12px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-[12px] py-[10px]">
      <div className="grid h-[36px] w-[36px] place-items-center rounded-[8px] bg-[var(--app-bg)] text-[var(--fg-2)]">
        <Icon className="h-[16px] w-[16px]" strokeWidth={1.8} />
      </div>
      <div className="min-w-0 flex-1">
        <a
          href={attachment.fileUrl}
          target="_blank"
          rel="noreferrer"
          className="block truncate text-[13px] font-bold text-[var(--fg)] hover:text-[var(--accent)]"
        >
          {attachment.fileName}
        </a>
        <div className="mt-[2px] font-mono text-[9.5px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
          {typeLabel} &middot; {sizeLabel}
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
  );
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
        <h4 className="font-display text-[12.5px] font-medium tracking-[-0.01em] text-[var(--fg)]">
          Did you go live?
        </h4>
      </div>
      <div className="grid grid-cols-2 gap-[8px]">
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

function ProofHint({ hasVisualProof, error }) {
  if (hasVisualProof && !error) {
    return (
      <div className="mb-3 rounded-[10px] border border-[var(--hair)] bg-[var(--app-bg-2)] px-3 py-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--fg-3)]">
        Proof attached &check;
      </div>
    );
  }
  return (
    <div className="mb-3 rounded-[10px] border border-[var(--hot)] bg-[rgba(225,29,72,0.08)] px-3 py-2 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--hot)]">
      {error ?? 'Proof \u00b7 image or video attachment required'}
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
      <Section title="Why didn't you go live?" hint="missed_reason_code">
        <div className="space-y-[6px]">
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

      <Section title="Note (optional)" hint="missed_reason_note">
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
