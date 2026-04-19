import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export default function PlatformAccountCreate() {
  const { platforms, users } = usePage().props;
  const form = useForm({
    name: '',
    platform_id: '',
    user_id: '',
    account_id: '',
    description: '',
    country_code: '',
    currency: '',
    is_active: true,
  });

  const submit = (e) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      platform_id: data.platform_id ? Number(data.platform_id) : null,
      user_id: data.user_id ? Number(data.user_id) : null,
      account_id: data.account_id || null,
      description: data.description || null,
      country_code: data.country_code || null,
      currency: data.currency || null,
    }));
    form.post('/livehost/platform-accounts');
  };

  return (
    <>
      <Head title="New Platform Account" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Platform Accounts', 'New']}
        actions={
          <Link href="/livehost/platform-accounts">
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
            New platform account
          </h1>
          <p className="text-[#737373] mt-1.5 text-sm">
            Add a shop, page, or seller account so the PIC can allocate time slots and schedules to it.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5"
        >
          <Field label="Account name" error={form.errors.name} hint="Shown across schedules and session slots.">
            <Input
              name="name"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              placeholder="e.g. Sarah Chen's Shopee"
              autoFocus
            />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Platform" error={form.errors.platform_id}>
              <select
                name="platform_id"
                value={form.data.platform_id}
                onChange={(e) => form.setData('platform_id', e.target.value)}
                className="h-9 w-full px-3 rounded-lg border border-[#EAEAEA] bg-white text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                <option value="">Select a platform…</option>
                {platforms.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Owner (user)" error={form.errors.user_id} hint="Optional.">
              <select
                name="user_id"
                value={form.data.user_id}
                onChange={(e) => form.setData('user_id', e.target.value)}
                className="h-9 w-full px-3 rounded-lg border border-[#EAEAEA] bg-white text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                <option value="">Unassigned</option>
                {users.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} · {u.email}
                  </option>
                ))}
              </select>
            </Field>
          </div>

          <Field
            label="Platform account ID"
            error={form.errors.account_id}
            hint="The platform's own identifier for this shop/page (optional)."
          >
            <Input
              name="account_id"
              value={form.data.account_id}
              onChange={(e) => form.setData('account_id', e.target.value)}
              placeholder="e.g. 711111001234"
            />
          </Field>

          <Field label="Description" error={form.errors.description} hint="Optional notes.">
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={3}
              className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              placeholder="Internal notes about this account…"
            />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Country code" error={form.errors.country_code} hint="2-letter ISO, e.g. MY.">
              <Input
                name="country_code"
                value={form.data.country_code}
                onChange={(e) => form.setData('country_code', e.target.value.toUpperCase())}
                maxLength={2}
                placeholder="MY"
              />
            </Field>
            <Field label="Currency" error={form.errors.currency} hint="3-letter ISO, e.g. MYR.">
              <Input
                name="currency"
                value={form.data.currency}
                onChange={(e) => form.setData('currency', e.target.value.toUpperCase())}
                maxLength={3}
                placeholder="MYR"
              />
            </Field>
          </div>

          <Field label="Status" error={form.errors.is_active}>
            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={form.data.is_active}
                onChange={(e) => form.setData('is_active', e.target.checked)}
                className="h-4 w-4 rounded border-[#EAEAEA] text-[#10B981] focus:ring-[#10B981]/20"
              />
              <span className="text-sm text-[#0A0A0A]">Active — available for scheduling</span>
            </label>
          </Field>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href="/livehost/platform-accounts">
              <Button type="button" variant="ghost" className="text-[#737373]">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create account'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

PlatformAccountCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, hint, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {hint && !error && <p className="text-[11.5px] text-[#737373]">{hint}</p>}
      {error && <p className="text-xs text-[#F43F5E] mt-1">{error}</p>}
    </div>
  );
}
