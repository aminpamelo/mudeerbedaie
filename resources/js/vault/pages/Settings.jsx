import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ShieldCheck, KeyRound, Lock, Eye, EyeOff, AlertTriangle } from 'lucide-react';
import VaultLayout from '@/vault/layouts/VaultLayout';
import { Card, Button, Field, Input } from '@/vault/components/Ui';

function ChangePasswordCard() {
  const [show, setShow] = useState(false);

  const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    form.put('/admin/vault/settings/password', {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    });
  };

  return (
    <Card className="p-5 sm:p-6">
      <div className="mb-5 flex items-start gap-3">
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-500/15">
          <KeyRound className="h-4.5 w-4.5 text-amber-400" strokeWidth={2} />
        </span>
        <div>
          <h2 className="text-[15px] font-bold text-white">Vault Password</h2>
          <p className="mt-0.5 text-[13px] text-white/50">
            The shared password everyone enters to open the vault.
          </p>
        </div>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <Field label="Current vault password" error={form.errors.current_password}>
          <Input
            value={form.data.current_password}
            onChange={(e) => form.setData('current_password', e.target.value)}
            type={show ? 'text' : 'password'}
            placeholder="Enter current password"
            autoComplete="current-password"
          />
        </Field>

        <Field label="New vault password" error={form.errors.password} hint="At least 8 characters.">
          <div className="relative">
            <Input
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              type={show ? 'text' : 'password'}
              placeholder="Choose a new password"
              className="pr-11"
              autoComplete="new-password"
            />
            <button
              type="button"
              onClick={() => setShow(!show)}
              className="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-lg text-white/40 hover:bg-white/10 hover:text-white"
              aria-label={show ? 'Hide password' : 'Show password'}
            >
              {show ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
            </button>
          </div>
        </Field>

        <Field label="Confirm new password" error={form.errors.password_confirmation}>
          <Input
            value={form.data.password_confirmation}
            onChange={(e) => form.setData('password_confirmation', e.target.value)}
            type={show ? 'text' : 'password'}
            placeholder="Re-enter new password"
            autoComplete="new-password"
          />
        </Field>

        <div className="flex items-start gap-2 rounded-xl border border-amber-500/20 bg-amber-500/8 px-3.5 py-3">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-400" strokeWidth={2} />
          <p className="text-[12.5px] leading-relaxed text-amber-200/80">
            Changing the password immediately logs out everyone else from the vault. They'll need the
            new password to get back in — use this when a staff member leaves.
          </p>
        </div>

        <div className="flex justify-end">
          <Button variant="primary" onClick={submit} disabled={form.processing}>
            {form.processing ? 'Saving…' : 'Change Password'}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function LockNowCard() {
  const lockNow = () => {
    router.post('/admin/vault/lock', {}, { preserveScroll: true });
  };

  return (
    <Card className="p-5 sm:p-6">
      <div className="mb-4 flex items-start gap-3">
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/8">
          <Lock className="h-4.5 w-4.5 text-white/60" strokeWidth={2} />
        </span>
        <div>
          <h2 className="text-[15px] font-bold text-white">Lock the Vault</h2>
          <p className="mt-0.5 text-[13px] text-white/50">
            Re-lock now. You'll need the vault password to open it again.
          </p>
        </div>
      </div>
      <Button variant="secondary" onClick={lockNow}>
        <div className="flex items-center justify-center gap-1.5">
          <Lock className="h-4 w-4" /> Lock Vault Now
        </div>
      </Button>
    </Card>
  );
}

export default function Settings() {
  return (
    <VaultLayout title="Settings" subtitle="Manage vault access and security">
      <Head title="Settings" />

      <div className="grid max-w-2xl gap-5">
        <ChangePasswordCard />
        <LockNowCard />

        <div className="flex items-center gap-2 px-1 text-[12px] text-white/40">
          <ShieldCheck className="h-3.5 w-3.5 text-amber-400/70" />
          All credentials are encrypted with AES-256 at rest.
        </div>
      </div>
    </VaultLayout>
  );
}
