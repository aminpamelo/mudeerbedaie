import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const DAY_OPTIONS = [
  { value: '', label: 'All days (global)' },
  { value: '0', label: 'Sunday' },
  { value: '1', label: 'Monday' },
  { value: '2', label: 'Tuesday' },
  { value: '3', label: 'Wednesday' },
  { value: '4', label: 'Thursday' },
  { value: '5', label: 'Friday' },
  { value: '6', label: 'Saturday' },
];

export default function TimeSlotsEdit() {
  const { timeSlot, platformAccounts } = usePage().props;

  const form = useForm({
    platform_account_id: timeSlot.platform_account_id?.toString() ?? '',
    day_of_week: timeSlot.day_of_week !== null && timeSlot.day_of_week !== undefined
      ? timeSlot.day_of_week.toString()
      : '',
    start_time: timeSlot.start_time ?? '09:00',
    end_time: timeSlot.end_time ?? '11:00',
    is_active: Boolean(timeSlot.is_active),
    sort_order: timeSlot.sort_order?.toString() ?? '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      platform_account_id: data.platform_account_id === '' ? null : data.platform_account_id,
      day_of_week: data.day_of_week === '' ? null : Number(data.day_of_week),
      sort_order: data.sort_order === '' ? null : Number(data.sort_order),
    })).put(`/livehost/time-slots/${timeSlot.id}`);
  };

  return (
    <>
      <Head title="Edit time slot" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Time Slots', `#${timeSlot.id}`, 'Edit']}
        actions={
          <Link href="/livehost/time-slots">
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
            Edit time slot
          </h1>
          <p className="text-[#737373] mt-1.5 text-sm">
            Update this reusable time window.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5"
        >
          <Field label="Platform account" error={form.errors.platform_account_id}>
            <Select
              value={form.data.platform_account_id}
              onChange={(e) => form.setData('platform_account_id', e.target.value)}
            >
              <option value="">Global (all platforms)</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name}
                  {pa.platform ? ` · ${pa.platform}` : ''}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Day of week" error={form.errors.day_of_week}>
            <Select
              value={form.data.day_of_week}
              onChange={(e) => form.setData('day_of_week', e.target.value)}
            >
              {DAY_OPTIONS.map((d) => (
                <option key={d.label} value={d.value}>
                  {d.label}
                </option>
              ))}
            </Select>
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Start time" error={form.errors.start_time}>
              <Input
                type="time"
                value={form.data.start_time}
                onChange={(e) => form.setData('start_time', e.target.value)}
                required
              />
            </Field>
            <Field label="End time" error={form.errors.end_time}>
              <Input
                type="time"
                value={form.data.end_time}
                onChange={(e) => form.setData('end_time', e.target.value)}
                required
              />
            </Field>
          </div>

          <Field label="Sort order (optional)" error={form.errors.sort_order}>
            <Input
              type="number"
              min="0"
              value={form.data.sort_order}
              onChange={(e) => form.setData('sort_order', e.target.value)}
              placeholder="0"
            />
          </Field>

          <div className="flex flex-col gap-3 border-t border-[#F0F0F0] pt-4">
            <Toggle
              label="Active"
              description="Only active slots are offered when building schedule assignments."
              checked={form.data.is_active}
              onChange={(value) => form.setData('is_active', value)}
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href="/livehost/time-slots">
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

TimeSlotsEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
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
