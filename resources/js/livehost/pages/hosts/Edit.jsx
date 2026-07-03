import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export default function HostEdit() {
  const { host } = usePage().props;
  const form = useForm({
    name: host.name ?? '',
    email: host.email ?? '',
    phone: host.phone ?? '',
    status: host.status ?? 'active',
    role: host.role ?? 'live_host',
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.put(`/livehost/hosts/${host.id}`);
  };

  return (
    <>
      <Head title={`Edit ${host.name}`} />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Hosts', host.name, 'Edit']}
        actions={
          <Link href={`/livehost/hosts/${host.id}`}>
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </Button>
          </Link>
        }
      />

      <div className="p-4 sm:p-6 lg:p-8 max-w-3xl">
        <div className="mb-6">
          <h1 className="text-2xl sm:text-3xl font-semibold tracking-[-0.03em] leading-[1.1] text-[#0A0A0A]">Edit host</h1>
          <p className="text-[#737373] mt-1.5 text-sm">Update {host.name}'s profile.</p>
        </div>

        <form
          onSubmit={submit}
          className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5"
        >
          <Field label="Full name" error={form.errors.name}>
            <Input
              name="name"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              autoFocus
            />
          </Field>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Field label="Email" error={form.errors.email}>
              <Input
                name="email"
                type="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
              />
            </Field>
            <Field label="Phone" error={form.errors.phone}>
              <Input
                name="phone"
                value={form.data.phone}
                onChange={(e) => form.setData('phone', e.target.value)}
                placeholder="60123456789"
              />
            </Field>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Field label="Status" error={form.errors.status}>
              <select
                name="status"
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value)}
                className="h-9 w-full px-3 rounded-lg border border-[#EAEAEA] bg-white text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
            </Field>
            <Field label="Role" error={form.errors.role}>
              <select
                name="role"
                value={form.data.role}
                onChange={(e) => form.setData('role', e.target.value)}
                className="h-9 w-full px-3 rounded-lg border border-[#EAEAEA] bg-white text-sm text-[#0A0A0A] focus:outline-none focus:ring-2 focus:ring-[#10B981]/20"
              >
                <option value="live_host">Live Host</option>
                <option value="livehost_assistant">Live Host Assistant</option>
              </select>
            </Field>
          </div>

          <div className="pt-4 border-t border-[#F0F0F0]">
            <p className="text-[13px] font-medium text-[#0A0A0A]">Password</p>
            <p className="mt-0.5 mb-3 text-xs text-[#737373]">
              Set a new password for this host. Leave blank to keep their current password.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="New password" error={form.errors.password}>
                <Input
                  type="password"
                  name="password"
                  autoComplete="new-password"
                  value={form.data.password}
                  onChange={(e) => form.setData('password', e.target.value)}
                  placeholder="••••••••"
                />
              </Field>
              <Field label="Confirm password" error={form.errors.password_confirmation}>
                <Input
                  type="password"
                  name="password_confirmation"
                  autoComplete="new-password"
                  value={form.data.password_confirmation}
                  onChange={(e) => form.setData('password_confirmation', e.target.value)}
                  placeholder="••••••••"
                />
              </Field>
            </div>
          </div>

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href={`/livehost/hosts/${host.id}`}>
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

HostEdit.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {error && <p className="text-xs text-[#F43F5E] mt-1">{error}</p>}
    </div>
  );
}
