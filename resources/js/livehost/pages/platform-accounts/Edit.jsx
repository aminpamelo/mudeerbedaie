import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export default function PlatformAccountEdit() {
  const { account, platforms, users } = usePage().props;
  const form = useForm({
    name: account.name ?? '',
    platform_id: account.platform_id ? String(account.platform_id) : '',
    user_id: account.user_id ? String(account.user_id) : '',
    account_id: account.account_id ?? '',
    description: account.description ?? '',
    country_code: account.country_code ?? '',
    currency: account.currency ?? '',
    is_active: Boolean(account.is_active),
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
    form.put(`/livehost/platform-accounts/${account.id}`);
  };

  return (
    <>
      <Head title={`Edit ${account.name}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Platform Accounts', account.name, 'Edit']}
        actions={
          <Link href={`/livehost/platform-accounts/${account.id}`}>
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
            Edit platform account
          </h1>
          <p className="text-[#737373] mt-1.5 text-sm">
            Update basic details. OAuth tokens and sync settings are managed in /admin/platforms.
          </p>
        </div>

        <form
          onSubmit={submit}
          className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5"
        >
          <Field label="Account name" error={form.errors.name}>
            <Input
              name="name"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
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
            <Field label="Owner (user)" error={form.errors.user_id}>
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

          <Field label="Platform account ID" error={form.errors.account_id}>
            <Input
              name="account_id"
              value={form.data.account_id}
              onChange={(e) => form.setData('account_id', e.target.value)}
            />
          </Field>

          <Field label="Description" error={form.errors.description}>
            <textarea
              name="description"
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              rows={3}
              className="w-full resize-y rounded-lg border border-[#EAEAEA] bg-white px-3 py-2 text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
            />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Country code" error={form.errors.country_code}>
              <Input
                name="country_code"
                value={form.data.country_code}
                onChange={(e) => form.setData('country_code', e.target.value.toUpperCase())}
                maxLength={2}
              />
            </Field>
            <Field label="Currency" error={form.errors.currency}>
              <Input
                name="currency"
                value={form.data.currency}
                onChange={(e) => form.setData('currency', e.target.value.toUpperCase())}
                maxLength={3}
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
            <Link href={`/livehost/platform-accounts/${account.id}`}>
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

PlatformAccountEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {error && <p className="text-xs text-[#F43F5E] mt-1">{error}</p>}
    </div>
  );
}
