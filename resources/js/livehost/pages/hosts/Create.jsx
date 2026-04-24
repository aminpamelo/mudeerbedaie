import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Sparkles } from 'lucide-react';
import LiveHostLayout, { TopBar } from '@/livehost/layouts/LiveHostLayout';
import { Button } from '@/livehost/components/ui/button';
import { Input } from '@/livehost/components/ui/input';
import { Label } from '@/livehost/components/ui/label';

export default function HostCreate() {
  const { prefilledUser } = usePage().props;

  const form = useForm({
    name: prefilledUser?.name ?? '',
    email: prefilledUser?.email ?? '',
    phone: prefilledUser?.phone ?? '',
    status: 'active',
  });

  const submit = (e) => {
    e.preventDefault();
    form.post('/livehost/hosts');
  };

  return (
    <>
      <Head title="New Live Host" />
      <TopBar
        breadcrumb={['Live Host Desk', 'Live Hosts', 'New']}
        actions={
          <Link href="/livehost/hosts">
            <Button variant="ghost" className="gap-1.5 text-[#737373] hover:text-[#0A0A0A]">
              <ArrowLeft className="w-3.5 h-3.5" />
              Back to hosts
            </Button>
          </Link>
        }
      />

      <div className="p-8 max-w-3xl">
        <div className="mb-6">
          <h1 className="text-3xl font-semibold tracking-[-0.03em] leading-[1.1] text-[#0A0A0A]">New live host</h1>
          <p className="text-[#737373] mt-1.5 text-sm">Create a profile for a live streaming host on your team.</p>
        </div>

        {prefilledUser && (
          <div className="mb-5 flex items-start gap-3 rounded-[12px] border border-[#BBF7D0] bg-[#F0FDF4] p-4">
            <Sparkles className="mt-0.5 h-4 w-4 text-[#047857]" strokeWidth={2.25} />
            <div className="text-[13px] text-[#065F46]">
              Prefilled from hired user <span className="font-semibold">#{prefilledUser.id}</span>.
              Review the details before creating the live host profile.
            </div>
          </div>
        )}

        <form onSubmit={submit} className="bg-white border border-[#EAEAEA] rounded-[16px] shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6 space-y-5">
          <Field label="Full name" error={form.errors.name}>
            <Input
              name="name"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              autoFocus
            />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Email" error={form.errors.email}>
              <Input
                type="email"
                name="email"
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

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-[#F0F0F0]">
            <Link href="/livehost/hosts">
              <Button type="button" variant="ghost" className="text-[#737373]">Cancel</Button>
            </Link>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create host'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

HostCreate.layout = (page) => <LiveHostLayout>{page}</LiveHostLayout>;

function Field({ label, error, children }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-[13px] font-medium text-[#0A0A0A]">{label}</Label>
      {children}
      {error && <p className="text-xs text-[#F43F5E] mt-1">{error}</p>}
    </div>
  );
}
