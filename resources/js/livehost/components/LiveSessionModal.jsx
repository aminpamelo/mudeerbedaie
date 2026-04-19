import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import {
  BadgeCheck,
  Check,
  Download,
  ExternalLink,
  FileText,
  Image as ImageIcon,
  Paperclip,
  Pencil,
  RotateCcw,
  Trash2,
  Upload,
  Video,
  X,
  XCircle,
} from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/livehost/components/ui/dialog';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';
import StatusChip from '@/livehost/components/StatusChip';

const STATUS_OPTIONS = [
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'live', label: 'Live' },
  { value: 'ended', label: 'Ended' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'missed', label: 'Missed' },
];

const MISSED_REASON_OPTIONS = [
  { value: '', label: '—' },
  { value: 'tech_issue', label: 'Tech / connection issue' },
  { value: 'sick', label: 'Sick' },
  { value: 'account_issue', label: 'Platform account issue' },
  { value: 'schedule_conflict', label: 'Schedule conflict' },
  { value: 'other', label: 'Other' },
];

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

function statusLabel(status) {
  return STATUS_OPTIONS.find((s) => s.value === status)?.label ?? status;
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

export default function LiveSessionModal({ open, onOpenChange, session, hosts = [], platformAccounts = [] }) {
  const [editing, setEditing] = useState(false);
  const [verifyNotesOpen, setVerifyNotesOpen] = useState(false);
  const fileInputRef = useRef(null);

  const form = useForm({
    title: '',
    description: '',
    live_host_id: '',
    platform_account_id: '',
    status: 'scheduled',
    scheduled_start_at: '',
    actual_start_at: '',
    actual_end_at: '',
    duration_minutes: '',
    remarks: '',
    missed_reason_code: '',
    missed_reason_note: '',
    analytics: {
      viewers_peak: 0,
      viewers_avg: 0,
      total_likes: 0,
      total_comments: 0,
      total_shares: 0,
      gifts_value: 0,
    },
  });

  const uploadForm = useForm({
    file: null,
    description: '',
  });

  const verifyForm = useForm({
    verification_status: 'pending',
    verification_notes: '',
  });

  useEffect(() => {
    if (!open) {
      setEditing(false);
      setVerifyNotesOpen(false);
      uploadForm.reset();
      uploadForm.clearErrors();
      verifyForm.reset();
      verifyForm.clearErrors();
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      return;
    }
    if (!session) {
      return;
    }
    form.clearErrors();
    form.setData({
      title: session.title ?? '',
      description: session.description ?? '',
      live_host_id: session.hostId ? String(session.hostId) : '',
      platform_account_id: session.platformAccountId ? String(session.platformAccountId) : '',
      status: session.status ?? 'scheduled',
      scheduled_start_at: toLocalDatetime(session.scheduledStart),
      actual_start_at: toLocalDatetime(session.actualStart),
      actual_end_at: toLocalDatetime(session.actualEnd),
      duration_minutes: session.durationMinutes != null ? String(session.durationMinutes) : '',
      remarks: session.remarks ?? '',
      missed_reason_code: session.missedReasonCode ?? '',
      missed_reason_note: session.missedReasonNote ?? '',
      analytics: {
        viewers_peak: session.analytics?.viewersPeak ?? 0,
        viewers_avg: session.analytics?.viewersAvg ?? 0,
        total_likes: session.analytics?.totalLikes ?? 0,
        total_comments: session.analytics?.totalComments ?? 0,
        total_shares: session.analytics?.totalShares ?? 0,
        gifts_value: session.analytics?.giftsValue ?? 0,
      },
    });
    verifyForm.setData({
      verification_status: session.verificationStatus ?? 'pending',
      verification_notes: session.verificationNotes ?? '',
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, session?.id]);

  if (!session) {
    return null;
  }

  const submit = (event) => {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      live_host_id: data.live_host_id === '' ? null : Number(data.live_host_id),
      platform_account_id: data.platform_account_id === '' ? null : Number(data.platform_account_id),
      scheduled_start_at: data.scheduled_start_at || null,
      actual_start_at: data.actual_start_at || null,
      actual_end_at: data.actual_end_at || null,
      duration_minutes: data.duration_minutes === '' ? null : Number(data.duration_minutes),
      remarks: data.remarks || null,
      missed_reason_code: data.status === 'missed' ? (data.missed_reason_code || null) : null,
      missed_reason_note: data.status === 'missed' ? (data.missed_reason_note || null) : null,
      analytics: {
        viewers_peak: Number(data.analytics.viewers_peak) || 0,
        viewers_avg: Number(data.analytics.viewers_avg) || 0,
        total_likes: Number(data.analytics.total_likes) || 0,
        total_comments: Number(data.analytics.total_comments) || 0,
        total_shares: Number(data.analytics.total_shares) || 0,
        gifts_value: Number(data.analytics.gifts_value) || 0,
      },
    }));
    form.put(`/livehost/sessions/${session.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setEditing(false);
        onOpenChange(false);
      },
    });
  };

  const setAnalytics = (key, value) => {
    form.setData('analytics', { ...form.data.analytics, [key]: value });
  };

  const handleFileSelect = (event) => {
    const file = event.target.files?.[0] ?? null;
    uploadForm.setData('file', file);
  };

  const clearPendingUpload = () => {
    uploadForm.reset();
    uploadForm.clearErrors();
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const submitUpload = (event) => {
    event.preventDefault();
    if (!uploadForm.data.file) {
      return;
    }
    uploadForm.post(`/livehost/sessions/${session.id}/attachments`, {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        clearPendingUpload();
      },
    });
  };

  const submitVerify = (nextStatus) => {
    verifyForm.transform((data) => ({
      ...data,
      verification_status: nextStatus,
      verification_notes: data.verification_notes || null,
    }));
    verifyForm.post(`/livehost/sessions/${session.id}/verify`, {
      preserveScroll: true,
      onSuccess: () => {
        setVerifyNotesOpen(false);
      },
    });
  };

  const handleDeleteAttachment = (attachment) => {
    if (!window.confirm(`Delete ${attachment.fileName}?`)) {
      return;
    }
    router.delete(`/livehost/sessions/${session.id}/attachments/${attachment.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[580px]">
        <DialogHeader className="text-left">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="font-mono text-[11px] uppercase tracking-wide text-[#737373]">
                {session.sessionId}
              </div>
              <DialogTitle className="mt-1 text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
                {editing ? 'Edit session' : (session.title ?? 'Untitled session')}
              </DialogTitle>
              <DialogDescription className="mt-1 text-[12.5px] text-[#737373]">
                {session.platformAccount ?? 'No platform account'}
                {session.platformType ? ` · ${session.platformType}` : ''}
              </DialogDescription>
            </div>
            {!editing && (
              <StatusChip variant={statusChipVariant(session.status)}>
                {statusLabel(session.status)}
              </StatusChip>
            )}
          </div>
        </DialogHeader>

        {editing ? (
          <form onSubmit={submit} className="space-y-5">
            <Fieldset title="Basics">
              <ModalField label="Title" error={form.errors.title}>
                <Input
                  value={form.data.title}
                  onChange={(e) => form.setData('title', e.target.value)}
                  maxLength={255}
                  placeholder="Session title"
                />
              </ModalField>

              <ModalField label="Description" error={form.errors.description}>
                <textarea
                  value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)}
                  rows={3}
                  maxLength={5000}
                  placeholder="Notes about this session…"
                  className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
              </ModalField>

              <div className="grid grid-cols-2 gap-3">
                <ModalField label="Host" error={form.errors.live_host_id}>
                  <ModalSelect
                    value={form.data.live_host_id}
                    onChange={(e) => form.setData('live_host_id', e.target.value)}
                  >
                    <option value="">Unassigned</option>
                    {hosts.map((h) => (
                      <option key={h.id} value={h.id}>
                        {h.name}
                      </option>
                    ))}
                  </ModalSelect>
                </ModalField>

                <ModalField label="Platform account" error={form.errors.platform_account_id}>
                  <ModalSelect
                    value={form.data.platform_account_id}
                    onChange={(e) => form.setData('platform_account_id', e.target.value)}
                  >
                    <option value="">None</option>
                    {platformAccounts.map((pa) => (
                      <option key={pa.id} value={pa.id}>
                        {pa.name}{pa.platform ? ` · ${pa.platform}` : ''}
                      </option>
                    ))}
                  </ModalSelect>
                </ModalField>
              </div>

              <ModalField label="Status" error={form.errors.status} required>
                <ModalSelect
                  value={form.data.status}
                  onChange={(e) => form.setData('status', e.target.value)}
                  required
                >
                  {STATUS_OPTIONS.map((s) => (
                    <option key={s.value} value={s.value}>
                      {s.label}
                    </option>
                  ))}
                </ModalSelect>
              </ModalField>
            </Fieldset>

            <Fieldset title="Timing">
              <ModalField label="Scheduled start" error={form.errors.scheduled_start_at}>
                <Input
                  type="datetime-local"
                  value={form.data.scheduled_start_at}
                  onChange={(e) => form.setData('scheduled_start_at', e.target.value)}
                />
              </ModalField>

              <div className="grid grid-cols-2 gap-3">
                <ModalField label="Actual start" error={form.errors.actual_start_at}>
                  <Input
                    type="datetime-local"
                    value={form.data.actual_start_at}
                    onChange={(e) => form.setData('actual_start_at', e.target.value)}
                  />
                </ModalField>
                <ModalField label="Actual end" error={form.errors.actual_end_at}>
                  <Input
                    type="datetime-local"
                    value={form.data.actual_end_at}
                    onChange={(e) => form.setData('actual_end_at', e.target.value)}
                  />
                </ModalField>
              </div>

              <ModalField label="Duration (minutes)" error={form.errors.duration_minutes}>
                <Input
                  type="number"
                  min="0"
                  step="1"
                  value={form.data.duration_minutes}
                  onChange={(e) => form.setData('duration_minutes', e.target.value)}
                  placeholder="Auto-calculated if blank"
                />
              </ModalField>
            </Fieldset>

            <Fieldset title="Host remarks">
              <ModalField label="Remarks" error={form.errors.remarks}>
                <textarea
                  value={form.data.remarks}
                  onChange={(e) => form.setData('remarks', e.target.value)}
                  rows={3}
                  maxLength={5000}
                  placeholder="How did the session go?"
                  className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                />
              </ModalField>
            </Fieldset>

            <Fieldset title="Analytics">
              <div className="grid grid-cols-2 gap-3">
                <AnalyticsField
                  label="Peak viewers"
                  value={form.data.analytics.viewers_peak}
                  onChange={(v) => setAnalytics('viewers_peak', v)}
                  error={form.errors['analytics.viewers_peak']}
                />
                <AnalyticsField
                  label="Avg viewers"
                  value={form.data.analytics.viewers_avg}
                  onChange={(v) => setAnalytics('viewers_avg', v)}
                  error={form.errors['analytics.viewers_avg']}
                />
                <AnalyticsField
                  label="Likes"
                  value={form.data.analytics.total_likes}
                  onChange={(v) => setAnalytics('total_likes', v)}
                  error={form.errors['analytics.total_likes']}
                />
                <AnalyticsField
                  label="Comments"
                  value={form.data.analytics.total_comments}
                  onChange={(v) => setAnalytics('total_comments', v)}
                  error={form.errors['analytics.total_comments']}
                />
                <AnalyticsField
                  label="Shares"
                  value={form.data.analytics.total_shares}
                  onChange={(v) => setAnalytics('total_shares', v)}
                  error={form.errors['analytics.total_shares']}
                />
                <AnalyticsField
                  label="Gifts value (RM)"
                  value={form.data.analytics.gifts_value}
                  onChange={(v) => setAnalytics('gifts_value', v)}
                  error={form.errors['analytics.gifts_value']}
                  step="0.01"
                />
              </div>
            </Fieldset>

            {form.data.status === 'missed' && (
              <Fieldset title="Missed reason">
                <ModalField label="Reason" error={form.errors.missed_reason_code}>
                  <ModalSelect
                    value={form.data.missed_reason_code}
                    onChange={(e) => form.setData('missed_reason_code', e.target.value)}
                  >
                    {MISSED_REASON_OPTIONS.map((r) => (
                      <option key={r.value} value={r.value}>
                        {r.label}
                      </option>
                    ))}
                  </ModalSelect>
                </ModalField>

                <ModalField label="Note" error={form.errors.missed_reason_note}>
                  <textarea
                    value={form.data.missed_reason_note}
                    onChange={(e) => form.setData('missed_reason_note', e.target.value)}
                    rows={2}
                    maxLength={1000}
                    placeholder="Optional note"
                    className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
                  />
                </ModalField>
              </Fieldset>
            )}

            <DialogFooter className="gap-2 sm:gap-2">
              <Button
                type="button"
                variant="ghost"
                onClick={() => setEditing(false)}
                className="text-[#737373]"
                disabled={form.processing}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Saving…' : 'Save changes'}
              </Button>
            </DialogFooter>
          </form>
        ) : (
          <div className="space-y-5">
            {session.description && (
              <p className="text-[13px] leading-relaxed text-[#404040]">{session.description}</p>
            )}

            <div className="grid grid-cols-2 gap-3">
              <InfoRow label="Host" value={session.hostName ?? 'Unassigned'} />
              <InfoRow label="Host email" value={session.hostEmail ?? '—'} />
              <InfoRow label="Scheduled" value={formatDateTime(session.scheduledStart)} />
              <InfoRow label="Duration" value={formatDuration(session.duration)} />
              <InfoRow label="Actual start" value={formatDateTime(session.actualStart)} />
              <InfoRow label="Actual end" value={formatDateTime(session.actualEnd)} />
            </div>

            <VerificationPanel
              session={session}
              verifyForm={verifyForm}
              notesOpen={verifyNotesOpen}
              onToggleNotes={() => setVerifyNotesOpen((v) => !v)}
              onVerify={() => submitVerify('verified')}
              onReject={() => submitVerify('rejected')}
              onReset={() => submitVerify('pending')}
            />

            <div>
              <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <Paperclip className="h-3.5 w-3.5 text-[#737373]" strokeWidth={2} />
                  <span className="text-[10.5px] uppercase tracking-wide text-[#A3A3A3] font-medium">
                    Attachments
                  </span>
                  <span className="rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10.5px] text-[#737373]">
                    {session.attachments?.length ?? 0}
                  </span>
                </div>
                {!uploadForm.data.file && (
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1 text-[11.5px] font-medium text-[#404040] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
                  >
                    <Upload className="h-3 w-3" strokeWidth={2} />
                    Upload
                  </button>
                )}
              </div>

              <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                onChange={handleFileSelect}
              />

              {uploadForm.data.file && (
                <form
                  onSubmit={submitUpload}
                  className="mb-3 rounded-[10px] border border-[#EAEAEA] bg-[#FAFAFA] p-3"
                >
                  <div className="flex items-center gap-2">
                    <Paperclip className="h-3.5 w-3.5 shrink-0 text-[#737373]" strokeWidth={2} />
                    <span className="min-w-0 flex-1 truncate text-[12.5px] font-medium text-[#0A0A0A]">
                      {uploadForm.data.file.name}
                    </span>
                    <span className="shrink-0 font-mono text-[10.5px] text-[#737373]">
                      {formatFileSize(uploadForm.data.file.size)}
                    </span>
                    <button
                      type="button"
                      onClick={clearPendingUpload}
                      className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[#737373] hover:bg-white hover:text-[#0A0A0A]"
                      title="Remove"
                    >
                      <X className="h-3 w-3" strokeWidth={2} />
                    </button>
                  </div>
                  <Input
                    value={uploadForm.data.description}
                    onChange={(e) => uploadForm.setData('description', e.target.value)}
                    maxLength={255}
                    placeholder="Description (optional)"
                    className="mt-2"
                  />
                  {uploadForm.errors.file && (
                    <p className="mt-1 text-xs text-[#F43F5E]">{uploadForm.errors.file}</p>
                  )}
                  {uploadForm.errors.description && (
                    <p className="mt-1 text-xs text-[#F43F5E]">
                      {uploadForm.errors.description}
                    </p>
                  )}
                  <div className="mt-2 flex justify-end gap-2">
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={clearPendingUpload}
                      className="text-[#737373]"
                      disabled={uploadForm.processing}
                    >
                      Cancel
                    </Button>
                    <Button type="submit" disabled={uploadForm.processing} className="gap-1.5">
                      <Upload className="h-3.5 w-3.5" strokeWidth={2} />
                      {uploadForm.processing
                        ? `Uploading${uploadForm.progress ? ` ${uploadForm.progress.percentage}%` : '…'}`
                        : 'Upload'}
                    </Button>
                  </div>
                </form>
              )}

              {session.attachments && session.attachments.length > 0 ? (
                <div className="flex flex-col gap-2">
                  {session.attachments.map((attachment) => (
                    <AttachmentRow
                      key={attachment.id}
                      attachment={attachment}
                      onDelete={() => handleDeleteAttachment(attachment)}
                    />
                  ))}
                </div>
              ) : (
                !uploadForm.data.file && (
                  <div className="rounded-[10px] border border-dashed border-[#EAEAEA] bg-[#FAFAFA] px-3 py-3 text-center text-[11.5px] text-[#737373]">
                    No attachments uploaded yet.
                  </div>
                )
              )}
            </div>

            <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-between sm:gap-2">
              <Link
                href={`/livehost/sessions/${session.id}`}
                className="inline-flex items-center gap-1.5 text-[12.5px] font-medium text-[#737373] hover:text-[#0A0A0A]"
              >
                <ExternalLink className="h-3.5 w-3.5" strokeWidth={2} />
                Open full detail
              </Link>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => onOpenChange(false)}
                  className="text-[#737373]"
                >
                  Close
                </Button>
                <Button type="button" onClick={() => setEditing(true)} className="gap-1.5">
                  <Pencil className="h-3.5 w-3.5" strokeWidth={2} />
                  Edit
                </Button>
              </div>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

function Fieldset({ title, children }) {
  return (
    <section className="space-y-3 rounded-[12px] border border-[#F0F0F0] bg-[#FAFAFA] p-3">
      <h4 className="text-[10.5px] uppercase tracking-wide font-medium text-[#737373]">
        {title}
      </h4>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

function AnalyticsField({ label, value, onChange, error, step = '1' }) {
  return (
    <ModalField label={label} error={error}>
      <Input
        type="number"
        min="0"
        step={step}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </ModalField>
  );
}

function ModalField({ label, error, required = false, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">
        {label}
        {required && <span className="ml-1 text-[#F43F5E]">*</span>}
      </Label>
      {children}
      {error && <p className="text-xs text-[#F43F5E]">{error}</p>}
    </div>
  );
}

function ModalSelect({ value, onChange, required = false, children }) {
  return (
    <select
      value={value}
      onChange={onChange}
      required={required}
      className="h-9 w-full rounded-lg border border-[#EAEAEA] bg-white px-3 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
    >
      {children}
    </select>
  );
}

function InfoRow({ label, value }) {
  return (
    <div>
      <div className="text-[10.5px] uppercase tracking-wide text-[#A3A3A3] font-medium">
        {label}
      </div>
      <div className="mt-0.5 truncate text-[13px] font-medium text-[#0A0A0A]">{value}</div>
    </div>
  );
}

function AttachmentRow({ attachment, onDelete }) {
  const Icon = attachment.isImage ? ImageIcon : attachment.isVideo ? Video : FileText;

  return (
    <div className="flex items-center gap-3 rounded-[10px] border border-[#F0F0F0] bg-[#FAFAFA] px-3 py-2.5">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-[#525252]">
        <Icon className="h-4 w-4" strokeWidth={1.8} />
      </div>
      <div className="min-w-0 flex-1">
        <div className="truncate text-[12.5px] font-medium text-[#0A0A0A]">
          {attachment.fileName}
        </div>
        <div className="mt-0.5 flex items-center gap-1.5 text-[10.5px] text-[#737373]">
          <span>{attachment.fileSizeFormatted}</span>
          {attachment.uploaderName && (
            <>
              <span>·</span>
              <span>By {attachment.uploaderName}</span>
            </>
          )}
        </div>
        {attachment.description && (
          <div className="mt-1 text-[11.5px] leading-snug text-[#525252]">
            {attachment.description}
          </div>
        )}
      </div>
      <a
        href={attachment.fileUrl}
        target="_blank"
        rel="noreferrer noopener"
        className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-[#EAEAEA] bg-white text-[#525252] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
        title="Download"
      >
        <Download className="h-3.5 w-3.5" strokeWidth={2} />
      </a>
      {onDelete && (
        <button
          type="button"
          onClick={onDelete}
          className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-[#EAEAEA] bg-white text-[#525252] hover:border-[#F43F5E]/50 hover:text-[#F43F5E]"
          title="Delete"
        >
          <Trash2 className="h-3.5 w-3.5" strokeWidth={2} />
        </button>
      )}
    </div>
  );
}

function VerificationPanel({ session, verifyForm, notesOpen, onToggleNotes, onVerify, onReject, onReset }) {
  const current = session.verificationStatus ?? 'pending';
  const processing = verifyForm.processing;

  const meta = {
    pending: {
      label: 'Pending PIC review',
      badgeClass: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
      icon: BadgeCheck,
      iconClass: 'text-[#92400E]',
    },
    verified: {
      label: 'Verified',
      badgeClass: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
      icon: Check,
      iconClass: 'text-[#166534]',
    },
    rejected: {
      label: 'Rejected',
      badgeClass: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]',
      icon: XCircle,
      iconClass: 'text-[#991B1B]',
    },
  };
  const { label, badgeClass, icon: Icon, iconClass } = meta[current] ?? meta.pending;

  return (
    <div className="rounded-[12px] border border-[#EAEAEA] bg-white p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <BadgeCheck className="h-4 w-4 text-[#737373]" strokeWidth={2} />
          <span className="text-[10.5px] uppercase tracking-wide text-[#A3A3A3] font-medium">
            Verification
          </span>
        </div>
        <span
          className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11.5px] font-medium ${badgeClass}`}
        >
          <Icon className={`h-3 w-3 ${iconClass}`} strokeWidth={2.2} />
          {label}
        </span>
      </div>

      {current !== 'pending' && (
        <div className="mt-3 text-[12px] leading-snug text-[#525252]">
          <div>
            By <span className="font-medium text-[#0A0A0A]">{session.verifiedByName ?? '—'}</span>
            {session.verifiedAt ? ` · ${formatDateTime(session.verifiedAt)}` : ''}
          </div>
          {session.verificationNotes && (
            <div className="mt-1 rounded-md bg-[#FAFAFA] px-2.5 py-1.5 text-[11.5px] text-[#404040]">
              {session.verificationNotes}
            </div>
          )}
        </div>
      )}

      {notesOpen && (
        <div className="mt-3">
          <textarea
            value={verifyForm.data.verification_notes}
            onChange={(e) => verifyForm.setData('verification_notes', e.target.value)}
            rows={2}
            maxLength={1000}
            placeholder="Notes (optional) — visible to the PIC reviewing this session"
            className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-[12.5px] text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
          />
          {verifyForm.errors.verification_notes && (
            <p className="mt-1 text-xs text-[#F43F5E]">{verifyForm.errors.verification_notes}</p>
          )}
        </div>
      )}

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={onToggleNotes}
          className="inline-flex items-center gap-1 rounded-md border border-[#EAEAEA] bg-white px-2.5 py-1 text-[11.5px] font-medium text-[#404040] hover:bg-[#F5F5F5] hover:text-[#0A0A0A]"
        >
          {notesOpen ? 'Hide notes' : 'Add notes'}
        </button>

        <div className="ml-auto flex flex-wrap gap-2">
          {current !== 'pending' && (
            <Button
              type="button"
              variant="ghost"
              onClick={onReset}
              disabled={processing}
              className="gap-1.5 text-[#737373]"
            >
              <RotateCcw className="h-3.5 w-3.5" strokeWidth={2} />
              Reset
            </Button>
          )}
          {current !== 'rejected' && (
            <Button
              type="button"
              onClick={onReject}
              disabled={processing}
              className="gap-1.5 border border-[#FECACA] bg-white text-[#991B1B] hover:bg-[#FEF2F2]"
            >
              <XCircle className="h-3.5 w-3.5" strokeWidth={2} />
              Reject
            </Button>
          )}
          {current !== 'verified' && (
            <Button
              type="button"
              onClick={onVerify}
              disabled={processing}
              className="gap-1.5 bg-[#10B981] text-white hover:bg-[#059669]"
            >
              <Check className="h-3.5 w-3.5" strokeWidth={2.5} />
              Verify
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

function formatFileSize(bytes) {
  if (bytes == null || !Number.isFinite(Number(bytes))) {
    return '';
  }
  const num = Number(bytes);
  if (num < 1024) {
    return `${num} B`;
  }
  if (num < 1024 * 1024) {
    return `${(num / 1024).toFixed(1)} KB`;
  }
  return `${(num / (1024 * 1024)).toFixed(1)} MB`;
}
