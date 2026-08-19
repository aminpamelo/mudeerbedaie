import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ShieldCheck, Lock, KeyRound, Eye, EyeOff, ArrowLeft } from 'lucide-react';
import { Button, Field, Input } from '@/vault/components/Ui';

/**
 * Standalone lock screen that gates the whole vault. Renders in two modes:
 *   - 'setup'  : no vault password exists yet → create one (with confirm).
 *   - 'locked' : a password exists → enter it to unlock this session.
 *
 * Deliberately does NOT use VaultLayout — when locked, no sidebar/nav is shown.
 */
export default function Unlock({ mode = 'locked' }) {
  const isSetup = mode === 'setup';
  const [show, setShow] = useState(false);

  const form = useForm({
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    const url = isSetup ? '/admin/vault/setup' : '/admin/vault/unlock';
    form.post(url, {
      preserveScroll: true,
      onError: () => form.reset('password', 'password_confirmation'),
    });
  };

  return (
    <div className="grid min-h-dvh place-items-center px-4 py-10">
      <Head title={isSetup ? 'Set Vault Password' : 'Unlock Vault'} />

      <div className="fade-up w-full max-w-md">
        <div className="mb-6 flex flex-col items-center text-center">
          <div className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-amber-500 to-yellow-500 text-white shadow-[0_16px_40px_-12px_rgba(245,158,11,0.8)]">
            <ShieldCheck className="h-7 w-7" strokeWidth={2.2} />
          </div>
          <h1 className="mt-4 text-[22px] font-bold tracking-[-0.02em] text-white">
            {isSetup ? 'Secure the Password Vault' : 'Password Vault Locked'}
          </h1>
          <p className="mt-1.5 max-w-sm text-[13.5px] text-white/50">
            {isSetup
              ? 'Set a shared vault password. Everyone must enter it to open the vault — change it whenever a staff member leaves.'
              : 'Enter the shared vault password to continue. It stays unlocked until you log out.'}
          </p>
        </div>

        <div className="panel rounded-[20px] p-6">
          <form onSubmit={submit} className="space-y-4">
            <Field
              label={isSetup ? 'New vault password' : 'Vault password'}
              error={form.errors.password}
              hint={isSetup ? 'At least 8 characters.' : undefined}
            >
              <div className="relative">
                <KeyRound className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
                <Input
                  value={form.data.password}
                  onChange={(e) => form.setData('password', e.target.value)}
                  type={show ? 'text' : 'password'}
                  placeholder={isSetup ? 'Choose a strong password' : 'Enter password'}
                  className="pl-10 pr-11"
                  autoFocus
                  autoComplete={isSetup ? 'new-password' : 'current-password'}
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

            {isSetup && (
              <Field label="Confirm vault password" error={form.errors.password_confirmation}>
                <div className="relative">
                  <KeyRound className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/30" />
                  <Input
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    type={show ? 'text' : 'password'}
                    placeholder="Re-enter password"
                    className="pl-10"
                    autoComplete="new-password"
                  />
                </div>
              </Field>
            )}

            <Button
              variant="primary"
              size="md"
              onClick={submit}
              disabled={form.processing}
              className="w-full"
            >
              <div className="flex items-center justify-center gap-1.5">
                <Lock className="h-4 w-4" />
                {form.processing
                  ? (isSetup ? 'Securing…' : 'Unlocking…')
                  : (isSetup ? 'Set Vault Password' : 'Unlock Vault')}
              </div>
            </Button>
          </form>
        </div>

        <div className="mt-5 text-center">
          <a
            href="/admin"
            className="inline-flex items-center gap-1.5 text-[12.5px] font-medium text-white/40 transition-colors hover:text-white/70"
          >
            <ArrowLeft className="h-3.5 w-3.5" />
            Back to Admin
          </a>
        </div>
      </div>
    </div>
  );
}
