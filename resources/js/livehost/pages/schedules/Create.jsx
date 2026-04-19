import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

const DAY_OPTIONS = [
  { value: 0, label: 'Sunday' },
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
];

export default function SchedulesCreate() {
  const { hosts, platformAccounts } = usePage().props;

  const form = useForm({
    platform_account_id: '',
    live_host_id: '',
    day_of_week: '1',
    start_time: '09:00',
    end_time: '11:00',
    is_active: true,
    is_recurring: true,
    remarks: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      live_host_id: data.live_host_id === '' ? null : data.live_host_id,
      day_of_week: Number(data.day_of_week),
    })).post('/livehost/schedules');
  };

  return (
    <>
      <Head title="New schedule" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Schedules', 'New']}
        actions={
          <Link href="/livehost/schedules">
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
            New schedule
          </h1>
          <p className="text-[#737373] mt-1.5 text-sm">
            Add a weekly recurring slot for a live host on a platform account.
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
              required
            >
              <option value="">Select a platform account</option>
              {platformAccounts.map((pa) => (
                <option key={pa.id} value={pa.id}>
                  {pa.name}
                  {pa.platform ? ` · ${pa.platform}` : ''}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Host (optional)" error={form.errors.live_host_id}>
            <Select
              value={form.data.live_host_id}
              onChange={(e) => form.setData('live_host_id', e.target.value)}
            >
              <option value="">Unassigned</option>
              {hosts.map((h) => (
                <option key={h.id} value={h.id}>
                  {h.name}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Day of week" error={form.errors.day_of_week}>
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

          <Field label="Remarks" error={form.errors.remarks}>
            <textarea
              value={form.data.remarks}
              onChange={(e) => form.setData('remarks', e.target.value)}
              rows={3}
              maxLength={500}
              placeholder="Any notes about this slot (optional)…"
              className="w-full rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </Field>

          <div className="flex flex-col gap-3 border-t border-[#F0F0F0] pt-4">
            <Toggle
              label="Active"
              description="Only active schedules appear on the public timetable."
              checked={form.data.is_active}
              onChange={(value) => form.setData('is_active', value)}
            />
            <Toggle
              label="Recurring weekly"
              description="Repeat every week on the selected day."
              checked={form.data.is_recurring}
              onChange={(value) => form.setData('is_recurring', value)}
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href="/livehost/schedules">
              <Button type="button" variant="ghost" className="text-[#737373]">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create schedule'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

SchedulesCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

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
