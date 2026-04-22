import { useEffect, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
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
import SearchableSelect, { colorFor, initialsFrom } from '@/livehost/components/SearchableSelect';

const DAY_NAMES_FULL = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
];

const STATUS_OPTIONS = [
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];

// Platform-aware accent so each marketplace is identifiable at a glance inside
// the searchable account picker. Matches the signal colors used elsewhere in
// the livehost dashboard.
const PLATFORM_AVATAR = {
  'tiktok-shop': { initials: 'TT', color: '#EC4899' },
  tiktok: { initials: 'TT', color: '#EC4899' },
  'facebook-shop': { initials: 'FB', color: '#3B82F6' },
  facebook: { initials: 'FB', color: '#3B82F6' },
  shopee: { initials: 'SP', color: '#F97316' },
  lazada: { initials: 'LZ', color: '#6366F1' },
  amazon: { initials: 'AZ', color: '#F59E0B' },
};

function platformAvatar(platform, fallbackLabel) {
  if (!platform) {
    return { initials: initialsFrom(fallbackLabel), color: colorFor(fallbackLabel) };
  }

  const slug = String(platform).toLowerCase().replace(/\s+/g, '-');

  return (
    PLATFORM_AVATAR[slug] ?? {
      initials: initialsFrom(platform),
      color: colorFor(platform),
    }
  );
}

function formatTimeLabel(time) {
  if (!time) {
    return '';
  }
  const [h, m] = time.split(':').map((v) => Number(v));
  const suffix = h >= 12 ? 'PM' : 'AM';
  const display = h === 0 ? 12 : h > 12 ? h - 12 : h;
  return `${display}:${String(m).padStart(2, '0')} ${suffix}`;
}

