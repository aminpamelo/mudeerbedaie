import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const DAY_OPTIONS = [
  { value: '0', label: 'Sunday' },
  { value: '1', label: 'Monday' },
  { value: '2', label: 'Tuesday' },
  { value: '3', label: 'Wednesday' },
  { value: '4', label: 'Thursday' },
  { value: '5', label: 'Friday' },
  { value: '6', label: 'Saturday' },
];

const STATUS_OPTIONS = [
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];

export default function SessionSlotsEdit() {
  const { sessionSlot, hosts, platformAccounts, timeSlots } = usePage().props;

  const form = useForm({
    platform_account_id: sessionSlot.platform_account_id?.toString() ?? '',
    time_slot_id: sessionSlot.time_slot_id?.toString() ?? '',
    live_host_id: sessionSlot.live_host_id?.toString() ?? '',
    day_of_week: sessionSlot.day_of_week !== null && sessionSlot.day_of_week !== undefined
      ? sessionSlot.day_of_week.toString()
      : '1',
    schedule_date: sessionSlot.schedule_date ?? '',
    is_template: Boolean(sessionSlot.is_template),
    status: sessionSlot.status ?? 'scheduled',
    remarks: sessionSlot.remarks ?? '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      platform_account_id: data.platform_account_id === '' ? null : Number(data.platform_account_id),
      time_slot_id: data.time_slot_id === '' ? null : Number(data.time_slot_id),
      live_host_id: data.live_host_id === '' ? null : Number(data.live_host_id),
      day_of_week: data.day_of_week === '' ? null : Number(data.day_of_week),
      schedule_date: data.schedule_date === '' ? null : data.schedule_date,
    }));
    form.put(`/livehost/session-slots/${sessionSlot.id}`);
  };

  return (
    <>
      <Head title="Edit session slot" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Session Slots', `#${sessionSlot.id}`, 'Edit']}
        actions={
          <Link href={`/livehost/session-slots/${sessionSlot.id}`}>
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="p-8 max-w-3xl">
        <div className="mb-6">
          <h1 className="text-3xl font-semibold tracking-[-0.03em] leading-[1.1] text-[#0A0A0A]">
            Edit session slot
          </h1>
          <p className="text-[#737373] mt-1.5 text-sm">
            Update this assignment. Changes apply immediately and are visible on the schedule grid.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5"
        >
          <Field label="Platform account" error={form.errors.platform_account_id} required>
            <Select
              value={form.data.platform_account_id}
              onChange={(e) => form.setData('platform_account_id', e.target.value)}
              required
            >
              <option value="">Select platform account</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name}
                  {pa.platform ? ` · ${pa.platform}` : ''}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Time slot" error={form.errors.time_slot_id} required>
            <Select
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
            </Select>
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Day of week" error={form.errors.day_of_week} required>
              <Select
                value={form.data.day_of_week}
                onChange={(e) => form.setData('day_of_week', e.target.value)}
                required
              >
                {DAY_OPTIONS.map((d) => (
                  <option key={d.value} value={d.value}>
                    {d.label}
                  </option>
                ))}
              </Select>
            </Field>

            <Field label="Status" error={form.errors.status}>
              <Select
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value)}
              >
                {STATUS_OPTIONS.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </Select>
            </Field>
          </div>

          <Field label="Live host (optional)" error={form.errors.live_host_id}>
            <Select
              value={form.data.live_host_id}
              onChange={(e) => form.setData('live_host_id', e.target.value)}
            >
              <option value="">Unassigned</option>
              {hosts.map((h) => (
                <option key={h.id} value={h.id}>
                  {h.name}
                  {h.email ? ` · ${h.email}` : ''}
                </option>
              ))}
            </Select>
          </Field>

          <Field
            label="Specific date (optional)"
            error={form.errors.schedule_date}
            hint="Leave blank to use this slot as part of the weekly template."
          >
            <Input
              type="date"
              value={form.data.schedule_date}
              onChange={(e) => form.setData('schedule_date', e.target.value)}
            />
          </Field>

          <Field label="Remarks (optional)" error={form.errors.remarks}>
            <textarea
              value={form.data.remarks}
              onChange={(e) => form.setData('remarks', e.target.value)}
              rows={3}
              maxLength={1000}
              placeholder="Notes for this assignment…"
              className="w-full resize-none rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </Field>

          <div className="flex flex-col gap-3 border-t border-[#F0F0F0] pt-4">
            <Toggle
              label="Weekly template"
              description="On: recurs every week on this day. Off: one-off for the specific date above."
              checked={form.data.is_template}
              onChange={(value) => form.setData('is_template', value)}
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href={`/livehost/session-slots/${sessionSlot.id}`}>
              <Button type="button" variant="ghost" className="text-[#737373]">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Saving…' : 'Save changes'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

SessionSlotsEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, hint, required = false, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">
        {label}
        {required && <span className="ml-1 text-[#F43F5E]">*</span>}
      </Label>
      {children}
      {hint && !error && <p className="text-[11px] text-[#737373]">{hint}</p>}
      {error && <p className="text-xs text-[#F43F5E] mt-1">{error}</p>}
    </div>
  );
}

function Select({ value, onChange, required = false, children }) {
  return (
    <select
      value={value}
      onChange={onChange}
      required={required}
      className="h-9 w-full px-3 rounded-lg border border-[#EAEAEA] bg-white text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
    >
      {children}
    </select>
  );
}

function Toggle({ label, description, checked, onChange }) {
  return (
    <label className="flex cursor-pointer items-start gap-3">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="mt-0.5 h-4 w-4 rounded border-[#D4D4D4] text-[#10B981] focus:ring-[#10B981]/30"
      />
      <span className="min-w-0">
        <span className="text-[13px] font-medium text-[#0A0A0A]">{label}</span>
        {description && (
          <span className="mt-0.5 block text-[11.5px] text-[#737373]">{description}</span>
        )}
      </span>
    </label>
  );
}
