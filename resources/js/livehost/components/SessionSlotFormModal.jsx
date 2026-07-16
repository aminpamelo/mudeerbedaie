import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown, Radio } from 'lucide-react';
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

function formatGmv(value) {
  const num = Number(value);
  if (!Number.isFinite(num) || num <= 0) {
    return null;
  }
  const hasSen = num % 1 !== 0;
  return `RM ${num.toLocaleString(undefined, {
    minimumFractionDigits: hasSen ? 2 : 0,
    maximumFractionDigits: 2,
  })}`;
}

function formatDuration(seconds) {
  const total = Number(seconds);
  if (!Number.isFinite(total) || total <= 0) {
    return null;
  }
  const mins = Math.round(total / 60);
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? `${h}h${m ? ` ${m}m` : ''}` : `${m}m`;
}

function tiktokRemark(suggestion) {
  const gmv = formatGmv(suggestion.gmv);
  const parts = [`Recorded from TikTok live on ${suggestion.scheduleDate}`];
  if (gmv) {
    parts.push(`GMV ${gmv}`);
  }
  return `${parts.join(' · ')}.`;
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
      remarks: prefill?.suggestion ? tiktokRemark(prefill.suggestion) : '',
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
    prefill?.suggestionId,
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

  // The hosts who share (operate) the selected creator account. Several staff
  // can broadcast on the same brand account, so the live-host picker surfaces
  // those who operate this account first to make "who is going live" explicit.
  const accountHostIds = useMemo(
    () => new Set((selectedAccount?.hostIds ?? []).map(Number)),
    [selectedAccount]
  );

  const hostOptions = useMemo(() => {
    const opts = hosts.map((h) => {
      const operates = accountHostIds.has(Number(h.id));
      return {
        value: String(h.id),
        label: h.name,
        hint: h.email ?? null,
        group: selectedAccount ? (operates ? 'Operates this account' : 'Other hosts') : undefined,
        keywords: [h.name, h.email].filter(Boolean).join(' '),
        avatar: { initials: initialsFrom(h.name), color: colorFor(h.name) },
      };
    });

    if (selectedAccount) {
      opts.sort((a, b) => {
        const ga = a.group === 'Operates this account' ? 0 : 1;
        const gb = b.group === 'Operates this account' ? 0 : 1;
        return ga !== gb ? ga - gb : a.label.localeCompare(b.label);
      });
    }

    return opts;
  }, [hosts, accountHostIds, selectedAccount]);

  // Auto-pick the host when exactly one operates the chosen account.
  useEffect(() => {
    if (mode === 'edit' || !selectedAccount) {
      return;
    }
    const ids = selectedAccount.hostIds ?? [];
    if (ids.length === 1 && !form.data.live_host_id) {
      form.setData('live_host_id', String(ids[0]));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.data.live_account_id, mode]);

  // Only linked creator accounts are assignable (affiliates and unclassified
  // "needs review" accounts are excluded). The currently-selected account is
  // always kept so editing/prefilling a slot on a non-linked account still shows it.
  const accountOptions = useMemo(
    () =>
      liveAccounts
        .filter((a) => a.isLinked || String(a.id) === String(form.data.live_account_id))
        .map((a) => {
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
    [liveAccounts, form.data.live_account_id]
  );

  // When assigning onto a per-creator slot override, the account's slots for the
  // selected day come from the override, not the normal weekly slots — so the
  // clicked override slot is selectable and its time aligns.
  const timeSlotOptions = useMemo(() => {
    const override = mode !== 'edit' ? prefill?.overrideTimeSlots : null;
    if (override?.length) {
      const day = Number(form.data.day_of_week);
      return override.filter((ts) => ts.dayOfWeek == null || Number(ts.dayOfWeek) === day);
    }
    return timeSlots;
  }, [mode, prefill?.overrideTimeSlots, form.data.day_of_week, timeSlots]);

  const contextLabel = useMemo(() => {
    if (mode === 'edit') {
      return null;
    }
    const dow = Number.isFinite(prefill?.dayOfWeek) ? prefill.dayOfWeek : null;
    const dayName = dow !== null ? DAY_NAMES_FULL[dow] : null;
    const matchedSlot = prefill?.timeSlotId
      ? timeSlotOptions.find((ts) => ts.id === prefill.timeSlotId)
      : null;
    if (!dayName && !matchedSlot) {
      return 'Create a new session slot assignment.';
    }
    if (!matchedSlot) {
      return dayName;
    }
    const range = `${formatTimeLabel(matchedSlot.startTime)} – ${formatTimeLabel(matchedSlot.endTime)}`;
    return dayName ? `${dayName}, ${range}` : range;
  }, [mode, prefill?.dayOfWeek, prefill?.timeSlotId, timeSlotOptions]);

  // Clicking a slot pre-fills account/shop/time/day/date, so the form collapses
  // to just "who's going live"; everything else lives behind "More options".
  const isQuickAssign = mode !== 'edit' && Boolean(prefill);
  const [showMore, setShowMore] = useState(false);
  useEffect(() => {
    setShowMore(false);
  }, [open, prefill?.timeSlotId]);

  const shopName = useMemo(() => {
    const pa = platformAccounts.find((p) => String(p.id) === String(form.data.platform_account_id));
    return pa?.name ?? null;
  }, [platformAccounts, form.data.platform_account_id]);

  const selectedTimeSlotLabel = useMemo(() => {
    const ts = timeSlotOptions.find((t) => String(t.id) === String(form.data.time_slot_id));
    return ts?.label ?? null;
  }, [timeSlotOptions, form.data.time_slot_id]);

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
  const submitLabel = mode === 'edit' ? 'Save changes' : (isQuickAssign ? 'Assign' : 'Create session slot');
  const processingLabel = mode === 'edit' ? 'Saving…' : (isQuickAssign ? 'Assigning…' : 'Creating…');

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

        {mode !== 'edit' && prefill?.suggestion && (
          <SuggestionBanner suggestion={prefill.suggestion} />
        )}

        <form onSubmit={submit} className="space-y-4">
          {/* Quick-assign summary of the pre-filled context. */}
          {isQuickAssign && (
            <div className="rounded-lg border border-[#EAEAEA] bg-[#FAFAFA] px-3 py-2">
              <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[12.5px] text-[#525252]">
                <span className="font-medium text-[#0A0A0A]">{selectedAccount?.label ?? 'No account'}</span>
                {shopName && (<><span className="text-[#C4C4C4]">·</span><span>{shopName}</span></>)}
                {selectedTimeSlotLabel && (<><span className="text-[#C4C4C4]">·</span><span className="tabular-nums">{selectedTimeSlotLabel}</span></>)}
                <span className="text-[#C4C4C4]">·</span>
                <span className="tabular-nums">{form.data.is_template ? 'Weekly' : (form.data.schedule_date || DAY_NAMES_FULL[Number(form.data.day_of_week)] || '')}</span>
              </div>
            </div>
          )}

          {/* Who's going live — the one thing to set for a quick assign. */}
          <ModalField
            label="Live host (who’s broadcasting)"
            error={form.errors.live_host_id}
            hint={
              selectedAccount && (selectedAccount.hostIds ?? []).length > 1
                ? 'Several hosts share this account — pick who is doing this live.'
                : 'Pick who goes live at this time.'
            }
            required
          >
            <SearchableSelect
              value={form.data.live_host_id}
              onChange={(next) => form.setData('live_host_id', next)}
              placeholder="Select live host"
              searchPlaceholder="Search host by name…"
              emptyLabel="No hosts match"
              options={hostOptions}
            />
          </ModalField>

          {isQuickAssign && (
            <button
              type="button"
              onClick={() => setShowMore((v) => !v)}
              className="flex w-full items-center gap-1.5 text-[12.5px] font-medium text-[#525252] transition-colors hover:text-[#0A0A0A]"
            >
              <ChevronDown className={`h-4 w-4 transition-transform ${showMore ? '' : '-rotate-90'}`} strokeWidth={2} />
              {showMore ? 'Fewer options' : 'More options'}
              <span className="text-[11px] font-normal text-[#A3A3A3]">· account, shop, time, day, date, remarks</span>
            </button>
          )}

          {(!isQuickAssign || showMore) && (
            <div className={isQuickAssign ? 'space-y-4 border-t border-[#F0F0F0] pt-4' : 'space-y-4'}>
              {/* The creator account (nickname) is the punca kuasa. */}
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
                  {timeSlotOptions.map((ts) => (
                    <option key={ts.id} value={ts.id}>
                      {ts.label}
                    </option>
                  ))}
                </ModalSelect>
              </ModalField>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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

              <ModalField
                label={form.data.is_template ? 'Specific date (optional)' : 'Specific date'}
                error={form.errors.schedule_date}
                hint={
                  form.data.is_template
                    ? 'Leave blank to use this slot as part of the weekly template.'
                    : 'Required for one-off sessions. Tick “Weekly template” below to make this optional.'
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
            </div>
          )}

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

function SuggestionBanner({ suggestion }) {
  const gmv = formatGmv(suggestion.gmv);
  const liveGmv = formatGmv(suggestion.liveAttributedGmv);
  const duration = formatDuration(suggestion.durationSeconds);
  const range = `${formatTimeLabel(suggestion.startTime)} – ${formatTimeLabel(suggestion.endTime)}`;
  const sourceLabel = suggestion.source === 'api_sync' ? 'TikTok API' : 'CSV import';

  const stats = [
    gmv ? { label: 'GMV', value: gmv } : null,
    liveGmv ? { label: 'Live GMV', value: liveGmv } : null,
    suggestion.viewers ? { label: 'Viewers', value: Number(suggestion.viewers).toLocaleString() } : null,
    suggestion.itemsSold ? { label: 'Items', value: Number(suggestion.itemsSold).toLocaleString() } : null,
  ].filter(Boolean);

  return (
    <div className="rounded-xl border border-[#F5D0E4] bg-gradient-to-br from-[#FDF2F8] to-white p-3">
      <div className="flex items-center gap-1.5">
        <Radio className="h-3.5 w-3.5 text-[#EC4899]" strokeWidth={2.4} />
        <span className="text-[12px] font-semibold text-[#9D174D]">Suggested from a TikTok live</span>
        <span className="ml-auto rounded-full bg-white/70 px-1.5 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-wide text-[#9D174D]">
          {sourceLabel}
        </span>
      </div>
      <p className="mt-1.5 text-[12.5px] text-[#525252]">
        {suggestion.creatorHandle && (
          <span className="font-mono font-medium text-[#0A0A0A]">@{suggestion.creatorHandle}</span>
        )}
        <span className="mx-1 text-[#D4D4D4]">·</span>
        <span className="tabular-nums text-[#0A0A0A]">{range}</span>
        {duration && <span className="text-[#A3A3A3]"> ({duration})</span>}
      </p>
      {stats.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
          {stats.map((stat) => (
            <div key={stat.label} className="flex items-baseline gap-1">
              <span className="font-mono text-[9px] uppercase tracking-wide text-[#A3A3A3]">
                {stat.label}
              </span>
              <span className="font-mono text-[11px] font-semibold tabular-nums text-[#0A0A0A]">
                {stat.value}
              </span>
            </div>
          ))}
        </div>
      )}
      <p className="mt-2 text-[11px] leading-snug text-[#737373]">
        Confirm the host below and create the slot. GMV stays unverified until you link this live in
        the session&rsquo;s verify step.
      </p>
    </div>
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