export default function SessionSlotFormModal({
  open,
  onOpenChange,
  mode = 'create',
  sessionSlot = null,
  prefill = null,
  hosts = [],
  platformAccounts = [],
  timeSlots = [],
  hostPlatformPivots = [],
  returnTo = null,
  weekOf = null,
  onSuccess = null,
}) {
  const form = useForm({
    platform_account_id: '',
    live_host_platform_account_id: '',
    time_slot_id: '',
    live_host_id: '',
    day_of_week: '1',
    schedule_date: '',
    is_template: false,
    status: 'scheduled',
    remarks: '',
  });

  const [quickCreator, setQuickCreator] = useState({
    creator_handle: '',
    creator_platform_user_id: '',
  });
  const [quickCreatorError, setQuickCreatorError] = useState(null);
  const [attaching, setAttaching] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    form.clearErrors();
    setQuickCreator({ creator_handle: '', creator_platform_user_id: '' });
    setQuickCreatorError(null);

    if (mode === 'edit' && sessionSlot) {
      form.setData({
        platform_account_id: sessionSlot.platformAccountId
          ? String(sessionSlot.platformAccountId)
          : '',
        live_host_platform_account_id: sessionSlot.liveHostPlatformAccountId
          ? String(sessionSlot.liveHostPlatformAccountId)
          : '',
        time_slot_id: sessionSlot.timeSlotId ? String(sessionSlot.timeSlotId) : '',
        live_host_id: sessionSlot.hostId ? String(sessionSlot.hostId) : '',
        day_of_week: String(sessionSlot.dayOfWeek ?? 1),
        schedule_date: sessionSlot.scheduleDate ?? '',
        is_template: Boolean(sessionSlot.isTemplate),
        status: sessionSlot.status ?? 'scheduled',
        remarks: sessionSlot.remarks ?? '',
      });
      return;
    }

    form.setData({
      platform_account_id: prefill?.platformAccountId ? String(prefill.platformAccountId) : '',
      live_host_platform_account_id: '',
      time_slot_id: prefill?.timeSlotId ? String(prefill.timeSlotId) : '',
      live_host_id: '',
      day_of_week: String(Number.isFinite(prefill?.dayOfWeek) ? prefill.dayOfWeek : 1),
      schedule_date: prefill?.scheduleDate ?? '',
      is_template: false,
      status: 'scheduled',
      remarks: '',
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    open,
    mode,
    sessionSlot?.id,
    prefill?.dayOfWeek,
    prefill?.timeSlotId,
    prefill?.platformAccountId,
    prefill?.scheduleDate,
  ]);

  // Task 23: narrow the creator-identity dropdown to pivots matching the
  // selected platform account (and, when provided, the selected host). Once
  // the candidate list is known, auto-pick the primary pivot so the
  // common-case flow ("assign session to host on their default shop") is
  // zero-click. User can override before submitting.
  const pivotCandidates = useMemo(() => {
    if (!form.data.platform_account_id) {
      return [];
    }
    const platformId = Number(form.data.platform_account_id);
    const hostId = form.data.live_host_id ? Number(form.data.live_host_id) : null;

    return hostPlatformPivots.filter((p) => {
      if (p.platformAccountId !== platformId) return false;
      if (hostId !== null && p.userId !== hostId) return false;
      return true;
    });
  }, [form.data.platform_account_id, form.data.live_host_id, hostPlatformPivots]);

  useEffect(() => {
    if (mode === 'edit') {
      return;
    }
    if (pivotCandidates.length === 0) {
      if (form.data.live_host_platform_account_id !== '') {
        form.setData('live_host_platform_account_id', '');
      }
      return;
    }
    const currentId = form.data.live_host_platform_account_id
      ? Number(form.data.live_host_platform_account_id)
      : null;
    const stillValid = currentId
      ? pivotCandidates.some((p) => p.id === currentId)
      : false;
    if (!stillValid) {
      const primary = pivotCandidates.find((p) => p.isPrimary);
      const next = primary ?? pivotCandidates[0];
      form.setData('live_host_platform_account_id', String(next.id));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pivotCandidates, mode]);

  // Quick-add shows when the user has picked both a platform account and a
  // host but no pivot exists for that pair yet. In edit mode we never prompt
  // for it because the existing pivot is already selected.
  const canQuickAddCreator =
    mode === 'create' &&
    Boolean(form.data.platform_account_id) &&
    Boolean(form.data.live_host_id) &&
    pivotCandidates.length === 0;

  const selectedHostLabel = useMemo(() => {
    if (!form.data.live_host_id) {
      return '';
    }
    const id = Number(form.data.live_host_id);
    return hosts.find((h) => Number(h.id) === id)?.name ?? '';
  }, [form.data.live_host_id, hosts]);

  const selectedPlatformLabel = useMemo(() => {
    if (!form.data.platform_account_id) {
      return '';
    }
    const id = Number(form.data.platform_account_id);
    return platformAccounts.find((pa) => Number(pa.id) === id)?.name ?? '';
  }, [form.data.platform_account_id, platformAccounts]);

  const attachCreator = () => {
    if (attaching) {
      return;
    }
    setQuickCreatorError(null);

    const payload = {
      user_id: Number(form.data.live_host_id),
      platform_account_id: Number(form.data.platform_account_id),
      creator_handle: quickCreator.creator_handle || null,
      creator_platform_user_id: quickCreator.creator_platform_user_id,
      is_primary: true,
    };

    setAttaching(true);
    router.post('/livehost/creators', payload, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setQuickCreator({ creator_handle: '', creator_platform_user_id: '' });
      },
      onError: (errors) => {
        const firstError =
          errors.creator_platform_user_id ||
          errors.creator_handle ||
          errors.platform_account_id ||
          errors.user_id ||
          Object.values(errors)[0];
        setQuickCreatorError(firstError ?? 'Could not attach creator identity.');
      },
      onFinish: () => setAttaching(false),
    });
  };

  const contextLabel = useMemo(() => {
    if (mode === 'edit') {
      return null;
    }
    const dow = Number.isFinite(prefill?.dayOfWeek) ? prefill.dayOfWeek : null;
    const dayName = dow !== null ? DAY_NAMES_FULL[dow] : null;
    const matchedSlot = prefill?.timeSlotId
      ? timeSlots.find((ts) => ts.id === prefill.timeSlotId)
      : null;
    if (!dayName && !matchedSlot) {
      return 'Create a new session slot assignment.';
    }
    if (!matchedSlot) {
      return dayName;
    }
    const range = `${formatTimeLabel(matchedSlot.startTime)} – ${formatTimeLabel(matchedSlot.endTime)}`;
    return dayName ? `${dayName}, ${range}` : range;
  }, [mode, prefill?.dayOfWeek, prefill?.timeSlotId, timeSlots]);

  const submit = (event) => {
    event.preventDefault();

    form.transform((data) => ({
      ...data,
      platform_account_id:
        data.platform_account_id === '' ? null : Number(data.platform_account_id),
      live_host_platform_account_id:
        data.live_host_platform_account_id === ''
          ? null
          : Number(data.live_host_platform_account_id),
      time_slot_id: data.time_slot_id === '' ? null : Number(data.time_slot_id),
      live_host_id: data.live_host_id === '' ? null : Number(data.live_host_id),
      day_of_week: data.day_of_week === '' ? null : Number(data.day_of_week),
      schedule_date: data.schedule_date === '' ? null : data.schedule_date,
      ...(returnTo ? { return_to: returnTo } : {}),
      ...(weekOf ? { week_of: weekOf } : {}),
    }));

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false);
        if (onSuccess) {
          onSuccess();
        }
      },
    };

    if (mode === 'edit' && sessionSlot?.id) {
      form.put(`/livehost/session-slots/${sessionSlot.id}`, options);
    } else {
      form.post('/livehost/session-slots', options);
    }
  };

  const title = mode === 'edit' ? 'Edit session slot' : 'Assign session slot';
  const submitLabel = mode === 'edit' ? 'Save changes' : 'Create session slot';
  const processingLabel = mode === 'edit' ? 'Saving…' : 'Creating…';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto border border-[#EAEAEA] bg-white text-[#0A0A0A] sm:max-w-[520px]">
        <DialogHeader className="text-left">
          <DialogTitle className="text-[17px] font-semibold tracking-[-0.02em] text-[#0A0A0A]">
            {title}
          </DialogTitle>
          {contextLabel && (
            <DialogDescription className="text-[13px] text-[#737373]">
              {contextLabel}
            </DialogDescription>
          )}
        </DialogHeader>

        <form onSubmit={submit} className="space-y-4">
          <ModalField label="Platform account" error={form.errors.platform_account_id} required>
            <SearchableSelect
              value={form.data.platform_account_id}
              onChange={(next) => form.setData('platform_account_id', next)}
              placeholder="Select platform account"
              searchPlaceholder="Search account or platform…"
              emptyLabel="No accounts match"
              options={platformAccounts.map((pa) => ({
                value: String(pa.id),
                label: pa.name,
                hint: pa.platform ?? null,
                keywords: [pa.name, pa.platform].filter(Boolean).join(' '),
                avatar: platformAvatar(pa.platform, pa.name),
              }))}
            />
          </ModalField>

          <ModalField
            label="Creator identity"
            error={form.errors.live_host_platform_account_id}
            hint={
              canQuickAddCreator
                ? null
                : pivotCandidates.length === 0
                  ? 'Pick a platform account (and optionally a host) to see creator identities.'
                  : 'Defaults to the host’s primary identity for this platform account.'
            }
            required
          >
            <ModalSelect
              value={form.data.live_host_platform_account_id}
              onChange={(e) => form.setData('live_host_platform_account_id', e.target.value)}
              required
            >
              <option value="">
                {pivotCandidates.length === 0
                  ? 'No creator identities available'
                  : 'Select creator identity'}
              </option>
              {pivotCandidates.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
                  {p.isPrimary ? ' · primary' : ''}
                </option>
              ))}
            </ModalSelect>

            {canQuickAddCreator && (
              <div className="mt-2 space-y-2 rounded-lg border border-dashed border-[#D4D4D4] bg-[#FAFAFA] p-3">
                <div className="text-[11.5px] text-[#525252]">
                  <span className="font-medium text-[#0A0A0A]">
                    {selectedHostLabel || 'This host'}
                  </span>
                  {' '}isn’t linked to{' '}
                  <span className="font-medium text-[#0A0A0A]">
                    {selectedPlatformLabel || 'this account'}
                  </span>
                  {' '}yet. Attach a creator identity to continue:
                </div>
                <Input
                  value={quickCreator.creator_platform_user_id}
                  onChange={(e) =>
                    setQuickCreator((prev) => ({
                      ...prev,
                      creator_platform_user_id: e.target.value,
                    }))
                  }
                  placeholder="Creator ID (from TikTok report) *"
                  disabled={attaching}
                />
                <Input
                  value={quickCreator.creator_handle}
                  onChange={(e) =>
                    setQuickCreator((prev) => ({
                      ...prev,
                      creator_handle: e.target.value,
                    }))
                  }
                  placeholder="Nickname (optional)"
                  disabled={attaching}
                />
                {quickCreatorError && (
                  <p className="text-[11.5px] text-[#F43F5E]">{quickCreatorError}</p>
                )}
                <div className="flex items-center justify-between gap-2">
                  <p className="text-[11px] text-[#737373]">
                    Marked as primary for this host. Edit later in Creators.
                  </p>
                  <Button
                    type="button"
                    size="sm"
                    onClick={attachCreator}
                    disabled={attaching || !quickCreator.creator_platform_user_id.trim()}
                  >
                    {attaching ? 'Attaching…' : 'Attach identity'}
                  </Button>
                </div>
              </div>
            )}
          </ModalField>

          <ModalField label="Time slot" error={form.errors.time_slot_id} required>
            <ModalSelect
              value={form.data.time_slot_id}
              onChange={(e) => form.setData('time_slot_id', e.target.value)}
              required
            >
              <option value="">Select time slot</option>
              {timeSlots.map((ts) => (
                <option key={ts.id} value={ts.id}>
                  {ts.label}
                </option>
              ))}
            </ModalSelect>
          </ModalField>

          <div className="grid grid-cols-2 gap-3">
            <ModalField label="Day of week" error={form.errors.day_of_week} required>
              <ModalSelect
                value={form.data.day_of_week}
                onChange={(e) => form.setData('day_of_week', e.target.value)}
                required
              >
                {DAY_NAMES_FULL.map((label, value) => (
                  <option key={label} value={value}>
                    {label}
                  </option>
                ))}
              </ModalSelect>
            </ModalField>

            <ModalField label="Status" error={form.errors.status}>
              <ModalSelect
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value)}
              >
                {STATUS_OPTIONS.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </ModalSelect>
            </ModalField>
          </div>

          <ModalField label="Live host (optional)" error={form.errors.live_host_id}>
            <SearchableSelect
              value={form.data.live_host_id}
              onChange={(next) => form.setData('live_host_id', next)}
              placeholder="Unassigned"
              searchPlaceholder="Search host by name…"
              emptyLabel="No hosts match"
              allowClear
              options={[
                { value: '', label: 'Unassigned', empty: true },
                ...hosts.map((h) => ({
                  value: String(h.id),
                  label: h.name,
                  hint: h.email ?? null,
                  keywords: [h.name, h.email].filter(Boolean).join(' '),
                  avatar: {
                    initials: initialsFrom(h.name),
                    color: colorFor(h.name),
                  },
                })),
              ]}
            />
          </ModalField>

          <ModalField
            label={form.data.is_template ? 'Specific date (optional)' : 'Specific date'}
            error={form.errors.schedule_date}
            hint={
              form.data.is_template
                ? 'Leave blank to use this slot as part of the weekly template.'
                : 'Required for one-off sessions. Tick “Weekly template” above to make this optional.'
            }
            required={!form.data.is_template}
          >
            <Input
              type="date"
              value={form.data.schedule_date}
              onChange={(e) => form.setData('schedule_date', e.target.value)}
              required={!form.data.is_template}
            />
          </ModalField>

          <ModalField label="Remarks (optional)" error={form.errors.remarks}>
            <textarea
              value={form.data.remarks}
              onChange={(e) => form.setData('remarks', e.target.value)}
              rows={2}
              maxLength={1000}
              placeholder="Notes for this assignment…"
              className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </ModalField>

          <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-[#F0F0F0] bg-[#FAFAFA] p-3">
            <input
              type="checkbox"
              checked={form.data.is_template}
              onChange={(e) => form.setData('is_template', e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30"
            />
            <span className="min-w-0">
              <span className="text-[13px] font-medium text-[#0A0A0A]">Weekly template</span>
              <span className="mt-0.5 block text-[11.5px] text-[#737373]">
                On: recurs every week on this day. Off: one-off for the specific date above.
              </span>
            </span>
          </label>

          <DialogFooter className="gap-2 sm:gap-2">
            <Button
              type="button"
              variant="ghost"
              onClick={() => onOpenChange(false)}
              className="text-[#737373]"
            >
              Cancel
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? processingLabel : submitLabel}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ModalField({ label, error, hint, required = false, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">
        {label}
        {required && <span className="ml-1 text-[#F43F5E]">*</span>}
      </Label>
      {children}
      {hint && !error && <p className="text-[11px] text-[#737373]">{hint}</p>}
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
