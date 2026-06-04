import { useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
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
// the searchable shop picker. Matches the signal colors used elsewhere.
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
  liveAccounts = [],
  timeSlots = [],
  // eslint-disable-next-line no-unused-vars -- legacy prop; pivot is superseded by live_account
  hostPlatformPivots = [],
  returnTo = null,
  weekOf = null,
  onSuccess = null,
}) {
  const form = useForm({
    live_account_id: '',
    platform_account_id: '',
    time_slot_id: '',
    live_host_id: '',
    day_of_week: '1',
    schedule_date: '',
    is_template: false,
    status: 'scheduled',
    remarks: '',
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    form.clearErrors();

    if (mode === 'edit' && sessionSlot) {
      form.setData({
        live_account_id: sessionSlot.liveAccountId ? String(sessionSlot.liveAccountId) : '',
        platform_account_id: sessionSlot.platformAccountId
          ? String(sessionSlot.platformAccountId)
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
      live_account_id: prefill?.liveAccountId ? String(prefill.liveAccountId) : '',
      platform_account_id: prefill?.platformAccountId ? String(prefill.platformAccountId) : '',
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
    prefill?.liveAccountId,
    prefill?.platformAccountId,
    prefill?.scheduleDate,
  ]);

  const selectedAccount = useMemo(
    () => liveAccounts.find((a) => String(a.id) === String(form.data.live_account_id)) ?? null,
    [liveAccounts, form.data.live_account_id]
  );

  const platformAccountById = useMemo(
    () => new Map(platformAccounts.map((pa) => [Number(pa.id), pa])),
    [platformAccounts]
  );

  // The shop being promoted is constrained to the account's affiliated shops.
  // When the account has no recorded affiliations yet, fall back to the full
  // shop list so scheduling is never blocked.
  const shopChoices = useMemo(() => {
    const affiliated = selectedAccount?.shops ?? [];
    const source = affiliated.length
      ? affiliated.map((s) => platformAccountById.get(Number(s.id)) ?? { id: s.id, name: s.name })
      : platformAccounts;

    return source.map((pa) => ({
      value: String(pa.id),
      label: pa.name,
      hint: pa.platform ?? null,
      keywords: [pa.name, pa.platform].filter(Boolean).join(' '),
      avatar: platformAvatar(pa.platform, pa.name),
    }));
  }, [selectedAccount, platformAccounts, platformAccountById]);

  // When the account changes, default the shop to the account's primary (or
  // sole) affiliation so the common case is zero-click.
  useEffect(() => {
    if (mode === 'edit' || !selectedAccount) {
      return;
    }
    const shops = selectedAccount.shops ?? [];
    if (shops.length === 0) {
      return;
    }
    const stillValid = shops.some((s) => String(s.id) === String(form.data.platform_account_id));
    if (!stillValid) {
      const primary = shops.find((s) => s.isPrimary) ?? shops[0];
      form.setData('platform_account_id', String(primary.id));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.data.live_account_id, mode]);

  const accountOptions = useMemo(
    () =>
      liveAccounts.map((a) => {
        const shopNames = (a.shops ?? []).map((s) => s.name).filter(Boolean);
        return {
          value: String(a.id),
          label: a.label,
          hint: a.needsReview
            ? 'Needs review'
            : shopNames.length
              ? shopNames.join(', ')
              : a.creatorUserId
                ? `ID ${a.creatorUserId}`
                : null,
          keywords: [a.label, a.nickname, a.displayName, a.creatorUserId, ...shopNames]
            .filter(Boolean)
            .join(' '),
          avatar: { initials: initialsFrom(a.label), color: colorFor(a.label) },
        };
      }),
    [liveAccounts]
  );

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
      live_account_id: data.live_account_id === '' ? null : Number(data.live_account_id),
      platform_account_id:
        data.platform_account_id === '' ? null : Number(data.platform_account_id),
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
          {/* The creator account (nickname) is the punca kuasa: chosen first. */}
          <ModalField
            label="Creator account"
            error={form.errors.live_account_id}
            hint="The account the host goes live on. The shop below is what this broadcast promotes."
            required
          >
            <SearchableSelect
              value={form.data.live_account_id}
              onChange={(next) => form.setData('live_account_id', next)}
              placeholder="Select creator account"
              searchPlaceholder="Search nickname, handle or shop…"
              emptyLabel="No accounts match"
              options={accountOptions}
            />
          </ModalField>

          <ModalField
            label="Shop (promoted)"
            error={form.errors.platform_account_id}
            hint={
              selectedAccount && (selectedAccount.shops ?? []).length === 0
                ? 'This account has no linked shops yet — pick any, then link it in Creators.'
                : 'Limited to the shops this account is affiliated with.'
            }
            required
          >
            <SearchableSelect
              value={form.data.platform_account_id}
              onChange={(next) => form.setData('platform_account_id', next)}
              placeholder="Select shop"
              searchPlaceholder="Search shop or platform…"
              emptyLabel="No shops match"
              options={shopChoices}
            />
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
